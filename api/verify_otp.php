<?php define('IS_API',1);require __DIR__.'/../includes/config.php';require __DIR__.'/../includes/otp.php';
// POST channel=email, email=..., code=123456, csrf=... Always JSON.
if($_SERVER['REQUEST_METHOD']!=='POST')json_out(['ok'=>false,'error'=>'POST required.'],405);
if(!otp_secret())json_out(['ok'=>false,'error'=>'Verification is not configured on this server yet.'],503);
if(($_POST['channel']??'')!=='email')json_out(['ok'=>false,'error'=>'Invalid request.'],400);
$d=norm_email($_POST['email']??'');if(!valid_email($d))json_out(['ok'=>false,'field'=>'email','error'=>'Enter a valid email address.'],422);
[$c,$r]=otp_verify('email',$d,trim((string)($_POST['code']??'')));json_out($r,$c);
