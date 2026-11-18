<?php
require_once __DIR__.'/includes/auth.php';

if(!current_user()){header('Location: '.root_url().'login.php');exit;}

$redirect=[
    'super_admin'=>'super-admin/dashboard/',
    'barangay_gso'=>'barangay/dashboard/',
    'gso'=>'gso/prototype/',
    'contractor'=>'contractor/dashboard/',
    'collector'=>'collector/dashboard/',
    'resident'=>'resident/dashboard/'
];

$role=current_user()['role'];
header('Location: '.root_url().($redirect[$role]??'login.php'));
exit;
