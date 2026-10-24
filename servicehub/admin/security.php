<?php require '_layout.php';
if(($_POST['action']??'')==='encrypt'){
 try{$n=encrypt_existing();flash('Encrypted '.$n['users'].' customer record(s) and '.$n['bookings'].' booking note(s).');}
 catch(Throwable $x){error_log('Encrypt existing data failed ('.get_class($x).')');flash('!Encryption could not run. Check that the PHP openssl extension is enabled.');}
 go('admin/security.php');}
$tls=db_tls();$k=data_key();$c=enc_counts();$root=dirname(__DIR__);
$hash=(string)one("SELECT password FROM admins WHERE id=?",[$_SESSION['admin']['id']]);
$dev=array_values(array_filter(['setup.php','migrate.php','admin/reset.php','tests','database.sql'],fn($f)=>file_exists($root.'/'.$f)));
$forced=env('APP_ENV')==='production'||env('FORCE_HTTPS')==='1';$cp=session_get_cookie_params();$plain=$c['plain_users']+$c['plain_bookings'];
// [label, state: true=good false=needs action null=information, details]
$rows=[
 ['This page is served over HTTPS',(bool)$_https,$_https?'Traffic between the browser and the server is encrypted.':'You are on plain HTTP (fine on your own computer, not on a live site).'],
 ['HTTPS is enforced (redirect + HSTS)',$forced,$forced?'Visitors are always sent to HTTPS.':'Set APP_ENV=production (live site) or FORCE_HTTPS=1.'],
 ['Session cookie is locked down',$_https&&session_name()==='__Host-ServiceHubSID'&&$cp['httponly']&&$cp['secure'],'Cookie "'.session_name().'": HttpOnly '.($cp['httponly']?'yes':'no').', Secure '.($cp['secure']?'yes':'no').', SameSite '.$cp['samesite'].($_https?'':' (the strongest "__Host-" cookie is used automatically on HTTPS)')],
 ['Database connection is encrypted (TLS)',$tls['on']?true:null,$tls['on']?'Encrypted with '.$tls['version'].' / '.$tls['cipher'].'.':'Not encrypted. That is normal when the database runs on this same server. If it is on another server set DB_SSL=1 (see SECURITY.md).'],
 ['Encryption key for personal data is set',$k!==null,$k!==null?'Source: '.env_source('DATA_KEY').'. Key fingerprint: '.data_key_id().'. Back the key up: without it the encrypted data cannot be recovered.':'No key available. Set DATA_KEY.'],
 ['Names, phone numbers and booking notes are encrypted (AES-256-GCM)',$plain===0,$plain===0?'Everything stored is encrypted.':$c['plain_users'].' customer record(s) and '.$c['plain_bookings'].' booking note(s) are still plain text. Press the button below.'],
 ['Encrypted data can be read with the current key',$c['undecryptable']===0,$c['undecryptable']===0?'Checked '.$c['checked'].' recent values.':$c['undecryptable'].' of '.$c['checked'].' recent values cannot be decrypted. The DATA_KEY was probably changed or lost.'],
 ['Admin password is not the default',!password_verify('password',$hash),'The starter password "password" must never be used on a live site.'],
 ['Development-only files are not on this server',!$dev,$dev?'Remove: '.implode(', ',$dev).'.':'None found.'],
 ['PHP errors are hidden from visitors',in_array(strtolower((string)ini_get('display_errors')),['','0','off','false','no','stderr'],true),'Visitors never see file paths or database details on a live site (APP_ENV=production).'],
 ['Content-Security-Policy blocks injected scripts',strtolower((string)env('CSP','enforce'))==='enforce','Mode: '.strtolower((string)env('CSP','enforce')).'. Scripts may only come from this site.'],
 ['Login lockout and automatic sign-out',null,'5 wrong passwords for one email (or 30 from one address) lock sign-in for 15 minutes. Admin is signed out after '.round((int)env('ADMIN_IDLE_SECONDS',1800)/60).' min, customers after '.round((int)env('USER_IDLE_SECONDS',7200)/60).' min of inactivity.'],
];
a_head('Security','security');?>
<p style="margin:0;color:var(--mute)">What protects your website and your customers' data right now. Anything marked "Action needed" should be fixed before you go live.</p>
<div class="panel"><div class="tbl"><table><tr><th>Protection</th><th>Status</th><th>Details</th></tr>
<?php foreach($rows as [$l,$s,$d]):?><tr><td><?=e($l)?></td><td><?=$s===true?pill('Good','live'):($s===false?pill('Action needed','blocked'):pill('Info','off'))?></td><td><small><?=e($d)?></small></td></tr><?php endforeach;?></table></div></div>
<div class="cols2"><form method="post" class="panel"><?=csrf()?><input type="hidden" name="action" value="encrypt"><h3>Encrypt existing data</h3>
<p>New customers and bookings are encrypted automatically. Use this once to encrypt records saved before encryption existed (safe to run again).</p>
<button class="btn"<?=$k===null?' disabled':''?>><?=$plain?'Encrypt '.(int)$plain.' record(s) now':'Check and encrypt again'?></button></form>
<div class="panel"><h3>Keep your key safe</h3><p>Customer names, phone numbers and booking notes are encrypted with your <b>DATA_KEY</b>. Anyone who gets only the database sees unreadable text; anyone who loses the key loses that data.</p>
<ul style="margin:0;padding-left:18px;color:var(--text)"><li>Store a copy of the key somewhere safe and separate from the database backups.</li><li>On a live site keep it in the <code>.env</code> file (or server environment), not in the database.</li><li>Customer emails stay readable on purpose: they are the sign-in name and where codes are sent.</li></ul></div></div>
<?php a_foot();
