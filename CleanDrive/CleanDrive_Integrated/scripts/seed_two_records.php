<?php
// Seed two demonstration records for operational tables.
// Geography tables (region, province, city, barangay) are not recreated or changed.
declare(strict_types=1);

require_once __DIR__.'/../includes/db.php';

$password = 'CleanDriveDemo!2026';
$pdo = db();
$pdo->beginTransaction();

try {
    $ensureUser = function (string $username, string $role) use ($password): int {
        $existing = one('SELECT user_id FROM `user` WHERE username=?', [$username]);
        if ($existing) {
            $uid = (int) $existing['user_id'];
            change('UPDATE `user` SET password_hash=?,role=?,status=? WHERE user_id=?', [password_hash($password, PASSWORD_DEFAULT), $role, 'active', $uid]);
            return $uid;
        }
        change('INSERT INTO `user` (username,password_hash,role,status) VALUES (?,?,?,?)', [$username, password_hash($password, PASSWORD_DEFAULT), $role, 'active']);
        return (int) db()->lastInsertId();
    };

    $superAdmin = one('SELECT super_admin_id FROM super_admin ORDER BY super_admin_id LIMIT 1');
    if (!$superAdmin) throw new RuntimeException('A Super Admin profile is required.');

    $cities = rows('SELECT city_id,city_name FROM city ORDER BY city_id LIMIT 2');
    $barangays = rows('SELECT barangay_id,barangay_name FROM barangay ORDER BY barangay_id LIMIT 2');
    if (count($cities) < 1 || count($barangays) < 2) throw new RuntimeException('At least one city and two barangays are required.');

    // Ensure two GSO accounts.
    $gsos = rows('SELECT gso_id,user_id FROM gso ORDER BY gso_id LIMIT 2');
    if (count($gsos) < 2) {
        $uid = $ensureUser('cd_demo_gso_2', 'gso');
        $existing = one('SELECT gso_id FROM gso WHERE user_id=?', [$uid]);
        if (!$existing) {
            change('INSERT INTO gso(user_id,super_admin_id,city_id,office_name,contact_no,email,office_address,status) VALUES(?,?,?,?,?,?,?,?)', [$uid, $superAdmin['super_admin_id'], $cities[0]['city_id'], 'CleanDrive GSO La Paz', '09171000002', 'gso2@example.test', 'La Paz, Iloilo City', 'active']);
        }
        $gsos = rows('SELECT gso_id,user_id FROM gso ORDER BY gso_id LIMIT 2');
    }

    // Ensure two Barangay GSO accounts.
    $barangayGsos = rows('SELECT barangay_gso_id,user_id,barangay_id FROM barangay_gso ORDER BY barangay_gso_id LIMIT 2');
    for ($i = 0; $i < 2; $i++) {
        $username = $i === 0 ? 'cd_demo_barangay' : 'cd_demo_barangay_2';
        $uid = $ensureUser($username, 'barangay_gso');
        $profile = one('SELECT barangay_gso_id FROM barangay_gso WHERE user_id=?', [$uid]);
        if (!$profile) {
            change('INSERT INTO barangay_gso(user_id,barangay_id,contact_name,contact_no,email,address,status) VALUES(?,?,?,?,?,?,?)', [$uid, $barangays[$i]['barangay_id'], 'Demo Barangay GSO '.$i+1, '0917200000'.($i+1), 'barangay'.($i+1).'@example.test', $barangays[$i]['barangay_name'], 'active']);
        }
    }
    $barangayGsos = rows('SELECT barangay_gso_id,user_id,barangay_id FROM barangay_gso ORDER BY barangay_gso_id LIMIT 2');

    // Ensure two contractors.
    $contractors = rows('SELECT contractor_id,user_id FROM contractor ORDER BY contractor_id LIMIT 2');
    for ($i = 0; $i < 2; $i++) {
        $username = $i === 0 ? 'cd_demo_contractor' : 'cd_demo_contractor_2';
        $uid = $ensureUser($username, 'contractor');
        $profile = one('SELECT contractor_id FROM contractor WHERE user_id=?', [$uid]);
        if (!$profile) {
            change('INSERT INTO contractor(user_id,gso_id,company_name,business_license_number,contact_no,email,address,status) VALUES(?,?,?,?,?,?,?,?)', [$uid, $gsos[$i]['gso_id'], 'CleanWay Services '.($i+1), 'LIC-DEMO-'.($i+1), '0917300000'.($i+1), 'contractor'.($i+1).'@example.test', 'Iloilo City', 'active']);
        }
    }
    $contractors = rows('SELECT contractor_id,user_id,gso_id FROM contractor ORDER BY contractor_id LIMIT 2');

    // Ensure two collectors.
    $collectors = rows('SELECT collector_id,user_id,contractor_id FROM collector ORDER BY collector_id LIMIT 2');
    for ($i = 0; $i < 2; $i++) {
        $username = $i === 0 ? 'cd_demo_collector' : 'cd_demo_collector_2';
        $uid = $ensureUser($username, 'collector');
        $profile = one('SELECT collector_id FROM collector WHERE user_id=?', [$uid]);
        if (!$profile) {
            change('INSERT INTO collector(contractor_id,user_id,first_name,last_name,contact_no,email,license_no,employment_status,date_joined) VALUES(?,?,?,?,?,?,?,?,?)', [$contractors[$i]['contractor_id'], $uid, ['Juan','Rosa'][$i], ['Dela Cruz','Santos'][$i], '0917400000'.($i+1), 'collector'.($i+1).'@example.test', 'LIC-COL-'.($i+1), 'active', date('Y-m-d')]);
        }
    }
    $collectors = rows('SELECT collector_id,user_id,contractor_id FROM collector ORDER BY collector_id LIMIT 2');

    // Ensure two resident accounts.
    $residents = rows('SELECT resident_id,user_id,barangay_id FROM resident ORDER BY resident_id LIMIT 2');
    for ($i = 0; $i < 2; $i++) {
        $username = $i === 0 ? 'cd_demo_resident' : 'cd_demo_resident_2';
        $uid = $ensureUser($username, 'resident');
        $profile = one('SELECT resident_id FROM resident WHERE user_id=?', [$uid]);
        if (!$profile) {
            change('INSERT INTO resident(user_id,barangay_id,first_name,last_name,birth_date,gender,contact_no,email,house_no,street) VALUES(?,?,?,?,?,?,?,?,?,?)', [$uid, $barangays[$i]['barangay_id'], ['Maria','Ana'][$i], ['Santos','Reyes'][$i], '2000-0'.($i+1).'-1'.($i+1), $i === 0 ? 'Female' : 'Female', '0917500000'.($i+1), 'resident'.($i+1).'@example.test', (string)(10+$i), 'Main Street']);
        }
    }
    $residents = rows('SELECT resident_id,user_id,barangay_id FROM resident ORDER BY resident_id LIMIT 2');

    // Ensure two vehicles.
    $vehicleCount = (int) one('SELECT COUNT(*) AS n FROM vehicle')['n'];
    for ($i = $vehicleCount; $i < 2; $i++) {
        change('INSERT INTO vehicle(contractor_id,plate_number,capacity,vehicle_type,status) VALUES(?,?,?,?,?)', [$contractors[$i]['contractor_id'], 'CD-DEMO-0'.($i+1), 8.00+$i, 'Garbage Truck', 'active']);
    }
    $vehicles = rows('SELECT vehicle_id,contractor_id FROM vehicle ORDER BY vehicle_id LIMIT 2');

    // Ensure two routes and two stops total.
    $routeCount = (int) one('SELECT COUNT(*) AS n FROM route')['n'];
    $routes=[];
    for ($i = $routeCount; $i < 2; $i++) {
        change('INSERT INTO route(gso_id,route_Name,total_Distance,estimated_Duration,status) VALUES(?,?,?,?,?)', [$gsos[$i]['gso_id'], 'Collection Route '.($i+1), 6.50+$i, 90+$i*15, 'active']);
    }
    $routes = rows('SELECT route_id,gso_id FROM route ORDER BY route_id LIMIT 2');

    $stopCount = (int) one('SELECT COUNT(*) AS n FROM route_stop')['n'];
    $stops=[];
    for ($i = $stopCount; $i < 2; $i++) {
        change('INSERT INTO route_stop(route_id,barangay_id,stop_order,scheduled_time,latitude,longitude,estimated_arrival_time,status) VALUES(?,?,?,?,?,?,?,?)', [$routes[$i]['route_id'], $barangays[$i]['barangay_id'], 1, '07:30:00', 10.7200000+$i, 122.5600000+$i, '07:30:00', 'active']);
    }
    $stops = rows('SELECT route_stop_id,route_id FROM route_stop ORDER BY route_stop_id LIMIT 2');

    // Ensure two schedules and two schedule days.
    $scheduleCount = (int) one('SELECT COUNT(*) AS n FROM collection_schedule')['n'];
    $schedules=[];
    for ($i = $scheduleCount; $i < 2; $i++) {
        change('INSERT INTO collection_schedule(route_id,effective_from,effective_until,status) VALUES(?,?,?,?)', [$routes[$i]['route_id'], date('Y-m-01'), date('Y-m-t'), 'active']);
    }
    $schedules = rows('SELECT collection_schedule_id,route_id FROM collection_schedule ORDER BY collection_schedule_id LIMIT 2');

    $dayCount = (int) one('SELECT COUNT(*) AS n FROM schedule_day')['n'];
    $days=[];
    for ($i = $dayCount; $i < 2; $i++) {
        change('INSERT INTO schedule_day(collection_schedule_id,day_of_week) VALUES(?,?)', [$schedules[$i]['collection_schedule_id'], ['Monday','Tuesday'][$i]]);
    }
    $days = rows('SELECT schedule_day_id,collection_schedule_id FROM schedule_day ORDER BY schedule_day_id LIMIT 2');

    // Ensure two assignments.
    $assignmentCount = (int) one('SELECT COUNT(*) AS n FROM collection_assignment')['n'];
    $assignments=[];
    for ($i = $assignmentCount; $i < 2; $i++) {
        change('INSERT INTO collection_assignment(collection_schedule_id,collector_id,vehicle_id,assigned_date,status) VALUES(?,?,?,?,?)', [$schedules[$i]['collection_schedule_id'], $collectors[$i]['collector_id'], $vehicles[$i]['vehicle_id'], date('Y-m-d'), 'active']);
    }
    $assignments = rows('SELECT collection_assignment_id,collector_id,vehicle_id FROM collection_assignment ORDER BY collection_assignment_id LIMIT 2');

    // Ensure two collection runs and two collection statuses.
    $runCount = (int) one('SELECT COUNT(*) AS n FROM collection_run')['n'];
    $runs=[];
    for ($i = $runCount; $i < 2; $i++) {
        change('INSERT INTO collection_run(schedule_day_id,collection_assignment_id,collection_date,started_at,status) VALUES(?,?,?,?,?)', [$days[$i]['schedule_day_id'], $assignments[$i]['collection_assignment_id'], date('Y-m-d'), date('Y-m-d H:i:s'), $i === 0 ? 'In Progress' : 'Completed']);
    }
    $runs = rows('SELECT run_id FROM collection_run ORDER BY run_id LIMIT 2');

    $statusCount = (int) one('SELECT COUNT(*) AS n FROM collection_status')['n'];
    for ($i = $statusCount; $i < 2; $i++) {
        change('INSERT INTO collection_status(run_id,route_stop_id,final_status,actual_arrival_time,actual_departure_time,remarks,updated_at) VALUES(?,?,?,?,?,?,NOW())', [$runs[$i]['run_id'], $stops[$i]['route_stop_id'], $i === 0 ? 'In Progress' : 'Completed', date('Y-m-d H:i:s'), $i === 1 ? date('Y-m-d H:i:s') : null, 'Demo collection status '.$i+1]);
    }

    // Ensure two vehicle locations.
    $locationCount = (int) one('SELECT COUNT(*) AS n FROM vehicle_location')['n'];
    for ($i = $locationCount; $i < 2; $i++) {
        change('INSERT INTO vehicle_location(collection_assignment_id,latitude,longitude,speed,heading,recorded_at) VALUES(?,?,?,?,?,NOW())', [$assignments[$i]['collection_assignment_id'], 10.7200000+$i, 122.5600000+$i, 18.00+$i*5, 90.00+$i*45]);
    }

    // Ensure two complaints and two resolutions.
    $complaintCount = (int) one('SELECT COUNT(*) AS n FROM complaint')['n'];
    $complaints=[];
    for ($i = $complaintCount; $i < 2; $i++) {
        change('INSERT INTO complaint(resident_id,barangay_gso_id,title,description,complaint_date,status) VALUES(?,?,?,?,NOW(),?)', [$residents[$i]['resident_id'], $barangayGsos[$i]['barangay_gso_id'], 'Collection Complaint '.($i+1), 'Demo complaint description '.($i+1), $i === 0 ? 'pending' : 'resolved']);
    }
    $complaints = rows('SELECT complaint_id FROM complaint ORDER BY complaint_id LIMIT 2');

    $resolutionCount = (int) one('SELECT COUNT(*) AS n FROM complaint_resolution')['n'];
    for ($i = $resolutionCount; $i < 2; $i++) {
        change('INSERT INTO complaint_resolution(complaint_id,barangay_gso_id,resolved_date,remarks) VALUES(?,?,NOW(),?)', [$complaints[$i]['complaint_id'], $barangayGsos[$i]['barangay_gso_id'], 'Demo resolution remarks '.($i+1)]);
    }

    // Ensure two documentation records.
    $docCount = (int) one('SELECT COUNT(*) AS n FROM documentation')['n'];
    for ($i = $docCount; $i < 2; $i++) {
        change('INSERT INTO documentation(barangay_gso_id,before_photo,after_photo,remarks,uploaded_date) VALUES(?,?,?,?,NOW())', [$barangayGsos[$i]['barangay_gso_id'], 'uploads/demo-before-'.($i+1).'.jpg', 'uploads/demo-after-'.($i+1).'.jpg', 'Demo documentation '.($i+1)]);
    }

    // Ensure two marketplace items and two messages.
    $itemCount = (int) one('SELECT COUNT(*) AS n FROM marketplace_item')['n'];
    $items=[];
    for ($i = $itemCount; $i < 2; $i++) {
        change('INSERT INTO marketplace_item(resident_id,item_name,description,category,price,quantity,address,status,posted_date) VALUES(?,?,?,?,?,?,?,?,NOW())', [$residents[$i]['resident_id'], ['Recyclable Plastic','Used Cardboard'][$i], 'Demo marketplace listing '.$i+1, ['Plastic','Paper'][$i], 50.00+$i*25, 10+$i, $barangays[$i]['barangay_name'], 'available']);
    }
    $items = rows('SELECT item_id,resident_id FROM marketplace_item ORDER BY item_id LIMIT 2');

    $messageCount = (int) one('SELECT COUNT(*) AS n FROM message')['n'];
    for ($i = $messageCount; $i < 2; $i++) {
        $sender = $residents[($i+1)%2]['resident_id'];
        $receiver = $items[$i]['resident_id'];
        if ($sender === $receiver) $receiver = $residents[$i]['resident_id'];
        change('INSERT INTO message(sender_id,receiver_id,item_id,message,status,sent_at) VALUES(?,?,?,?,?,NOW())', [$sender, $receiver, $items[$i]['item_id'], 'Demo message '.$i+1, 'sent']);
    }

    $soldCount = (int) one('SELECT COUNT(*) AS n FROM sold_item')['n'];
    for ($i = $soldCount; $i < 2; $i++) {
        change('INSERT INTO sold_item(item_id,sold_date) VALUES(?,NOW())', [$items[$i]['item_id']]);
    }

    // Ensure two notifications and recipients.
    $notificationCount = (int) one('SELECT COUNT(*) AS n FROM notification')['n'];
    $notifications=[];
    for ($i = $notificationCount; $i < 2; $i++) {
        change('INSERT INTO notification(title,message,type,date_created) VALUES(?,?,?,NOW())', ['CleanDrive Notice '.($i+1), 'Demo notification '.$i+1, 'demo']);
    }
    $notifications = rows('SELECT notification_id FROM notification ORDER BY notification_id LIMIT 2');

    $recipientCount = (int) one('SELECT COUNT(*) AS n FROM notification_recipient')['n'];
    for ($i = $recipientCount; $i < 2; $i++) {
        change('INSERT INTO notification_recipient(notification_id,user_id,is_read,read_at,date_created) VALUES(?,?,?,?,NOW())', [$notifications[$i]['notification_id'], $i === 0 ? $residents[0]['user_id'] : $collectors[0]['user_id'], 0, null]);
    }

    $pdo->commit();
    echo "Two-record demo data seeded for operational tables.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Seed failed: '.$e->getMessage()."\n");
    exit(1);
}
