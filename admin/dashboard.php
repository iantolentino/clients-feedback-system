<?php
declare(strict_types=1);
require_once __DIR__.'/../auth.php';
$pdo = db();
$name = trim((string)($_GET['client'] ?? ''));
$rating = (int)($_GET['rating'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$scoreExpression = 'COALESCE((s.relationship_score + s.business_needs_score + s.communication_support_score + s.concerns_comfort_score + s.partnership_confidence_score) / 5, s.relationship_score)';
$where = [];
$params = [];
if ($name !== '') { $where[] = 'c.client_name LIKE ?'; $params[] = '%'.$name.'%'; }
if ($rating >= 1 && $rating <= 5) { $where[] = 'ROUND('.$scoreExpression.', 0) = ?'; $params[] = $rating; }
$condition = $where ? ' WHERE '.implode(' AND ', $where) : '';
$count = $pdo->prepare('SELECT COUNT(*) FROM feedback_submissions s JOIN clients c ON c.id=s.client_id'.$condition);
$count->execute($params);
$totalRows = (int)$count->fetchColumn();
$pages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $pages);
$sql = 'SELECT s.id, c.client_name, s.poc_name, s.staff_name, s.relationship_score,
        s.business_needs_score, s.communication_support_score, s.concerns_comfort_score,
        s.partnership_confidence_score, f.submitted_at, s.created_at, '.$scoreExpression.' AS average_score
        FROM feedback_submissions s JOIN clients c ON c.id=s.client_id JOIN feedback_forms f ON f.id=s.form_id'
        .$condition.' ORDER BY s.created_at DESC, s.id DESC LIMIT '.$perPage.' OFFSET '.(($page-1)*$perPage);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$totalClients = (int)$pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
$totalSubmissions = (int)$pdo->query('SELECT COUNT(*) FROM feedback_submissions')->fetchColumn();
$average = $pdo->query('SELECT AVG('.$scoreExpression.') FROM feedback_submissions s')->fetchColumn();
$risk = (int)$pdo->query('SELECT COUNT(*) FROM feedback_submissions s
    WHERE s.id = (SELECT s2.id FROM feedback_submissions s2 WHERE s2.client_id = s.client_id ORDER BY s2.created_at DESC, s2.id DESC LIMIT 1)
    AND '.$scoreExpression.' <= 2')->fetchColumn();
function scoreClass(float $score): string { return $score >= 4 ? 'good' : ($score >= 3 ? 'warn' : 'bad'); }
function needsReview(array $row): bool {
    foreach (['relationship_score','business_needs_score','communication_support_score','concerns_comfort_score','partnership_confidence_score'] as $field) {
        if ($row[$field] !== null && (int)$row[$field] <= 2) return true;
    }
    return false;
}
function pageUrl(int $number): string { $query = $_GET; $query['page'] = $number; return '?'.http_build_query($query); }
function displayDate(?string $value): string { return $value ? date('M j, Y', strtotime($value)) : '—'; }
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard | Client Health Tracker</title><link rel="icon" href="../assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="../assets/style.css?v=20261002-2"></head>
<body><header class="topbar"><a class="brand" href="dashboard.php"><img class="brand-logo" src="../assets/stratastaff-logo.png" alt="Strata Staff Global"></a><nav><a href="../settings.php">Settings</a><a href="../logout.php">Log out</a></nav></header>
<main class="container dashboard-page">
<div class="dashboard-heading"><div><h1>Feedback dashboard</h1></div><div class="dashboard-heading-actions"><a class="button dashboard-export-link" href="export-feedback.php">Export all to Excel</a><a class="button dashboard-form-link" href="../feedback.php">View feedback form <span aria-hidden="true">↗</span></a></div></div>
<div class="dashboard-metrics">
  <div class="card dashboard-metric"><span class="metric-label">Total responses</span><strong><?=e((string)$totalSubmissions)?></strong><small>Feedback received</small></div>
  <div class="card dashboard-metric"><span class="metric-label">Average rating</span><strong><?=e($average === null ? '—' : number_format((float)$average, 1))?><span class="metric-unit"> / 5</span></strong><small>Five answers per response</small></div>
  <div class="card dashboard-metric"><span class="metric-label">Clients</span><strong><?=e((string)$totalClients)?></strong><small>In your directory</small></div>
  <div class="card dashboard-metric"><span class="metric-label">At risk</span><strong><?=e((string)$risk)?></strong><small>Latest average of 2 or below</small></div>
</div>
<section class="card dashboard-list" aria-labelledby="submissions-heading">
  <div class="dashboard-list-heading"><div><h2 id="submissions-heading">Recent feedback</h2><p class="muted"><?=e((string)$totalRows)?> <?=($name !== '' || $rating) ? 'matching' : 'total'?> <?=($totalRows === 1 ? 'response' : 'responses')?></p></div></div>
  <form class="dashboard-filters" method="get">
    <label class="search-field"><span class="sr-only">Search client</span><input name="client" type="search" placeholder="Search by client name" value="<?=e($name)?>"></label>
    <label class="rating-field"><span class="sr-only">Rounded average rating</span><select name="rating"><option value="0">All averages</option><?php for ($i=5;$i>=1;$i--): ?><option value="<?=$i?>" <?=$rating===$i?'selected':''?>>About <?=$i?> / 5</option><?php endfor; ?></select></label>
    <button class="button primary" type="submit">Apply filters</button>
    <?php if ($name !== '' || $rating): ?><a class="clear-filters" href="dashboard.php">Clear</a><?php endif; ?>
  </form>
  <?php if ($rows): ?>
  <div class="table-wrap"><table class="dashboard-table"><thead><tr><th>Client</th><th>POC</th><th>Average rating</th><th>Submitted</th><th><span class="sr-only">Open</span></th></tr></thead><tbody>
  <?php foreach ($rows as $row): ?><tr>
    <td data-label="Client"><strong><?=e($row['client_name'])?></strong></td>
    <td data-label="POC"><?=e($row['poc_name'] ?: ($row['staff_name'] ?: '—'))?></td>
    <td data-label="Average rating"><span class="badge <?=scoreClass((float)$row['average_score'])?>"><?=e(number_format((float)$row['average_score'], 1))?> / 5</span><?php if (needsReview($row)): ?><span class="review-tag" title="At least one rating is 1 or 2">Review</span><?php endif; ?></td>
    <td data-label="Submitted"><?=e(displayDate($row['submitted_at'] ?: $row['created_at']))?></td>
    <td class="row-action"><a href="view-submission.php?id=<?=(int)$row['id']?>" aria-label="View feedback from <?=e($row['client_name'])?>">View <span aria-hidden="true">→</span></a></td>
  </tr><?php endforeach; ?></tbody></table></div>
  <?php else: ?><div class="dashboard-empty"><strong>No feedback found</strong><p class="muted"><?=($name !== '' || $rating) ? 'Try another search or clear the filters.' : 'Responses will appear here when clients submit the form.'?></p></div><?php endif; ?>
  <?php if ($pages > 1): ?><div class="pagination"><span class="muted">Page <?=$page?> of <?=$pages?></span><div><?php if ($page > 1): ?><a class="button small" href="<?=e(pageUrl($page-1))?>">Previous</a><?php endif; ?><?php if ($page < $pages): ?><a class="button small" href="<?=e(pageUrl($page+1))?>">Next</a><?php endif; ?></div></div><?php endif; ?>
</section></main></body></html>
