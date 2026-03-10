<?php
define('PV_APP', true);
require_once __DIR__ . '/lib/pv_core.php';

pv_session_boot();
unset($_SESSION['pv_user']);
header('Location: /login.php');
exit;
