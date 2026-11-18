<?php
// Require an authenticated GSO account before loading the frontend prototype.
require_once __DIR__.'/../../includes/auth.php';
require_login('gso');

// This frontend still uses browser localStorage until MySQL actions are added.
require __DIR__.'/index.html';
