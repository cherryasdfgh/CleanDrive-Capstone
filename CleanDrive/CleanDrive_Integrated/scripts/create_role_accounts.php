<?php
// Create one demonstration login and profile for each CleanDrive role.
// Safe to run repeatedly: existing usernames are updated instead of duplicated.
require_once __DIR__.'/../includes/db.php';

$password = 'CleanDriveDemo!2026';
$pdo = db();

// Find one existing GSO and one existing barangay for profile links.
$gso = one('SELECT gso_id FROM gso ORDER BY gso_id LIMIT 1');
$barangay = one('SELECT barangay_id FROM barangay ORDER BY barangay_id LIMIT 1');

if (!$gso || !$barangay) {
    fwrite(STDERR, "Create at least one GSO and one barangay before running this script.\n");
    exit(1);
}

$pdo->beginTransaction();

try {
    // Create or update a login record and return its user_id.
    $ensureUser = function (string $username, string $role) use (&$pdo, $password): int {
        $existing = one('SELECT user_id FROM `user` WHERE username=?', [$username]);
        $hash = password_hash($password, PASSWORD_DEFAULT);

        if ($existing) {
            $uid = (int) $existing['user_id'];
            change('UPDATE `user` SET password_hash=?,role=?,status=? WHERE user_id=?', [$hash, $role, 'active', $uid]);
            return $uid;
        }

        change('INSERT INTO `user` (username,password_hash,role,status) VALUES (?,?,?,?)', [$username, $hash, $role, 'active']);
        return (int) $pdo->lastInsertId();
    };

    // Barangay GSO profile.
    $barangayUid = $ensureUser('cd_demo_barangay', 'barangay_gso');
    $barangayProfile = one('SELECT barangay_gso_id FROM barangay_gso WHERE user_id=?', [$barangayUid]);
    if ($barangayProfile) {
        change('UPDATE barangay_gso SET barangay_id=?,contact_name=?,contact_no=?,email=?,address=?,status=? WHERE user_id=?', [$barangay['barangay_id'], 'Demo Barangay GSO', '09172222222', 'barangay@example.test', 'Aguinaldo', 'active', $barangayUid]);
    } else {
        change('INSERT INTO barangay_gso (user_id,barangay_id,contact_name,contact_no,email,address,status) VALUES (?,?,?,?,?,?,?)', [$barangayUid, $barangay['barangay_id'], 'Demo Barangay GSO', '09172222222', 'barangay@example.test', 'Aguinaldo', 'active']);
    }

    // Contractor profile.
    $contractorUid = $ensureUser('cd_demo_contractor', 'contractor');
    $contractorProfile = one('SELECT contractor_id FROM contractor WHERE user_id=?', [$contractorUid]);
    if ($contractorProfile) {
        change('UPDATE contractor SET gso_id=?,company_name=?,business_license_number=?,contact_no=?,email=?,address=?,status=? WHERE user_id=?', [$gso['gso_id'], 'Demo Waste Contractor', 'DEMO-LIC-001', '09174444444', 'contractor@example.test', 'Iloilo City', 'active', $contractorUid]);
        $contractorId = (int) $contractorProfile['contractor_id'];
    } else {
        change('INSERT INTO contractor (user_id,gso_id,company_name,business_license_number,contact_no,email,address,status) VALUES (?,?,?,?,?,?,?,?)', [$contractorUid, $gso['gso_id'], 'Demo Waste Contractor', 'DEMO-LIC-001', '09174444444', 'contractor@example.test', 'Iloilo City', 'active']);
        $contractorId = (int) $pdo->lastInsertId();
    }

    // Collector profile and linked login.
    $collectorUid = $ensureUser('cd_demo_collector', 'collector');
    $collectorProfile = one('SELECT collector_id FROM collector WHERE user_id=?', [$collectorUid]);
    if ($collectorProfile) {
        change('UPDATE collector SET contractor_id=?,first_name=?,last_name=?,contact_no=?,email=?,license_no=?,employment_status=?,date_joined=? WHERE user_id=?', [$contractorId, 'Pedro', 'Reyes', '09175555555', 'collector@example.test', 'DEMO-LICENSE', 'active', date('Y-m-d'), $collectorUid]);
    } else {
        change('INSERT INTO collector (contractor_id,user_id,first_name,last_name,contact_no,email,license_no,employment_status,date_joined) VALUES (?,?,?,?,?,?,?,?,?)', [$contractorId, $collectorUid, 'Pedro', 'Reyes', '09175555555', 'collector@example.test', 'DEMO-LICENSE', 'active', date('Y-m-d')]);
    }

    // Resident profile and linked login.
    $residentUid = $ensureUser('cd_demo_resident', 'resident');
    $residentProfile = one('SELECT resident_id FROM resident WHERE user_id=?', [$residentUid]);
    if ($residentProfile) {
        change('UPDATE resident SET barangay_id=?,first_name=?,last_name=?,contact_no=?,email=?,house_no=?,street=? WHERE user_id=?', [$barangay['barangay_id'], 'Maria', 'Santos', '09173333333', 'resident@example.test', '10', 'Main Street', $residentUid]);
    } else {
        change('INSERT INTO resident (user_id,barangay_id,first_name,last_name,contact_no,email,house_no,street) VALUES (?,?,?,?,?,?,?,?)', [$residentUid, $barangay['barangay_id'], 'Maria', 'Santos', '09173333333', 'resident@example.test', '10', 'Main Street']);
    }

    $pdo->commit();
    echo "Role accounts are ready.\n";
    echo "Demo password for all accounts: {$password}\n";
    echo "cd_demo_barangay (Barangay GSO)\n";
    echo "cd_demo_contractor (Contractor)\n";
    echo "cd_demo_collector (Collector)\n";
    echo "cd_demo_resident (Resident)\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Failed: '.$e->getMessage()."\n");
    exit(1);
}
