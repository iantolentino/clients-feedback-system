<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';
$pdo = db();
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if (!validCsrf('settings_csrf', $_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please reload and try again.';
    } elseif ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $q = $pdo->prepare('SELECT password_hash FROM sales_admins WHERE id = ?');
        $q->execute([(int)$currentAdmin['id']]);
        $admin = $q->fetch();
        if (!$admin || !password_verify($current, $admin['password_hash'])) $error = 'Current password is incorrect.';
        elseif (strlen($new) < 12) $error = 'New password must be at least 12 characters.';
        elseif ($new !== $confirm) $error = 'New passwords do not match.';
        else {
            $pdo->prepare('UPDATE sales_admins SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), (int)$currentAdmin['id']]);
            $message = 'Password updated successfully.';
        }
    } elseif ($action === 'account') {
        if ($currentAdmin['role'] !== 'admin') {
            http_response_code(403);
            $error = 'Only administrators can register accounts.';
        } else {
            $name = trim((string)($_POST['full_name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = (string)($_POST['role'] ?? '');
            if ($name === '' || mb_strlen($name) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)
                || strlen($email) > 190 || strlen($password) < 12 || !in_array($role, ['sales','admin'], true)) {
                $error = 'Enter a name, valid email, role, and password of at least 12 characters.';
            } else {
                try {
                    $pdo->prepare('INSERT INTO sales_admins (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)')
                        ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
                    $message = 'Account created successfully.';
                } catch (PDOException $e) {
                    $error = 'That email may already be registered.';
                }
            }
        }
    } else {
        $error = 'Invalid action.';
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Settings | Client Health Tracker</title><link rel="icon" href="assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="assets/style.css"></head><body>
<header class="topbar"><a class="brand" href="admin/dashboard.php"><img class="brand-logo" src="assets/stratastaff-logo.png" alt="Strata Staff Global"></a><nav><a href="admin/dashboard.php">Dashboard</a><a href="logout.php">Log out</a></nav></header>
<main class="container narrow"><p class="eyebrow">Administration</p><h1>Settings</h1>
<?php if ($message): ?><div class="alert success" role="status"><?=e($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="alert danger" role="alert"><?=e($error)?></div><?php endif; ?>
<section class="card"><h2>Change your password</h2><form method="post"><input type="hidden" name="action" value="password"><input type="hidden" name="csrf" value="<?=e(csrfToken('settings_csrf'))?>">
<label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
<label>New password<input type="password" name="new_password" minlength="12" required autocomplete="new-password"></label>
<label>Confirm new password<input type="password" name="confirm_password" minlength="12" required autocomplete="new-password"></label>
<button class="button primary" type="submit">Update password</button></form></section>
<?php if ($currentAdmin['role'] === 'admin'): ?>
<section class="card"><h2>Register an account</h2><form method="post"><input type="hidden" name="action" value="account"><input type="hidden" name="csrf" value="<?=e(csrfToken('settings_csrf'))?>">
<label>Full name<input name="full_name" maxlength="150" required autocomplete="name"></label>
<label>Email<input type="email" name="email" maxlength="190" required autocomplete="email"></label>
<label>Temporary password<input type="password" name="password" minlength="12" required autocomplete="new-password"></label>
<label>Role<select name="role"><option value="sales">Sales</option><option value="admin">Admin</option></select></label>
<button class="button secondary" type="submit">Create account</button></form></section>
<?php endif; ?></main></body></html>
