</main><footer class="foot"><div class="wrap foot-in"><div><a class="logo" href="<?=BASE?>"><span class="mark"></span><?=e(SITE)?></a>
<p>Unhurried treatments by trained, background-checked therapists.</p></div>
<div><b>Explore</b><a href="<?=BASE?>#services">Treatments</a><a href="<?=BASE?>#how">Your visit</a><a href="<?=BASE?>register.php">Create account</a></div>
<div><b>Account</b><a href="<?=BASE?>login.php">Log in</a><a href="<?=BASE?>user/dashboard.php">My bookings</a><a href="<?=BASE?>admin/login.php">Admin login</a></div>
<?php if(cfg('phone').cfg('email').cfg('address').cfg('hours')):?><div><b>Visit us</b><?php foreach(['address','hours','phone','email'] as $k)if(cfg($k)):?><span><?=nl2br(e(cfg($k)))?></span><?php endif;?></div><?php endif;?></div>
<div class="wrap copy">&copy; <?=date('Y')?> <?=e(SITE)?>. All rights reserved.</div></footer>
<?php if(!empty($js)):?><script src="<?=BASE?>assets/js/register.js"></script><?php endif;?></body></html>
