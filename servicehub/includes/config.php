<?php
/* ---- Database settings come from .env (DB_HOST, DB_NAME, DB_USER, DB_PASS). The defaults below suit XAMPP. ---- */
require_once __DIR__.'/env.php';
define('DB_HOST',env('DB_HOST','localhost'));define('DB_NAME',env('DB_NAME','servicehub'));define('DB_USER',env('DB_USER','root'));define('DB_PASS',env('DB_PASS',''));
require_once __DIR__.'/crypto.php';require_once __DIR__.'/security.php';
const SLOT_CAPACITY=2; // bookings allowed per treatment, date and time
const SLOTS=['10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00'];
/* ---------------------------------------------- */
date_default_timezone_set('Asia/Kolkata');
$_https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||(env('TRUST_PROXY')==='1'&&($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');
if((env('APP_ENV')==='production'||env('FORCE_HTTPS')==='1')&&PHP_SAPI!=='cli'){ // live site (or FORCE_HTTPS=1): everything runs over HTTPS
 if(!$_https&&preg_match('/^[A-Za-z0-9.\-:\[\]]+$/',$_SERVER['HTTP_HOST']??'')){header('Location: https://'.$_SERVER['HTTP_HOST'].($_SERVER['REQUEST_URI']??'/'),true,301);exit;}
 header('Strict-Transport-Security: max-age=31536000');}
if(env('APP_ENV')==='production'){ini_set('display_errors','0');ini_set('log_errors','1');} // never show PHP errors to visitors on a live site
// Security headers on every page: no caching of private pages, strict Content-Security-Policy (no inline scripts), clickjacking and sniffing protection.
if(PHP_SAPI!=='cli'&&!headers_sent()){header_remove('X-Powered-By');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: strict-origin-when-cross-origin');
 header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');header('Cross-Origin-Opener-Policy: same-origin');header('Cache-Control: no-store, max-age=0');header('Pragma: no-cache');
 $_csp=strtolower((string)env('CSP','enforce'));
 if($_csp!=='off')header(($_csp==='report'?'Content-Security-Policy-Report-Only':'Content-Security-Policy').": default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https://images.unsplash.com; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'".($_https?'; upgrade-insecure-requests':''));}
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
if($_https&&session_status()===PHP_SESSION_NONE)session_name('__Host-ServiceHubSID'); // __Host- cookies are only accepted over HTTPS, for this exact host, with no sub-domain tricks
if(session_status()===PHP_SESSION_NONE)session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Lax','secure'=>$_https]);
if(session_status()===PHP_SESSION_NONE&&!@session_start())fail('Sessions are not working','PHP cannot save sessions. Make sure the folder set in <code>session.save_path</code> (php.ini) exists and is writable, then refresh.');
// Sign people out after inactivity (admin 30 min, customers 2 h) and when a session gets too old (12 h).
$_t=time();foreach(['admin'=>(int)env('ADMIN_IDLE_SECONDS',1800),'user'=>(int)env('USER_IDLE_SECONDS',7200)] as $_who=>$_idle)
 if(isset($_SESSION[$_who])&&((isset($_SESSION['seen'])&&$_t-$_SESSION['seen']>$_idle)||(isset($_SESSION['born'])&&$_t-$_SESSION['born']>(int)env('SESSION_MAX_SECONDS',43200)))){unset($_SESSION['admin'],$_SESSION['user']);$_SESSION['flash']='!You were signed out to protect your account (inactive for a while). Please sign in again.';break;}
$_SESSION['seen']=$_t;
// BASE = the URL path of this site. Worked out from the running script, so it is right in any folder, alias or virtual host.
$_app=str_replace('\\','/',(string)realpath(__DIR__.'/..'));$_scr=str_replace('\\','/',(string)realpath($_SERVER['SCRIPT_FILENAME']??''));
$_rel=($_scr!==''&&stripos($_scr,$_app)===0)?substr($_scr,strlen($_app)):'';$_sn=str_replace('\\','/',$_SERVER['SCRIPT_NAME']??'');
if($_rel!==''&&strlen($_sn)>=strlen($_rel)&&strcasecmp(substr($_sn,-strlen($_rel)),$_rel)===0)$_b=substr($_sn,0,-strlen($_rel));
else{$_r=rtrim(str_replace('\\','/',(string)realpath($_SERVER['DOCUMENT_ROOT']??'')),'/');$_b=($_r!==''&&stripos($_app,$_r)===0)?substr($_app,strlen($_r)):'';}
define('BASE',rtrim($_b,'/').'/');
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function go($p){header('Location: '.BASE.$p);exit;}
function flash($m=null){if($m!==null){$_SESSION['flash']=$m;return;}$f=$_SESSION['flash']??'';unset($_SESSION['flash']);return $f;}
function csrf(){$_SESSION['csrf']=$_SESSION['csrf']??bin2hex(random_bytes(16));return '<input type="hidden" name="csrf" value="'.$_SESSION['csrf'].'">';}
function json_out($a,$c=200){http_response_code($c);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($a);exit;}
function fail($t,$m){if(defined('IS_API')){error_log('API failure: '.strip_tags($t));json_out(['ok'=>false,'error'=>'The service is temporarily unavailable. Please try again shortly.'],503);}http_response_code(500);die('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font:17px/1.6 system-ui,sans-serif;max-width:560px;margin:12vh auto;padding:0 20px;color:#241a30"><h2>'.$t.'</h2><p>'.$m.'</p>');}
// True only when you browse from the same computer via localhost (never behind a proxy). Gates the admin repair tools.
// On a live site the well-known default admin password is never accepted.
function default_admin_blocked($h){return env('APP_ENV')==='production'&&password_verify('password',(string)$h);}
function is_local(){if(env('APP_ENV')==='production')return false;$h=strtolower(preg_replace('/:\d+$/','',$_SERVER['HTTP_HOST']??''));
 return in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)&&empty($_SERVER['HTTP_X_FORWARDED_FOR'])&&(in_array($h,['localhost','127.0.0.1','[::1]'],true)||preg_match('/\.(test|local|localhost)$/',$h));}
// Input hardening: form values must be plain, valid UTF-8 text of sane length. Arrays, binary junk and oversized values are
// emptied before any page sees them, so malformed requests can no longer crash a page or hit database length limits.
foreach(['_POST','_GET'] as $_g)foreach($GLOBALS[$_g] as $_k=>$_v)if(!is_string($_v)||strlen($_v)>5000||!preg_match('//u',$_v))$GLOBALS[$_g][$_k]='';
function cut($s,$n){return function_exists('mb_strcut')?mb_strcut((string)$s,0,$n,'UTF-8'):substr((string)$s,0,$n);}
if($_SERVER['REQUEST_METHOD']==='POST'&&!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){
 if(defined('IS_API'))json_out(['ok'=>false,'error'=>'Your session expired. Refresh the page and try again.'],419);
 if(empty($_COOKIE[session_name()]))fail('Your browser is blocking cookies','This site could not save your login cookie. Allow cookies for this address (for example localhost), then reload the page and try again.');
 flash('!That page had expired. Please try again.');header('Location: '.($_SERVER['REQUEST_URI']??BASE));exit;}
if(!defined('IN_SETUP')){
 // Encrypted database connection: set DB_SSL=1 (and DB_SSL_CA to verify the server). With DB_SSL=1 the site refuses to run on an unencrypted connection.
 $_dbo=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
 if(env('DB_SSL')==='1'){$_ca=(string)env('DB_SSL_CA','');
  if($_ca!==''){$_dbo[PDO::MYSQL_ATTR_SSL_CA]=$_ca;$_dbo[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=env('DB_SSL_VERIFY','1')!=='0';}
  else{$_dbo[PDO::MYSQL_ATTR_SSL_CIPHER]='DEFAULT';$_dbo[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=false;}
  if(env('DB_SSL_CERT')&&env('DB_SSL_KEY')){$_dbo[PDO::MYSQL_ATTR_SSL_CERT]=env('DB_SSL_CERT');$_dbo[PDO::MYSQL_ATTR_SSL_KEY]=env('DB_SSL_KEY');}}
 try{$pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,$_dbo);
  $pdo->query('SELECT 1 FROM bookings LIMIT 1');$pdo->query('SELECT is_verified,email_verified_at FROM users LIMIT 1');$pdo->query('SELECT 1 FROM otp_challenges LIMIT 1');$pdo->query('SELECT 1 FROM rate_limits LIMIT 1');
  if(preg_match('/varchar\((\d+)\)/i',(string)($pdo->query("SHOW COLUMNS FROM users LIKE 'name'")->fetch()['Type']??''),$_m)&&(int)$_m[1]<600)throw new PDOException('encrypted_fields migration missing');
  if(env('DB_SSL')==='1'&&(string)($pdo->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_NUM)[1]??'')==='')throw new PDOException('Database connection is not encrypted (DB_SSL=1)');}
 catch(PDOException $x){$c=(int)$x->getCode();$s=$x->getMessage();
  if(env('APP_ENV')==='production'){error_log('Database error: '.$s);fail('The site is temporarily unavailable','Please try again in a few minutes. If this continues, contact the site owner.');}
  if(preg_match('/otp_challenges|rate_limits|is_verified|_verified_at|encrypted_fields/',$s))fail('Database update needed','Run the OTP update once: open <a href="'.BASE.'migrate.php">migrate.php</a> on this computer (or import the files in the <code>migrations</code> folder in phpMyAdmin).');
  if(stripos($s,'not encrypted')!==false||(env('DB_SSL')==='1'&&stripos($s,'using SSL')!==false))fail('Database connection is not encrypted','DB_SSL=1 is set, but the database did not use TLS. Use a TCP host (127.0.0.1 or the remote host name, not "localhost"), enable TLS on the database server, or remove DB_SSL.');
  if($c===1049||stripos($s,'doesn\'t exist')!==false)fail('Database not set up yet','Create it in one click: <a href="'.BASE.'setup.php">run setup</a>.');
  if($c===1045)fail('MySQL rejected the login','Check DB_USER and DB_PASS in your <code>.env</code> file (XAMPP default: user root, empty password).');
  fail('Cannot reach MySQL','Start MySQL in XAMPP/WAMP, then refresh this page.<br><small>'.e($s).'</small>');}}
$_cfg=[];
if(!defined('IN_SETUP')){try{$_cfg=$pdo->query("SELECT k,v FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);}catch(PDOException $x){$pdo->exec("CREATE TABLE IF NOT EXISTS settings(k VARCHAR(50) PRIMARY KEY,v TEXT)");}}
function cfg($k,$d=''){global $_cfg;return(isset($_cfg[$k])&&$_cfg[$k]!=='')?$_cfg[$k]:$d;}
define('SITE',cfg('name','Stillwater Spa')); // set it in Admin > Settings > Business details
function slug($n){return trim(preg_replace('/[^a-z0-9]+/','-',strtolower($n)),'-');}
// Photo for a treatment: an uploaded/local file assets/img/<slug>.(jpg|png|webp) wins, otherwise a hosted photo, otherwise '' (gradient tile).
function img($name){$s=slug($name);
 foreach(['jpg','jpeg','png','webp'] as $x){$f=__DIR__."/../assets/img/$s.$x";if(is_file($f))return BASE."assets/img/$s.$x?v=".filemtime($f);}
 $u=['swedish-massage'=>'photo-1544161515-4ab6ce6db874','deep-tissue-massage'=>'photo-1540555700478-4be289fbecef','aromatherapy-massage'=>'photo-1507652313519-d4e9174996dd','hot-stone-therapy'=>'photo-1519823551278-64ac92734fb1','signature-facial'=>'photo-1570172619644-dfd03ed5d881','hero'=>'photo-1540555700478-4be289fbecef','about'=>'photo-1507652313519-d4e9174996dd'];
 return isset($u[$s])?"https://images.unsplash.com/{$u[$s]}?auto=format&fit=crop&w=1000&q=70":'';}
