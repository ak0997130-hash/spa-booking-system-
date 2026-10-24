<?php define('IN_SETUP',1);require 'includes/config.php';
// Local installer (XAMPP/WAMP). On a hosting account, create the database in the control panel and import database_hosting.sql instead.
$opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION];$installed=false;
try{(new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,$opt))->query('SELECT 1 FROM admins LIMIT 1');$installed=true;}catch(PDOException $x){}
if($_POST&&!$installed){
 if(!preg_match('/^[A-Za-z0-9_$-]+$/',DB_NAME))$err='DB_NAME may only contain letters, numbers, _ - and $.';
 else try{$p=new PDO('mysql:host='.DB_HOST.';charset=utf8mb4',DB_USER,DB_PASS,$opt);
  try{$p->exec('CREATE DATABASE IF NOT EXISTS `'.DB_NAME.'` CHARACTER SET utf8mb4');}catch(PDOException $x){} // hosts usually forbid this: the database must already exist there
  $p->exec('USE `'.DB_NAME.'`');
  $sql=preg_replace('/^--.*$/m','',file_get_contents(__DIR__.'/database.sql'));
  foreach(explode(';',$sql) as $q){$q=trim($q);if($q===''||preg_match('/^(CREATE DATABASE|USE)\b/i',$q))continue;$p->exec($q);}
  flash('Setup complete. You can now create an account or log in.');go('login.php');}
 catch(PDOException $x){$err=$x->getCode()==1045?'MySQL rejected the login. Check DB_USER and DB_PASS (.env).':(in_array((int)$x->getCode(),[2002,2006])?'Cannot reach MySQL. Start it in XAMPP/WAMP and try again.':($x->getCode()==1049?'The database "'.DB_NAME.'" does not exist and could not be created. Create it first.':$x->getMessage()));}}
$title='Setup - '.SITE;include 'includes/header.php';?>
<div class="box"><h2>Database setup</h2>
<?php if($installed):?><p class="flash ok">The database is already installed. For safety, delete <code>setup.php</code> from the server.</p><a class="btn" href="<?=BASE?>">Go to the site</a>
<?php else:?><p>This creates the <b><?=e(DB_NAME)?></b> database, its tables, the treatments and a starter admin account (admin@servicehub.com / password, change it at once).</p><?=isset($err)?'<p class="flash err">'.e($err).'</p>':''?>
<form method="post"><?=csrf()?><button class="btn">Install database</button></form><?php endif;?></div>
<?php include 'includes/footer.php';
