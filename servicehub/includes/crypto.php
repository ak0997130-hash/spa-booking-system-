<?php
// Field-level encryption for personal data stored in the database: AES-256-GCM (authenticated), a fresh random IV per value,
// and the column name bound in as "additional authenticated data" so a ciphertext cannot be moved into a different column.
const ENC_PREFIX='enc1:';
// The key comes from DATA_KEY (server environment or .env). If none exists one is generated and saved in includes/local.settings.php.
// BACK THE KEY UP: without it the encrypted data can never be read again.
function data_key(){static $k=false;if($k!==false)return $k;$h=(string)env('DATA_KEY','');
 if($h===''&&function_exists('local_set')){local_set(['DATA_KEY'=>bin2hex(random_bytes(32))],true);env_reset();$h=(string)env('DATA_KEY','');}
 if($h==='')return $k=null;$raw=preg_match('/^[0-9a-f]{64}$/i',$h)?hex2bin($h):hash('sha256',$h,true);
 return $k=hash_hmac('sha256','servicehub-field-encryption-v1',$raw,true);}
function data_key_id(){$k=data_key();return $k?substr(hash('sha256','id|'.$k),0,8):'';}
function is_enc($v){return is_string($v)&&strncmp($v,ENC_PREFIX,5)===0;}
function enc($v,$ctx){if($v===null||$v==='')return $v;$k=data_key();
 if(!$k||!function_exists('openssl_encrypt')||!in_array('aes-256-gcm',openssl_get_cipher_methods(),true))throw new RuntimeException('Data encryption is not available (PHP openssl / DATA_KEY)');
 $iv=random_bytes(12);$ct=openssl_encrypt((string)$v,'aes-256-gcm',$k,OPENSSL_RAW_DATA,$iv,$tag,$ctx,16);if($ct===false)throw new RuntimeException('Encryption failed');
 return ENC_PREFIX.rtrim(strtr(base64_encode($iv.$tag.$ct),'+/','-_'),'=');}
// Returns the plain text. Old rows that were saved before encryption existed are returned as they are; a damaged value or a wrong key gives ''.
function dec($v,$ctx){if($v===null||$v==='')return (string)$v;$v=(string)$v;if(!is_enc($v))return $v;$k=data_key();
 $raw=base64_decode(strtr(substr($v,5),'-_','+/'),true);if(!$k||$raw===false||strlen($raw)<28)return '';
 $p=openssl_decrypt(substr($raw,28),'aes-256-gcm',$k,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),$ctx);return $p===false?'':$p;}
// Encrypts rows that were saved in plain text before encryption was switched on. Safe to run again.
function encrypt_existing($max=5000){global $pdo;$n=['users'=>0,'bookings'=>0];
 $up=$pdo->prepare("UPDATE users SET name=?,phone=? WHERE id=?");
 foreach($pdo->query("SELECT id,name,phone FROM users WHERE (name<>'' AND name NOT LIKE 'enc1:%') OR (phone<>'' AND phone NOT LIKE 'enc1:%') LIMIT ".(int)$max)->fetchAll() as $r){
  $up->execute([is_enc($r['name'])?$r['name']:enc($r['name'],'users.name'),is_enc($r['phone'])?$r['phone']:enc($r['phone'],'users.phone'),$r['id']]);$n['users']++;}
 $up=$pdo->prepare("UPDATE bookings SET address=? WHERE id=?");
 foreach($pdo->query("SELECT id,address FROM bookings WHERE address<>'' AND address NOT LIKE 'enc1:%' LIMIT ".(int)$max)->fetchAll() as $r){$up->execute([enc($r['address'],'bookings.address'),$r['id']]);$n['bookings']++;}
 return $n;}
function enc_counts(){global $pdo;
 $pu=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE (name<>'' AND name NOT LIKE 'enc1:%') OR (phone<>'' AND phone NOT LIKE 'enc1:%')")->fetchColumn();
 $pb=(int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE address<>'' AND address NOT LIKE 'enc1:%'")->fetchColumn();$bad=0;$tot=0;
 foreach($pdo->query("SELECT name,phone FROM users WHERE name LIKE 'enc1:%' OR phone LIKE 'enc1:%' ORDER BY id DESC LIMIT 100")->fetchAll() as $r){foreach(['name'=>'users.name','phone'=>'users.phone'] as $c=>$x)if(is_enc($r[$c])){$tot++;if(dec($r[$c],$x)==='')$bad++;}}
 foreach($pdo->query("SELECT address FROM bookings WHERE address LIKE 'enc1:%' ORDER BY id DESC LIMIT 100")->fetchAll() as $r){$tot++;if(dec($r['address'],'bookings.address')==='')$bad++;}
 return ['plain_users'=>$pu,'plain_bookings'=>$pb,'checked'=>$tot,'undecryptable'=>$bad];}
