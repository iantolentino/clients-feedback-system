<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
startAppSession();
if (empty($_SESSION['admin_id'])) redirect(str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/') ? '../login.php' : 'login.php');
$account = db()->prepare('SELECT id, full_name, role, is_active FROM sales_admins WHERE id = ? LIMIT 1');
$account->execute([(int)$_SESSION['admin_id']]);
$currentAdmin = $account->fetch();
if (!$currentAdmin || !(int)$currentAdmin['is_active']) {
    $_SESSION = [];
    session_destroy();
    redirect(str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/') ? '../login.php' : 'login.php');
}
