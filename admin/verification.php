<?php require '_layout.php';
$K=['MAIL_HOST','MAIL_PORT','MAIL_ENCRYPTION','MAIL_USER','MAIL_FROM','MAIL_FROM_NAME'];$res=null;$act=$_POST['action']??'';
if($act==='save'){$kv=[];foreach($K as $k)$kv[$k]=trim($_POST[$k]??'');if(($_POST['MAIL_PASS']??'')!=='')$kv['MAIL_PASS']=$_POST['MAIL_PASS']; // blank password = keep the saved one
 $kv['OTP_DEV_MODE']=isset($_POST['dev'])?'1':'0';$kv['MAIL_DRIVER']=($_POST['MAIL_DRIVER']??'')==='mail'?'mail':'smtp';$port=(int)$kv['MAIL_PORT'];$err='';
 if($kv['MAIL_HOST']!==''&&!preg_match('/^[A-Za-z0-9.\-]{1,255}$/',$kv['MAIL_HOST']))$err='Enter a valid SMTP host name (letters, numbers, dots and dashes only).';
 elseif(strlen($kv['MAIL_USER'])>255||strlen($kv['MAIL_FROM_NAME'])>100||strlen((string)($_POST['MAIL_PASS']??''))>255)$err='One of the fields is too long.';
 elseif($kv['MAIL_HOST']!==''&&($port<1||$port>65535))$err='Enter a valid email port (usually 587 or 465).';
 elseif(!in_array($kv['MAIL_ENCRYPTION'],['','tls','ssl','none'],true))$err='Choose an encryption type.';
 elseif(($kv['MAIL_HOST']!==''||$kv['MAIL_DRIVER']==='mail')&&!valid_email(norm_email($kv['MAIL_FROM'])))$err='Enter a valid "from" email address.';
 if($err)flash('!'.$err);else flash(local_set($kv)?'Settings saved.':'!Could not write includes/local.settings.php. Make the includes folder writable, or put the settings in a .env file instead.');
 go('admin/verification.php');}
if($act==='test_email'){$to=norm_email($_POST['to']??'');
 if(!valid_email($to))$res=[false,'Enter a valid email address to send the test to.'];
 else{$r=mail_send($to,SITE.' test email','<p>Your email settings work. Customers will receive verification codes like this.</p>','Your email settings work.');$res=[$r===true,$r===true?(env('MAIL_DRIVER')==='mail'?'Test email handed to the mail system for '.$to.'. PHP mail() cannot confirm delivery, so check the inbox (and spam) within a few minutes.':'Test email sent to '.$to.'. Check the inbox (and spam).'):($r==='not_configured'?'Email is not configured yet. Save the email settings first.':'Could not send: '.($GLOBALS['otp_detail']?:'unknown error'))];}}
$st=otp_status();$v=fn($k)=>e(env($k,''));$has=env('MAIL_PASS','')!==''?'(saved - leave blank to keep)':'';
$chk=[['Secret key',strlen((string)env('APP_SECRET',''))>=32,'Created automatically'],['PHP extension: openssl (secure email)',extension_loaded('openssl'),'Enable extension=openssl in php.ini'],['Email settings',$st['email'],'Fill in the Email section'],['Local test mode',$st['dev'],'Optional. Shows codes on screen on this computer only']];
a_head('Verification','verification');?>
<p style="margin:0;color:var(--mute)">Customers verify their email with a one-time code when they register. Enter your sending details here, then send a test to confirm they work.</p>
<?php if($res):?><p class="toast<?=$res[0]?'':' err'?>" style="margin:0"><?=e($res[1])?></p><?php endif;?>
<div class="panel"><h3>Status</h3><table><?php foreach($chk as [$l,$okk,$h]):?><tr><td><?=e($l)?></td><td><?=pill($okk?'Ready':'Not ready',$okk?'live':'off')?></td><td><small><?=$okk?'':e($h)?></small></td></tr><?php endforeach;?></table></div>
<form method="post" class="panel"><?=csrf()?><input type="hidden" name="action" value="save">
<h3>Email</h3><div class="cols2"><div><label>Sending method</label><select name="MAIL_DRIVER"><option value="smtp"<?=env('MAIL_DRIVER')!=='mail'?' selected':''?>>SMTP (recommended)</option><option value="mail"<?=env('MAIL_DRIVER')==='mail'?' selected':''?>>PHP mail() function</option></select></div>
<p style="color:var(--mute);margin:24px 0 0;font-size:.88rem">SMTP signs in to a mail server (works with a Gmail App Password). PHP mail() uses the mail program of your server and needs only the "From" address below; the SMTP fields are then ignored.</p></div><div class="cols2"><div><label>SMTP host</label><input name="MAIL_HOST" value="<?=$v('MAIL_HOST')?>" placeholder="smtp.gmail.com or mail.yourdomain.com"></div><div class="row2"><div><label>Port</label><input name="MAIL_PORT" value="<?=$v('MAIL_PORT')?:'587'?>"></div>
<div><label>Encryption</label><select name="MAIL_ENCRYPTION"><?php foreach(['tls'=>'TLS (587)','ssl'=>'SSL (465)','none'=>'None'] as $k=>$l):?><option value="<?=$k?>"<?=(env('MAIL_ENCRYPTION','tls')===$k)?' selected':''?>><?=$l?></option><?php endforeach;?></select></div></div>
<div><label>Username</label><input name="MAIL_USER" value="<?=$v('MAIL_USER')?>" autocomplete="off"></div><div><label>Password / app password</label><input type="password" name="MAIL_PASS" placeholder="<?=e($has)?>" autocomplete="new-password"></div>
<div><label>From email</label><input name="MAIL_FROM" value="<?=$v('MAIL_FROM')?>" placeholder="no-reply@yourdomain.com"></div><div><label>From name</label><input name="MAIL_FROM_NAME" value="<?=$v('MAIL_FROM_NAME')?:e(SITE)?>"></div></div>
<h3 style="margin-top:22px">Local test mode</h3><label style="font-weight:400"><input type="checkbox" name="dev"<?=env('OTP_DEV_MODE')==='1'?' checked':''?>> Show verification codes on screen instead of emailing them. Works only when you browse from this computer (localhost) and never on a live site. Use it to try sign-up before email is ready.</label>
<p></p><button class="btn">Save settings</button> <small style="color:var(--mute)">Settings in a .env file or server environment variables override these. Saved to includes/local.settings.php (not publicly readable).</small></form>
<form method="post" class="panel"><?=csrf()?><input type="hidden" name="action" value="test_email"><h3>Send a test email</h3><label>Send to</label><input name="to" type="email" required placeholder="your.address@example.com"><p></p><button class="btn ghost">Send test email</button></form>
<?php a_foot();
