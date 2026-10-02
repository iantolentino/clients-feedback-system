<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
startAppSession();
if (empty($_SESSION['public_feedback_csrf'])) $_SESSION['public_feedback_csrf'] = bin2hex(random_bytes(32));

$ratings = [
    'relationship_score' => 'Overall, how would you rate your experience with Strata Staff?',
    'business_needs_score' => 'How well do you feel Strata Staff understands your business needs and expectations?',
    'communication_support_score' => 'How satisfied are you with the level of communication and support you receive from your Strata Staff?',
    'concerns_comfort_score' => 'Do you feel comfortable raising concerns, questions, or feedback with Strata Staff?',
    'partnership_confidence_score' => 'How confident are you in continuing your partnership with Strata Staff?',
];
$error = '';
$submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientName = trim((string)($_POST['client_name'] ?? ''));
    $pocName = trim((string)($_POST['poc_name'] ?? ''));
    $improvement = trim((string)($_POST['improvement_feedback'] ?? ''));
    $relationship = trim((string)($_POST['relationship_description'] ?? ''));
    if (!validCsrf('public_feedback_csrf', $_POST['csrf'] ?? null)) $error = 'Your session expired. Please reload the form.';
    elseif ($clientName === '' || $pocName === '') $error = 'Please enter the client and POC names.';
    elseif (mb_strlen($clientName) > 190 || mb_strlen($pocName) > 190) $error = 'Names must be 190 characters or fewer.';
    else foreach ($ratings as $field => $question) {
        if (!in_array((string)($_POST[$field] ?? ''), ['1','2','3','4','5'], true)) {
            $error = 'Please answer all five rating questions.';
            break;
        }
    }
    if (!$error && ($improvement === '' || $relationship === '')) $error = 'Please answer both written questions.';
    if (!$error && (mb_strlen($improvement) > 10000 || mb_strlen($relationship) > 10000)) $error = 'Written answers must be 10,000 characters or fewer.';
    if (!$error) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $find = $pdo->prepare('SELECT id FROM clients WHERE client_name = ? LIMIT 1');
            $find->execute([$clientName]);
            $clientId = $find->fetchColumn();
            if (!$clientId) {
                $add = $pdo->prepare("INSERT INTO clients (client_name, company_name) VALUES (?, 'Public feedback submission')");
                $add->execute([$clientName]);
                $clientId = (int)$pdo->lastInsertId();
            }
            $form = $pdo->prepare("INSERT INTO feedback_forms (client_id, token, status, meeting_date) VALUES (?, ?, 'pending', CURDATE())");
            $form->execute([$clientId, bin2hex(random_bytes(32))]);
            $formId = (int)$pdo->lastInsertId();
            $insert = $pdo->prepare('INSERT INTO feedback_submissions
                (client_id, form_id, relationship_score, respondent_client_name, poc_name,
                 business_needs_score, communication_support_score, concerns_comfort_score,
                 partnership_confidence_score, improvement_feedback, relationship_description, submitted_ip)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([
                $clientId, $formId, (int)$_POST['relationship_score'], $clientName, $pocName,
                (int)$_POST['business_needs_score'], (int)$_POST['communication_support_score'],
                (int)$_POST['concerns_comfort_score'], (int)$_POST['partnership_confidence_score'],
                $improvement, $relationship, $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            $pdo->prepare("UPDATE feedback_forms SET status = 'submitted', submitted_at = NOW() WHERE id = ?")->execute([$formId]);
            $pdo->commit();
            $submitted = true;
            $_SESSION['public_feedback_csrf'] = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log((string)$e);
            $error = 'We could not save your feedback. Please try again.';
        }
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Client feedback | Strata Staff Global</title><link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/style.css?v=20261002-7"></head><body>
<main class="auth-shell"><section class="card feedback-card">
<img class="site-logo" src="assets/stratastaff-logo.png" alt="Strata Staff Global">
<?php if ($submitted): ?>
<div class="thank-you"><p class="eyebrow">Feedback received</p><h1>Thank you for your feedback.</h1>
<p class="muted">Your responses help us serve you better.</p><a class="button primary" href="feedback.php">Submit another response</a></div>
<?php else: ?>
<div class="form-intro"><h1>Tell us about your experience</h1>
<p class="muted">Your feedback helps us support your team better.</p></div>
<div class="sticky-progress" aria-live="polite">
<div class="form-progress"><span id="step-label">Page 1 of 2</span><span id="progress-label">0 of 7 questions answered</span></div>
<div class="progress-track"><span id="progress-fill"></span></div>
</div>
<?php if ($error): ?><div class="alert danger" role="alert"><?=e($error)?></div><?php endif; ?>
<form method="post" id="feedback-form">
<input type="hidden" name="csrf" value="<?=e($_SESSION['public_feedback_csrf'])?>">
<div class="form-step active" data-step="1">
<div class="identity-grid">
<label>Client name <span class="required">*</span><input name="client_name" maxlength="190" required autocomplete="organization" value="<?=e((string)($_POST['client_name']??''))?>"></label>
<label>POC Name <span class="required">*</span><input name="poc_name" maxlength="190" required autocomplete="name" value="<?=e((string)($_POST['poc_name']??''))?>"></label>
</div>
<div class="section-heading rating-heading"><h2>Rate your Experience <span class="rating-legend">(5 is the highest · 1 is the lowest)</span></h2></div>
<?php foreach ($ratings as $field => $question): ?>
<fieldset class="question"><legend><?=e($question)?> <span class="required">*</span></legend>
<div class="score-options" role="group" aria-label="<?=e($question)?>">
<?php for ($i=5; $i>=1; $i--): ?><label class="score-option"><input type="radio" name="<?=e($field)?>" value="<?=$i?>" <?=((string)($_POST[$field]??''))===(string)$i?'checked':''?> required><span><?=$i?></span></label><?php endfor; ?>
</div></fieldset>
<?php endforeach; ?>
<div class="step-actions"><button class="button primary next-button" type="button">Continue</button></div>
</div>
<div class="form-step" data-step="2">
<div class="section-heading"><h2>Share your thoughts</h2><p class="muted">A few words are enough.</p></div>
<label class="text-question">Is there anything Strata Staff could improve to better support you or your team? <span class="required">*</span>
<textarea name="improvement_feedback" rows="5" maxlength="10000" required><?=e((string)($_POST['improvement_feedback']??''))?></textarea></label>
<label class="text-question">At this point, how would you describe your relationship with Strata Staff? <span class="required">*</span>
<textarea name="relationship_description" rows="5" maxlength="10000" required><?=e((string)($_POST['relationship_description']??''))?></textarea></label>
<div class="step-actions"><button class="button secondary back-button" type="button">Back</button><button class="button primary" type="submit">Submit feedback</button></div>
</div></form>
<script>
const form=document.getElementById('feedback-form'),steps=[...document.querySelectorAll('.form-step')];
let currentStep=<?=($error && isset($_POST['improvement_feedback']) ? '2' : '1')?>;
function update(){
  steps.forEach((step,i)=>step.classList.toggle('active',i===currentStep-1));
  document.getElementById('step-label').textContent='Page '+currentStep+' of 2';
  const fields=['relationship_score','business_needs_score','communication_support_score','concerns_comfort_score','partnership_confidence_score','improvement_feedback','relationship_description'];
  const answered=fields.filter(name=>{const field=form.elements[name];return field instanceof RadioNodeList?Boolean(field.value):Boolean(field.value.trim())}).length;
  document.getElementById('progress-label').textContent=answered+' of 7 questions answered';
  document.getElementById('progress-fill').style.width=(answered/7*100)+'%';
}
function firstInvalid(step){return [...step.querySelectorAll('input,textarea')].find(el=>!el.checkValidity())}
document.querySelector('.next-button').addEventListener('click',()=>{const invalid=firstInvalid(steps[0]);if(invalid){invalid.reportValidity();return}currentStep=2;update();window.scrollTo({top:0,behavior:'smooth'})});
document.querySelector('.back-button').addEventListener('click',()=>{currentStep=1;update();window.scrollTo({top:0,behavior:'smooth'})});
form.addEventListener('submit',event=>{const invalid=firstInvalid(steps[1]);if(invalid){event.preventDefault();invalid.reportValidity()}});
form.addEventListener('input',update);form.addEventListener('change',update);update();
</script>
<?php endif; ?></section></main></body></html>
