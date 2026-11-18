<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
function reply(array $out,int $status=200):never {http_response_code($status);echo json_encode($out,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
function strval_field(array $a,string $k,int $max=255):string {return mb_substr(trim((string)($a[$k]??'')),0,$max);}
function required(array $a,array $keys):void {foreach($keys as $k)if(trim((string)($a[$k]??''))==='')throw new InvalidArgumentException("$k is required.");}
function id(array $a,string $key='id'):int {$v=(int)($a[$key]??0);if($v<=0)throw new InvalidArgumentException('Invalid record identifier.');return $v;}
function allowed_status(array $a,array $allowed=['active','inactive']):string {$s=strtolower(strval_field($a,'status',30));if(!in_array($s,$allowed,true))throw new InvalidArgumentException('Invalid status.');return $s;}
try {
 $u=current_user();if(!$u)reply(['error'=>'Sign in required.'],401);
 $method=$_SERVER['REQUEST_METHOD'];if($method!=='GET'&&$method!=='POST')reply(['error'=>'Method not allowed.'],405);
 $d=$method==='POST'?(json_decode(file_get_contents('php://input'),true)?:[]):$_GET;
 $action=(string)($d['action']??'');
 if($method==='POST')verify_csrf();
 if(in_array($u['role'],['contractor','collector','resident'],true))require __DIR__.'/extended.php';
 $gso=$u['role']==='gso';
 $sa=$u['role']==='super_admin';$bg=$u['role']==='barangay_gso';
 if(!$sa&&!$bg&&!$gso)reply(['error'=>'Forbidden role.'],403);
 $profile=$gso
  ?one('SELECT gso_id,office_name,city_id FROM gso WHERE user_id=?',[$u['id']])
  :($bg?one('SELECT barangay_gso_id,barangay_id FROM barangay_gso WHERE user_id=?',[$u['id']]):one('SELECT super_admin_id FROM super_admin WHERE user_id=?',[$u['id']]));
 if(!$profile)reply(['error'=>'No linked role profile found.'],403);
 $bid=$bg?(int)$profile['barangay_id']:0;$staffId=$bg?(int)$profile['barangay_gso_id']:0;$gsoId=$gso?(int)$profile['gso_id']:0;

 if($gso){
  if($action==='gso_tracking')reply(['data'=>rows('SELECT a.collection_assignment_id,v.vehicle_id,v.plate_number,v.vehicle_type,CONCAT(c.first_name," ",c.last_name) AS collector_name,co.company_name,rt.route_id,rt.route_Name AS route_name,vl.latitude,vl.longitude,vl.speed,vl.heading,vl.recorded_at,cr.status AS run_status FROM collection_assignment a JOIN collector c ON c.collector_id=a.collector_id JOIN contractor co ON co.contractor_id=c.contractor_id JOIN vehicle v ON v.vehicle_id=a.vehicle_id JOIN collection_schedule cs ON cs.collection_schedule_id=a.collection_schedule_id JOIN route rt ON rt.route_id=cs.route_id LEFT JOIN vehicle_location vl ON vl.vehicle_location_id=(SELECT MAX(vl2.vehicle_location_id) FROM vehicle_location vl2 WHERE vl2.collection_assignment_id=a.collection_assignment_id) LEFT JOIN collection_run cr ON cr.run_id=(SELECT MAX(cr2.run_id) FROM collection_run cr2 WHERE cr2.collection_assignment_id=a.collection_assignment_id) WHERE rt.gso_id=? ORDER BY vl.recorded_at DESC,a.collection_assignment_id DESC',[$gsoId])]);
  reply(['error'=>'Unknown or unauthorized action.'],404);
 }
 if($action==='profile')reply(['data'=>['username'=>$u['username'],'role'=>$u['role']]]);
 if($action==='change_password'&&$method==='POST'){
  required($d,['current_password','new_password']);if(strlen($d['new_password'])<10)throw new InvalidArgumentException('New password must have at least 10 characters.');
  $old=one('SELECT password_hash FROM `user` WHERE user_id=?',[$u['id']]);if(!$old||!password_verify((string)$d['current_password'],$old['password_hash']))throw new InvalidArgumentException('Current password is incorrect.');
  change('UPDATE `user` SET password_hash=? WHERE user_id=?',[password_hash($d['new_password'],PASSWORD_DEFAULT),$u['id']]);reply(['ok'=>true]);
 }
 if($sa){
  if($action==='cities')reply(['data'=>rows('SELECT city_id,city_name FROM city ORDER BY city_name')]);
  if($action==='gso_list')reply(['data'=>rows('SELECT g.gso_id,g.office_name,g.city_id,c.city_name,g.contact_no,g.email,g.office_address,g.status,u.username,u.status AS login_status FROM gso g JOIN `user` u ON u.user_id=g.user_id JOIN city c ON c.city_id=g.city_id WHERE g.super_admin_id=? ORDER BY g.gso_id DESC',[$profile['super_admin_id']])]);
  if($action==='gso_save'&&$method==='POST'){
   required($d,['office_name','city_id','contact_no','email','office_address','username']);
   if(!filter_var($d['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid email.');
   $rid=(int)($d['id']??0);$status=allowed_status($d);$city=id($d,'city_id');
   if(!one('SELECT city_id FROM city WHERE city_id=?',[$city]))throw new InvalidArgumentException('City does not exist.');
   $pdo=db();$pdo->beginTransaction();
   try {
    if($rid){$g=one('SELECT user_id FROM gso WHERE gso_id=? AND super_admin_id=?',[$rid,$profile['super_admin_id']]);if(!$g)throw new InvalidArgumentException('GSO record not found.');
      change('UPDATE gso SET office_name=?,city_id=?,contact_no=?,email=?,office_address=?,status=? WHERE gso_id=?',[$d['office_name'],$city,$d['contact_no'],$d['email'],$d['office_address'],$status,$rid]);
      change('UPDATE `user` SET username=?,status=? WHERE user_id=?',[$d['username'],$status,$g['user_id']]);
      if(!empty($d['password'])){if(strlen($d['password'])<10)throw new InvalidArgumentException('Password must have at least 10 characters.');change('UPDATE `user` SET password_hash=? WHERE user_id=?',[password_hash($d['password'],PASSWORD_DEFAULT),$g['user_id']]);}
    } else {
      required($d,['password']);if(strlen($d['password'])<10)throw new InvalidArgumentException('Password must have at least 10 characters.');
      change('INSERT INTO `user`(username,password_hash,role,status) VALUES(?,?,?,?)',[$d['username'],password_hash($d['password'],PASSWORD_DEFAULT),'gso',$status]);$userId=(int)$pdo->lastInsertId();
      change('INSERT INTO gso(user_id,super_admin_id,city_id,office_name,contact_no,email,office_address,status) VALUES(?,?,?,?,?,?,?,?)',[$userId,$profile['super_admin_id'],$city,$d['office_name'],$d['contact_no'],$d['email'],$d['office_address'],$status]);
    }
    $pdo->commit();reply(['ok'=>true]);
   }catch(Throwable $e){$pdo->rollBack();throw $e;}
  }
  if($action==='gso_toggle'&&$method==='POST'){
   $rid=id($d);$g=one('SELECT user_id,status FROM gso WHERE gso_id=? AND super_admin_id=?',[$rid,$profile['super_admin_id']]);if(!$g)throw new InvalidArgumentException('GSO account not found.');
   $next=strtolower($g['status']??'')==='active'?'inactive':'active';$pdo=db();$pdo->beginTransaction();try{change('UPDATE gso SET status=? WHERE gso_id=?',[$next,$rid]);change('UPDATE `user` SET status=? WHERE user_id=?',[$next,$g['user_id']]);$pdo->commit();reply(['ok'=>true]);}catch(Throwable $e){$pdo->rollBack();throw $e;}
  }
  if($action==='gso_delete'&&$method==='POST'){
   // Soft-delete to preserve references, auditability, and FK consistency.
   $rid=id($d);$g=one('SELECT user_id FROM gso WHERE gso_id=? AND super_admin_id=?',[$rid,$profile['super_admin_id']]);if(!$g)throw new InvalidArgumentException('GSO account not found.');
   $pdo=db();$pdo->beginTransaction();try{change('UPDATE gso SET status=? WHERE gso_id=?',['inactive',$rid]);change('UPDATE `user` SET status=? WHERE user_id=?',['inactive',$g['user_id']]);$pdo->commit();reply(['ok'=>true,'message'=>'Account deactivated; records preserved.']);}catch(Throwable $e){$pdo->rollBack();throw $e;}
  }
 }
 if($bg){
  $residentSql='SELECT r.resident_id,r.user_id,r.first_name,r.last_name,r.contact_no,r.email,r.house_no,r.street,u.username,u.status FROM resident r JOIN `user` u ON u.user_id=r.user_id WHERE r.barangay_id=? ORDER BY r.resident_id DESC';
  if($action==='residents')reply(['data'=>rows($residentSql,[$bid])]);
  if($action==='resident_save'&&$method==='POST'){
   required($d,['first_name','last_name','username']);$rid=(int)($d['id']??0);$status=allowed_status($d);
   if(!empty($d['email'])&&!filter_var($d['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Invalid email.');
   $pdo=db();$pdo->beginTransaction();try{
    if($rid){$r=one('SELECT user_id FROM resident WHERE resident_id=? AND barangay_id=?',[$rid,$bid]);if(!$r)throw new InvalidArgumentException('Resident not found.');$userId=$r['user_id'];
      change('UPDATE `user` SET username=?,status=? WHERE user_id=?',[$d['username'],$status,$userId]);
      if(!empty($d['password'])){if(strlen($d['password'])<10)throw new InvalidArgumentException('Password requires at least 10 characters.');change('UPDATE `user` SET password_hash=? WHERE user_id=?',[password_hash($d['password'],PASSWORD_DEFAULT),$userId]);}
      change('UPDATE resident SET first_name=?,last_name=?,contact_no=?,email=?,house_no=?,street=? WHERE resident_id=?',[$d['first_name'],$d['last_name'],$d['contact_no']??'',$d['email']??'',$d['house_no']??'',$d['street']??'',$rid]);
    }else{required($d,['password']);if(strlen($d['password'])<10)throw new InvalidArgumentException('Password requires at least 10 characters.');
      change('INSERT INTO `user`(username,password_hash,role,status) VALUES(?,?,?,?)',[$d['username'],password_hash($d['password'],PASSWORD_DEFAULT),'resident',$status]);$userId=(int)$pdo->lastInsertId();
      change('INSERT INTO resident(user_id,barangay_id,first_name,last_name,contact_no,email,house_no,street) VALUES(?,?,?,?,?,?,?,?)',[$userId,$bid,$d['first_name'],$d['last_name'],$d['contact_no']??'',$d['email']??'',$d['house_no']??'',$d['street']??'']);
    }
    $pdo->commit();reply(['ok'=>true]);
   }catch(Throwable $e){$pdo->rollBack();throw $e;}
  }
  if($action==='resident_toggle'&&$method==='POST'){
   $r=one('SELECT u.user_id,u.status FROM resident r JOIN `user` u ON u.user_id=r.user_id WHERE r.resident_id=? AND r.barangay_id=?',[id($d),$bid]);if(!$r)throw new InvalidArgumentException('Resident not found.');$next=strtolower($r['status'])==='active'?'inactive':'active';change('UPDATE `user` SET status=? WHERE user_id=?',[$next,$r['user_id']]);reply(['ok'=>true]);
  }
  if($action==='schedules')reply(['data'=>rows('SELECT cs.collection_schedule_id,rt.route_id,rt.route_name,rt.total_distance,rt.estimated_duration,rt.status AS route_status,cs.effective_from,cs.effective_until,cs.status,GROUP_CONCAT(DISTINCT sd.day_of_week ORDER BY FIELD(sd.day_of_week,"Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday") SEPARATOR ", ") AS days FROM collection_schedule cs JOIN route rt ON rt.route_id=cs.route_id JOIN route_stop rs ON rs.route_id=rt.route_id LEFT JOIN schedule_day sd ON sd.collection_schedule_id=cs.collection_schedule_id WHERE rs.barangay_id=? GROUP BY cs.collection_schedule_id,rt.route_id,rt.route_name,rt.total_distance,rt.estimated_duration,rt.status,cs.effective_from,cs.effective_until,cs.status ORDER BY cs.collection_schedule_id DESC',[$bid])]);
  if($action==='stops')reply(['data'=>rows('SELECT rs.route_stop_id,rs.stop_order,b.barangay_name,rs.scheduled_time,rs.estimated_arrival_time,rs.status FROM route_stop rs JOIN barangay b ON b.barangay_id=rs.barangay_id WHERE rs.route_id=? AND EXISTS(SELECT 1 FROM route_stop own WHERE own.route_id=rs.route_id AND own.barangay_id=?) ORDER BY rs.stop_order',[id($d,'route_id'),$bid])]);
  if($action==='complaints')reply(['data'=>rows('SELECT cp.complaint_id,cp.title,cp.description,cp.complaint_date,cp.status,cp.image,r.first_name,r.last_name,r.resident_id,cr.remarks AS resolution_remarks,cr.resolved_date FROM complaint cp JOIN resident r ON r.resident_id=cp.resident_id LEFT JOIN complaint_resolution cr ON cr.resolution_id=(SELECT MAX(cr2.resolution_id) FROM complaint_resolution cr2 WHERE cr2.complaint_id=cp.complaint_id) WHERE r.barangay_id=? ORDER BY cp.complaint_date DESC,cp.complaint_id DESC',[$bid])]);
  if($action==='complaint_resolve'&&$method==='POST'){
   $rid=id($d);$status=allowed_status($d,['pending','under review','resolved']);$c=one('SELECT cp.complaint_id FROM complaint cp JOIN resident r ON r.resident_id=cp.resident_id WHERE cp.complaint_id=? AND r.barangay_id=?',[$rid,$bid]);if(!$c)throw new InvalidArgumentException('Complaint not found.');
   $pdo=db();$pdo->beginTransaction();try{change('UPDATE complaint SET status=?,barangay_gso_id=? WHERE complaint_id=?',[$status,$staffId,$rid]);
   if(trim((string)($d['remarks']??''))!==''){change('INSERT INTO complaint_resolution(complaint_id,barangay_gso_id,resolved_date,remarks) VALUES(?,?,?,?)',[$rid,$staffId,$status==='resolved'?date('Y-m-d H:i:s'):null,$d['remarks']]);}
   $pdo->commit();reply(['ok'=>true]);}catch(Throwable $e){$pdo->rollBack();throw $e;}
  }
  if($action==='monitoring')reply(['data'=>rows('SELECT ca.collection_assignment_id,v.plate_number,v.vehicle_type, CONCAT(co.first_name," ",co.last_name) AS collector_name,rt.route_name,vl.latitude,vl.longitude,vl.speed,vl.heading,vl.recorded_at,cr.status AS run_status FROM collection_assignment ca JOIN vehicle v ON v.vehicle_id=ca.vehicle_id JOIN collector co ON co.collector_id=ca.collector_id JOIN collection_schedule cs ON cs.collection_schedule_id=ca.collection_schedule_id JOIN route rt ON rt.route_id=cs.route_id JOIN route_stop rs ON rs.route_id=rt.route_id AND rs.barangay_id=? LEFT JOIN vehicle_location vl ON vl.vehicle_location_id=(SELECT MAX(vl2.vehicle_location_id) FROM vehicle_location vl2 WHERE vl2.collection_assignment_id=ca.collection_assignment_id) LEFT JOIN collection_run cr ON cr.run_id=(SELECT MAX(cr2.run_id) FROM collection_run cr2 WHERE cr2.collection_assignment_id=ca.collection_assignment_id) GROUP BY ca.collection_assignment_id,v.plate_number,v.vehicle_type,co.first_name,co.last_name,rt.route_name,vl.latitude,vl.longitude,vl.speed,vl.heading,vl.recorded_at,cr.status ORDER BY ca.collection_assignment_id DESC',[$bid])]);
  if($action==='activities')reply(['data'=>rows('SELECT cr.collection_date,rt.route_name,b.barangay_name,cs.final_status,cs.updated_at,cs.remarks FROM collection_status cs JOIN collection_run cr ON cr.run_id=cs.run_id JOIN route_stop rs ON rs.route_stop_id=cs.route_stop_id JOIN barangay b ON b.barangay_id=rs.barangay_id JOIN collection_assignment ca ON ca.collection_assignment_id=cr.collection_assignment_id JOIN collection_schedule sch ON sch.collection_schedule_id=ca.collection_schedule_id JOIN route rt ON rt.route_id=sch.route_id WHERE rs.barangay_id=? ORDER BY cr.collection_date DESC,cs.updated_at DESC LIMIT 150',[$bid])]);
  if($action==='report'){
   $type=(string)($d['type']??'Collection Performance');$from=(string)($d['from']??'');$to=(string)($d['to']??'');
   if(($from&&(!preg_match('/^\d{4}-\d\d-\d\d$/',$from)))||($to&&(!preg_match('/^\d{4}-\d\d-\d\d$/',$to))))throw new InvalidArgumentException('Invalid date format.');
   if($from&&$to&&$from>$to)throw new InvalidArgumentException('Start date must precede end date.');
   $filter='';$args=[$bid];if($from){$filter.=' AND cr.collection_date >= ?';$args[]=$from;}if($to){$filter.=' AND cr.collection_date <= ?';$args[]=$to;}
   if(in_array($type,['Collection Performance','Route Stop Performance'],true)){
    $data=rows('SELECT cr.collection_date AS date,rt.route_name AS related,COALESCE(cs.final_status,"Scheduled") AS status,CONCAT("Stop #",rs.stop_order,IFNULL(CONCAT(" — ",cs.remarks),"")) AS detail FROM collection_status cs JOIN collection_run cr ON cr.run_id=cs.run_id JOIN route_stop rs ON rs.route_stop_id=cs.route_stop_id JOIN route rt ON rt.route_id=rs.route_id WHERE rs.barangay_id=?'.$filter.' ORDER BY cr.collection_date DESC LIMIT 500',$args);
   }elseif($type==='Complaints & Resolutions'){
    $q='SELECT DATE(cp.complaint_date) AS date,CONCAT(r.first_name," ",r.last_name) AS related,cp.status,cp.title AS detail FROM complaint cp JOIN resident r ON r.resident_id=cp.resident_id WHERE r.barangay_id=?';$p=[$bid];if($from){$q.=' AND DATE(cp.complaint_date)>=?';$p[]=$from;}if($to){$q.=' AND DATE(cp.complaint_date)<=?';$p[]=$to;}$data=rows($q.' ORDER BY cp.complaint_date DESC LIMIT 500',$p);
   }elseif($type==='Resident Summary'){
    $data=rows('SELECT DATE(u.last_login) AS date,CONCAT(r.first_name," ",r.last_name) AS related,u.status,CONCAT("Account: ",u.username) AS detail FROM resident r JOIN `user` u ON u.user_id=r.user_id WHERE r.barangay_id=? ORDER BY r.resident_id DESC LIMIT 500',[$bid]);
   }else throw new InvalidArgumentException('Unknown report category.');
   reply(['data'=>$data]);
  }
 }
 reply(['error'=>'Unknown or unauthorized action.'],404);
}catch(InvalidArgumentException $e){reply(['error'=>$e->getMessage()],422);}catch(PDOException $e){$msg=$e->getCode()==='23000'?'A username or referenced record already exists or is in use.':'Database operation failed. Check schema and connection.';reply(['error'=>$msg],500);}catch(Throwable $e){reply(['error'=>$e->getMessage()],500);}
