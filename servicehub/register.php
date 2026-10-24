<?php require 'includes/config.php';require 'includes/otp.php';
$ccs=['+91'=>'India (+91)','+1'=>'US / Canada (+1)','+44'=>'UK (+44)','+971'=>'UAE (+971)','+65'=>'Singapore (+65)','+61'=>'Australia (+61)','+977'=>'Nepal (+977)','+880'=>'Bangladesh (+880)','+94'=>'Sri Lanka (+94)','+92'=>'Pakistan (+92)','+966'=>'Saudi Arabia (+966)'];
$f=['name'=>'','email'=>'','cc'=>'+91','phone'=>''];$errs=[];
if($_POST){foreach($f as $k=>$_)$f[$k]=trim((string)($_POST[$k]??''));$p=(string)($_POST['password']??'');$cf=(string)($_POST['confirm']??'');
 $em=norm_email($f['email']);$ph=norm_phone($f['cc'],$f['phone']);
 if(!preg_match('/^.{2,100}$/us',$f['name']))$errs['name']='Enter your full name.';
 if(!valid_email($em))$errs['email']='Enter a valid email address.';
 if(!$ph)$errs['phone']='Enter a valid mobile number with its country code.';
 if(strlen($p)<8||!preg_match('/[a-z]/',$p)||!preg_match('/[A-Z]/',$p)||!preg_match('/\d/',$p))$errs['password']='Use 8+ characters with an uppercase letter, a lowercase letter and a number.';
 elseif($p!==$cf)$errs['confirm']='Passwords do not match.';
 if(!$errs&&!otp_secret())$errs['form']='Verification is not configured on this server yet.';
 if(!$errs){try{$pdo->beginTransaction();
   // Only the server's own record counts: a verified, unused code for exactly this email, requested from this browser session.
   $id=qx("SELECT id FROM otp_challenges WHERE bind_key=? AND channel='email' AND destination=? AND status='verified' AND verified_at>NOW()-INTERVAL ".VERIFIED_TTL." SECOND ORDER BY verified_at DESC LIMIT 1 FOR UPDATE",[bind_key(),$em])->fetchColumn();
   if(!$id){$errs['email']='Verify your email address with a code before registering.';$pdo->rollBack();}
   else{qx("INSERT INTO users(name,email,phone,password,email_verified_at,is_verified) VALUES(?,?,?,?,NOW(),1)",[enc($f['name'],'users.name'),$em,enc($ph,'users.phone'),password_hash($p,defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT)]);
    if(qx("UPDATE otp_challenges SET status='consumed' WHERE id=? AND status='verified'",[$id])->rowCount()!==1)throw new RuntimeException('challenge already used');
    $pdo->commit();unset($_SESSION['reg_bind']);session_regenerate_id(true);flash('Account created and verified. Please log in.');go('login.php');}}
  catch(Throwable $x){if($pdo->inTransaction())$pdo->rollBack();$dup=$x instanceof PDOException&&(int)($x->errorInfo[1]??0)===1062;
   $errs['form']=$dup?'These details cannot be used to create a new account. If you already registered, please log in.':'We could not create the account right now. Please try again.';
   if(!$dup)error_log('Registration failed ('.get_class($x).')');}}}
$vE=verified_dest('email');$vs=otp_status();$setup=is_local()&&!$vs['dev']&&!$vs['email'];
$title='Create account - '.SITE;$bg=img('hero');$js=true;include 'includes/header.php';?>
<div class="auth"><aside class="auth-side" style="<?=$bg?'background-image:url('.e($bg).')':''?>"><div><h2>Your first treatment is two minutes away</h2><p>Free to join. We verify your email so booking updates always reach you.</p></div></aside>
<div class="auth-form"><form method="post" id="reg" novalidate data-base="<?=e(BASE)?>" data-ve="<?=e($vE)?>"><h2>Create account</h2><p class="sub">Verify your email to finish.</p>
<?php if($setup):?><p class="flash err" role="note"><b>Setup needed (only you see this, on this computer):</b> email verification is not configured yet. <a href="<?=BASE?>admin/verification.php">Open Verification settings</a> to add your email details, or switch on local test mode to try the sign-up now.</p><?php endif;?>
<?=!empty($errs['form'])?'<p class="flash err" role="alert">'.e($errs['form']).'</p>':''?><?=csrf()?>
<div class="field"><label for="name">Full name</label><input id="name" name="name" autocomplete="name" required value="<?=e($f['name'])?>" aria-describedby="name-err"><p class="msg err" id="name-err" role="alert"><?=e($errs['name']??'')?></p></div>
<div class="field vf" data-ch="email"><label for="email">Email address</label>
 <div class="inline"><input id="email" name="email" type="email" autocomplete="email" required value="<?=e($f['email'])?>" aria-describedby="email-msg"><button type="button" class="btn ghost sm" data-act="send">Send Email OTP</button></div>
 <p class="vok" hidden>&#10003; Email verified <button type="button" class="link" data-act="change">Change</button></p>
 <div class="otp" hidden><label for="email-otp">Email code</label><div class="inline"><input id="email-otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="6-digit code"><button type="button" class="btn sm" data-act="verify">Verify Email</button></div>
 <p class="hint"><span class="t-exp"></span> <button type="button" class="link" data-act="resend" disabled>Resend code</button></p></div>
 <p class="msg<?=isset($errs['email'])?' err':''?>" id="email-msg" role="status" aria-live="polite"><?=e($errs['email']??'')?></p></div>
<p class="hint" style="margin-top:-6px">No code after a minute? Check the address and your spam folder, or <a href="<?=BASE?>login.php">log in</a> if you already have an account.</p>
<div class="field"><label for="phone">Mobile number</label><div class="inline"><select name="cc" id="cc" aria-label="Country code"><?php foreach($ccs as $v=>$l):?><option value="<?=$v?>"<?=$f['cc']===$v?' selected':''?>><?=e($l)?></option><?php endforeach;?></select>
 <input id="phone" name="phone" type="tel" autocomplete="tel-national" required value="<?=e($f['phone'])?>" aria-describedby="phone-err"></div><p class="msg err" id="phone-err" role="alert"><?=e($errs['phone']??'')?></p></div>
<div class="field"><label for="pw">Password</label><input id="pw" type="password" name="password" autocomplete="new-password" required aria-describedby="password-err"><p class="msg err" id="password-err" role="alert"><?=e($errs['password']??'')?></p></div>
<div class="field"><label for="cf">Confirm password</label><input id="cf" type="password" name="confirm" autocomplete="new-password" required aria-describedby="confirm-err"><p class="msg err" id="confirm-err" role="alert"><?=e($errs['confirm']??'')?></p></div>
<button class="btn block" id="regbtn">Register Account</button><p class="alt">Already registered? <a href="<?=BASE?>login.php">Log in</a></p></form></div></div>
<?php include 'includes/footer.php';
