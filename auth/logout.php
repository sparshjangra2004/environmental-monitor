<?php
require_once __DIR__ . '/../includes/session.php';

session_destroy();

header("Location: /environmental_monitor/auth/login.php");
exit();
?>