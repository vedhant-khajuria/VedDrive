<?php
require __DIR__.'/private/bootstrap.php';
$error=''; $done=false;
try {
 $installed=query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch();
} catch(Throwable $e) {$installed=false;}
if($installed) {http_response_code(403); exit('VedDrive is installed. Remove setup.php from the server.');}
if($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  if(!hash_equals($_SESSION['csrf'],$_POST['csrf']??'')||!hash_equals($config['setup_key'],$_POST['key']??'')) throw new RuntimeException('Invalid setup key.');
  $password=$_POST['password']??''; if(strlen($password)<12||strlen($password)>72) throw new RuntimeException('Use a password between 12 and 72 characters.');
  if(!extension_loaded('curl')||!extension_loaded('pdo_mysql')) throw new RuntimeException('Enable cURL and PDO MySQL in cPanel.');
  foreach(explode(';',file_get_contents(__DIR__.'/schema.sql')) as $sql) if(trim($sql)) db()->exec($sql);
  // Serialize installation so only one administrator can be created.
  if((int)query("SELECT GET_LOCK('veddrive_install',10) AS locked")->fetch()['locked']!==1) throw new RuntimeException('Setup is busy. Retry.');
  if(query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch()) throw new RuntimeException('Already installed.');
  query("INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,'admin')",['Vedhant Khajuria',$config['admin_email'],password_hash($password,PASSWORD_DEFAULT)]);
  query("SELECT RELEASE_LOCK('veddrive_install')"); $done=true;
 } catch(Throwable $e) {$error=$e instanceof PDOException?'Check the database settings in private/config.php and database user permissions.':$e->getMessage();}
}
?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Set up VedDrive</title><link rel="stylesheet" href="style.css"><body class="setup-page"><main class="auth-card"><img src="assets/logo.svg" width="52" height="52" alt=""><h1>Welcome to VedDrive.</h1><?php if($done): ?><p>Installation complete. Delete setup.php in cPanel File Manager, then sign in with the administrator email from your config.</p><a class="button primary" href="index.php">Open VedDrive</a><?php else: ?><p>Enter the setup key from private/config.php and choose your admin password.</p><p role="alert"><?=htmlspecialchars($error)?></p><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'])?>"><label>Setup key<input name="key" required autocomplete="off"></label><label>Admin password<input type="password" name="password" minlength="12" maxlength="72" required autocomplete="new-password"></label><button class="primary">Install VedDrive</button></form><?php endif; ?></main></body></html>
