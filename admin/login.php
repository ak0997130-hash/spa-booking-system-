<?php require '../includes/config.php';
if(!empty($_SESSION['admin']))go('admin/index.php');
if($_POST){$em=trim($_POST['email']);
 if(login_blocked($em))$err='Too many failed attempts. For your security please wait 15 minutes and try again.';
 else{$q=$pdo->prepare("SELECT * FROM admins WHERE email=?");$q->execute([$em]);$a=$q->fetch();if(!$a)password_verify((string)$_POST['password'],DUMMY_HASH);
 if(!$a||!password_verify((string)$_POST['password'],$a['password'])){$err='Wrong email or password. Use the admin account, not a customer account.';login_failed($em);}
 elseif(default_admin_blocked($a['password']))$err='The default admin password cannot be used on a live site. Create your own admin with setup-admin.php.';
 else{login_ok($em);session_regenerate_id(true);$_SESSION['born']=time();$_SESSION['admin']=['id'=>$a['id'],'email'=>$a['email']];go('admin/index.php');}}}
if(!isset($err)&&!$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn())$err='No admin account exists yet. '.(is_local()?'Open admin/reset.php on this computer to create one.':'Create one with admin/setup-admin.php.');
$m=flash();?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin login - <?=e(SITE)?></title>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?=BASE?>assets/css/admin.css"></head><body><div class="login"><form method="post"><h1><?=e(SITE)?></h1><p class="sub">Admin sign in</p>
<?=$m?'<p class="toast" style="margin:12px 0">'.e($m).'</p>':''?><?=isset($err)?'<p class="err">'.e($err).'</p>':''?><?=csrf()?>
<label for="email">Email</label><input id="email" type="email" name="email" autocomplete="username" required autofocus><label for="password">Password</label><input id="password" type="password" name="password" autocomplete="current-password" required><p></p>
<button class="btn block">Sign in</button><p class="alt"><a href="<?=BASE?>">Back to website</a> &middot; <a href="<?=BASE?>login.php">Customer log in</a></p></form></div></body></html>
