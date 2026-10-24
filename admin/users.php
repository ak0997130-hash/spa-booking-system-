<?php require '_layout.php';
if($_POST&&($_POST['action']??'')==='block'){$pdo->prepare("UPDATE users SET blocked=1-blocked WHERE id=?")->execute([(int)$_POST['id']]);flash('Customer updated.');go('admin/users.php');}
$q=trim($_GET['q']??'');$r=$pdo->query("SELECT u.*,COUNT(b.id) bc FROM users u LEFT JOIN bookings b ON b.user_id=u.id GROUP BY u.id ORDER BY u.id DESC LIMIT 1000")->fetchAll();
foreach($r as &$x){$x['name']=dec($x['name'],'users.name');$x['phone']=dec($x['phone'],'users.phone');}unset($x); // stored encrypted; search runs on the decrypted values
if($q!=='')$r=array_values(array_filter($r,function($x)use($q){foreach([$x['name'],$x['email'],$x['phone']] as $f)if(contains_ci($f,$q))return true;return false;}));
$r=array_slice($r,0,200);
a_head('Customers','users');?>
<div class="filters"><span><?=(int)one("SELECT COUNT(*) FROM users")?> registered customers</span><form class="search" method="get"><input name="q" value="<?=e($q)?>" placeholder="Search name, email or phone"><button class="btn">Search</button></form></div>
<div class="panel"><?php if(!$r):?><p class="empty">No customers found.</p><?php else:?><div class="tbl"><table><tr><th>Name</th><th>Email</th><th>Phone</th><th>Bookings</th><th>Joined</th><th>Status</th><th></th></tr><?php foreach($r as $u):?>
<tr><td><?=e($u['name'])?></td><td><?=e($u['email'])?></td><td><?=e($u['phone'])?></td><td><?=(int)$u['bc']?></td><td><?=e(date('d M Y',strtotime($u['created_at'])))?></td><td><?=$u['blocked']?pill('Blocked','blocked'):pill('Active','live')?></td>
<td style="text-align:right"><form method="post"><?=csrf()?><input type="hidden" name="action" value="block"><input type="hidden" name="id" value="<?=$u['id']?>"><button class="btn <?=$u['blocked']?'ok':'bad'?>"><?=$u['blocked']?'Unblock':'Block'?></button></form></td></tr><?php endforeach;?></table></div><?php endif;?></div>
<?php a_foot();
