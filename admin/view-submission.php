<?php
declare(strict_types=1);
require_once __DIR__.'/../auth.php';
$query=db()->prepare('SELECT s.*, f.meeting_date, f.submitted_at, c.client_name, c.company_name, c.contact_email FROM feedback_submissions s JOIN feedback_forms f ON f.id=s.form_id JOIN clients c ON c.id=s.client_id WHERE s.id=?');
$query->execute([(int)($_GET['id']??0)]);
$row=$query->fetch();
if (!$row) redirect('dashboard.php');
$ratings=[
 'relationship_score'=>'Overall, how would you rate your experience with Strata Staff?',
 'business_needs_score'=>'How well do you feel Strata Staff understands your business needs and expectations?',
 'communication_support_score'=>'How satisfied are you with the level of communication and support you receive from your Strata Staff?',
 'concerns_comfort_score'=>'Do you feel comfortable raising concerns, questions, or feedback with Strata Staff?',
 'partnership_confidence_score'=>'How confident are you in continuing your partnership with Strata Staff?',
];
$legacy=json_decode((string)($row['additional_responses']??''),true)?:[];
$hasFiveRatings = $row['business_needs_score'] !== null && $row['communication_support_score'] !== null
    && $row['concerns_comfort_score'] !== null && $row['partnership_confidence_score'] !== null;
$averageRating = $hasFiveRatings ? (
    (int)$row['relationship_score'] + (int)$row['business_needs_score']
    + (int)$row['communication_support_score'] + (int)$row['concerns_comfort_score']
    + (int)$row['partnership_confidence_score']
) / 5 : null;
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Submission | Client Health Tracker</title><link rel="icon" href="../assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="../assets/style.css?v=20261002-2"></head>
<body><header class="topbar"><a class="brand" href="dashboard.php"><img class="brand-logo" src="../assets/stratastaff-logo.png" alt="Strata Staff Global"></a><nav><a href="../settings.php">Settings</a><a href="../logout.php">Log out</a></nav></header>
<main class="container submission-container"><div class="detail-toolbar"><a href="dashboard.php">← Back to dashboard</a><button class="button secondary" type="button" onclick="openSaveCopy()">Save a copy</button></div>
<section class="card detail"><p class="eyebrow">Feedback submission</p><h1><?=e($row['client_name'])?></h1>
<div class="detail-grid"><div><span class="muted">POC Name</span><strong><?=e($row['poc_name'] ?: ($row['staff_name'] ?: 'Not provided'))?></strong></div><div><span class="muted">Submitted</span><strong><?=e($row['submitted_at']??$row['created_at'])?></strong></div><?php if ($averageRating !== null): ?><div><span class="muted">Average of five ratings</span><strong><?=e(number_format($averageRating, 1))?> / 5</strong></div><?php endif; ?></div>
<h2>Ratings</h2><div class="response-list">
<?php foreach ($ratings as $field=>$question): if ($field!=='relationship_score' && $row[$field]===null) continue; ?>
<div><span><?=e($question)?></span><strong><?=e((string)$row[$field])?> / 5</strong></div>
<?php endforeach; ?></div>
<?php if ($row['improvement_feedback']!==null || $row['relationship_description']!==null): ?>
<h2>Written feedback</h2><div class="written-response"><h3>Is there anything Strata Staff could improve to better support you or your team?</h3><p><?=nl2br(e($row['improvement_feedback']))?></p></div>
<div class="written-response"><h3>At this point, how would you describe your relationship with Strata Staff?</h3><p><?=nl2br(e($row['relationship_description']))?></p></div>
<?php else: ?>
<h2>Previous form responses</h2>
<?php foreach (['meeting_notes'=>'Meeting notes','action_items'=>'Concerns','client_comments'=>'Your Comments'] as $field=>$label): ?><div class="written-response"><h3><?=e($label)?></h3><p><?=nl2br(e($row[$field]))?></p></div><?php endforeach; ?>
<?php if ($legacy): ?><div class="response-list"><?php foreach ($legacy as $key=>$value): ?><div><span><?=e(ucwords(str_replace('_',' ',$key)))?></span><strong><?=e((string)$value)?></strong></div><?php endforeach; ?></div><?php endif; ?>
<?php endif; ?>
</section></main>
<dialog class="save-copy-dialog" id="save-copy-dialog" aria-labelledby="save-copy-title">
  <div class="save-copy-heading"><h2 id="save-copy-title">Save a copy</h2><button type="button" class="dialog-close" aria-label="Close" onclick="closeSaveCopy()">×</button></div>
  <p class="muted">Choose how you want to keep this feedback.</p>
  <div class="save-copy-options">
    <button class="save-copy-option" type="button" onclick="saveFeedbackImage()"><strong>Save as Image</strong><span>Download a PNG of this response.</span></button>
    <button class="save-copy-option" type="button" onclick="saveFeedbackPdf()"><strong>Save as PDF</strong><span>Download a paginated PDF, including long answers.</span></button>
  </div>
</dialog>
<script src="../assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="../assets/vendor/pdfmake/vfs_fonts.js"></script>
<script src="../assets/export.js?v=20261002-3"></script></body></html>
