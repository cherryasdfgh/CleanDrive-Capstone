<?php
require_once __DIR__.'/includes/auth.php';

// Redirect an already-signed-in account to the correct role portal.
if (current_user()) {
    $redirect = [
        'super_admin' => 'super-admin/dashboard/',
        'barangay_gso' => 'barangay/dashboard/',
        'gso' => 'gso/prototype/',
        'contractor' => 'contractor/dashboard/',
        'collector' => 'collector/dashboard/',
        'resident' => 'resident/dashboard/'
    ];
    $role = current_user()['role'];
    if (isset($redirect[$role])) {
        header('Location: '.root_url().$redirect[$role]);
        exit;
    }
}

$error = '';
$success = '';

// Get only barangays that exist in the database for the resident sign-up dropdown.
try {
    $barangays = rows('SELECT b.barangay_id,b.barangay_name,c.city_name FROM barangay b JOIN city c ON c.city_id=b.city_id ORDER BY c.city_name,b.barangay_name');
} catch (Throwable $e) {
    $barangays = [];
    $error = 'Database unavailable. Check your config and imported schema.';
}

$signupMode = (($_GET['mode'] ?? '') === 'signup') || (($_POST['form_action'] ?? '') === 'signup');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Resident self-registration from the Sign Up form.
        if (($_POST['form_action'] ?? '') === 'signup') {
            verify_csrf();

            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
            $barangayId = (int) ($_POST['barangay_id'] ?? 0);
            $firstName = trim((string) ($_POST['first_name'] ?? ''));
            $lastName = trim((string) ($_POST['last_name'] ?? ''));
            $contactNo = trim((string) ($_POST['contact_no'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $houseNo = trim((string) ($_POST['house_no'] ?? ''));
            $street = trim((string) ($_POST['street'] ?? ''));

            if ($username === '' || $password === '' || $barangayId <= 0 || $firstName === '' || $lastName === '') {
                throw new InvalidArgumentException('Please complete all required fields.');
            }
            if (strlen($username) > 100) {
                throw new InvalidArgumentException('Username is too long.');
            }
            if (strlen($password) < 10) {
                throw new InvalidArgumentException('Password must have at least 10 characters.');
            }
            if (!hash_equals($password, $confirmPassword)) {
                throw new InvalidArgumentException('Passwords do not match.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Enter a valid email address.');
            }
            if (one('SELECT user_id FROM `user` WHERE username=?', [$username])) {
                throw new InvalidArgumentException('That username is already registered.');
            }
            if (!one('SELECT barangay_id FROM barangay WHERE barangay_id=?', [$barangayId])) {
                throw new InvalidArgumentException('Please choose a valid barangay.');
            }

            $pdo = db();
            $pdo->beginTransaction();
            try {
                // Resident login record.
                change('INSERT INTO `user` (username,password_hash,role,status) VALUES (?,?,?,?)', [
                    $username,
                    password_hash($password, PASSWORD_DEFAULT),
                    'resident',
                    'active'
                ]);
                $userId = (int) $pdo->lastInsertId();

                // Resident profile linked to the selected database barangay.
                change('INSERT INTO resident (user_id,barangay_id,first_name,last_name,contact_no,email,house_no,street) VALUES (?,?,?,?,?,?,?,?)', [
                    $userId,
                    $barangayId,
                    $firstName,
                    $lastName,
                    $contactNo,
                    $email,
                    $houseNo,
                    $street
                ]);

                $pdo->commit();
                $success = 'Resident account created successfully. You can now sign in.';
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        } else {
            // Existing login form handling.
            $u = one('SELECT user_id,username,password_hash,role,status FROM `user` WHERE username=?', [(string) ($_POST['username'] ?? '')]);
            if (!$u || !password_verify((string) ($_POST['password'] ?? ''), $u['password_hash']) || strtolower($u['status']) !== 'active') {
                $error = 'Incorrect credentials or inactive account.';
            } elseif (!in_array($u['role'], ['super_admin', 'barangay_gso', 'gso', 'contractor', 'collector', 'resident'], true)) {
                $error = 'This role is not available in this prototype yet.';
            } else {
                session_regenerate_id(true);
                $_SESSION['cd_user'] = ['id' => (int) $u['user_id'], 'username' => $u['username'], 'role' => $u['role']];
                change('UPDATE `user` SET last_login=NOW() WHERE user_id=?', [$u['user_id']]);

                $redirect = [
                    'super_admin' => 'super-admin/dashboard/',
                    'barangay_gso' => 'barangay/dashboard/',
                    'gso' => 'gso/prototype/',
                    'contractor' => 'contractor/dashboard/',
                    'collector' => 'collector/dashboard/',
                    'resident' => 'resident/dashboard/'
                ];
                header('Location: '.root_url().$redirect[$u['role']]);
                exit;
            }
        }
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = 'Registration or login could not be completed. Please try again.';
    } catch (Throwable $e) {
        $error = 'Database unavailable. Check your config and imported schema.';
    }
}

$csrf = csrf();
$signupMode = (($_GET['mode'] ?? '') === 'signup') || (($_POST['form_action'] ?? '') === 'signup');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CleanDrive | Sign in</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
.auth-actions{display:grid;grid-template-columns:1fr;gap:10px;margin-top:18px}.auth-actions .btn,.auth-actions a.btn{width:100%;margin:0!important}.auth-card{width:min(760px,92vw)!important}.signup-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.signup-only{display:none}.auth-card.signup-mode{width:min(760px,92vw)!important}.auth-card.signup-mode .signup-only{display:block}.auth-card.signup-mode .signup-grid{display:grid}.signup-grid{gap:12px}.signup-grid .wide{grid-column:1/-1}@media(max-width:520px){.signup-grid{grid-template-columns:1fr}.signup-grid .wide{grid-column:auto}}
.auth-actions a.btn{text-decoration:none;text-align:center;display:flex;align-items:center;justify-content:center}
</style>
</head>
<body style="display:grid;place-items:center;min-height:100vh;padding:24px 0">
<form id="auth-form" method="post" class="panel auth-card <?= $signupMode ? 'signup-mode' : '' ?>" style="padding:36px">
    <div class="brand"><span class="logo">♻</span><div><b>Clean<span>Drive</span></b><small>SECURE PORTAL</small></div></div>

    <?php if ($error): ?><p class="tip" role="alert"><?=htmlspecialchars($error)?></p><?php endif; ?>
    <?php if ($success): ?><p class="tip" role="status"><?=htmlspecialchars($success)?></p><?php endif; ?>

    <input type="hidden" name="form_action" id="form_action" value="<?= $signupMode ? 'signup' : 'login' ?>">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">

    <div id="login-fields" style="<?= $signupMode ? 'display:none' : '' ?>">
        <h1>Welcome back</h1>
        <p class="muted">Sign in using your CleanDrive account.</p>
        <div class="field"><label>Username</label><input name="username" autocomplete="username" required></div>
        <div class="field space"><label>Password</label><input name="password" type="password" autocomplete="current-password" required></div>

        <div class="auth-actions">
            <button type="submit" class="btn primary" id="login-button">Login</button>
            <a class="btn" href="login.php?mode=signup">Sign Up as a Resident</a>
        </div>
    </div>

    <div id="signup-fields" class="signup-only" style="<?= $signupMode ? '' : 'display:none' ?>">
        <h1>Create resident account</h1>
        <p class="muted">Choose the barangay where you belong.</p>
        <div class="signup-grid">
            <div class="field"><label>First Name *</label><input name="first_name" maxlength="100"></div>
            <div class="field"><label>Last Name *</label><input name="last_name" maxlength="100"></div>
            <div class="field wide"><label>Barangay *</label><select name="barangay_id"><option value="">Select barangay</option><?php foreach ($barangays as $barangay): ?><option value="<?= (int) $barangay['barangay_id'] ?>"><?= htmlspecialchars($barangay['barangay_name'].' — '.$barangay['city_name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Contact Number</label><input name="contact_no" maxlength="30"></div>
            <div class="field"><label>Email</label><input name="email" type="email" maxlength="190"></div>
            <div class="field"><label>House Number</label><input name="house_no" maxlength="50"></div>
            <div class="field"><label>Street</label><input name="street" maxlength="200"></div>
            <div class="field"><label>Username *</label><input name="signup_username" maxlength="100" autocomplete="username"></div>
            <div class="field"><label>Password * (10+ characters)</label><input name="signup_password" type="password" minlength="10" autocomplete="new-password"></div>
            <div class="field wide"><label>Confirm Password *</label><input name="confirm_password" type="password" minlength="10" autocomplete="new-password"></div>
        </div>
        <div class="auth-actions">
            <button type="submit" class="btn primary">Create Resident Account</button>
            <a class="btn" href="login.php">Back to Login</a>
        </div>
    </div>

</form>
<script>
const authForm=document.querySelector('#auth-form'), modeInput=document.querySelector('#form_action');

// Copy sign-up values into the PHP login field names when submitting registration.
authForm.onsubmit=()=>{
  if(modeInput.value==='signup'){
    const signupUsername=authForm.querySelector('[name=signup_username]');
    const signupPassword=authForm.querySelector('[name=signup_password]');
    let username=authForm.querySelector('[name=username]');
    let password=authForm.querySelector('[name=password]');
    if(!username){username=document.createElement('input');username.type='hidden';username.name='username';authForm.appendChild(username)}
    if(!password){password=document.createElement('input');password.type='hidden';password.name='password';authForm.appendChild(password)}
    username.value=signupUsername.value;
    password.value=signupPassword.value;
  }
};
</script>
</body>
</html>
