<?php
// modules/export/pdf.php — GG-028: Export Lesson Plan as PDF
require_once '../../config/session.php';
require_once '../../config/db.php';
require_once '../../config/gemini.php'; // FIX (June 2026): kailangan para sa getSectionLabels()
requireLogin();
$tid = $_SESSION['teacher_id'];
$id  = (int)($_GET['id'] ?? 0);

$lp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM lesson_plans WHERE id='$id' AND teacher_id='$tid'"));

// FIX: Dating ang UPDATE export_count ay tumatakbo BAGO pa man
// macheck kung totoong umiiral ang lesson plan ($lp) — kahit walang
// nahanap na record, tumatakbo pa rin ang query (walang epekto
// dahil walang row na tutugma, pero maling pagkasunod-sunod ito).
// Inilipat ang UPDATE pagkatapos ng existence AND is_saved check.
if (!$lp) {
    header("Location: ../library/index.php");
    exit();
}

// FIX: Bagong check — backend-level na proteksyon (hindi lang UI)
// laban sa pag-export ng lesson plan na hindi pa na-save. Kahit
// diretso pumunta ang isang user sa export URL gamit ang valid na
// ID (halimbawa, sa pamamagitan ng direktang pag-type sa browser),
// hindi pa rin papayagan ang export kung is_saved = 0 — ito ang
// "defense in depth" approach, kapareho ng security philosophy na
// gamit sa ibang parte ng system (hindi lang nag-asa sa pag-disable
// ng button sa frontend).
if ((int)($lp['is_saved'] ?? 0) !== 1) {
    header("Location: ../generator/index.php?export_error=not_saved");
    exit();
}

// Track export count — dito na lang, pagkatapos kumpirmang totoong
// umiiral at naka-save ang lesson plan
mysqli_query($conn, "UPDATE lesson_plans SET export_count = export_count + 1 WHERE id='$id'");

// FIX (June 2026): dati, hardcoded generic labels ang ginamit dito
// ("I. Learning Objectives", atbp.) kaya kahit ILAW o 4A's na lesson
// plan ang i-export, lumalabas pa rin ang generic DLP-style labels —
// hindi tugma sa nakikita ng teacher sa Generator/View page.
// AYOS: kunin ang format na talagang ginamit sa lesson plan na ito
// (mula sa 'format' column), tapos tawagin ang getSectionLabels()
// mula sa config/gemini.php — ito ang EXACT na parehong function na
// ginagamit ng Generator page, kaya guaranteed na magkatugma na ang
// label dito sa export at sa nakikita sa system.
$format = $lp['format'] ?? 'DLP';
$sections = [];
foreach (getSectionLabels($format) as $key => [$ico, $label, $desc]) {
    $sections[$key] = [$ico, $label];
}

$title    = htmlspecialchars($lp['title']);
$grade    = htmlspecialchars($lp['grade_level']);
$subject  = htmlspecialchars($lp['subject']);
$topic    = htmlspecialchars($lp['topic'] ?? '');
$duration = htmlspecialchars($lp['duration'] ?? '45 minutes');
$strategy = htmlspecialchars($lp['strategy'] ?? 'Discussion-Based');
$date     = date('F d, Y', strtotime($lp['updated_at']));
$teacher  = htmlspecialchars($_SESSION['teacher_name']);
$filename = preg_replace('/[^a-z0-9]+/i', '_', $lp['title']) . '.pdf';

// Build HTML for PDF
$html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 11pt; color: #1E293B; margin: 0; padding: 0; }
  .header { background: #1C3557; color: white; padding: 20px 28px; }
  .header h1 { margin: 0 0 4px; font-size: 16pt; }
  .header p  { margin: 0; font-size: 10pt; opacity: 0.85; }
  .meta { background: #F0F4F8; padding: 12px 28px; border-bottom: 2px solid #1C3557; display: flex; flex-wrap: wrap; gap: 8px; }
  .meta-item { font-size: 10pt; color: #1E293B; margin-right: 20px; }
  .meta-item strong { color: #1C3557; }
  .body { padding: 20px 28px; }
  .section { margin-bottom: 18px; border: 1px solid #E2E8F0; border-radius: 6px; overflow: hidden; page-break-inside: avoid; }
  .section-head { background: #1C3557; color: white; padding: 8px 14px; font-size: 10pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
  .section-body { padding: 12px 14px; font-size: 10pt; line-height: 1.7; white-space: pre-wrap; }
  .disclaimer { background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 5px; padding: 8px 12px; font-size: 9pt; color: #92400E; margin-bottom: 16px; }
  .footer { margin-top: 24px; padding-top: 12px; border-top: 1px solid #E2E8F0; font-size: 9pt; color: #94A3B8; text-align: center; }
</style>
</head><body>
<div class="header">
  <h1>🎓 Gurong GabAI — Lesson Plan</h1>
  <p>AI-Powered Lesson Plan Generator | DepEd Matatag Curriculum</p>
</div>
<div class="meta">
  <div class="meta-item"><strong>Teacher:</strong> ' . $teacher . '</div>
  <div class="meta-item"><strong>Grade Level:</strong> ' . $grade . '</div>
  <div class="meta-item"><strong>Subject:</strong> ' . $subject . '</div>
  <div class="meta-item"><strong>Topic:</strong> ' . $topic . '</div>
  <div class="meta-item"><strong>Duration:</strong> ' . $duration . '</div>
  <div class="meta-item"><strong>Strategy:</strong> ' . $strategy . '</div>
  <div class="meta-item"><strong>Date:</strong> ' . $date . '</div>
  <div class="meta-item"><strong>Curriculum:</strong> ' . htmlspecialchars($lp['curriculum'] ?? 'MATATAG') . '</div>
  <div class="meta-item"><strong>Format:</strong> ' . htmlspecialchars($format) . '</div>
</div>
<div class="body">
  <div class="disclaimer">⚠️ AI-generated content is for reference only. Always review before using in the classroom.</div>';

foreach ($sections as $key => [$ico, $label]) {
    $content = htmlspecialchars($lp[$key] ?? '');
    $html .= '<div class="section">
      <div class="section-head">' . $label . '</div>
      <div class="section-body">' . nl2br($content) . '</div>
    </div>';
}

$html .= '<div class="footer">Generated by Gurong GabAI | AI-generated content is for reference only. Always review before using in the classroom.</div>
</div></body></html>';

// Output as PDF using browser print
header('Content-Type: text/html; charset=UTF-8');
echo $html;
echo '<script>window.onload = function() { window.print(); }</script>';
?>