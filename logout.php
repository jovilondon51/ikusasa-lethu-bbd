<?php
require_once __DIR__ . '/includes/security.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') failRequest(405, 'Please use the Logout button.');
destroyLogin();
header('Location: /index.php');
exit;
