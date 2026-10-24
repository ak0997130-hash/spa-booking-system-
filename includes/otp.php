<?php
// OTP service for registration: validation, rate limiting, challenges (stored as HMAC hashes) and email/SMS delivery.
const OTP_TTL=300,OTP_COOLDOWN=60,OTP_MAX_ATTEMPTS=5,VERIFIED_TTL=1800;
function qx($sql,$p=[]){global $pdo;$s=$pdo->prepare($sql);$s->execute($p);return $s;}
// APP_SECRET: from .env/server env, else generated once and saved in includes/local.settings.php (never hand-edit needed).
function otp_secret(){$s=(string)env('APP_SECRET','');if(strlen($s)<32){local_set(['APP_SECRET'=>bin2hex(random_bytes(32))],true);$s=(string)env('APP_SECRET','');}return strlen($s)>=32?$s:null;}
// Local test mode: shows the code on screen instead of sending it. Only ever active on localhost, never in production, and only when switched on.
function is_dev_mode(){return env('APP_ENV')!=='production'&&is_local()&&env('OTP_DEV_MODE')==='1';}
// MAIL_DRIVER=smtp (default: logs in to a mail server) or MAIL_DRIVER=mail (PHP's built-in mail() function, needs only a From address).
function mail_ready(){if(env('MAIL_DRIVER')==='mail')return function_exists('mail')&&valid_email(norm_email((string)env('MAIL_FROM','')));return (bool)(env('MAIL_HOST')&&env('MAIL_FROM'));}
function otp_status(){return ['email'=>mail_ready(),'dev'=>is_dev_mode()];}
// Each browser session gets a random binding key; challenges only count for the session that requested them.
function bind_key(){if(empty($_SESSION['reg_bind']))$_SESSION['reg_bind']=bin2hex(random_bytes(16));return hash('sha256',$_SESSION['reg_bind']);}
function norm_email($e){return strtolower(trim((string)$e));}
function valid_email($e){return strlen($e)<=120&&filter_var($e,FILTER_VALIDATE_EMAIL)!==false;}
function norm_phone($cc,$n){$cc=preg_replace('/\D/','',(string)$cc);$n=ltrim(preg_replace('/\D/','',(string)$n),'0');$all=$cc.$n;
 if($cc===''||$n===''||strlen($all)<8||strlen($all)>15)return null;
 if($cc==='91'&&!preg_match('/^[6-9]\d{9}$/',$n))return null;
 return '+'.$all;}
function otp_hash($otp,$id,$ch,$d){return hash_hmac('sha256',$otp,otp_secret().'|'.$id.'|'.$ch.'|'.$d);}
function rate_ok($key,$max,$win){$k=substr(hash('sha256',$key),0,40);$w=(int)$win;
 qx("INSERT INTO rate_limits(k,hits,window_start) VALUES(?,1,NOW()) ON DUPLICATE KEY UPDATE hits=IF(window_start<NOW()-INTERVAL $w SECOND,1,hits+1),window_start=IF(window_start<NOW()-INTERVAL $w SECOND,NOW(),window_start)",[$k]);
 return (int)qx("SELECT hits FROM rate_limits WHERE k=?",[$k])->fetchColumn()<=$max;}
function otp_cleanup(){qx("DELETE FROM otp_challenges WHERE created_at<NOW()-INTERVAL 1 DAY");qx("DELETE FROM rate_limits WHERE window_start<NOW()-INTERVAL 1 DAY");}
function verified_dest($ch){return (string)qx("SELECT destination FROM otp_challenges WHERE bind_key=? AND channel=? AND status='verified' AND verified_at>NOW()-INTERVAL ".VERIFIED_TTL." SECOND ORDER BY verified_at DESC LIMIT 1",[bind_key(),$ch])->fetchColumn();}

