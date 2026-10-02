<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
startAppSession();
if (!empty($_SESSION['admin_id'])) redirect('admin/dashboard.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'since' => time()];
    if (time() - (int)$attempts['since'] > 900) $attempts = ['count' => 0, 'since' => time()];
    if (!validCsrf('login_csrf', $_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please reload and try again.';
    } elseif ($attempts['count'] >= 5) {
        $error = 'Too many sign-in attempts. Please try again in 15 minutes.';
    } else {
        $q = db()->prepare('SELECT id, full_name, password_hash, is_active FROM sales_admins WHERE email = ? LIMIT 1');
        $q->execute([$email]);
        $admin = $q->fetch();
        if ($admin && (int)$admin['is_active'] === 1 && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            unset($_SESSION['login_attempts'], $_SESSION['login_csrf']);
            redirect('admin/dashboard.php');
        }
        $attempts['count']++;
        $_SESSION['login_attempts'] = $attempts;
        $error = 'The email or password is incorrect.';
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in | Client Health Tracker</title><link rel="icon" href="assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="assets/style.css"></head><body><main class="auth-shell"><section class="card auth-card"><img class="site-logo" src="assets/stratastaff-logo.png" alt="Strata Staff Global"><p class="eyebrow">Strata Staff Global</p><h1>Client Health Tracker</h1><p class="muted">Sign in to manage client feedback.</p><?php if($error):?><div class="alert danger" role="alert"><?=e($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrfToken('login_csrf'))?>"><label>Email<input type="email" name="email" required autocomplete="username"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button class="button primary" type="submit">Sign in</button></form></section></main></body></html>
