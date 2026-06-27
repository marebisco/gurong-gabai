<?php
require_once 'config/session.php';
if (isset($_SESSION['teacher_id'])) {
    header("Location: " . ($_SESSION['role']==='admin'
        ? "/gurong-gabai/modules/admin/dashboard.php"
        : "/gurong-gabai/modules/dashboard/index.php"));
    exit();
}
header("Location: /gurong-gabai/modules/auth/login.php");
exit();
?>