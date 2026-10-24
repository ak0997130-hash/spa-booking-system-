<?php require 'includes/config.php';
$s=$pdo->query("SELECT * FROM services WHERE active=1")->fetchAll();
include 'includes/header.php';?>
<section class="hero"><?php if($h=img('hero')):?><img src="<?=e($h)?>" alt="" data-fb><?php endif;?>
<div class="hero-in"><h1><?=e(cfg('hero_title',"Slow down. You've earned it."))?></h1>
<p class="lead"><?=e(cfg('hero_text',"Massage, facials and body care by trained therapists. Pick a treatment, choose your time, and we'll take care of the rest."))?></p>
<form class="finder" action="<?=BASE?>user/dashboard.php"><label for="fs">Choose a treatment</label>
<select id="fs" name="service"><?php foreach($s as $x):?><option value="<?=(int)$x['id']?>"><?=e($x['name'])?> &ndash; &#8377;<?=number_format($x['price'])?></option><?php endforeach;?></select><button class="btn gold">Book a time</button></form>
<ul class="ticks"><li>Price shown before you book</li><li>You choose the time slot</li><li>Cancel pending bookings anytime</li></ul></div></section>

<section id="services" class="wrap"><div class="head"><h2>Our treatments</h2><p>Every price includes the full session. Nothing extra to pay when you arrive.</p></div>
<div class="grid"><?php foreach($s as $x):?><article class="card svc"><?php if($p=img($x['name'])):?><img src="<?=e($p)?>" alt="<?=e($x['name'])?>" loading="lazy" data-fb><?php endif;?>
<?php if(!empty($_SESSION['admin'])):?><a class="edit" href="<?=BASE?>admin/services.php#s<?=(int)$x['id']?>">Edit</a><?php endif;?><div class="in"><h3><?=e($x['name'])?></h3><p><?=e($x['description'])?></p>
<div class="pr"><span><b>&#8377;<?=number_format($x['price'])?></b><small><?=(int)$x['duration']?> min</small></span><a class="btn" href="<?=BASE?>user/dashboard.php?service=<?=(int)$x['id']?>">Book now</a></div></div></article><?php endforeach;?></div></section>

<section id="about" class="wrap" style="padding-top:0"><div class="about"><div class="ph"><?php if($a=img('about')):?><img src="<?=e($a)?>" alt="Spa towels, stones and oils" loading="lazy" data-fb><?php endif;?></div>
<div><h2>A quieter hour in a busy week</h2>
<p><?=e(SITE)?> connects busy people with trained, background-checked therapists. You see the price before you book, choose your own time slot, and can cancel any pending booking from your dashboard.</p>
<ul><li>Trained, background-checked therapists</li><li>Clear prices, no surprise charges</li><li>Manage every booking from one dashboard</li></ul></div></div></section>

<section id="how" class="wrap" style="padding-top:0"><div class="head"><h2>Your visit, step by step</h2></div>
<ol class="steps"><li><h3>Choose a treatment</h3><p>Compare duration and price, then pick what suits you.</p></li><li><h3>Pick your time</h3><p>Select a date and time slot that fits your day.</p></li><li><h3>Arrive and unwind</h3><p>Your therapist takes it from there. Change your mind? Cancel from your dashboard.</p></li></ol></section>

<section class="wrap" style="padding-top:0"><div class="cta"><div><h2>Book your first treatment</h2><p style="margin:.5em 0 0">Create a free account and choose a time in under two minutes.</p></div><a class="btn gold" href="<?=BASE?>register.php">Create free account</a></div></section>
<?php include 'includes/footer.php';
