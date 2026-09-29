<?php
declare(strict_types=1);
ini_set('display_errors', '0');
$config = require __DIR__.'/config.php';
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','samesite'=>'Lax','path'=>'/']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
function db(): PDO {
 global $config;
 static $pdo;
 if (!$pdo) $pdo = new PDO('mysql:host='.$config['db_host'].';dbname='.$config['db_name'].';charset=utf8mb4', $config['db_user'], $config['db_password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 return $pdo;
}
function query(string $sql, array $args=[]): PDOStatement { $q=db()->prepare($sql); $q->execute($args); return $q; }
function fail(string $message, int $status=400): never { http_response_code($status); header('Content-Type: application/json'); echo json_encode(['error'=>$message]); exit; }
function respond(array $data=[]): never { header('Content-Type: application/json'); echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function user(): array {
 $u=query('SELECT * FROM users WHERE id=?', [$_SESSION['uid']??0])->fetch();
 if (!$u || $u['blocked'] || (int)$u['session_version'] !== ($_SESSION['version']??0)) fail('Please sign in to continue.',401);
 return $u;
}
function publicUser(array $u): array { unset($u['password_hash'],$u['session_version']); return $u; }
function admin(): array { $u=user(); if($u['role']!=='admin') fail('Admin access required.',403); return $u; }
function csrf(): void { if(!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??'')) fail('Session expired. Refresh and try again.',403); }
function body(): array { return json_decode(file_get_contents('php://input'),true) ?: []; }
function label($s,int $max=180): string { $s=trim((string)$s); if($s==='' || strlen($s)>$max || preg_match('/[\x00-\x1f]/',$s)) fail('Please use a valid name (up to '.$max.' characters).'); return $s; }
function folder($id,int $uid): ?int { if(!$id) return null; $f=query('SELECT id FROM folders WHERE id=? AND user_id=?',[$id,$uid])->fetch(); if(!$f) fail('Folder not found.',404); return (int)$f['id']; }
function ownedFile($id,int $uid): array { $f=query('SELECT * FROM files WHERE id=? AND user_id=?',[$id,$uid])->fetch(); if(!$f) fail('File not found.',404); return $f; }
function setting(string $key,string $default): string { $r=query('SELECT value FROM settings WHERE name=?',[$key])->fetch(); return $r ? $r['value'] : $default; }
function throttle(string $key,int $limit=12): void {
 $bucket=hash('sha256',$key); $now=time();
 query('INSERT INTO login_attempts(bucket,attempts,started_at) VALUES (?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(started_at < ?,1,attempts+1), started_at=IF(started_at < ?,VALUES(started_at),started_at)',[$bucket,$now,$now-900,$now-900]);
 $r=query('SELECT attempts FROM login_attempts WHERE bucket=?',[$bucket])->fetch();
 if($r['attempts']>$limit) fail('Too many attempts. Try again in 15 minutes.',429);
}
function remote(string $method,array $params): array {
 global $config;
 $c=curl_init('https://api.telegram.org/bot'.$config['bot_token'].'/'.$method);
 curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$params,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>20,CURLOPT_TIMEOUT=>150]);
 $raw=curl_exec($c); $status=curl_getinfo($c,CURLINFO_RESPONSE_CODE); curl_close($c);
 $data=is_string($raw)?json_decode($raw,true):null;
 if(!$data || empty($data['ok'])) { if($status===429) throw new RuntimeException('Storage is busy. Please retry in a minute.'); throw new RuntimeException('Storage service is temporarily unavailable. Please retry.'); }
 return $data['result'];
}
set_exception_handler(function(Throwable $e){ if(isset($GLOBALS['config']) && dbSafeTransaction()) db()->rollBack(); fail($e instanceof RuntimeException && !($e instanceof PDOException)?$e->getMessage():'Server setup or database error. Please contact the administrator.',500); });
function dbSafeTransaction(): bool { try{return db()->inTransaction();}catch(Throwable $e){return false;} }
