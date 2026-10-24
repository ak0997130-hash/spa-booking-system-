<?php require '../includes/config.php';
if(empty($_SESSION['user']))go('login.php');
$uid=(int)$_SESSION['user']['id'];
$q=$pdo->prepare("SELECT name,blocked,is_verified FROM users WHERE id=?");$q->execute([$uid]);$me=$q->fetch();
if(!$me||$me['blocked']||!$me['is_verified']){session_destroy();session_start();flash('This account is blocked or no longer exists.');go('login.php');}
if($_POST){$a=$_POST['action']??'';
 if($a==='cancel'){$q=$pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=? AND user_id=? AND status='pending'");$q->execute([(int)$_POST['id'],$uid]);
  flash($q->rowCount()?'Booking cancelled.':'Only pending bookings can be cancelled.');go('user/dashboard.php');}
 if($a==='book'){$sid=(int)($_POST['service']??0);$d=$_POST['bdate']??'';$t=$_POST['btime']??'';$ad=cut(trim($_POST['address']??''),500);
  $q=$pdo->prepare("SELECT id FROM services WHERE id=? AND active=1");$q->execute([$sid]);$dt=DateTime::createFromFormat('Y-m-d',$d);
  if(!$q->fetch())$err='Choose a treatment.';
  elseif(!$dt||$dt->format('Y-m-d')!==$d||$d<date('Y-m-d'))$err='Choose a date from today onwards.';
  elseif(!in_array($t,SLOTS,true))$err='Choose a time slot.';
  elseif($d===date('Y-m-d')&&$t<=date('H:i'))$err='That time has already passed today. Choose a later slot.';
  else{$q=$pdo->prepare("SELECT COUNT(*) FROM bookings WHERE service_id=? AND bdate=? AND btime=? AND status<>'cancelled'");$q->execute([$sid,$d,$t]);
   if($q->fetchColumn()>=SLOT_CAPACITY)$err='That time is fully booked. Please choose another slot.';
   else{$pdo->prepare("INSERT INTO bookings(user_id,service_id,bdate,btime,address) VALUES(?,?,?,?,?)")->execute([$uid,$sid,$d,$t,enc($ad,'bookings.address')]);
    flash('Booking requested. We will confirm it shortly.');go('user/dashboard.php');}}}}
$svs=$pdo->query("SELECT id,name,price,duration FROM services WHERE active=1")->fetchAll();
$sel=(int)($_POST['service']??$_GET['service']??0);
$q=$pdo->prepare("SELECT b.*,s.name,s.price FROM bookings b JOIN services s ON s.id=b.service_id WHERE b.user_id=? ORDER BY b.bdate DESC,b.btime DESC");$q->execute([$uid]);$bk=$q->fetchAll();
$title='My bookings - '.SITE;include '../includes/header.php';?>
<section class="wrap"><h2>Hello, <?=e(explode(' ',dec($me['name'],'users.name'))[0])?></h2>
<div class="dash"><form class="card" method="post"><h3>Book a treatment</h3><?=isset($err)?'<p class="flash err">'.e($err).'</p>':''?><?=csrf()?><input type="hidden" name="action" value="book">
<label for="sv">Treatment</label><select id="sv" name="service" required><?php foreach($svs as $x):?><option value="<?=$x['id']?>"<?=$sel===(int)$x['id']?' selected':''?>><?=e($x['name'])?> - &#8377;<?=number_format($x['price'])?> (<?=(int)$x['duration']?> min)</option><?php endforeach;?></select>
<label for="bd">Date</label><input id="bd" type="date" name="bdate" min="<?=date('Y-m-d')?>" value="<?=e($_POST['bdate']??'')?>" required>
<label for="bt">Time</label><select id="bt" name="btime" required><?php foreach(SLOTS as $t):?><option<?=($_POST['btime']??'')===$t?' selected':''?>><?=$t?></option><?php endforeach;?></select>
<label for="ad">Address or notes (optional)</label><textarea id="ad" name="address" rows="3"><?=e($_POST['address']??'')?></textarea><p></p><button class="btn block">Request booking</button></form>
<div><h3>Your bookings</h3><?php if(!$bk):?><p class="flash ok">No bookings yet. Choose a treatment to book your first visit.</p><?php else:?>
<div class="tbl"><table><tr><th>Treatment</th><th>When</th><th>Status</th><th></th></tr><?php foreach($bk as $b):?>
<tr><td><?=e($b['name'])?><br><small>&#8377;<?=number_format($b['price'])?></small></td><td><?=e(date('d M Y',strtotime($b['bdate'])))?><br><small><?=e($b['btime'])?></small></td><td><span class="badge <?=e($b['status'])?>"><?=e($b['status'])?></span></td>
<td><?php if($b['status']==='pending'):?><form method="post"><?=csrf()?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?=$b['id']?>"><button class="btn ghost sm" data-confirm="Cancel this booking?">Cancel</button></form><?php endif;?></td></tr><?php endforeach;?></table></div><?php endif;?></div></div></section>
<?php include '../includes/footer.php';
