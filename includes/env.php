<?php
// Settings come from (1) real environment variables, (2) the .env file, (3) includes/local.settings.php (written by Admin > Verification).
function local_file(){return __DIR__.'/local.settings.php';}
function local_all(){$p=local_file();if(!is_file($p))return [];$r=(string)@file_get_contents($p);$n=strpos($r,"\n");$j=$n===false?null:json_decode(substr($r,$n+1),true);return is_array($j)?$j:[];}
// The saved file begins with a PHP "exit" line, so even a misconfigured web server shows nothing if someone requests it directly.
function local_set($kv,$ifMissing=false){$h=@fopen(local_file(),'c+');if(!$h)return false;flock($h,LOCK_EX);$r=(string)stream_get_contents($h);$cur=[];
 if(($n=strpos($r,"\n"))!==false){$j=json_decode(substr($r,$n+1),true);if(is_array($j))$cur=$j;}
 foreach($kv as $k=>$v){if($ifMissing&&isset($cur[$k])&&$cur[$k]!=='')continue;if($v===null)unset($cur[$k]);else $cur[$k]=(string)$v;}
 ftruncate($h,0);rewind($h);$ok=fwrite($h,"<?php exit; ?>\n".json_encode($cur,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES))!==false;fflush($h);flock($h,LOCK_UN);fclose($h);@chmod(local_file(),0600);env_reset();return $ok;}
function env_reset(){unset($GLOBALS['_envc']);}
function env_load(){$f=[];$p=dirname(__DIR__).'/.env';
 if(is_file($p))foreach(file($p,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $l){$l=trim($l);if($l===''||$l[0]==='#'||strpos($l,'=')===false)continue;
  [$a,$b]=explode('=',$l,2);$b=trim($b);if(strlen($b)>1&&($b[0]==='"'||$b[0]==="'")&&substr($b,-1)===$b[0])$b=substr($b,1,-1);$f[trim($a)]=$b;}
 return ['file'=>$f,'local'=>local_all()];}
function env($k,$d=null){if(!isset($GLOBALS['_envc']))$GLOBALS['_envc']=env_load();$c=$GLOBALS['_envc'];$v=getenv($k);
 if($v!==false&&$v!=='')return $v;foreach(['file','local'] as $s)if(isset($c[$s][$k])&&$c[$s][$k]!=='')return $c[$s][$k];return $d;}
