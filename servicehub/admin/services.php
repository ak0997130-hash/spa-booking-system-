<?php require '_layout.php';
const EXT=['jpg','jpeg','png','webp'];$dir=__DIR__.'/../assets/img/';
function photo($slug,$f,$dir){if(($f['error']??4)===4)return '';if($f['error']!==0||$f['size']>4194304)return 'Photo must be under 4 MB.';
 $i=@getimagesize($f['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$i['mime']??'']??null;if(!$ext)return 'Use a JPG, PNG or WebP image.';
 if(!is_dir($dir))@mkdir($dir,0755,true);if(!is_writable($dir))return 'The folder assets/img is not writable.';
 foreach(EXT as $x)@unlink("$dir$slug.$x");return move_uploaded_file($f['tmp_name'],"$dir$slug.$ext")?'':'Could not save the photo.';}
if($_POST&&($_POST['action']??'')==='save'){$id=(int)$_POST['id'];$n=cut(trim($_POST['name']??''),120);$d=cut(trim($_POST['description']??''),1000);$p=(float)$_POST['price'];$m=(int)$_POST['duration'];$ac=isset($_POST['active'])?1:0;
 if(!$n||!slug($n)||$p<=0||$p>9999999||$m<=0||$m>1440){flash('!Enter a name, a price between 1 and 9,999,999 and a duration of 1 to 1440 minutes.');go('admin/services.php');}
 if($id){$old=one("SELECT name FROM services WHERE id=?",[$id]);if(slug($old)!==slug($n))foreach(EXT as $x)if(is_file("$dir".slug($old).".$x")&&!is_file("$dir".slug($n).".$x"))@rename("$dir".slug($old).".$x","$dir".slug($n).".$x");
  $pdo->prepare("UPDATE services SET name=?,description=?,price=?,duration=?,active=? WHERE id=?")->execute([$n,$d,$p,$m,$ac,$id]);}
 else{$pdo->prepare("INSERT INTO services(name,description,price,duration,active) VALUES(?,?,?,?,?)")->execute([$n,$d,$p,$m,$ac]);$id=(int)$pdo->lastInsertId();}
 $e=photo(slug($n),$_FILES['photo']??[],$dir);flash($e?'!Saved, but the photo was not updated: '.$e:'Treatment saved. It is live on the website.');go('admin/services.php#s'.$id);}
$all=$pdo->query("SELECT s.*,(SELECT COUNT(*) FROM bookings b WHERE b.service_id=s.id AND b.status IN('pending','confirmed')) open_n FROM services s ORDER BY s.id")->fetchAll();
$all[]=['id'=>0,'name'=>'','description'=>'','price'=>'','duration'=>60,'active'=>1,'open_n'=>0];
a_head('Treatments','services');?>
<p style="margin:0;color:var(--mute)">Changes here appear on the website straight away. Photos you upload replace the default image.</p>
<div class="tiles"><?php foreach($all as $x):$new=!$x['id'];$ph=$new?'':img($x['name']);?><div class="tile" id="s<?=$x['id']?>">
<div class="ph"><?php if($ph):?><img src="<?=e($ph)?>" alt="" data-fb><?php endif;?><?=$new?'':pill($x['active']?'Live':'Hidden',$x['active']?'live':'off')?></div>
<div class="in"><?php if(!$new):?><h3><?=e($x['name'])?></h3><p><?=money($x['price'])?> &middot; <?=(int)$x['duration']?> min &middot; <?=(int)$x['open_n']?> open booking<?=$x['open_n']==1?'':'s'?></p><?php else:?><h3>Add a treatment</h3><p>Create a new treatment for the website.</p><?php endif;?>
<details<?=$new?'':''?>><summary><?=$new?'Add new':'Edit'?></summary><form method="post" enctype="multipart/form-data"><?=csrf()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$x['id']?>">
<label>Name</label><input name="name" value="<?=e($x['name'])?>" required><label>Description</label><textarea name="description" rows="2"><?=e($x['description'])?></textarea>
<div class="row2"><div><label>Price (&#8377;)</label><input name="price" type="number" step="0.01" min="1" value="<?=e($x['price'])?>" required></div><div><label>Minutes</label><input name="duration" type="number" min="5" value="<?=e($x['duration'])?>" required></div></div>
<label>Photo (JPG, PNG or WebP, max 4 MB)</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp"><label><input type="checkbox" name="active"<?=$x['active']?' checked':''?>> Show on website</label><p></p><button class="btn"><?=$new?'Add treatment':'Save changes'?></button></form></details></div></div><?php endforeach;?></div>
<?php a_foot();
