<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($title??SITE.' - Massage, facials and body care')?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?=BASE?>assets/css/style.css"><script src="<?=BASE?>assets/js/app.js" defer></script></head><body>
<?php if(!empty($_SESSION['admin'])):?><div class="adminbar"><span>Admin mode</span><a href="<?=BASE?>admin/index.php">Dashboard</a><a href="<?=BASE?>admin/bookings.php">Bookings</a><a href="<?=BASE?>admin/services.php">Edit treatments</a></div><?php endif;?>
<header class="top"><div class="wrap bar"><a class="logo" href="<?=BASE?>"><span class="mark"></span><?=e(SITE)?></a>
<button class="menu" aria-label="Menu" aria-expanded="false" data-toggle-nav=".nav"><span></span><span></span></button>
<nav class="nav"><a href="<?=BASE?>#services">Treatments</a><a href="<?=BASE?>#how">Your visit</a><a href="<?=BASE?>#about">About</a>
<?php if(!empty($_SESSION['admin'])):?><a href="<?=BASE?>admin/index.php">Admin panel</a><a class="btn ghost" href="<?=BASE?>admin/logout.php">Log out</a>
<?php elseif(!empty($_SESSION['user'])):?><a href="<?=BASE?>user/dashboard.php">My bookings</a><a class="btn ghost" href="<?=BASE?>logout.php">Log out</a>
<?php else:?><a href="<?=BASE?>login.php">Log in</a><a class="btn" href="<?=BASE?>register.php">Sign up</a><?php endif;?></nav></div></header>
<main><?php if($m=flash()):?><div class="wrap"><p class="flash <?=$m[0]==='!'?'err':'ok'?>"><?=e(ltrim($m,'!'))?></p></div><?php endif;?>
