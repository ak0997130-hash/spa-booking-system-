<?php require '_layout.php';
if($_POST&&($_POST['action']??'')==='password'){$h=(string)one("SELECT password FROM admins WHERE id=?",[$_SESSION['admin']['id']]);
 if(!password_verify($_POST['current'],$h))flash('!Your current password is not correct.');elseif(strlen($_POST['new'])<8||$_POST['new']!==$_POST['confirm'])flash('!The new password needs 8+ characters and must match the confirmation.');
 else{$pdo->prepare("UPDATE admins SET password=? WHERE id=?")->execute([password_hash($_POST['new'],PASSWORD_DEFAULT),$_SESSION['admin']['id']]);flash('Password changed.');}go('admin/settings.php');}
if($_POST&&($_POST['action']??'')==='details'){$L=['name'=>60,'hero_title'=>120,'hero_text'=>300,'phone'=>30,'email'=>120,'address'=>200,'hours'=>120];$v=[];
 foreach($L as $k=>$n)$v[$k]=cut(trim($_POST[$k]??''),$n);
 if($v['email']!==''&&!valid_email(norm_email($v['email'])))flash('!Enter a valid contact email address, or leave it blank.');
 else{foreach($v as $k=>$x){if($x==='')qx("DELETE FROM settings WHERE k=?",[$k]);else qx("INSERT INTO settings(k,v) VALUES(?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)",[$k,$x]);}flash('Business details saved. They are live on the website.');}
 go('admin/settings.php');}
a_head('Settings','settings');?>
<form class="panel" method="post"><h3>Business details</h3><p style="margin:0 0 6px;color:var(--mute)">Shown on the website (name, home page heading, footer contact details) and in verification emails. Leave a field blank to use the default.</p><?=csrf()?><input type="hidden" name="action" value="details">
<div class="cols2"><div><label>Business name</label><input name="name" value="<?=e(cfg('name'))?>" placeholder="Stillwater Spa" maxlength="60"></div><div><label>Contact phone</label><input name="phone" value="<?=e(cfg('phone'))?>" maxlength="30"></div>
<div><label>Contact email</label><input name="email" type="email" value="<?=e(cfg('email'))?>" maxlength="120"></div><div><label>Opening hours</label><input name="hours" value="<?=e(cfg('hours'))?>" placeholder="Mon-Sun 10:00 - 19:00" maxlength="120"></div>
<div><label>Address</label><input name="address" value="<?=e(cfg('address'))?>" maxlength="200"></div><div><label>Home page heading</label><input name="hero_title" value="<?=e(cfg('hero_title'))?>" placeholder="Slow down. You've earned it." maxlength="120"></div></div>
<label>Home page intro text</label><textarea name="hero_text" rows="2" maxlength="300"><?=e(cfg('hero_text'))?></textarea><p></p><button class="btn">Save business details</button></form>
<div class="cols2"><form class="panel" method="post"><h3>Change password</h3><?=csrf()?><input type="hidden" name="action" value="password"><label>Current password</label><input type="password" name="current" required><label>New password (8+ characters)</label><input type="password" name="new" minlength="8" required><label>Confirm new password</label><input type="password" name="confirm" required><p></p><button class="btn">Update password</button></form>
<div class="panel"><h3>Your website</h3><p>Everything customers see is managed from here: treatments, prices, photos and bookings.</p><p><a class="btn ghost" href="<?=BASE?>" target="_blank">Open website</a> <a class="btn ghost" href="<?=BASE?>register.php" target="_blank">Sign-up page</a></p>
<p style="color:var(--mute)">Site name: <b><?=e(SITE)?></b>.<br>Booking limit: <b><?=SLOT_CAPACITY?></b> per treatment and time slot.</p></div></div>
<?php a_foot();
