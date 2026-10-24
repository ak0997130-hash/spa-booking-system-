<?php require '../includes/config.php';
// Emergency admin reset. Works only from the computer running the site (localhost). Delete this file when you are done.
if(!is_local())fail('Not available','The admin reset page only works on the computer the site runs on. Open it as http://localhost/... on that computer.');
if($_POST){$em=trim($_POST['email']);$pw=(string)$_POST['password'];
 if(!filter_var($em,FILTER_VALIDATE_EMAIL)||strlen($pw)<8)$err='Enter a valid email and a password of 8+ characters.';
 else{$pdo->beginTransaction();$pdo->exec("DELETE FROM admins");$pdo->prepare("INSERT INTO admins(email,password) VALUES(?,?)")->execute([$em,password_hash($pw,PASSWORD_DEFAULT)]);$pdo->commit();
  unset($_SESSION['admin']);flash('Admin account reset. Log in below, then delete admin/reset.php.');go('admin/login.php');}}
$title='Reset admin - '.SITE;include '../includes/header.php';?>
<form class="box" method="post"><h2>Reset admin account</h2><p>Sets the admin email and password. Any previous admin login is replaced.</p><?=isset($err)?'<p class="flash err">'.e($err).'</p>':''?><?=csrf()?>
<label for="email">Admin email</label><input id="email" type="email" name="email" value="admin@servicehub.com" required><label for="password">New password (8+ characters)</label><input id="password" type="password" name="password" minlength="8" required><p></p><button class="btn block">Reset admin</button></form>
<?php include '../includes/footer.php';
