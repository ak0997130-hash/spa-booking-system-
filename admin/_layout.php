<?php require __DIR__.'/../includes/config.php';
if(empty($_SESSION['admin']))go('admin/login.php');
require_once __DIR__.'/../includes/otp.php';
const NAV=['index'=>['Dashboard','M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z'],'bookings'=>['Bookings','M7 3v4M17 3v4M4 9h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z'],'services'=>['Treatments','M12 3c4 3 6 6 6 9a6 6 0 01-12 0c0-3 2-6 6-9zM12 21v-8'],'users'=>['Customers','M16 20v-1a4 4 0 00-4-4H8a4 4 0 00-4 4v1M10 11a3 3 0 100-6 3 3 0 000 6zM20 20v-1a3 3 0 00-2-2.8M16 5.2a3 3 0 010 5.6'],'verification'=>['Verification','M12 3l7 3v5c0 5-3.5 8-7 9-3.5-1-7-4-7-9V6l7-3zM9 12l2 2 4-4'],'security'=>['Security','M7 11V8a5 5 0 0110 0v3M5 11h14v9H5z'],'settings'=>['Settings','M4 7h10M18 7h2M4 17h2M10 17h10M14 5v4M6 15v4']];
function one($sql,$p=[]){global $pdo;$q=$pdo->prepare($sql);$q->execute($p);return $q->fetchColumn();}
function money($n){return '&#8377;'.number_format((float)$n);}
function pill($s,$c=null){return '<span class="pill '.e($c??$s).'">'.e($s).'</span>';}
function when($d,$t){return e(date('d M Y',strtotime($d))).' &middot; '.e($t);}
function back($dflt){$b=$_POST['back']??'';go('admin/'.(in_array($b,['index.php','bookings.php'],true)?$b:$dflt));}
function a_head($title,$on){$n=(int)one("SELECT COUNT(*) FROM bookings WHERE status='pending'");$m=flash();?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title.' - '.SITE)?></title>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?=BASE?>assets/css/admin.css"><script src="<?=BASE?>assets/js/app.js" defer></script></head><body>
<div class="shell"><aside class="side" id="side"><a class="brand" href="<?=BASE?>admin/index.php"><i></i><span><?=e(SITE)?><small>Admin</small></span></a>
<?php foreach(NAV as $k=>[$l,$d]):?><a class="nav<?=$on===$k?' on':''?>" href="<?=BASE?>admin/<?=$k?>.php"><svg viewBox="0 0 24 24"><path d="<?=$d?>"/></svg><?=$l?><?=$k==='bookings'&&$n?'<b class="n">'.$n.'</b>':''?></a><?php endforeach;?>
<div class="sp"></div><a class="nav" href="<?=BASE?>" target="_blank"><svg viewBox="0 0 24 24"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"/></svg>View website</a>
<a class="nav" href="<?=BASE?>admin/logout.php"><svg viewBox="0 0 24 24"><path d="M15 4h3a1 1 0 011 1v14a1 1 0 01-1 1h-3M10 16l4-4-4-4M14 12H4"/></svg>Log out</a></aside>
<div class="main"><div class="top"><div><h1><?=e($title)?></h1></div><div><button class="menu" data-toggle-nav="#side">Menu</button> <span><?=e($_SESSION['admin']['email'])?></span></div></div>
<?php if($m):?><p class="toast<?=$m[0]==='!'?' err':''?>"><?=e(ltrim($m,'!'))?></p><?php endif;?><div class="content">
<?php }
function a_foot(){?></div></div></div></body></html><?php }
