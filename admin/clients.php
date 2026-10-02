<?php
declare(strict_types=1);
require_once __DIR__.'/../auth.php';
if ($currentAdmin['role'] !== 'admin') { http_response_code(403); exit('Access denied.'); }
$pdo = db();
$message = '';
$error = '';
$link = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrf('clients_csrf', $_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please reload and try again.';
    } elseif (($_POST['action'] ?? '') === 'add') {
        $clientName = trim((string)($_POST['client_name'] ?? ''));
        $company = trim((string)($_POST['company'] ?? ''));
        $email = trim((string)($_POST['contact_email'] ?? ''));
        if ($clientName === '' || mb_strlen($clientName) > 190 || mb_strlen($company) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $error = 'Enter a client name and valid email.';
        } else {
            $pdo->prepare('INSERT INTO clients (client_name, company_name, contact_email) VALUES (?, ?, ?)')
                ->execute([$clientName, $company, $email]);
            $message = 'Client added.';
        }
    } elseif (($_POST['action'] ?? '') === 'generate') {
        $clientId = (int)($_POST['client_id'] ?? 0);
        $check = $pdo->prepare('SELECT id FROM clients WHERE id = ?');
        $check->execute([$clientId]);
        if (!$check->fetchColumn()) {
            $error = 'Client not found.';
        } else {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("INSERT INTO feedback_forms (client_id, token, status, sent_by_id) VALUES (?, ?, 'pending', ?)")
                ->execute([$clientId, $token, (int)$currentAdmin['id']]);
            $base = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
                .($_SERVER['HTTP_HOST'] ?? 'localhost').dirname(dirname($_SERVER['SCRIPT_NAME'])).'/feedback.php';
            $link = $base.'?token='.$token;
        }
    } else {
        $error = 'Invalid action.';
    }
}
$clients = $pdo->query('SELECT id, client_name, company_name, contact_email FROM clients ORDER BY client_name')->fetchAll();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Clients | Client Health Tracker</title><link rel="icon" href="../assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="../assets/style.css"></head>
<body><header class="topbar"><a class="brand" href="dashboard.php"><img class="brand-logo" src="../assets/stratastaff-logo.png" alt="Strata Staff Global"></a><nav><a href="dashboard.php">Dashboard</a><a href="../settings.php">Settings</a><a href="../logout.php">Log out</a></nav></header>
<main class="container"><p class="eyebrow">Directory</p><h1>Manage clients</h1>
<?php if ($message): ?><div class="alert success" role="status"><?=e($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="alert danger" role="alert"><?=e($error)?></div><?php endif; ?>
<?php if ($link): ?><div class="alert success">Feedback link: <input readonly value="<?=e($link)?>" onclick="this.select()"></div><?php endif; ?>
<section class="card"><h2>Add client</h2><form class="grid-form" method="post"><input type="hidden" name="action" value="add"><input type="hidden" name="csrf" value="<?=e(csrfToken('clients_csrf'))?>">
<label>Client name<input name="client_name" maxlength="190" required></label><label>Company<input name="company" maxlength="190"></label><label>Contact email<input type="email" name="contact_email" maxlength="190" required></label><button class="button primary" type="submit">Add client</button></form></section>
<section class="card"><h2>Existing clients</h2><div class="table-wrap"><table><thead><tr><th>Name</th><th>Company</th><th>Email</th><th></th></tr></thead><tbody>
<?php foreach ($clients as $client): ?><tr><td><?=e($client['client_name'])?></td><td><?=e($client['company_name'])?></td><td><?=e($client['contact_email'])?></td><td><form method="post"><input type="hidden" name="action" value="generate"><input type="hidden" name="client_id" value="<?=(int)$client['id']?>"><input type="hidden" name="csrf" value="<?=e(csrfToken('clients_csrf'))?>"><button class="button small" type="submit">Generate feedback link</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></section></main></body></html>
