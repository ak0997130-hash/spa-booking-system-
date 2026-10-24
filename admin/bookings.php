<?php require '_layout.php';
$next=['pending'=>['confirmed'=>'Confirm','cancelled'=>'Cancel'],'confirmed'=>['completed'=>'Complete','cancelled'=>'Cancel'],'completed'=>[],'cancelled'=>['pending'=>'Reopen']];
if($_POST&&($_POST['action']??'')==='status'){$cur=one("SELECT status FROM bookings WHERE id=?",[(int)$_POST['id']]);$new=$_POST['status']??'';
 if($cur&&isset($next[$cur][$new])){$pdo->prepare("UPDATE bookings SET status=? WHERE id=?")->execute([$new,(int)$_POST['id']]);flash('Booking marked '.$new.'.');}else flash('!That change is not allowed.');
 back('bookings.php');}
$st=in_array($_GET['status']??'',array_keys($next),true)?$_GET['status']:'';$q=trim($_GET['q']??'');$w=[];$p=[];
if($st){$w[]='b.status=?';$p[]=$st;}
$sql="SELECT b.*,u.name un,u.email,u.phone,s.name sn,s.price FROM bookings b JOIN users u ON u.id=b.user_id JOIN services s ON s.id=b.service_id".($w?' WHERE '.implode(' AND ',$w):'')." ORDER BY b.bdate DESC,b.btime DESC LIMIT 1000";
$r=$pdo->prepare($sql);$r->execute($p);$r=$r->fetchAll();
foreach($r as &$x){$x['un']=dec($x['un'],'users.name');$x['phone']=dec($x['phone'],'users.phone');$x['address']=dec($x['address'],'bookings.address');}unset($x); // personal data is stored encrypted; search runs on the decrypted values
if($q!=='')$r=array_values(array_filter($r,function($x)use($q){foreach([$x['un'],$x['email'],$x['sn'],$x['phone']] as $f)if(contains_ci($f,$q))return true;return false;}));
$r=array_slice($r,0,200);$cnt=$pdo->query("SELECT status,COUNT(*) FROM bookings GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
a_head('Bookings','bookings');?>
<div class="filters"><div class="tabs"><a href="bookings.php"<?=$st===''?' class="on"':''?>>All<sup><?=array_sum($cnt)?></sup></a><?php foreach(array_keys($next) as $s):?><a href="?status=<?=$s?>"<?=$st===$s?' class="on"':''?>><?=ucfirst($s)?><sup><?=(int)($cnt[$s]??0)?></sup></a><?php endforeach;?></div>
<form class="search" method="get"><?=$st?'<input type="hidden" name="status" value="'.e($st).'">':''?><input name="q" value="<?=e($q)?>" placeholder="Search customer or treatment"><button class="btn">Search</button></form></div>
<div class="panel"><?php if(!$r):?><p class="empty">No bookings found.</p><?php else:?><div class="tbl"><table><tr><th>Customer</th><th>Treatment</th><th>When</th><th>Notes</th><th>Status</th><th></th></tr><?php foreach($r as $b):?>
<tr><td><?=e($b['un'])?><br><small><?=e($b['phone']?:$b['email'])?></small></td><td><?=e($b['sn'])?><br><small><?=money($b['price'])?></small></td><td><?=when($b['bdate'],$b['btime'])?></td><td><small><?=e($b['address'])?></small></td><td><?=pill($b['status'])?></td>
<td style="text-align:right;white-space:nowrap"><?php foreach($next[$b['status']] as $k=>$l):?><form method="post"><?=csrf()?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=$b['id']?>"><button class="btn <?=$k==='cancelled'?'bad':($k==='confirmed'||$k==='completed'?'ok':'ghost')?>" name="status" value="<?=$k?>"><?=$l?></button></form> <?php endforeach;?></td></tr><?php endforeach;?></table></div><?php endif;?></div>
<?php a_foot();
