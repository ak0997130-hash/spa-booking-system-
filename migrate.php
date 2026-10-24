<?php define('IN_SETUP',1);require 'includes/config.php';
if(!is_local())fail('Not available','The migration page only works from the computer the site runs on (localhost). On a live server, import the files in the migrations folder (in order) in phpMyAdmin instead.');
$log=[];$bad=null;
if($_POST){try{$p=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $sql='';$fs=glob(__DIR__.'/migrations/*.sql');sort($fs);foreach($fs as $fl)$sql.=preg_replace('/^--.*$/m','',(string)file_get_contents($fl)).";\n";
 foreach(explode(';',$sql) as $q){$q=trim($q);if($q==='')continue;$n=preg_replace('/\s+/',' ',substr($q,0,60));
  try{$p->exec($q);$log[]=['ok','Applied: '.$n];}
  catch(PDOException $x){$c=(int)($x->errorInfo[1]??0);
   if(in_array($c,[1060,1061,1050,1091],true))$log[]=['skip','Already in place: '.$n];
   elseif($c===1062){$bad='Two customers have the same phone number, so the unique phone index cannot be added. Find them with: SELECT phone,COUNT(*) FROM users GROUP BY phone HAVING COUNT(*)>1; fix or clear the duplicates, then run this page again.';break;}
   else{$bad='Migration stopped at: '.$n.' ('.$x->getMessage().')';break;}}}
 if(!$bad)flash('Database updated for OTP verification.');}
 catch(PDOException $x){$bad=$x->getCode()==1049?'The database does not exist yet. Run setup.php first.':'Could not connect: '.$x->getMessage();}}
$title='Database update - '.SITE;include 'includes/header.php';?>
<div class="box" style="max-width:640px"><h2>Database update (OTP verification)</h2>
<p>Adds the email-verification columns to <b>users</b> and creates the verification tables. Existing customers, bookings and services are kept. Safe to run more than once.</p>
<?php if($_POST&&!$bad):?><p class="flash ok">Done. The database is up to date.</p><?php endif;?><?=$bad?'<p class="flash err">'.e($bad).'</p>':''?>
<?php foreach($log as [$k,$m]):?><p style="margin:2px 0;font-size:.9rem"><?=$k==='ok'?'&#10003;':'&bull;'?> <?=e($m)?></p><?php endforeach;?>
<form method="post"><?=csrf()?><p></p><button class="btn">Run update</button></form></div>
<?php include 'includes/footer.php';
