<?php
// Load the shared database connection and prepared SQL helper functions.
require_once __DIR__.'/../includes/db.php';

// Local demonstration credentials. Change these values before deploying to a real system.
$username = 'cd_demo_admin';
$password = 'CleanDriveDemo!2026';

// Reuse one PDO connection throughout this script.
$pdo = db();

// Start a transaction so the login record and Super Admin profile are created together.
$pdo->beginTransaction();

try {
    // Look for an existing login record so the script can be run repeatedly.
    $existing = one('SELECT user_id FROM `user` WHERE username=?', [$username]);

    if ($existing) {
        // Reset the existing account to the configured demo credentials and role.
        $uid = (int) $existing['user_id'];
        change('UPDATE `user` SET password_hash=?, role=?, status=? WHERE user_id=?', [
            password_hash($password, PASSWORD_DEFAULT), 'super_admin', 'active', $uid
        ]);
    } else {
        // Create the login record first. Authentication uses password_hash(), not the legacy password field.
        change('INSERT INTO `user` (username,password_hash,role,status) VALUES (?,?,?,?)', [
            $username, password_hash($password, PASSWORD_DEFAULT), 'super_admin', 'active'
        ]);

        // PDO provides the new user_id so it can be linked to the profile table.
        $uid = (int) $pdo->lastInsertId();
    }

    // A Super Admin login must also have a matching row in super_admin.
    $profile = one('SELECT super_admin_id FROM super_admin WHERE user_id=?', [$uid]);
    if ($profile) {
        // Synchronize profile fields if the account already has a profile.
        change('UPDATE super_admin SET contact_name=?,contact_no=?,email=?,status=? WHERE user_id=?', [
            'Demo Super Admin', '09170000000', 'admin@example.test', 'active', $uid
        ]);
    } else {
        // Create the linked Super Admin profile.
        change('INSERT INTO super_admin (user_id,contact_name,contact_no,email,status) VALUES (?,?,?,?,?)', [
            $uid, 'Demo Super Admin', '09170000000', 'admin@example.test', 'active'
        ]);
    }

    // Commit only after both database records are successfully saved.
    $pdo->commit();
    echo "Super Admin ready: {$username} / {$password}\n";
} catch (Throwable $e) {
    // Undo all changes if an insert or update fails.
    $pdo->rollBack();
    fwrite(STDERR, 'Failed: '.$e->getMessage()."\n");
    exit(1);
}
