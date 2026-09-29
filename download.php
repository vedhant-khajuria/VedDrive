<?php
require __DIR__.'/private/bootstrap.php';
$u=user(); $f=ownedFile($_GET['id']??0,(int)$u['id']);
if($f['status']!=='ready') fail('File is not available.',404);
$parts=query('SELECT * FROM chunks WHERE file_id=? ORDER BY part',[$f['id']])->fetchAll();
$size=(int)$f['size']; $start=0; $end=$size-1;
if(isset($_SERVER['HTTP_RANGE'])) {
 if(!preg_match('/^bytes=(\d*)-(\d*)$/',$_SERVER['HTTP_RANGE'],$m) || ($m[1]===''&&$m[2]==='')) {header('Content-Range: bytes */'.$size); http_response_code(416); exit;}
 if($m[1]==='') $start=max(0,$size-(int)$m[2]); else { $start=(int)$m[1]; if($m[2]!=='') $end=min($end,(int)$m[2]); }
 if($start>$end||$start>=$size) {header('Content-Range: bytes */'.$size); http_response_code(416); exit;}
 http_response_code(206); header("Content-Range: bytes $start-$end/$size");
}
$safe=['image/jpeg','image/png','image/gif','image/webp','image/avif','video/mp4','video/webm','audio/mpeg','audio/mp4','audio/ogg','audio/wav','application/pdf'];
$inline=isset($_GET['preview'])&&in_array($f['mime'],$safe,true);
header('Content-Type: '.($inline?$f['mime']:'application/octet-stream'));
header('Content-Security-Policy: sandbox');
header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="download"; filename*=UTF-8\'\''.rawurlencode($f['name']));
header('Accept-Ranges: bytes'); header('Content-Length: '.($end-$start+1));
session_write_close(); set_time_limit(0);
if($_SERVER['REQUEST_METHOD']==='HEAD') exit;
$offset=0;
foreach($parts as $part) {
 $partEnd=$offset+(int)$part['size']-1;
 if($partEnd>=$start&&$offset<=$end) {
  try {
   $r=remote('getFile',['file_id'=>$part['remote_id']]);
   $c=curl_init('https://api.telegram.org/file/bot'.$config['bot_token'].'/'.$r['file_path']);
   curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>20,CURLOPT_TIMEOUT=>150]);
   $data=curl_exec($c); $code=curl_getinfo($c,CURLINFO_RESPONSE_CODE); curl_close($c);
   if($code!==200||!is_string($data)||strlen($data)!==(int)$part['size']) exit;
   echo substr($data,max(0,$start-$offset),min($end,$partEnd)-max($start,$offset)+1); flush();
  } catch(Throwable $e) {exit;}
 }
 $offset=$partEnd+1; if($offset>$end||connection_aborted()) break;
}