function otp_send($ch,$d){global $pdo;$b=bind_key();
 $w=qx("SELECT TIMESTAMPDIFF(SECOND,NOW(),resend_available_at) w,GREATEST(0,TIMESTAMPDIFF(SECOND,NOW(),expires_at)) x,status FROM otp_challenges WHERE bind_key=? AND channel=? ORDER BY created_at DESC LIMIT 1",[$b,$ch])->fetch();
 if($w&&(int)$w['w']>0)return [429,['ok'=>false,'error'=>"Please wait {$w['w']} seconds before requesting another code.",'retry_in'=>(int)$w['w'],'expires_in'=>$w['status']==='pending'?(int)$w['x']:0]];
 foreach([['ip:'.($_SERVER['REMOTE_ADDR']??''),(int)env('OTP_LIMIT_IP',20)],['sess:'.$b,10],['dest:'.$ch.':'.$d,$ch==='email'?5:3]] as [$k,$m])
  if(!rate_ok('send|'.$k,$m,3600))return [429,['ok'=>false,'error'=>'Too many code requests. Please try again later.','retry_in'=>3600]];
 // Already-registered email/phone: answer exactly like a normal request but send nothing (prevents account enumeration).
 $taken=(bool)qx("SELECT 1 FROM users WHERE ".($ch==='email'?'email':'phone')."=? LIMIT 1",[$d])->fetchColumn();
 if(mt_rand(1,20)===1)otp_cleanup();
 $id=bin2hex(random_bytes(16));$otp=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
 $pdo->beginTransaction();
 qx("UPDATE otp_challenges SET status='expired' WHERE bind_key=? AND channel=? AND status IN('pending','verified')",[$b,$ch]); // new code invalidates the old one and any earlier verification
 qx("INSERT INTO otp_challenges(id,purpose,channel,destination,otp_hash,bind_key,status,attempts,resend_available_at,expires_at,created_at) VALUES(?,'register',?,?,?,?,'pending',0,NOW()+INTERVAL ".OTP_COOLDOWN." SECOND,NOW()+INTERVAL ".OTP_TTL." SECOND,NOW())",[$id,$ch,$d,otp_hash($otp,$id,$ch,$d),$b]);
 $pdo->commit();
 $ok=['ok'=>true,'message'=>'If this '.($ch==='email'?'address':'number').' can be used, a 6-digit code is on its way. It expires in 5 minutes.','expires_in'=>OTP_TTL,'resend_in'=>OTP_COOLDOWN];
 if(is_dev_mode()){$ok['message']='Test mode (this computer only): nothing was sent.';if($taken)$ok['message'].=' This '.($ch==='email'?'email':'number').' is already registered.';else $ok['dev_code']=$otp;return [200,$ok];}
 if(!$taken){$r=mail_otp($d,$otp);
  if($r!==true){qx("UPDATE otp_challenges SET status='expired',resend_available_at=NOW() WHERE id=?",[$id]);
   $e=$r==='not_configured'?[503,['ok'=>false,'error'=>($ch==='email'?'Email':'SMS').' delivery is not set up on this server yet. The site owner needs to complete the provider settings.']]:[502,['ok'=>false,'error'=>'We could not send the code right now. Please try again in a moment.']];
   if(is_local()){$e[1]['debug']=(string)($GLOBALS['otp_detail']??'');$e[1]['hint']='Open Admin > Verification to enter your email/SMS settings, or switch on test mode.';}  // detail is shown on localhost only
   return $e;}}
 return [200,$ok];}

function otp_verify($ch,$d,$code){global $pdo;$b=bind_key();
 if(!preg_match('/^\d{6}$/',$code))return [422,['ok'=>false,'field'=>'code','error'=>'Enter the 6-digit code.']];
 foreach([['ip:'.($_SERVER['REMOTE_ADDR']??''),(int)env('OTP_LIMIT_VERIFY_IP',60)],['sess:'.$b,30]] as [$k,$m])
  if(!rate_ok('verify|'.$k,$m,3600))return [429,['ok'=>false,'error'=>'Too many attempts. Please try again later.']];
 $pdo->beginTransaction();
 $c=qx("SELECT *,(expires_at<=NOW()) AS gone FROM otp_challenges WHERE bind_key=? AND channel=? AND destination=? AND status='pending' ORDER BY created_at DESC LIMIT 1 FOR UPDATE",[$b,$ch,$d])->fetch();
 if(!$c){$pdo->commit();return [422,['ok'=>false,'error'=>'No active code for this '.($ch==='email'?'address':'number').'. Request a new one.']];}
 if($c['gone']){qx("UPDATE otp_challenges SET status='expired' WHERE id=?",[$c['id']]);$pdo->commit();return [410,['ok'=>false,'error'=>'That code has expired. Request a new one.']];}
 if(!hash_equals($c['otp_hash'],otp_hash($code,$c['id'],$ch,$d))){$n=(int)$c['attempts']+1;
  qx("UPDATE otp_challenges SET attempts=?,status=? WHERE id=?",[$n,$n>=OTP_MAX_ATTEMPTS?'locked':'pending',$c['id']]);$pdo->commit();
  return [422,['ok'=>false,'error'=>$n>=OTP_MAX_ATTEMPTS?'Too many incorrect attempts. Request a new code.':'Incorrect code. '.(OTP_MAX_ATTEMPTS-$n).' attempt(s) left.','attempts_left'=>max(0,OTP_MAX_ATTEMPTS-$n)]];}
 qx("UPDATE otp_challenges SET status='verified',verified_at=NOW() WHERE id=?",[$c['id']]);$pdo->commit();
 return [200,['ok'=>true,'verified'=>true,'message'=>($ch==='email'?'Email':'Phone number').' verified.']];}

