<?php
// Login throttling (brute-force protection) and small security helpers.
const DUMMY_HASH='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // checked when an email is unknown, so timing does not reveal which emails exist
function thr_key($k,$id){return substr(hash('sha256','thr|'.$k.'|'.$id),0,40);}
function thr_count($k,$id,$win){global $pdo;$s=$pdo->prepare("SELECT hits FROM rate_limits WHERE k=? AND window_start>=NOW()-INTERVAL ".(int)$win." SECOND");$s->execute([thr_key($k,$id)]);return (int)$s->fetchColumn();}
function thr_hit($k,$id,$win){global $pdo;$w=(int)$win;$pdo->prepare("INSERT INTO rate_limits(k,hits,window_start) VALUES(?,1,NOW()) ON DUPLICATE KEY UPDATE hits=IF(window_start<NOW()-INTERVAL $w SECOND,1,hits+1),window_start=IF(window_start<NOW()-INTERVAL $w SECOND,NOW(),window_start)")->execute([thr_key($k,$id)]);}
function thr_clear($k,$id){global $pdo;$pdo->prepare("DELETE FROM rate_limits WHERE k=?")->execute([thr_key($k,$id)]);}
function login_ident($email){return ($_SERVER['REMOTE_ADDR']??'').'|'.strtolower(trim((string)$email));}
// 5 wrong passwords for one email from one address, or 30 from one address, in 15 minutes -> locked until the window passes.
function login_blocked($email){return thr_count('lf-ie',login_ident($email),900)>=5||thr_count('lf-ip',$_SERVER['REMOTE_ADDR']??'',900)>=30;}
function login_failed($email){thr_hit('lf-ie',login_ident($email),900);thr_hit('lf-ip',$_SERVER['REMOTE_ADDR']??'',900);}
function login_ok($email){thr_clear('lf-ie',login_ident($email));}
function contains_ci($h,$n){return function_exists('mb_stripos')?mb_stripos((string)$h,(string)$n)!==false:stripos((string)$h,(string)$n)!==false;}
function db_tls(){global $pdo;$r=[];try{foreach($pdo->query("SHOW SESSION STATUS WHERE Variable_name IN('Ssl_cipher','Ssl_version')")->fetchAll(PDO::FETCH_NUM) as $x)$r[$x[0]]=$x[1];}catch(Throwable $e){}
 return ['on'=>($r['Ssl_cipher']??'')!=='','cipher'=>$r['Ssl_cipher']??'','version'=>$r['Ssl_version']??''];}
function env_source($k){env($k);$c=$GLOBALS['_envc']??[];if(getenv($k)!==false&&getenv($k)!=='')return 'server environment';if(!empty($c['file'][$k]))return '.env file';if(!empty($c['local'][$k]))return 'includes/local.settings.php';return 'not set';}
