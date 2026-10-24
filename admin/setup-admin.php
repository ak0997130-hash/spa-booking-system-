<?php require '../includes/config.php';require '../includes/otp.php';
// Create or reset the admin account on a LIVE server. Protected by ADMIN_SETUP_KEY, a secret you put in .env. Remove the key afterwards.
$key=(string)env('ADMIN_SETUP_KEY','');$ready=strlen($key)>=12;
if($ready&&$_POST){
 if(!rate_ok('adminsetup|'.($_SERVER['REMOTE_ADDR']??''),8,3600))$err='Too many attempts. Please try again later.';
 elseif(!hash_equals($key,(string)($_POST['key']??'')))$err='The setup key is not correct.';
 else{$em=norm_email($_POST['email']??'');$pw=(string)($_POST['password']??'');
  if(!valid_email($em))$err='Enter a valid email address.';
  elseif(strlen($pw)<10||!preg_match('/[a-z]/',$pw)||!preg_match('/[A-Z]/',$pw)||!preg_match('/\d/',$pw))$err='Use 10+ characters with an uppercase letter, a lowercase letter and a number.';
  elseif($pw!==(string)($_POST['confirm']??''))$err='Passwords do not match.';
  else{$pdo->beginTransaction();$pdo->exec("DELETE FROM admins");qx("INSERT INTO admins(email,password) VALUES(?,?)",[$em,password_hash($pw,PASSWORD_DEFAULT)]);$pdo->commit();unset($_SESSION['admin']);
   flash('Admin account saved. Sign in below, then remove ADMIN_SETUP_KEY from your .env file.');go('admin/login.php');}}}
$title='Admin setup - '.SITE;include '../includes/header.php';?>
<div class="box"><h2>Admin account setup</h2>
<?php if(!$ready):?><p>This page is switched off. To create (or reset) the admin account on this server:</p><ol><li>Open the <code>.env</code> file on the server.</li><li>Add a line <code>ADMIN_SETUP_KEY=</code> followed by a long private phrase (12+ characters).</li><li>Reload this page.</li></ol><p>Remove that line again when you are done.</p>
<?php else:?><p>Creates the admin account, replacing any existing one. Enter your setup key from <code>.env</code>.</p><?=isset($err)?'<p class="flash err" role="alert">'.e($err).'</p>':''?>
<form method="post"><?=csrf()?><label for="key">Setup key</label><input id="key" type="password" name="key" autocomplete="off" required>
<label for="email">Admin email</label><input id="email" type="email" name="email" autocomplete="username" required value="<?=e($_POST['email']??'')?>">
<label for="password">New password (10+ characters)</label><input id="password" type="password" name="password" autocomplete="new-password" required>
<label for="confirm">Confirm password</label><input id="confirm" type="password" name="confirm" autocomplete="new-password" required><p></p><button class="btn block">Save admin account</button></form><?php endif;?></div>
<?php include '../includes/footer.php';
