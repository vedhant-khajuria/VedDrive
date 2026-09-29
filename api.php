<?php
require __DIR__.'/private/bootstrap.php';
$action=$_GET['action']??'session';
if($_SERVER['REQUEST_METHOD']==='GET') {
 if($action==='session') {
  $u=isset($_SESSION['uid'])?user():null;
  respond(['user'=>$u?publicUser($u):null,'csrf'=>$_SESSION['csrf'],'chunkBytes'=>$config['chunk_bytes'],'maxFileBytes'=>$config['max_file_bytes'],'registration'=>setting('registration_open',$config['registration_open']?'1':'0')==='1','googleClientId'=>$config['google_client_id']??'']);
 }
 $u=user(); $uid=(int)$u['id'];
 if($action==='library') respond(['files'=>query("SELECT id,folder_id,name,mime,size,status,favorite,created_at FROM files WHERE user_id=? AND status!='uploading' ORDER BY created_at DESC",[$uid])->fetchAll(),'folders'=>query('SELECT id,parent_id,name FROM folders WHERE user_id=? ORDER BY name',[$uid])->fetchAll(),'used'=>query('SELECT COALESCE(SUM(size),0) AS bytes FROM files WHERE user_id=?',[$uid])->fetch()['bytes'],'pending'=>query("SELECT id,name,size FROM files WHERE user_id=? AND status='uploading'",[$uid])->fetchAll()]);
 if($action==='admin') { admin(); respond(['users'=>query("SELECT u.id,u.name,u.email,u.role,u.blocked,u.quota_bytes,u.created_at,COALESCE(SUM(f.size),0) AS used,COUNT(f.id) AS files FROM users u LEFT JOIN files f ON f.user_id=u.id GROUP BY u.id ORDER BY u.created_at DESC")->fetchAll(),'registration'=>setting('registration_open',$config['registration_open']?'1':'0')==='1']); }
 fail('Unknown action.',404);
}
if($_SERVER['REQUEST_METHOD']!=='POST') fail('Method not allowed.',405);
csrf(); $b=body();
if(in_array($action,['login','register'],true)) {
 throttle('auth-ip:'.($_SERVER['REMOTE_ADDR']??'local'),35);
 $email=strtolower(trim($b['email']??'')); $password=(string)($b['password']??'');
 if(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190) fail('Enter a valid email address.');
 throttle('auth-email:'.$email);
 if($action==='register') {
  if(setting('registration_open',$config['registration_open']?'1':'0')!=='1') fail('New accounts are currently paused.',403);
  if(strlen($password)<12 || strlen($password)>72) fail('Use a password between 12 and 72 characters.');
  $name=label($b['name']??'',80);
  try {query('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT)]);} catch(PDOException $e){if($e->getCode()==='23000') fail('Unable to create this account. Try signing in.'); throw $e;}
 }
 $u=query('SELECT * FROM users WHERE email=?',[$email])->fetch();
 if(!$u || !password_verify($password,$u['password_hash']) || $u['blocked']) fail('Email or password is incorrect, or the account is unavailable.',401);
 session_regenerate_id(true); $_SESSION['uid']=(int)$u['id']; $_SESSION['version']=(int)$u['session_version']; $_SESSION['csrf']=bin2hex(random_bytes(32));
 respond(['user'=>publicUser($u),'csrf'=>$_SESSION['csrf']]);
}
if($action==='google-login') {
 throttle('auth-ip:'.($_SERVER['REMOTE_ADDR']??'local'),35);
 $clientId=(string)($config['google_client_id']??''); $credential=(string)($b['credential']??'');
 if($clientId==='' || $credential==='') fail('Google sign-in is not configured.',503);
 if(strlen($credential)>10000 || !function_exists('curl_init')) fail('Google sign-in is unavailable.',503);
 $c=curl_init('https://oauth2.googleapis.com/tokeninfo?id_token='.rawurlencode($credential));
 curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>15]);
 $raw=curl_exec($c); $status=curl_getinfo($c,CURLINFO_RESPONSE_CODE); curl_close($c);
 $claims=is_string($raw)?json_decode($raw,true):null;
 if($status!==200 || !is_array($claims) || !hash_equals($clientId,(string)($claims['aud']??'')) || ($claims['email_verified']??'')!=='true' || empty($claims['email'])) fail('Google sign-in could not verify this account.',401);
 $email=strtolower((string)$claims['email']); if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190) fail('Google returned an invalid email address.',401);
 $u=query('SELECT * FROM users WHERE email=?',[$email])->fetch();
 if(!$u) {
  if(setting('registration_open',$config['registration_open']?'1':'0')!=='1') fail('New accounts are currently paused.',403);
  $name=label($claims['name']??strstr($email,'@',true),80);
  try { query("INSERT INTO users(name,email,password_hash) VALUES(?,?,?)",[$name,$email,password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]); }
  catch(PDOException $e) { if($e->getCode()!=='23000') throw $e; }
  $u=query('SELECT * FROM users WHERE email=?',[$email])->fetch();
 }
 if(!$u || $u['blocked']) fail('This account is unavailable.',403);
 session_regenerate_id(true); $_SESSION['uid']=(int)$u['id']; $_SESSION['version']=(int)$u['session_version']; $_SESSION['csrf']=bin2hex(random_bytes(32));
 respond(['user'=>publicUser($u),'csrf'=>$_SESSION['csrf']]);
}
$u=user(); $uid=(int)$u['id'];
if($action==='logout') { $_SESSION=[]; session_destroy(); respond(['ok'=>true]); }
if($action==='profile') {
 $name=label($b['name']??'',80);
 if(!password_verify((string)($b['currentPassword']??''),$u['password_hash'])) fail('Current password is incorrect.');
 $password=(string)($b['password']??'');
 if($password!=='' && (strlen($password)<12||strlen($password)>72)) fail('Use a password between 12 and 72 characters.');
 query('UPDATE users SET name=?,password_hash=?,session_version=session_version+1 WHERE id=?',[$name,$password!==''?password_hash($password,PASSWORD_DEFAULT):$u['password_hash'],$uid]);
 $_SESSION['version']++; respond(['user'=>publicUser(user())]);
}
if($action==='folder-create') { $parent=folder($b['parent']??null,$uid); $name=label($b['name']??'',180); if(query('SELECT id FROM folders WHERE user_id=? AND parent_id <=> ? AND name=?',[$uid,$parent,$name])->fetch()) fail('A folder with this name already exists here.',409); query('INSERT INTO folders(user_id,parent_id,name) VALUES(?,?,?)',[$uid,$parent,$name]); respond(['ok'=>true]); }
if($action==='folder-edit') {
 $id=folder($b['id']??0,$uid); if(!$id) fail('Choose a folder.');
 $parent=folder($b['parent']??null,$uid); $cursor=$parent;
 while($cursor) {if($cursor===$id) fail('A folder cannot be moved inside itself.'); $r=query('SELECT parent_id FROM folders WHERE id=? AND user_id=?',[$cursor,$uid])->fetch(); $cursor=$r['parent_id']?(int)$r['parent_id']:null;}
 query('UPDATE folders SET name=?,parent_id=? WHERE id=? AND user_id=?',[label($b['name']??''),$parent,$id,$uid]); respond(['ok'=>true]);
}
if($action==='folder-delete') {
 $id=folder($b['id']??0,$uid); if(!$id) fail('Choose a folder.');
 if(query('SELECT id FROM files WHERE folder_id=? UNION ALL SELECT id FROM folders WHERE parent_id=? LIMIT 1',[$id,$id])->fetch()) fail('Move or remove the folder contents first, including items in Trash.');
 query('DELETE FROM folders WHERE id=? AND user_id=?',[$id,$uid]); respond(['ok'=>true]);
}
if($action==='upload-start') {
 $size=filter_var($b['size']??0,FILTER_VALIDATE_INT); if(!$size || $size<1 || $size>$config['max_file_bytes']) fail('File is empty or exceeds the configured file limit.');
 $fid=folder($b['folder']??null,$uid); $name=label($b['name']??'',240);
 db()->beginTransaction(); $locked=query('SELECT * FROM users WHERE id=? FOR UPDATE',[$uid])->fetch();
 $used=(int)query('SELECT COALESCE(SUM(size),0) AS bytes FROM files WHERE user_id=?',[$uid])->fetch()['bytes'];
 if($locked['quota_bytes'] && $used+$size>(int)$locked['quota_bytes']) fail('Your storage allowance has been reached.',409);
 query('INSERT INTO files(user_id,folder_id,name,mime,size) VALUES(?,?,?,?,?)',[$uid,$fid,$name,substr((string)($b['mime']??'application/octet-stream'),0,150),$size]);
 $id=db()->lastInsertId(); db()->commit(); respond(['id'=>$id]);
}
if($action==='upload-chunk') {
 $f=ownedFile($_GET['id']??0,$uid); if($f['status']!=='uploading') fail('Upload is already finished.');
 $part=filter_var($_GET['part']??-1,FILTER_VALIDATE_INT); $count=(int)ceil($f['size']/$config['chunk_bytes']);
 if($part===false||$part<0||$part>=$count) fail('Invalid upload part.');
 $upload=$_FILES['chunk']??null; $expected=min($config['chunk_bytes'],(int)$f['size']-$part*$config['chunk_bytes']);
 if(!$upload||$upload['error']!==UPLOAD_ERR_OK||$upload['size']!==$expected||!is_uploaded_file($upload['tmp_name'])) fail('Upload part was incomplete. Please retry.');
 if(query('SELECT part FROM chunks WHERE file_id=? AND part=?',[$f['id'],$part])->fetch()) respond(['ok'=>true]);
 $r=remote('sendDocument',['chat_id'=>$config['channel_id'],'document'=>new CURLFile($upload['tmp_name'],'application/octet-stream','v-'.$f['id'].'-'.$part.'.bin'),'disable_notification'=>'true']);
 query('INSERT INTO chunks(file_id,part,remote_id,message_id,size) VALUES(?,?,?,?,?)',[$f['id'],$part,$r['document']['file_id'],$r['message_id'],$expected]); respond(['ok'=>true]);
}
if($action==='upload-finish') {
 $f=ownedFile($b['id']??0,$uid); if($f['status']!=='uploading') fail('Upload is already finished.');
 $parts=query('SELECT COUNT(*) AS n,COALESCE(SUM(size),0) AS bytes FROM chunks WHERE file_id=?',[$f['id']])->fetch();
 if((int)$parts['bytes']!==(int)$f['size'] || (int)$parts['n']!==(int)ceil($f['size']/$config['chunk_bytes'])) fail('Some upload parts are missing.');
 query("UPDATE files SET status='ready' WHERE id=?",[$f['id']]); respond(['ok'=>true]);
}
if($action==='file-edit') {
 $f=ownedFile($b['id']??0,$uid);
 if($f['status']==='uploading') fail('Wait for the upload to finish.');
 $op=$b['op']??'';
 if($op==='rename') query('UPDATE files SET name=? WHERE id=?',[label($b['name']??'',240),$f['id']]);
 elseif($op==='move') query('UPDATE files SET folder_id=? WHERE id=?',[folder($b['folder']??null,$uid),$f['id']]);
 elseif($op==='favorite') query('UPDATE files SET favorite=1-favorite WHERE id=?',[$f['id']]);
 elseif($op==='trash') query("UPDATE files SET status='trash' WHERE id=?",[$f['id']]);
 elseif($op==='restore') query("UPDATE files SET status='ready' WHERE id=?",[$f['id']]);
 else fail('Invalid file action.'); respond(['ok'=>true]);
}
if($action==='file-delete') {
 $f=ownedFile($b['id']??0,$uid); if($f['status']==='ready') fail('Move the file to Trash first.');
 // Remote storage retains archived parts. Removing the index revokes access through VedDrive.
 query('DELETE FROM files WHERE id=? AND user_id=?',[$f['id'],$uid]); respond(['ok'=>true]);
}
if($action==='admin-user') {
 admin(); $target=query('SELECT * FROM users WHERE id=?',[$b['id']??0])->fetch(); if(!$target) fail('User not found.',404);
 if($target['role']==='admin') fail('Administrator accounts cannot be changed here.');
 $quota=filter_var($b['quota']??0,FILTER_VALIDATE_INT); if($quota===false||$quota<0) fail('Invalid storage allowance.');
 query('UPDATE users SET blocked=?,quota_bytes=?,session_version=session_version+1 WHERE id=?',[!empty($b['blocked'])?1:0,$quota,$target['id']]);
 respond(['ok'=>true]);
}
if($action==='admin-password') {
 admin(); $target=query('SELECT role FROM users WHERE id=?',[$b['id']??0])->fetch(); if(!$target||$target['role']==='admin') fail('Choose a member account.');
 $p=(string)($b['password']??''); if(strlen($p)<12||strlen($p)>72) fail('Use a password between 12 and 72 characters.');
 query('UPDATE users SET password_hash=?,session_version=session_version+1 WHERE id=?',[password_hash($p,PASSWORD_DEFAULT),$b['id']]); respond(['ok'=>true]);
}
if($action==='admin-settings') {admin(); query("INSERT INTO settings(name,value) VALUES('registration_open',?) ON DUPLICATE KEY UPDATE value=VALUES(value)",[!empty($b['registration'])?'1':'0']); respond(['ok'=>true]);}
fail('Unknown action.',404);
