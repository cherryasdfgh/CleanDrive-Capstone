<?php
// Included by api/index.php after session and CSRF checks. All queries use account scoping.
$contractor=$u['role']==='contractor';
$collector=$u['role']==='collector';
$profile=$contractor
 ?one('SELECT contractor_id FROM contractor WHERE user_id=?',[$u['id']])
 :($collector
   ?one('SELECT collector_id,contractor_id FROM collector WHERE user_id=?',[$u['id']])
   :one('SELECT resident_id,barangay_id FROM resident WHERE user_id=?',[$u['id']])
 );
if(!$profile)reply(['error'=>'Your login is not linked to a role profile.'],403);
$pid=(int)($contractor?$profile['contractor_id']:($collector?$profile['collector_id']:$profile['resident_id']));
if($action==='change_password'&&$method==='POST'){
 required($d,['current_password','new_password']);if(strlen($d['new_password'])<10)throw new InvalidArgumentException('Use at least 10 characters.');
 $old=one('SELECT password_hash FROM `user` WHERE user_id=?',[$u['id']]);if(!password_verify((string)$d['current_password'],$old['password_hash']))throw new InvalidArgumentException('Current password is incorrect.');
 change('UPDATE `user` SET password_hash=? WHERE user_id=?',[password_hash($d['new_password'],PASSWORD_DEFAULT),$u['id']]);reply(['ok'=>true]);
}
if($contractor){
 if($action==='collectors')reply(['data'=>rows('SELECT c.*,u.username,u.status AS login_status FROM collector c JOIN `user` u ON u.user_id=c.user_id WHERE c.contractor_id=? ORDER BY c.collector_id DESC',[$pid])]);
 if($action==='vehicles')reply(['data'=>rows('SELECT v.* FROM vehicle v WHERE v.contractor_id=? ORDER BY v.vehicle_id DESC',[$pid])]);
 if($action==='schedules')reply(['data'=>rows('SELECT cs.collection_schedule_id,rt.route_name,cs.effective_from,cs.effective_until,cs.status,GROUP_CONCAT(sd.day_of_week ORDER BY FIELD(sd.day_of_week,"Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday") SEPARATOR ", ") AS days FROM collection_schedule cs JOIN route rt ON rt.route_id=cs.route_id LEFT JOIN schedule_day sd ON sd.collection_schedule_id=cs.collection_schedule_id GROUP BY cs.collection_schedule_id,rt.route_name,cs.effective_from,cs.effective_until,cs.status ORDER BY cs.collection_schedule_id DESC LIMIT 300')]);
 if($action==='assignments')reply(['data'=>rows('SELECT a.collection_assignment_id,a.collection_schedule_id,a.collector_id,a.vehicle_id,a.assigned_date,a.status,rt.route_name, CONCAT(c.first_name," ",c.last_name) AS collector_name,v.plate_number FROM collection_assignment a JOIN collector c ON c.collector_id=a.collector_id JOIN vehicle v ON v.vehicle_id=a.vehicle_id JOIN collection_schedule s ON s.collection_schedule_id=a.collection_schedule_id JOIN route rt ON rt.route_id=s.route_id WHERE c.contractor_id=? AND v.contractor_id=? ORDER BY a.collection_assignment_id DESC',[$pid,$pid])]);
 if($action==='fleet')reply(['data'=>rows('SELECT v.vehicle_id,v.plate_number,v.vehicle_type,v.status,a.collection_assignment_id,rt.route_name,CONCAT(c.first_name," ",c.last_name) AS collector_name,vl.latitude,vl.longitude,vl.speed,vl.recorded_at FROM vehicle v LEFT JOIN collection_assignment a ON a.collection_assignment_id=(SELECT MAX(a2.collection_assignment_id) FROM collection_assignment a2 WHERE a2.vehicle_id=v.vehicle_id) LEFT JOIN collector c ON c.collector_id=a.collector_id LEFT JOIN collection_schedule cs ON cs.collection_schedule_id=a.collection_schedule_id LEFT JOIN route rt ON rt.route_id=cs.route_id LEFT JOIN vehicle_location vl ON vl.vehicle_location_id=(SELECT MAX(vl2.vehicle_location_id) FROM vehicle_location vl2 WHERE vl2.collection_assignment_id=a.collection_assignment_id) WHERE v.contractor_id=? ORDER BY v.vehicle_id DESC',[$pid])]);
 if($action==='collector_save'&&$method==='POST'){
  required($d,['first_name','last_name','username']);$rid=(int)($d['id']??0);$status=allowed_status($d);$em=strval_field($d,'email');if($em&&!filter_var($em,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Invalid email.');
  $pdo=db();$pdo->beginTransaction();try{
   if($rid){$c=one('SELECT user_id FROM collector WHERE collector_id=? AND contractor_id=?',[$rid,$pid]);if(!$c)throw new InvalidArgumentException('Collector not found.');$uid=$c['user_id'];change('UPDATE `user` SET username=?,status=? WHERE user_id=?',[$d['username'],$status,$uid]);if(!empty($d['password'])){if(strlen($d['password'])<10)throw new InvalidArgumentException('Password must contain at least 10 characters.');change('UPDATE `user` SET password_hash=? WHERE user_id=?',[password_hash($d['password'],PASSWORD_DEFAULT),$uid]);}}
   else{required($d,['password']);if(strlen($d['password'])<10)throw new InvalidArgumentException('Password must contain at least 10 characters.');change('INSERT INTO `user`(username,password_hash,role,status) VALUES(?,?,"collector",?)',[$d['username'],password_hash($d['password'],PASSWORD_DEFAULT),$status]);$uid=(int)$pdo->lastInsertId();}
   $fields=[strval_field($d,'first_name'),strval_field($d,'last_name'),strval_field($d,'contact_no'),$em,strval_field($d,'license_no'),$status,($d['date_joined']??'')?:null,($d['date_ended']??'')?:null];
   if($rid)change('UPDATE collector SET first_name=?,last_name=?,contact_no=?,email=?,license_no=?,employment_status=?,date_joined=?,date_ended=? WHERE collector_id=? AND contractor_id=?',[...$fields,$rid,$pid]);
   else change('INSERT INTO collector(first_name,last_name,contact_no,email,license_no,employment_status,date_joined,date_ended,user_id,contractor_id) VALUES(?,?,?,?,?,?,?,?,?,?)',[...$fields,$uid,$pid]);
   $pdo->commit();reply(['ok'=>true]);
  }catch(Throwable $e){$pdo->rollBack();throw $e;}
 }
 if($action==='vehicle_save'&&$method==='POST'){
  required($d,['plate_number','vehicle_type']);$rid=(int)($d['id']??0);$status=allowed_status($d,['active','inactive','under maintenance']);$cap=($d['capacity']??'')===''?null:(float)$d['capacity'];if($cap!==null&&$cap<0)throw new InvalidArgumentException('Capacity must not be negative.');
  if($rid){if(!one('SELECT vehicle_id FROM vehicle WHERE vehicle_id=? AND contractor_id=?',[$rid,$pid]))throw new InvalidArgumentException('Vehicle not found.');change('UPDATE vehicle SET plate_number=?,vehicle_type=?,capacity=?,status=? WHERE vehicle_id=? AND contractor_id=?',[$d['plate_number'],$d['vehicle_type'],$cap,$status,$rid,$pid]);}
  else change('INSERT INTO vehicle(plate_number,vehicle_type,capacity,status,contractor_id) VALUES(?,?,?,?,?)',[$d['plate_number'],$d['vehicle_type'],$cap,$status,$pid]);reply(['ok'=>true]);
 }
 if($action==='assign_save'&&$method==='POST'){
  $collector=id($d,'collector_id');$vehicle=id($d,'vehicle_id');$schedule=id($d,'collection_schedule_id');$rid=(int)($d['id']??0);
  if(!one('SELECT collector_id FROM collector WHERE collector_id=? AND contractor_id=?',[$collector,$pid])||!one('SELECT vehicle_id FROM vehicle WHERE vehicle_id=? AND contractor_id=?',[$vehicle,$pid]))throw new InvalidArgumentException('Select a collector and vehicle from your company.');
  if(!one('SELECT collection_schedule_id FROM collection_schedule WHERE collection_schedule_id=?',[$schedule]))throw new InvalidArgumentException('Unknown collection schedule.');
  $assigned=($d['assigned_date']??'')?:date('Y-m-d');$status=allowed_status($d,['assigned','active','completed','cancelled']);
  if($rid){if(!one('SELECT a.collection_assignment_id FROM collection_assignment a JOIN collector c ON c.collector_id=a.collector_id WHERE a.collection_assignment_id=? AND c.contractor_id=?',[$rid,$pid]))throw new InvalidArgumentException('Assignment not found.');change('UPDATE collection_assignment SET collector_id=?,vehicle_id=?,collection_schedule_id=?,assigned_date=?,status=? WHERE collection_assignment_id=?',[$collector,$vehicle,$schedule,$assigned,$status,$rid]);}
  else change('INSERT INTO collection_assignment(collector_id,vehicle_id,collection_schedule_id,assigned_date,status) VALUES(?,?,?,?,?)',[$collector,$vehicle,$schedule,$assigned,$status]);reply(['ok'=>true]);
 }
}
if($collector){
 if($action==='collector_profile')reply(['data'=>one('SELECT c.collector_id,c.first_name,c.last_name,c.contact_no,c.email,c.license_no,c.employment_status,c.date_joined,u.username,u.status AS login_status,co.company_name FROM collector c JOIN `user` u ON u.user_id=c.user_id JOIN contractor co ON co.contractor_id=c.contractor_id WHERE c.collector_id=?',[$pid])]);
 if($action==='collector_dashboard')reply(['data'=>rows('SELECT a.collection_assignment_id,a.assigned_date,a.status AS assignment_status,cs.collection_schedule_id,rt.route_id,rt.route_name,v.plate_number,v.vehicle_type,run.run_id,run.collection_date,run.started_at,run.completed_at,run.status AS run_status FROM collection_assignment a JOIN vehicle v ON v.vehicle_id=a.vehicle_id JOIN collection_schedule cs ON cs.collection_schedule_id=a.collection_schedule_id JOIN route rt ON rt.route_id=cs.route_id LEFT JOIN collection_run run ON run.run_id=(SELECT MAX(r2.run_id) FROM collection_run r2 WHERE r2.collection_assignment_id=a.collection_assignment_id) WHERE a.collector_id=? ORDER BY a.assigned_date DESC,a.collection_assignment_id DESC',[$pid])]);
 if($action==='collector_route')reply(['data'=>rows('SELECT rs.route_stop_id,rs.route_id,rs.stop_order,rs.scheduled_time,rs.estimated_arrival_time,rs.latitude,rs.longitude,rs.status,b.barangay_name,cs.collection_status_id,cs.final_status,cs.actual_arrival_time,cs.actual_departure_time,cs.remarks FROM collection_assignment ca JOIN collection_schedule sch ON sch.collection_schedule_id=ca.collection_schedule_id JOIN route rt ON rt.route_id=sch.route_id JOIN route_stop rs ON rs.route_id=rt.route_id JOIN barangay b ON b.barangay_id=rs.barangay_id LEFT JOIN collection_run cr ON cr.run_id=(SELECT MAX(r2.run_id) FROM collection_run r2 WHERE r2.collection_assignment_id=ca.collection_assignment_id) LEFT JOIN collection_status cs ON cs.collection_status_id=(SELECT MAX(s2.collection_status_id) FROM collection_status s2 WHERE s2.run_id=cr.run_id AND s2.route_stop_id=rs.route_stop_id) WHERE ca.collector_id=? ORDER BY rs.route_id,rs.stop_order',[$pid])]);
 if($action==='collector_statuses')reply(['data'=>rows('SELECT rs.route_stop_id,rs.stop_order,b.barangay_name,cs.collection_status_id,cs.final_status,cs.actual_arrival_time,cs.actual_departure_time,cs.remarks,cs.updated_at,a.collection_assignment_id FROM collection_assignment a JOIN collection_schedule sch ON sch.collection_schedule_id=a.collection_schedule_id JOIN route rt ON rt.route_id=sch.route_id JOIN route_stop rs ON rs.route_id=rt.route_id JOIN barangay b ON b.barangay_id=rs.barangay_id LEFT JOIN collection_run cr ON cr.run_id=(SELECT MAX(r2.run_id) FROM collection_run r2 WHERE r2.collection_assignment_id=a.collection_assignment_id) LEFT JOIN collection_status cs ON cs.collection_status_id=(SELECT MAX(s2.collection_status_id) FROM collection_status s2 WHERE s2.run_id=cr.run_id AND s2.route_stop_id=rs.route_stop_id) WHERE a.collector_id=? ORDER BY rs.route_id,rs.stop_order',[$pid])]);
 if($action==='collector_update_status'&&$method==='POST'){
  $assignment=id($d,'assignment_id');$stop=id($d,'route_stop_id');$status=strtolower(strval_field($d,'status',30));
  $statusMap=['pending'=>'Pending','in progress'=>'In Progress','completed'=>'Completed','delayed'=>'Delayed','missed'=>'Missed'];
  if(!isset($statusMap[$status]))throw new InvalidArgumentException('Invalid collection status.');
  $status=$statusMap[$status];
  $valid=one('SELECT ca.collection_assignment_id,cr.run_id FROM collection_assignment ca JOIN collection_schedule sch ON sch.collection_schedule_id=ca.collection_schedule_id JOIN route rt ON rt.route_id=sch.route_id JOIN route_stop rs ON rs.route_stop_id=? AND rs.route_id=rt.route_id LEFT JOIN collection_run cr ON cr.run_id=(SELECT MAX(r2.run_id) FROM collection_run r2 WHERE r2.collection_assignment_id=ca.collection_assignment_id) WHERE ca.collection_assignment_id=? AND ca.collector_id=?',[$stop,$assignment,$pid]);
  if(!$valid)throw new InvalidArgumentException('This assignment or route stop is not assigned to you.');
  $pdo=db();$pdo->beginTransaction();try{
   $run=(int)($valid['run_id']??0);
   if(!$run){$scheduleDay=one('SELECT sd.schedule_day_id FROM collection_assignment ca JOIN collection_schedule sch ON sch.collection_schedule_id=ca.collection_schedule_id JOIN schedule_day sd ON sd.collection_schedule_id=sch.collection_schedule_id WHERE ca.collection_assignment_id=? ORDER BY sd.schedule_day_id LIMIT 1',[$assignment]);if(!$scheduleDay)throw new InvalidArgumentException('No schedule day is available for this assignment.');change('INSERT INTO collection_run(schedule_day_id,collection_assignment_id,collection_date,started_at,status) VALUES(?,?,?,?,?)',[$scheduleDay['schedule_day_id'],$assignment,date('Y-m-d'),date('Y-m-d H:i:s'),'In Progress']);$run=(int)$pdo->lastInsertId();}
   $existing=one('SELECT collection_status_id FROM collection_status WHERE run_id=? AND route_stop_id=?',[$run,$stop]);
   if($existing)change('UPDATE collection_status SET final_status=?,remarks=?,updated_at=NOW() WHERE collection_status_id=?',[$status,strval_field($d,'remarks',5000),(int)$existing['collection_status_id']]);
   else change('INSERT INTO collection_status(run_id,route_stop_id,final_status,actual_arrival_time,actual_departure_time,remarks,updated_at) VALUES(?,?,?,?,?,?,NOW())',[$run,$stop,$status,($status==='In Progress'?date('Y-m-d H:i:s'):null),($status==='Completed'?date('Y-m-d H:i:s'):null),strval_field($d,'remarks',5000)]);
   $pdo->commit();reply(['ok'=>true,'message'=>'Collection status updated.']);
  }catch(Throwable $e){$pdo->rollBack();throw $e;}
 }
 if($action==='collector_vehicle_location'&&$method==='POST'){
  $assignment=id($d,'assignment_id');$latitude=(float)($d['latitude']??0);$longitude=(float)($d['longitude']??0);$speed=($d['speed']??'')===''?null:(float)$d['speed'];$heading=($d['heading']??'')===''?null:(float)$d['heading'];
  if($latitude<-90||$latitude>90||$longitude<-180||$longitude>180)throw new InvalidArgumentException('Invalid GPS coordinates.');
  if(!one('SELECT collection_assignment_id FROM collection_assignment WHERE collection_assignment_id=? AND collector_id=?',[$assignment,$pid]))throw new InvalidArgumentException('This vehicle assignment is not yours.');
  change('INSERT INTO vehicle_location(collection_assignment_id,latitude,longitude,speed,heading,recorded_at) VALUES(?,?,?,?,?,NOW())',[$assignment,$latitude,$longitude,$speed,$heading]);reply(['ok'=>true,'message'=>'GPS position recorded.']);
 }
}
else{
 $bid=(int)$profile['barangay_id'];
 if($action==='resident_profile')reply(['data'=>one('SELECT r.resident_id,r.first_name,r.last_name,r.barangay_id,b.barangay_name FROM resident r JOIN barangay b ON b.barangay_id=r.barangay_id WHERE r.resident_id=?',[$pid])]);
 if($action==='schedules')reply(['data'=>rows('SELECT cs.collection_schedule_id,rt.route_id,rt.route_name,cs.effective_from,cs.effective_until,cs.status,sd.day_of_week,rs.stop_order,rs.estimated_arrival_time,rs.scheduled_time FROM route_stop rs JOIN route rt ON rt.route_id=rs.route_id JOIN collection_schedule cs ON cs.route_id=rt.route_id JOIN schedule_day sd ON sd.collection_schedule_id=cs.collection_schedule_id WHERE rs.barangay_id=? ORDER BY FIELD(sd.day_of_week,"Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"),rs.stop_order',[$bid])]);
 if($action==='tracking')reply(['data'=>rows('SELECT a.collection_assignment_id,rt.route_name,v.plate_number,CONCAT(c.first_name," ",c.last_name) AS collector_name,vl.latitude,vl.longitude,vl.speed,vl.recorded_at,run.status AS run_status,rs.estimated_arrival_time FROM route_stop rs JOIN collection_schedule sch ON sch.route_id=rs.route_id JOIN route rt ON rt.route_id=rs.route_id JOIN collection_assignment a ON a.collection_schedule_id=sch.collection_schedule_id JOIN vehicle v ON v.vehicle_id=a.vehicle_id JOIN collector c ON c.collector_id=a.collector_id LEFT JOIN vehicle_location vl ON vl.vehicle_location_id=(SELECT MAX(vl2.vehicle_location_id) FROM vehicle_location vl2 WHERE vl2.collection_assignment_id=a.collection_assignment_id) LEFT JOIN collection_run run ON run.run_id=(SELECT MAX(run2.run_id) FROM collection_run run2 WHERE run2.collection_assignment_id=a.collection_assignment_id) WHERE rs.barangay_id=? ORDER BY a.collection_assignment_id DESC LIMIT 100',[$bid])]);
 if($action==='my_complaints')reply(['data'=>rows('SELECT cp.complaint_id,cp.title,cp.description,cp.image,cp.complaint_date,cp.status,cr.remarks AS resolution_remarks,cr.resolved_date FROM complaint cp LEFT JOIN complaint_resolution cr ON cr.resolution_id=(SELECT MAX(c2.resolution_id) FROM complaint_resolution c2 WHERE c2.complaint_id=cp.complaint_id) WHERE cp.resident_id=? ORDER BY cp.complaint_id DESC',[$pid])]);
 if($action==='complaint_submit'&&$method==='POST'){
  required($d,['title','description']);change('INSERT INTO complaint(resident_id,title,description,complaint_date,status) VALUES(?,?,?,NOW(),"pending")',[$pid,strval_field($d,'title',200),strval_field($d,'description',5000)]);reply(['ok'=>true,'message'=>'Complaint submitted. Image uploads are not enabled in this prototype.']);
 }
 if($action==='marketplace')reply(['data'=>rows('SELECT m.item_id,m.resident_id,m.item_name,m.description,m.category,m.price,m.quantity,m.address,m.status,m.posted_date,CONCAT(r.first_name," ",r.last_name) AS seller FROM marketplace_item m JOIN resident r ON r.resident_id=m.resident_id ORDER BY m.item_id DESC LIMIT 300')]);
 if($action==='my_messages')reply(['data'=>rows('SELECT m.message_id,m.item_id,m.sender_id,m.receiver_id,m.message,m.sent_at,mi.item_name,CONCAT(r.first_name," ",r.last_name) AS sender FROM message m JOIN marketplace_item mi ON mi.item_id=m.item_id JOIN resident r ON r.resident_id=m.sender_id WHERE m.receiver_id=? OR m.sender_id=? ORDER BY m.message_id DESC LIMIT 120',[$pid,$pid])]);
 if($action==='marketplace_save'&&$method==='POST'){
  required($d,['item_name','category','price','quantity']);$rid=(int)($d['id']??0);$price=(float)$d['price'];$qty=(int)$d['quantity'];if($price<0||$qty<1)throw new InvalidArgumentException('Enter a nonnegative price and quantity of at least 1.');$status=allowed_status($d,['available','reserved','sold','inactive']);
  $args=[strval_field($d,'item_name',200),strval_field($d,'description',5000),strval_field($d,'category',100),$price,$qty,strval_field($d,'address',1000),$status];
  if($rid){if(!one('SELECT item_id FROM marketplace_item WHERE item_id=? AND resident_id=?',[$rid,$pid]))throw new InvalidArgumentException('This listing is not yours.');change('UPDATE marketplace_item SET item_name=?,description=?,category=?,price=?,quantity=?,address=?,status=? WHERE item_id=? AND resident_id=?',[...$args,$rid,$pid]);}
  else change('INSERT INTO marketplace_item(item_name,description,category,price,quantity,address,status,resident_id,posted_date) VALUES(?,?,?,?,?,?,?,?,NOW())',[...$args,$pid]);reply(['ok'=>true]);
 }
 if($action==='message_send'&&$method==='POST'){
  $item=id($d,'item_id');$entry=one('SELECT resident_id FROM marketplace_item WHERE item_id=?',[$item]);if(!$entry)throw new InvalidArgumentException('Listing not found.');$recipient=(int)$entry['resident_id'];if($recipient===$pid)throw new InvalidArgumentException('You cannot message your own listing.');required($d,['message']);change('INSERT INTO message(sender_id,receiver_id,item_id,message,status) VALUES(?,?,?, ?,"sent")',[$pid,$recipient,$item,strval_field($d,'message',3000)]);reply(['ok'=>true]);
 }
}
reply(['error'=>'Unknown or unauthorized action.'],404);
