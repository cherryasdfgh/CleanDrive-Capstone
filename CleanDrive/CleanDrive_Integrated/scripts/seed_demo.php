<?php
/**
 * Local development only. Run explicitly from CLI: php scripts/seed_demo.php --demo
 * Adds demo geography, two user accounts, sample schedule, complaints and location records.
 * Never run against a production database. Uses username lookup to avoid duplicate accounts.
 */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array('--demo',$argv,true)){fwrite(STDERR,"Local demo seed only: php scripts/seed_demo.php --demo\n");exit(1);}
require_once __DIR__.'/../includes/db.php';
$pdo=db();
if(one('SELECT user_id FROM `user` WHERE username=?',['cd_demo_admin'])){echo "Demo seed already present. No changes.\n";exit;}
$pdo->beginTransaction();
try{
 change('INSERT INTO region(region_name) VALUES(?)',['Region VI – Western Visayas']);$region=(int)$pdo->lastInsertId();
 change('INSERT INTO province(region_id,province_name) VALUES(?,?)',[$region,'Iloilo']);$province=(int)$pdo->lastInsertId();
 change('INSERT INTO city(province_id,city_name) VALUES(?,?)',[$province,'Iloilo City']);$city=(int)$pdo->lastInsertId();
 change('INSERT INTO barangay(city_id,barangay_name) VALUES(?,?)',[$city,'Balabago']);$barangay=(int)$pdo->lastInsertId();
 change('INSERT INTO barangay(city_id,barangay_name) VALUES(?,?)',[$city,'Tagbak']);$other=(int)$pdo->lastInsertId();
 $password= getenv('CLEANDRIVE_DEMO_PASSWORD') ?: 'CleanDriveDemo!2026';
 function adduser(string $name,string $role,string $password):int{change('INSERT INTO `user`(username,password_hash,role,status) VALUES(?,?,?,?)',[$name,password_hash($password,PASSWORD_DEFAULT),$role,'active']);return (int)db()->lastInsertId();}
 $uid=adduser('cd_demo_admin','super_admin',$password);
 change('INSERT INTO super_admin(user_id,contact_name,contact_no,email,status) VALUES(?,?,?,?,?)',[$uid,'Demo Super Admin','09170000000','admin@example.test','active']);$admin=(int)$pdo->lastInsertId();
 $uid=adduser('cd_demo_gso','gso',$password);
 change('INSERT INTO gso(user_id,super_admin_id,city_id,office_name,contact_no,email,office_address,status) VALUES(?,?,?,?,?,?,?,?)',[$uid,$admin,$city,'General Services Office','09171111111','gso@example.test','Iloilo City Hall','active']);$gso=(int)$pdo->lastInsertId();
 $uid=adduser('cd_demo_barangay','barangay_gso',$password);
 change('INSERT INTO barangay_gso(user_id,barangay_id,contact_name,contact_no,email,address,status) VALUES(?,?,?,?,?,?,?)',[$uid,$barangay,'Demo Barangay Officer','09172222222','barangay@example.test','Balabago','active']);$staff=(int)$pdo->lastInsertId();
 for($i=1;$i<=3;$i++){$uid=adduser('cd_demo_resident'.$i,'resident',$password);change('INSERT INTO resident(user_id,barangay_id,first_name,last_name,contact_no,email,house_no,street) VALUES(?,?,?,?,?,?,?,?)',[$uid,$barangay,['Maria','Jose','Ana'][$i-1],['Santos','Dela Cruz','Reyes'][$i-1],'09173333333','resident'.$i.'@example.test',(string)(10+$i),'Main Street']);$residents[]=(int)$pdo->lastInsertId();}
 foreach([['Missed waste collection','No collection truck arrived on the scheduled day.','pending'],['Uncollected recyclables','Recyclables remained at the pickup point.','under review'],['Delayed pickup','Pickup was completed later than scheduled.','resolved']] as $i=>$c){change('INSERT INTO complaint(resident_id,barangay_gso_id,title,description,complaint_date,status) VALUES(?,?,?,?,NOW(),?)',[$residents[$i],$staff,$c[0],$c[1],$c[2]]);}
 change('INSERT INTO route(gso_id,route_name,total_distance,estimated_duration,status) VALUES(?,?,?,?,?)',[$gso,'Jaro North Collection Route',8.50,150,'active']);$route=(int)$pdo->lastInsertId();
 foreach([[$barangay,1,'07:30:00',10.7621000,122.5766000],[$other,2,'08:15:00',10.7761000,122.5906000]] as $s){change('INSERT INTO route_stop(route_id,barangay_id,stop_order,scheduled_time,estimated_arrival_time,latitude,longitude,status) VALUES(?,?,?,?,?,?,?,?)',[$route,$s[0],$s[1],$s[2],$s[2],$s[3],$s[4],'active']);$stops[]=(int)$pdo->lastInsertId();}
 change('INSERT INTO collection_schedule(route_id,effective_from,effective_until,status) VALUES(?,?,?,?)',[$route,date('Y-m-01'),date('Y-m-t',strtotime('+2 months')),'active']);$schedule=(int)$pdo->lastInsertId();
 change('INSERT INTO schedule_day(collection_schedule_id,day_of_week) VALUES(?,?)',[$schedule,date('l')]);$day=(int)$pdo->lastInsertId();
 $uid=adduser('cd_demo_contractor','contractor',$password);
 change('INSERT INTO contractor(user_id,gso_id,company_name,business_license_number,contact_no,status) VALUES(?,?,?,?,?,?)',[$uid,$gso,'Greenway Collection Services','DEMO-LIC-001','09174444444','active']);$contractor=(int)$pdo->lastInsertId();
 $uid=adduser('cd_demo_collector','collector',$password);
 change('INSERT INTO collector(contractor_id,user_id,first_name,last_name,contact_no,employment_status,date_joined) VALUES(?,?,?,?,?,?,?)',[$contractor,$uid,'Pedro','Reyes','09175555555','active',date('Y-m-d')]);$collector=(int)$pdo->lastInsertId();
 change('INSERT INTO vehicle(contractor_id,plate_number,capacity,vehicle_type,status) VALUES(?,?,?,?,?)',[$contractor,'CD-DEMO-01',4.5,'Garbage Truck','active']);$vehicle=(int)$pdo->lastInsertId();
 change('INSERT INTO collection_assignment(collection_schedule_id,collector_id,vehicle_id,assigned_date,status) VALUES(?,?,?,?,?)',[$schedule,$collector,$vehicle,date('Y-m-d'),'active']);$assignment=(int)$pdo->lastInsertId();
 change('INSERT INTO collection_run(schedule_day_id,collection_assignment_id,collection_date,started_at,status) VALUES(?,?,?,?,?)',[$day,$assignment,date('Y-m-d'),date('Y-m-d H:i:s'),'In Progress']);$run=(int)$pdo->lastInsertId();
 change('INSERT INTO collection_status(run_id,route_stop_id,final_status,actual_arrival_time,updated_at,remarks) VALUES(?,?,?,NOW(),NOW(),?)',[$run,$stops[0],'In Progress','Collecting waste at the route stop']);
 change('INSERT INTO vehicle_location(collection_assignment_id,latitude,longitude,speed,heading,recorded_at) VALUES(?,?,?,?,?,NOW())',[$assignment,10.7621000,122.5766000,18.0,90.0]);
 $pdo->commit();echo "Demo records added to database cleandrive.\nLogin: cd_demo_admin or cd_demo_barangay\nPassword: $password\nCHANGE these demo passwords before real use.\n";
}catch(Throwable $e){$pdo->rollBack();fwrite(STDERR,"Seed failed: ".$e->getMessage()."\n");exit(1);}
