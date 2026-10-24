<?php require '_layout.php';$today=date('Y-m-d');
$rows=$pdo->prepare("SELECT DATE(created_at) d,COUNT(*) n FROM bookings WHERE created_at>=? GROUP BY d");$rows->execute([date('Y-m-d',strtotime('-13 day'))]);$rows=$rows->fetchAll(PDO::FETCH_KEY_PAIR);
for($i=13;$i>=0;$i--){$d=date('Y-m-d',strtotime("-$i day"));$days[$d]=(int)($rows[$d]??0);}$max=max(1,max($days));
$st=$pdo->query("SELECT status,COUNT(*) FROM bookings GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);$tot=max(1,array_sum($st));
$pend=$pdo->query("SELECT b.id,u.name un,s.name sn,b.bdate,b.btime FROM bookings b JOIN users u ON u.id=b.user_id JOIN services s ON s.id=b.service_id WHERE b.status='pending' ORDER BY b.created_at DESC LIMIT 6")->fetchAll();
$next=$pdo->prepare("SELECT u.name un,s.name sn,b.bdate,b.btime,b.status FROM bookings b JOIN users u ON u.id=b.user_id JOIN services s ON s.id=b.service_id WHERE b.bdate>=? AND b.status IN('pending','confirmed') ORDER BY b.bdate,b.btime LIMIT 6");$next->execute([$today]);$next=$next->fetchAll();
a_head('Dashboard','index');$vs=otp_status();?>
<?php if(!$vs['dev']&&!$vs['email']):?><div class="panel" style="background:#fdf6e7;border-color:#ecd9ac"><b>Customer sign-up needs email set up (it sends the verification code).</b> Email is not configured. <a href="verification.php">Open Verification settings</a> to enter your email details and send a test, or switch on local test mode.</div><?php endif;?>
<div class="stats"><div class="stat hot"><span>Pending requests</span><b><?=(int)($st['pending']??0)?></b></div>
<div class="stat"><span>Appointments today</span><b><?=(int)one("SELECT COUNT(*) FROM bookings WHERE bdate=? AND status<>'cancelled'",[$today])?></b></div>
<div class="stat"><span>Customers</span><b><?=(int)one("SELECT COUNT(*) FROM users")?></b></div>
<div class="stat"><span>Revenue (confirmed + completed)</span><b><?=money(one("SELECT COALESCE(SUM(s.price),0) FROM bookings b JOIN services s ON s.id=b.service_id WHERE b.status IN('confirmed','completed')"))?></b></div></div>
<div class="cols"><div class="panel"><h3>New bookings, last 14 days</h3><div class="chart"><?php foreach($days as $d=>$n):?><div title="<?=e($d)?>"><b><?=$n?:''?></b><i style="height:<?=round($n/$max*100)?>%"></i><?=date('j',strtotime($d))?></div><?php endforeach;?></div></div>
<div class="panel"><h3>By status</h3><?php foreach(['pending','confirmed','completed','cancelled'] as $s):$n=(int)($st[$s]??0);?><div class="bar"><?=$s?><span><i style="width:<?=round($n/$tot*100)?>%"></i></span><b><?=$n?></b></div><?php endforeach;?></div></div>
<div class="cols2"><div class="panel"><h3>Needs your attention <a href="bookings.php?status=pending">View all</a></h3>
<?php if(!$pend):?><p class="empty">All caught up. No pending requests.</p><?php else:?><div class="tbl"><table><?php foreach($pend as $b):?><tr><td><?=e(dec($b['un'],'users.name'))?><br><small><?=e($b['sn'])?> &middot; <?=when($b['bdate'],$b['btime'])?></small></td>
<td style="text-align:right"><form method="post" action="bookings.php"><?=csrf()?><input type="hidden" name="action" value="status"><input type="hidden" name="back" value="index.php"><input type="hidden" name="id" value="<?=$b['id']?>"><button class="btn ok" name="status" value="confirmed">Confirm</button></form></td></tr><?php endforeach;?></table></div><?php endif;?></div>
<div class="panel"><h3>Next appointments <a href="bookings.php">All bookings</a></h3>
<?php if(!$next):?><p class="empty">Nothing scheduled yet.</p><?php else:?><div class="tbl"><table><?php foreach($next as $b):?><tr><td><?=e(dec($b['un'],'users.name'))?><br><small><?=e($b['sn'])?></small></td><td><?=when($b['bdate'],$b['btime'])?></td><td><?=pill($b['status'])?></td></tr><?php endforeach;?></table></div><?php endif;?></div></div>
<?php a_foot();
