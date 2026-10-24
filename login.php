<?php require 'includes/config.php';
if($_POST){$em=trim($_POST['email']);$pw=(string)$_POST['password'];
 if(login_blocked($em))$err='Too many failed attempts. For your security please wait 15 minutes and try again.';
 else{$q=$pdo->prepare("SELECT * FROM users WHERE email=?");$q->execute([$em]);$u=$q->fetch();$bad=false;
 if($u&&password_verify($pw,$u['password'])){
  if($u['blocked'])$err='This account is blocked. Contact support.';
  elseif(empty($u['is_verified']))$err='This account has not completed email verification.';
  else{login_ok($em);session_regenerate_id(true);$_SESSION['born']=time();$_SESSION['user']=['id'=>$u['id'],'name'=>dec($u['name'],'users.name')];go('user/dashboard.php');}}
 else{$q=$pdo->prepare("SELECT * FROM admins WHERE email=?");$q->execute([$em]);$a=$q->fetch();if(!$u&&!$a)password_verify($pw,DUMMY_HASH);
  if($a&&password_verify($pw,$a['password'])&&!default_admin_blocked($a['password'])){login_ok($em);session_regenerate_id(true);$_SESSION['born']=time();$_SESSION['admin']=['id'=>$a['id'],'email'=>$a['email']];go('admin/index.php');}
  if($a&&password_verify($pw,$a['password']))$err='The default admin password cannot be used on a live site. Create your own admin with admin/setup-admin.php.';
  else{$bad=true;$err=(is_local()&&$a)?'That email belongs to the admin account, and the password does not match. Use the Admin login page, where you can restore it.':'Wrong email or password.';}}
 if($bad)login_failed($em);}}
$title='Log in - '.SITE.'';$bg=img('hero');include 'includes/header.php';?>
<div class="auth"><aside class="auth-side" style="<?=$bg?'background-image:url('.e($bg).')':''?>"><div><h2>Welcome back</h2><p>See your upcoming treatments, book a new time or cancel a pending visit.</p></div></aside>
<div class="auth-form"><form method="post"><h2>Log in</h2><p class="sub">Use the email you registered with.</p>
<?=isset($err)?'<p class="flash err">'.e($err).'</p>':''?><?=csrf()?>
<label for="email">Email</label><input id="email" type="email" name="email" autocomplete="email" required>
<label for="password">Password</label><input id="password" type="password" name="password" autocomplete="current-password" required>
<p></p><button class="btn block">Log in</button><p class="alt">New here? <a href="<?=BASE?>register.php">Create an account</a></p></form></div></div>
<?php include 'includes/footer.php';