function ca_file(){$f=__DIR__.'/cacert.pem';return is_file($f)?$f:null;}
// Sends with PHP's built-in mail(). Note: mail() only hands the message to the server's mail system; it cannot confirm delivery.
function mail_php($to,$subject,$html,$alt){$from=norm_email((string)env('MAIL_FROM',''));$name=trim(str_replace(["\r","\n"],' ',(string)env('MAIL_FROM_NAME',SITE)));
 if(!valid_email($from)||!valid_email($to))return 'not_configured';
 $enc=function($s){return '=?UTF-8?B?'.base64_encode($s).'?=';};$nl="\r\n";$bd='b'.bin2hex(random_bytes(12));
 $h=['MIME-Version: 1.0','From: '.$enc($name).' <'.$from.'>','Reply-To: '.$from,'Date: '.date('r'),'Message-ID: <'.bin2hex(random_bytes(12)).'@'.substr(strrchr($from,'@'),1).'>','Content-Type: multipart/alternative; boundary="'.$bd.'"'];
 $part=function($type,$body)use($bd,$nl){return '--'.$bd.$nl.'Content-Type: '.$type.'; charset=UTF-8'.$nl.'Content-Transfer-Encoding: base64'.$nl.$nl.chunk_split(base64_encode($body),76,$nl);};
 $msg=$part('text/plain',$alt).$part('text/html',$html).'--'.$bd.'--'.$nl;$extra=PHP_OS_FAMILY==='Windows'?'':'-f'.$from;
 $ok=@mail($to,$enc($subject),$msg,implode($nl,$h),$extra);if(!$ok&&$extra!=='')$ok=@mail($to,$enc($subject),$msg,implode($nl,$h)); // some hosts refuse the -f option
 if($ok)return true;$e=error_get_last();$GLOBALS['otp_detail']='PHP mail() could not hand the message to the mail system'.($e?': '.trim(preg_replace('/\s+/',' ',strip_tags($e['message']))):'.');
 error_log('OTP email delivery failed (mail())');return 'failed';}
function mail_send($to,$subject,$html,$alt){$GLOBALS['otp_detail']='';if(!mail_ready())return 'not_configured';if(env('MAIL_DRIVER')==='mail')return mail_php($to,$subject,$html,$alt);
 foreach(['Exception','SMTP','PHPMailer'] as $f)require_once __DIR__."/lib/PHPMailer/$f.php";
 try{$m=new PHPMailer\PHPMailer\PHPMailer(true);$m->isSMTP();$m->Host=env('MAIL_HOST');$m->Port=(int)env('MAIL_PORT',587);$m->Timeout=10;$m->CharSet='UTF-8';
  $u=(string)env('MAIL_USER','');$m->SMTPAuth=$u!=='';if($u!==''){$m->Username=$u;$m->Password=(string)env('MAIL_PASS','');}
  $enc=strtolower((string)env('MAIL_ENCRYPTION','tls'));$m->SMTPSecure=$enc==='ssl'?'ssl':($enc==='none'?'':'tls');$m->SMTPAutoTLS=$enc==='tls';
  // XAMPP on Windows often has no CA bundle configured; fall back to the bundled one. MAIL_VERIFY_PEER=0 disables checking (local testing only).
  if(env('MAIL_VERIFY_PEER')==='0')$m->SMTPOptions=['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true]];
  elseif(($ca=ca_file())&&!ini_get('openssl.cafile'))$m->SMTPOptions=['ssl'=>['cafile'=>$ca]];
  $m->setFrom(env('MAIL_FROM'),env('MAIL_FROM_NAME',SITE));$m->addAddress($to);$m->Subject=$subject;$m->isHTML(true);$m->Body=$html;$m->AltBody=$alt;$m->send();return true;}
 catch(Throwable $x){$d=trim(preg_replace('/\s+/',' ',strip_tags($x->getMessage())));$pw=(string)env('MAIL_PASS','');if($pw!=='')$d=str_replace($pw,'***',$d);
  $GLOBALS['otp_detail']=$d;error_log('OTP email delivery failed ('.get_class($x).')');return 'failed';}}
function mail_otp($to,$otp){return mail_send($to,SITE.' verification code','<p>Your verification code is <b style="font-size:22px;letter-spacing:3px">'.$otp.'</b></p><p>It expires in 5 minutes. If you did not request it, you can ignore this email.</p>',"Your verification code is $otp. It expires in 5 minutes. If you did not request it, ignore this email.");}
