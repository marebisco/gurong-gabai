<?php
// ============================================================
// FILE: modules/generator/index.php
// PURPOSE: Ito ang AI Lesson Plan Generator page — dito pinipili ng
//          teacher ang grade, subject, topic, curriculum, academic
//          calendar, lesson plan format, duration, at teaching
//          strategy, tapos ipinapakita ang AI-generated na resulta
//          bilang editable na lesson plan.
//
// ARCHITECTURE NOTE: Apat na hiwalay na dropdown/dimensyon:
//   1. Curriculum         — MATATAG (K-10) o K-12 SHS (11-12)
//   2. Academic Calendar  — Four-Quarter o Three-Term (ILAW calendar)
//   3. Lesson Plan Format — ILAW, DLP, 4A's, 5E's, Traditional, Semi, DLL
//   4. Teaching Strategy  — Discussion-Based, Activity-Based, atbp.
// Dati, pinagsama ang #1 at #2 sa isang dropdown — nadoble ang "ILAW"
// (lumalabas bilang curriculum AT format). Ngayon, malinaw na hiwalay.
// ============================================================

require_once '../../config/session.php';
require_once '../../config/db.php';
require_once '../../config/gemini.php';

// Pinapahaba ang max execution time dahil ang AI generation (kasama
// ang multi-model fallback chain) ay puwedeng tumagal ng hanggang ~90 segundo
set_time_limit(180);
ini_set('max_execution_time', 180);

if (!isset($_SESSION['teacher_id'])) {
    header('Location: ../../modules/auth/login.php'); exit;
}

$teacher_id   = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'];
$active_nav   = 'generator';

$grade_levels = ['Kinder','Grade 1','Grade 2','Grade 3','Grade 4','Grade 5',
                 'Grade 6','Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12'];

// Listahan ng subjects per grade level — ginagamit para i-populate
// ang Subject dropdown depende sa pinili na Grade
$subjects_map = [
    'Kinder'   => ['Mother Tongue','Mathematics','Makabansa','MAPEH'],
    'Grade 1'  => ['Filipino','English','Mathematics','Mother Tongue','Araling Panlipunan','Science','MAPEH','ESP'],
    'Grade 2'  => ['Filipino','English','Mathematics','Science','Araling Panlipunan','MAPEH','ESP'],
    'Grade 3'  => ['Filipino','English','Mathematics','Science','Araling Panlipunan','MAPEH','ESP'],
    'Grade 4'  => ['Filipino','English','Mathematics','Science','Araling Panlipunan','MAPEH','ESP','TLE'],
    'Grade 5'  => ['Filipino','English','Mathematics','Science','Araling Panlipunan','MAPEH','ESP','TLE'],
    'Grade 6'  => ['Filipino','English','Mathematics','Science','Araling Panlipunan','MAPEH','ESP','TLE'],
    'Grade 7'  => ['Filipino','English','Mathematics','Science','Araling Panlipunan','MAPEH','ESP','TLE'],
    'Grade 8'  => ['Filipino','English','Mathematics','Science','Araling Panlipunan','MAPEH','ESP','TLE'],
    'Grade 9'  => ['Filipino','English','Mathematics','Science','Araling Panlipunan','MAPEH','ESP','TLE'],
    'Grade 10' => ['Filipino','English','Mathematics','Science','Araling Panlipunan','MAPEH','ESP','TLE'],
    'Grade 11' => ['Oral Communication','Reading and Writing','Earth and Life Science','General Mathematics',
                   'Understanding Culture Society and Politics','Physical Education and Health',
                   'Contemporary Philippine Arts','Media and Information Literacy'],
    'Grade 12' => ['English for Academic and Professional Purposes','Practical Research 1',
                   'Practical Research 2','Filipino sa Piling Larang','Pre-Calculus',
                   'Basic Calculus','Physical Education and Health'],
];

// Default values ng form — gagamitin kapag fresh load (walang POST pa)
$generated       = false;
$plan_data       = [];
$gen_error       = '';
$sel_grade       = '';
$sel_subject     = '';
$sel_topic       = '';
$sel_format      = 'ILAW';       // Default: ILAW lesson plan format
$sel_curr        = 'MATATAG';    // Default: MATATAG curriculum (K-10)
$sel_calendar    = 'ThreeTerm';  // Default: Three-Term calendar (DO 9, s. 2026)
$sel_dur         = '45 minutes';
$sel_strat       = 'Discussion-Based';
// bagong variable — kapag ang Regenerate ay galing sa Library/
// View page (regenFromView form sa view.php), kasama dito ang ID ng
// ORIHINAL na lesson plan. Ipapasa ito papuntang #save-form bilang
// existing_id, para kung i-save pagkatapos i-regenerate, ang save.php
// ay mag-UPDATE sa ORIHINAL na record imbes na gumawa ng bago.
$sel_existing_id = 0;

// ── PROCESS FORM — kapag pinindot ang "Generate Lesson Plan" ──────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['generate'])) {
    $sel_grade       = trim($_POST['grade']      ?? '');
    $sel_subject     = trim($_POST['subject']    ?? '');
    $sel_topic       = trim($_POST['topic']      ?? '');
    $sel_format      = trim($_POST['format']     ?? 'ILAW');
    $sel_curr        = trim($_POST['curriculum'] ?? 'MATATAG');
    $sel_calendar    = trim($_POST['calendar']   ?? 'ThreeTerm');
    $sel_dur         = trim($_POST['duration']   ?? '45 minutes');
    $sel_strat       = trim($_POST['strategy']   ?? 'Discussion-Based');
    // kunin ang existing_id kung ipinasa (galing sa Regenerate
    // button ng view.php) — ito ang ORIHINAL na lesson plan na dapat
    // ma-UPDATE sa Save, hindi gawan ng bagong record.
    $sel_existing_id = (int)($_POST['existing_id'] ?? 0);
    // Bagong flag — totoo ito kapag galing sa Regenerate button
    // (hidden #regen-form), para masabi sa generateLessonPlan() na
    // gumawa ng tunay na ibang version, hindi lang ulit-ulitin ang
    // parehong prompt at umasa lang sa randomness ng AI.
    $isRegenerate = !empty($_POST['is_regenerate']);

    if (!$sel_grade || !$sel_subject || !$sel_topic) {
        $gen_error = 'Please fill in Grade Level, Subject, and Lesson Topic.';
    } else {
        // Tawagin ang AI generator — ito ang tumatawag sa buildPrompt()
        // at callAI() (na may multi-model fallback) sa loob ng gemini.php
        $result = generateLessonPlan(
            $sel_grade, $sel_subject, $sel_topic,
            $sel_dur, $sel_strat, $sel_curr, $sel_calendar, $sel_format,
            $isRegenerate
        );

        if ($result) {
            $generated = true;
            $plan_data = $result;

            // ── I-LOG SA lesson_plan_history ANG "generated" ACTION ──
            // Ang lesson_plan_history table ay activity log (parang
            // version history) — itinatala dito ang bawat generation,
            // kahit hindi pa na-save sa Library. Sa puntong ito (bagong
            // generate, hindi pa na-save), NULL muna ang lesson_plan_id
            // dahil walang row pa sa lesson_plans — mapupunan ito kapag
            // na-save (tingnan ang save.php para sa "saved" action log).
            try {
                $stmt = $conn->prepare(
                    'INSERT INTO lesson_plan_history
                     (teacher_id, lesson_plan_id, action, curriculum, format, strategy, created_at)
                     VALUES (?,NULL,?,?,?,?,NOW())'
                );
                if ($stmt) {
                    $action = 'generated';
                    // 5 placeholders (?): teacher_id, action, curriculum,
                    // format, strategy — "i" para integer, "ssss" para
                    // sa apat na string fields
                    $stmt->bind_param('issss',
                        $teacher_id, $action, $sel_curr, $sel_format, $sel_strat
                    );
                    $stmt->execute();
                }
            } catch (Exception $e) {
                error_log('[GabAI] lesson_plan_history insert failed: ' . $e->getMessage());
            }
        } else {
            // Lumabas lang ito kung NABIGO ang LAHAT ng 5 AI models
            $gen_error = 'AI generation failed. Possible reasons:<br>
                &bull; No internet connection or server timeout<br>
                &bull; API key out of credits (all backup models also failed)<br>
                &bull; Try again in a moment — the system retries up to 5 AI models.<br><br>
                If the issue persists, contact your administrator.';
        }
    }
}

// Kunin ang mga dropdown options mula sa gemini.php
$formats      = getLessonPlanFormats();    // 7 formats, kasama ang ILAW
$curricula    = getAvailableCurricula();   // MATATAG, K-12 — totoong curricula lang
$calendars    = getAcademicCalendars();    // Four-Quarter, Three-Term — hiwalay sa curriculum
$section_info = getSectionLabels($sel_format ?: 'ILAW');

// Helper: ligtas na i-convert ang AI output papuntang readable text.
// Tumatawag lang ito sa humanizeValue() mula sa gemini.php — ginawa
// itong wrapper function dito para consistent ang behavior, hindi
// duplicate na logic.
function safeStr($val): string {
    return humanizeValue($val);
}

$page_title = 'AI Lesson Plan Generator — Gurong GabAI';
include '../../includes/header.php';
?>

<style>
/* ── Generator Split Layout ─────────────────────────────── */
.gen-split {
  display: grid;
  grid-template-columns: 370px 1fr;
  gap: 20px;
  align-items: start;
}
.gen-left-panel { position: sticky; top: 20px; }
.gen-right-panel { min-width: 0; }

/* ILAW badge */
.ilaw-badge {
  display: inline-block;
  background: linear-gradient(135deg, #7c3aed, #4f46e5);
  color: #fff; font-size: 9px; font-weight: 800;
  letter-spacing: 1px; text-transform: uppercase;
  padding: 2px 7px; border-radius: 20px; vertical-align: middle;
  margin-left: 4px;
}

/* Format note box */
.format-note-box {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 7px;
  padding: 8px 11px;
  font-size: 11px;
  color: #1e40af;
  margin-top: 5px;
  line-height: 1.5;
  display: none;
}
.format-note-box.active { display: block; }

/* ILAW highlight */
.ilaw-note-box {
  background: linear-gradient(135deg, #f5f3ff, #ede9fe);
  border: 1px solid #c4b5fd;
  border-radius: 7px;
  padding: 9px 12px;
  font-size: 11px;
  color: #4c1d95;
  margin-top: 5px;
  line-height: 1.6;
  display: none;
}
.ilaw-note-box.active { display: block; }

/* LP Document */
.lp-document {
  background: var(--color-background-primary);
  border: 1px solid var(--color-border-secondary);
  border-radius: 12px; overflow: hidden; margin-bottom: 14px;
}
.lp-doc-header {
  background: linear-gradient(135deg, #060c18, #0f1e3d);
  color: white; text-align: center; padding: 20px 16px 16px; line-height: 1.6;
}
.lp-doc-header p { font-size: 11px; opacity: 0.75; margin: 0; }
.lp-doc-header strong { font-size: 12px; opacity: 0.9; }
.lp-doc-header h2 {
  font-size: 15px; font-weight: 700; margin: 12px 0 0;
  letter-spacing: 1px; text-transform: uppercase; color: #60a5fa;
}
.lp-format-tag {
  display: inline-block;
  background: rgba(96,165,250,0.15);
  border: 1px solid rgba(96,165,250,0.3);
  color: #93c5fd; font-size: 10px; font-weight: 600;
  padding: 2px 10px; border-radius: 20px; margin-top: 6px;
}
.lp-meta-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  border-bottom: 1px solid var(--color-border-secondary);
}
.lp-meta-cell {
  padding: 8px 14px;
  border-right: 1px solid var(--color-border-secondary);
  border-bottom: 1px solid var(--color-border-secondary);
}
.lp-meta-cell:nth-child(even) { border-right: none; }
.lp-meta-lbl {
  font-size: 9px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.6px; color: var(--color-text-secondary); margin-bottom: 2px;
}
.lp-meta-val { font-size: 12px; font-weight: 500; color: var(--color-text-primary); }
.lp-disclaimer {
  background: var(--color-background-warning);
  border-bottom: 1px solid var(--color-border-warning);
  padding: 8px 14px; font-size: 11px; color: #92400e;
}
.lp-section-row {
  border-bottom: 1px solid var(--color-border-secondary);
  display: grid; grid-template-columns: 180px 1fr;
}
.lp-section-row:last-child { border-bottom: none; }
.lp-section-label {
  background: var(--color-background-secondary);
  border-right: 1px solid var(--color-border-secondary);
  padding: 10px 14px; font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.5px;
  color: var(--color-text-secondary); display: flex; align-items: flex-start;
}
/* ILAW sections get a purple accent */
.lp-section-row.ilaw-section .lp-section-label {
  color: #6d28d9;
  background: #f5f3ff;
}
.lp-section-content textarea {
  width: 100%; border: none; outline: none; padding: 10px 14px;
  resize: vertical; font-family: inherit; font-size: 12px; line-height: 1.7;
  color: var(--color-text-primary); background: var(--color-background-primary);
  min-height: 80px; transition: background 0.2s;
}
.lp-section-content textarea:focus { background: var(--color-background-secondary); }

/* Placeholder */
.gen-placeholder {
  background: var(--color-background-primary);
  border: 2px dashed var(--color-border-secondary);
  border-radius: 12px; display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  min-height: 400px; padding: 40px; text-align: center;
  color: var(--color-text-secondary);
}
.gen-placeholder i { font-size: 48px; opacity: 0.2; margin-bottom: 16px; display: block; }
.gen-placeholder h3 { font-size: 15px; margin-bottom: 6px; }
.gen-placeholder p { font-size: 13px; opacity: 0.7; }

/* Loading overlay */
.gen-loading-overlay {
  display: none; position: fixed; inset: 0; z-index: 9999;
  background: rgba(0,0,0,0.55); backdrop-filter: blur(4px);
  align-items: center; justify-content: center;
  flex-direction: column; gap: 16px;
}
.gen-loading-overlay.active { display: flex; }
.gen-loading-spinner {
  width: 52px; height: 52px; border-radius: 50%;
  border: 4px solid rgba(255,255,255,0.2);
  border-top-color: #818cf8;
  animation: spin-loader 0.9s linear infinite;
}
@keyframes spin-loader { to { transform: rotate(360deg); } }
.gen-loading-text { color: #fff; font-size: 15px; font-weight: 600; text-align: center; line-height: 1.6; }
.gen-loading-sub  { color: rgba(255,255,255,0.6); font-size: 12px; }

@media (max-width: 900px) {
  .gen-split { grid-template-columns: 1fr; }
  .gen-left-panel { position: static; }
  .lp-section-row { grid-template-columns: 1fr; }
  .lp-section-label { border-right: none; border-bottom: 1px solid var(--color-border-secondary); }
}
@media print {
  .app-shell > nav, .gen-left-panel, .gen-actions, .gen-loading-overlay { display: none !important; }
  .gen-split { grid-template-columns: 1fr !important; }
  .lp-section-content textarea {
    height: auto !important; overflow: visible !important;
    border: none !important; background: transparent !important;
  }
}
</style>

<!-- Loading Overlay — lumalabas habang tumatawag sa AI -->
<div class="gen-loading-overlay" id="loadingOverlay">
  <div class="gen-loading-spinner"></div>
  <div class="gen-loading-text">Generating your lesson plan...<br>
    <span style="font-size:12px;font-weight:400;opacity:0.8;">Using AI — this may take up to 60 seconds.</span>
  </div>
  <div class="gen-loading-sub">Please do not close this page.</div>
</div>

<div class="app-shell">
<?php include '../../includes/sidenav.php'; ?>

<div class="main">
  <div class="page-title">AI Lesson Plan Generator</div>
  <div class="page-sub">Curriculum-aware &middot; Multi-format &middot; DepEd-aligned &middot; SY 2026-2027</div>

  <?php if ($gen_error): ?>
    <div class="alert alert-error" style="margin-bottom:16px;max-width:700px;">
      <?= $gen_error ?>
    </div>
  <?php endif; ?>

  <?php if (($_GET['export_error'] ?? '') === 'not_saved'): ?>
    <!-- Lalabas ito kung sinubukan ng isang teacher na i-access
         ang export URL diretso (halimbawa, sa pag-type sa browser)
         para sa isang lesson plan na hindi pa na-save — ang backend
         check sa pdf.php/docx.php ang nagre-redirect dito. Ito ang
         "defense in depth" layer, hiwalay sa pag-disable ng button
         sa UI, kung sakaling malusutan ang frontend check. -->
    <div class="alert alert-error" style="margin-bottom:16px;max-width:700px;">
      ⚠️ This lesson plan has not been saved yet. Please save it to your Resource Library first before exporting.
    </div>
  <?php endif; ?>

  <div class="gen-split">

    <!-- ═══ LEFT: Form ═══════════════════════════════════════ -->
    <div class="gen-left-panel">
      <div class="card">
        <div class="card-head"><h3>Lesson Plan Details</h3></div>
        <div class="card-body">

          <form method="POST" id="gen-form" onsubmit="return handleGenSubmit()">
            <input type="hidden" name="generate" value="1">

            <!-- Grade Level -->
            <div class="field" style="margin-bottom:12px;">
              <label>Grade Level <span style="color:#ef4444">*</span></label>
              <select name="grade" id="grade-sel" required
                      onchange="updateSubjects(); updateCurriculumAuto();">
                <option value="">Select Grade</option>
                <?php foreach ($grade_levels as $g): ?>
                  <option value="<?= $g ?>" <?= $sel_grade===$g?'selected':'' ?>>
                    <?= $g ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Subject -->
            <div class="field" style="margin-bottom:12px;">
              <label>Subject / Learning Area <span style="color:#ef4444">*</span></label>
              <select name="subject" id="subject-sel" required
                      onchange="updateTopicSuggestion();">
                <option value="">Select Subject</option>
                <?php if ($sel_grade && isset($subjects_map[$sel_grade])): ?>
                  <?php foreach ($subjects_map[$sel_grade] as $s): ?>
                    <option value="<?= $s ?>" <?= $sel_subject===$s?'selected':'' ?>>
                      <?= $s ?>
                    </option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
            </div>

            <!-- Topic -->
            <div class="field" style="margin-bottom:12px;">
              <label>Lesson Topic / Title <span style="color:#ef4444">*</span></label>
              <input type="text" name="topic" id="topic-inp"
                     placeholder="Select a grade and subject first..."
                     value="<?= htmlspecialchars($sel_topic) ?>" required>
              <div style="font-size:10px;color:var(--color-text-secondary);margin-top:3px;">
                Auto-suggested based on curriculum — editable anytime.
              </div>
            </div>

            <!-- Curriculum (ANO ang itinuturo — totoong curriculum lang) -->
            <div class="field" style="margin-bottom:12px;">
              <label>Curriculum</label>
              <select name="curriculum" id="curr-sel" onchange="updateCurrNote();">
                <?php foreach ($curricula as $cKey => $cLabel): ?>
                  <option value="<?= $cKey ?>" <?= $sel_curr===$cKey?'selected':'' ?>>
                    <?= $cLabel ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div id="curr-note" style="font-size:10px;color:var(--color-text-secondary);margin-top:2px;"></div>
            </div>

            <!-- Academic Calendar (KAILAN/paano hinati ang school year — hiwalay sa curriculum) -->
            <div class="field" style="margin-bottom:12px;">
              <label>Academic Calendar</label>
              <select name="calendar" id="calendar-sel" onchange="updateCalendarNote();">
                <?php foreach ($calendars as $calKey => $calLabel): ?>
                  <option value="<?= $calKey ?>" <?= $sel_calendar===$calKey?'selected':'' ?>>
                    <?= $calLabel ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div id="calendar-note" style="font-size:10px;color:var(--color-text-secondary);margin-top:2px;"></div>
            </div>

            <!-- Lesson Plan Format (PAANO isusulat ang papel — 7 choices, hiwalay sa curriculum/calendar) -->
            <div class="field" style="margin-bottom:12px;">
              <label>Lesson Plan Format</label>
              <select name="format" id="format-sel" onchange="updateFormatNote();">
                <?php foreach ($formats as $fKey => $fLabel): ?>
                  <option value="<?= $fKey ?>" <?= $sel_format===$fKey?'selected':'' ?>>
                    <?= $fLabel ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <!-- ILAW note (shown only when ILAW format is selected) -->
              <div class="ilaw-note-box" id="ilaw-format-note">
                <strong>🆕 ILAW Format</strong> — New official lesson plan framework per
                DepEd Order No. 16, s. 2026. Required starting Term 2 of SY 2026-2027.
                Sections: <strong>I</strong>ntentions · <strong>L</strong>earning Experiences ·
                <strong>A</strong>ssessment · <strong>W</strong>ays Forward.
              </div>

              <!-- Note for other formats -->
              <div class="format-note-box" id="other-format-note"></div>
            </div>

            <!-- Duration -->
            <div class="field" style="margin-bottom:12px;">
              <label>Class Duration</label>
              <select name="duration">
                <?php foreach (['30 minutes','45 minutes','60 minutes','90 minutes'] as $d): ?>
                  <option <?= $sel_dur===$d?'selected':'' ?>><?= $d ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Teaching Strategy -->
            <div class="field" style="margin-bottom:20px;">
              <label>Teaching Strategy</label>
              <select name="strategy">
                <?php foreach ([
                  'Discussion-Based','Activity-Based','Collaborative Learning',
                  'Inquiry-Based','Project-Based','Differentiated Instruction',
                  'Lecture Method','Demonstration','Problem-Based Learning',
                ] as $st): ?>
                  <option <?= $sel_strat===$st?'selected':'' ?>><?= $st ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <button type="submit" id="gen-btn"
              style="width:100%;padding:13px;border:none;border-radius:10px;
                     background:linear-gradient(135deg,#3730a3,#4f46e5,#7c3aed);
                     color:#fff;font-family:inherit;font-size:14px;font-weight:700;
                     cursor:pointer;transition:all .25s;
                     box-shadow:0 4px 18px rgba(79,70,229,.4);"
              onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 26px rgba(79,70,229,.5)'"
              onmouseout="this.style.transform='';this.style.boxShadow='0 4px 18px rgba(79,70,229,.4)'">
              <i class="bi bi-stars"></i> Generate Lesson Plan
            </button>
          </form>

        </div>
      </div>
    </div>

    <!-- ═══ RIGHT: Output ════════════════════════════════════ -->
    <div class="gen-right-panel">

      <?php if (!$generated): ?>
      <div class="gen-placeholder">
        <i class="bi bi-file-earmark-ruled"></i>
        <h3>Your lesson plan will appear here</h3>
        <p>Fill in the details on the left and click<br>
           <strong>Generate Lesson Plan</strong> to get started.</p>
        <p style="margin-top:12px;font-size:11px;">
          💡 Try the new <strong>ILAW Format</strong> for SY 2026-2027!
        </p>
      </div>

      <?php else: ?>

      <!-- ── HIDDEN REGENERATE FORM ──────────────────────────
           Ang Regenerate button ay isusumite ang FORM NA ITO, hindi
           ang form sa kaliwang panel — dahil naglalaman ito ng EXACT
           na kopya ng mga value na ginamit sa kasalukuyang lesson plan
           (grade, subject, topic, curriculum, calendar, format,
           strategy). Pag-click ng Regenerate, ito mismo ang isusumite
           — paulit-ulit gamit ang PAREHONG settings. ── -->
      <form method="POST" action="index.php" id="regen-form" style="display:none;">
        <input type="hidden" name="generate" value="1">
        <input type="hidden" name="is_regenerate" value="1">
        <input type="hidden" name="grade"      value="<?= htmlspecialchars($sel_grade) ?>">
        <input type="hidden" name="subject"    value="<?= htmlspecialchars($sel_subject) ?>">
        <input type="hidden" name="topic"      value="<?= htmlspecialchars($sel_topic) ?>">
        <input type="hidden" name="curriculum" value="<?= htmlspecialchars($sel_curr) ?>">
        <input type="hidden" name="calendar"   value="<?= htmlspecialchars($sel_calendar) ?>">
        <input type="hidden" name="format"     value="<?= htmlspecialchars($sel_format) ?>">
        <input type="hidden" name="duration"   value="<?= htmlspecialchars($sel_dur) ?>">
        <input type="hidden" name="strategy"   value="<?= htmlspecialchars($sel_strat) ?>">
      </form>

      <!-- Dating ang form na ito ay nag-POST diretso sa save.php,
           na nag-re-redirect papuntang Library page pagkatapos ma-save
           — kaya umaalis ang teacher sa AI Generator page. Ngayon,
           ang form ay hindi na talaga "susubmit" sa normal na paraan
           (action="" na, walang real navigation) — ang Save button
           ay tumatawag sa handleSaveToLibrary() sa JavaScript, na
           gumagamit ng fetch() papuntang bagong save.php endpoint,
           at pinoproseso ang JSON response DITO sa parehong page. -->
      <form id="save-form" onsubmit="return false;">
        <!-- bagong hidden field — ito ang dating-saved na
             lesson_plan_id (kung meron na). Blangko sa unang Save
             (bagong INSERT); mapupunan AGAD pagkatapos ng successful
             unang save (via JS), para kung pindutin ulit ang Save
             matapos mag-edit, alam na ng save.php na UPDATE na
             ang dapat gawin, hindi bagong INSERT (kaya hindi
             dumodoble ang record sa database). -->
        <!-- dating laging blangko ang value nito, kaya kahit
             galing sa Regenerate ng isang ALREADY-SAVED lesson plan
             (mula view.php), walang paraan para malaman ng JavaScript
             na ito ay UPDATE dapat — laging nagiging bagong INSERT.
             Ngayon, kapag may $sel_existing_id mula sa Regenerate
             button ng view.php, dito na ito agad nailalagay bilang
             starting value — kahit hindi pa pinindot ang Save. -->
        <input type="hidden" id="existing-plan-id" name="existing_id" value="<?= (int)$sel_existing_id ?>">
        <input type="hidden" name="grade_level" value="<?= htmlspecialchars($sel_grade) ?>">
        <input type="hidden" name="subject"     value="<?= htmlspecialchars($sel_subject) ?>">
        <input type="hidden" name="topic"       value="<?= htmlspecialchars($sel_topic) ?>">
        <input type="hidden" name="curriculum"  value="<?= htmlspecialchars($sel_curr) ?>">
        <input type="hidden" name="calendar"    value="<?= htmlspecialchars($sel_calendar) ?>">
        <input type="hidden" name="format"      value="<?= htmlspecialchars($sel_format) ?>">
        <input type="hidden" name="strategy"    value="<?= htmlspecialchars($sel_strat) ?>">

        <!-- Success/error banner — lalabas DITO sa parehong page
             pagkatapos i-click ang Save, imbes na mag-redirect. Naka-
             tago ito (display:none) by default, ipinapakita lang via
             JavaScript pagkatapos ng matagumpay o nabigong save. -->
        <div id="save-banner" style="display:none;margin-bottom:14px;"></div>

        <!-- ── ACTION BUTTONS (sa taas lang — wala nang duplicate
             sa ibaba ng lesson plan, para hindi na malito) ── -->
        <div class="gen-actions" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;position:relative;">

          <!-- hindi na type="submit" — tumatawag na sa AJAX
               handler imbes na talagang isumite ang form -->
          <button type="button" id="save-btn" onclick="handleSaveToLibrary()"
            style="padding:10px 20px;background:linear-gradient(135deg,#065f46,#059669);color:#fff;
                   border:none;border-radius:9px;font-family:inherit;font-size:13px;font-weight:700;
                   cursor:pointer;box-shadow:0 2px 10px rgba(5,150,105,.3);">
            <i class="bi bi-save"></i> <span id="save-btn-label">Save to Library</span>
          </button>

          <!-- Export button ay NAKA-DISABLE by default — hindi
               pwedeng i-export ang isang lesson plan na hindi pa
               na-save sa database (walang valid ID pa). Ma-e-enable
               lang ito via JavaScript pagkatapos ng successful save. -->
          <div style="position:relative;display:inline-block;">
            <button type="button" id="export-btn" onclick="toggleExportMenu(event)" disabled
              title="Save this lesson plan first before exporting"
              style="padding:10px 16px;background:var(--color-background-tertiary);
                     color:var(--color-text-tertiary);border:1px solid var(--color-border-secondary);
                     border-radius:9px;font-family:inherit;font-size:13px;font-weight:600;cursor:not-allowed;opacity:0.6;">
              <i class="bi bi-download"></i> Export <i class="bi bi-chevron-down" style="font-size:10px;"></i>
            </button>
            <div id="export-menu"
              style="display:none;position:absolute;top:calc(100% + 4px);left:0;z-index:50;
                     background:var(--color-background-primary);border:1px solid var(--color-border-secondary);
                     border-radius:9px;box-shadow:0 8px 24px rgba(0,0,0,.12);overflow:hidden;min-width:160px;">
              <!-- NOTE: gagana lang ang export pagkatapos ma-save (kailangan ng valid ID) -->
              <a href="#" onclick="exportPlan('pdf'); return false;"
                style="display:block;padding:10px 14px;font-size:13px;color:var(--color-text-primary);text-decoration:none;">
                <i class="bi bi-file-pdf" style="color:#dc2626;"></i> Export as PDF
              </a>
              <a href="#" onclick="exportPlan('docx'); return false;"
                style="display:block;padding:10px 14px;font-size:13px;color:var(--color-text-primary);text-decoration:none;border-top:1px solid var(--color-border-tertiary);">
                <i class="bi bi-file-word" style="color:#1d4ed8;"></i> Export as DOCX
              </a>
            </div>
          </div>

          <!-- Regenerate — isusumite ang HIDDEN #regen-form (parehong settings) -->
          <button type="button" onclick="handleRegenerate()"
            style="padding:10px 16px;background:var(--color-background-secondary);
                   color:var(--color-text-primary);border:1px solid var(--color-border-secondary);
                   border-radius:9px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;">
            <i class="bi bi-arrow-repeat"></i> Regenerate
          </button>
        </div>

        <!-- ── Professional LP Document ── -->
        <div class="lp-document">
          <div class="lp-doc-header">
            <p>Republic of the Philippines</p>
            <p><strong>Department of Education</strong></p>
            <h2><?= htmlspecialchars($formats[$sel_format] ?? $sel_format) ?></h2>
            <div class="lp-format-tag">
              <?= htmlspecialchars($sel_curr) ?> Curriculum
              <?php if ($sel_format === 'ILAW'): ?>
                &nbsp;·&nbsp; DepEd Order No. 16, s. 2026
              <?php endif; ?>
            </div>
          </div>

          <?php if ($sel_format === 'ILAW'): ?>
          <div style="background:#f5f3ff;border-bottom:1px solid #c4b5fd;padding:8px 14px;font-size:11px;color:#4c1d95;">
            <i class="bi bi-info-circle"></i>
            <strong> ILAW Format</strong> — New official lesson plan format per DepEd Order No. 16, s. 2026.
            Required for Term 2 onward (SY 2026-2027). Please review AI-generated content before classroom use.
          </div>
          <?php endif; ?>

          <div class="lp-disclaimer">
            <i class="bi bi-exclamation-triangle"></i>
            <strong> AI Disclaimer:</strong> This is a generated draft based on the
            <?= htmlspecialchars($sel_curr) ?> curriculum.
            Always review and revise before classroom use.
          </div>

          <!-- Metadata grid -->
          <div class="lp-meta-grid">
            <div class="lp-meta-cell">
              <div class="lp-meta-lbl">Teacher</div>
              <div class="lp-meta-val"><?= htmlspecialchars($teacher_name) ?></div>
            </div>
            <div class="lp-meta-cell">
              <div class="lp-meta-lbl">Grade Level</div>
              <div class="lp-meta-val"><?= htmlspecialchars($sel_grade) ?></div>
            </div>
            <div class="lp-meta-cell">
              <div class="lp-meta-lbl">Learning Area</div>
              <div class="lp-meta-val"><?= htmlspecialchars($sel_subject) ?></div>
            </div>
            <div class="lp-meta-cell">
              <div class="lp-meta-lbl">Topic / Lesson</div>
              <div class="lp-meta-val"><?= htmlspecialchars($sel_topic) ?></div>
            </div>
            <div class="lp-meta-cell">
              <div class="lp-meta-lbl">Duration</div>
              <div class="lp-meta-val"><?= htmlspecialchars($sel_dur) ?></div>
            </div>
            <div class="lp-meta-cell">
              <div class="lp-meta-lbl">Teaching Strategy</div>
              <div class="lp-meta-val"><?= htmlspecialchars($sel_strat) ?></div>
            </div>
          </div>

          <!-- ── Sections — format-specific labels mula sa getSectionLabels() ── -->
          <?php foreach ($section_info as $key => [$ico, $label, $desc]): ?>
          <div class="lp-section-row <?= $sel_format === 'ILAW' ? 'ilaw-section' : '' ?>">
            <div class="lp-section-label">
              <?= $ico ?>&nbsp;<?= htmlspecialchars($label) ?>
            </div>
            <div class="lp-section-content">
              <textarea name="<?= $key ?>"
                oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px';"
                placeholder="<?= htmlspecialchars($desc) ?>"><?= htmlspecialchars(safeStr($plan_data[$key] ?? '')) ?></textarea>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </form>

      <?php endif; ?>
    </div>
  </div>
</div>
</div>

<script>
// Subjects per grade — ginagamit para i-populate ang Subject dropdown
const subjectsMap = <?= json_encode($subjects_map) ?>;

// Default topic suggestions per grade+subject — auto-fills ang Topic
// field para gabay sa teacher (editable pa rin)
const topicsMap = {
  'Kinder':   { 'Mother Tongue':'Oral Language and Literacy', 'Mathematics':'Numbers and Number Sense (1-10)', 'Makabansa':'Myself, My Family, and My Community', 'MAPEH':'Music, Arts, Physical Education, and Health' },
  'Grade 1':  { 'Filipino':'Komunikasyon at Pag-unawa sa Binasa', 'English':'Phonological Awareness and Early Reading', 'Mathematics':'Numbers 1-100 and Basic Addition and Subtraction', 'Mother Tongue':'Oral Language Development', 'Araling Panlipunan':'Sarili, Pamilya at Paaralan', 'Science':'Living and Non-Living Things', 'MAPEH':'Music, Arts, Physical Education and Health', 'ESP':'Pagpapahalaga sa Sarili at Kapwa' },
  'Grade 2':  { 'Filipino':'Pakikinig at Pagsasalita', 'English':'Reading Comprehension and Vocabulary Building', 'Mathematics':'Addition and Subtraction with Regrouping', 'Science':'Animals and Their Characteristics', 'Araling Panlipunan':'Pamayanan at Lipunan', 'MAPEH':'Musika, Sining, PE at Kalusugan', 'ESP':'Pagpapahalaga sa Kapwa' },
  'Grade 3':  { 'Filipino':'Pagbabasa at Pagsulat ng mga Pangungusap', 'English':'Grammar and Reading Comprehension', 'Mathematics':'Multiplication and Division of Whole Numbers', 'Science':'Matter and Its Properties', 'Araling Panlipunan':'Kasaysayan ng Ating Lugar', 'MAPEH':'Musika, Sining, PE at Kalusugan', 'ESP':'Mabuting Pagpapahalaga' },
  'Grade 4':  { 'Filipino':'Panitikang Pilipino at Wika', 'English':'Literary Texts and Language Use', 'Mathematics':'Fractions and Decimals', 'Science':'Ecosystems and Living Things', 'Araling Panlipunan':'Heograpiya ng Pilipinas', 'MAPEH':'Philippine Folk Dances and Health', 'ESP':'Pagpapahalaga sa Pamilya at Pamayanan', 'TLE':'Home Economics Basics' },
  'Grade 5':  { 'Filipino':'Pagsulat ng Iba\'t Ibang Uri ng Teksto', 'English':'Reading Informational Texts', 'Mathematics':'Ratio and Proportion', 'Science':'Mixtures and Solutions', 'Araling Panlipunan':'Kasaysayan ng Asya', 'MAPEH':'Team Sports and Health', 'ESP':'Pakikiisa sa Kapwa at Lipunan', 'TLE':'Agriculture and Food Technology' },
  'Grade 6':  { 'Filipino':'Panitikan at Wika ng Pilipinas', 'English':'English for Communication', 'Mathematics':'Integers and Algebraic Expressions', 'Science':'Body Systems and Their Functions', 'Araling Panlipunan':'Kasaysayan ng Daigdig', 'MAPEH':'Physical Fitness and Health', 'ESP':'Pagmamahal sa Bayan', 'TLE':'Entrepreneurship and ICT Basics' },
  'Grade 7':  { 'Filipino':'Masining na Pagpapahayag', 'English':'Literature and Language in Context', 'Mathematics':'Sets and the Real Number System', 'Science':'Matter and Its Properties', 'Araling Panlipunan':'Sinaunang Kabihasnan sa Daigdig', 'MAPEH':'Health and Physical Education', 'ESP':'Pag-unawa sa Sarili', 'TLE':'Agricultural and Fishery Arts' },
  'Grade 8':  { 'Filipino':'Komunikasyon at Pananaliksik sa Wika', 'English':'World Literature and Language Skills', 'Mathematics':'Linear Equations and Inequalities', 'Science':'Force, Motion, and Energy', 'Araling Panlipunan':'Ekonomiks: Konsepto at Prinsipyo', 'MAPEH':'Health and Wellness', 'ESP':'Pagpapalakas ng Relasyon', 'TLE':'Technical-Vocational Livelihood' },
  'Grade 9':  { 'Filipino':'Panitikang Panlipunan', 'English':'Research and Argumentative Writing', 'Mathematics':'Quadratic Equations and Functions', 'Science':'Living Things and Their Environment', 'Araling Panlipunan':'Sibika at Kultura ng Pilipinas', 'MAPEH':'Individual and Dual Sports', 'ESP':'Responsibilidad sa Lipunan', 'TLE':'Technical-Vocational Livelihood' },
  'Grade 10': { 'Filipino':'Pag-aaral ng Akdang Pampanitikan', 'English':'English for Specific Purposes', 'Mathematics':'Polynomial Functions and Circles', 'Science':'Plate Tectonics and the Universe', 'Araling Panlipunan':'Kontemporaryong Isyu', 'MAPEH':'Lifetime Sports and Health', 'ESP':'Pagpapalakas ng Pagkatao', 'TLE':'Technical-Vocational Livelihood' },
  'Grade 11': { 'Oral Communication':'Introduction to Communication', 'Reading and Writing':'Reading Academic Texts', 'Earth and Life Science':'Origin of the Universe and the Solar System', 'General Mathematics':'Functions and Their Graphs', 'Understanding Culture Society and Politics':'Anthropological and Sociological Perspectives', 'Physical Education and Health':'Physical Fitness and Wellness', 'Contemporary Philippine Arts':'Arts from the Regions', 'Media and Information Literacy':'Introduction to Media and Information' },
  'Grade 12': { 'English for Academic and Professional Purposes':'Communicating in the Workplace', 'Practical Research 1':'Introduction to Qualitative Research', 'Practical Research 2':'Introduction to Quantitative Research', 'Filipino sa Piling Larang':'Akademikong Filipino', 'Pre-Calculus':'Analytic Geometry', 'Basic Calculus':'Limits and Continuity', 'Physical Education and Health':'Exercise for Fitness' },
};

// Short descriptions na lumalabas sa ilalim ng Format dropdown
// (maliban sa ILAW, na may sariling mas detalyadong box)
const formatNotes = {
  'DLP':         'Most detailed format. Required for new teachers per DepEd DO 42, s. 2016.',
  '4As':         'Most popular in PH public schools. Activity → Analysis → Abstraction → Application.',
  '5Es':         'Common in Science classes. Engage → Explore → Explain → Elaborate → Evaluate.',
  'Traditional': '5-part format. Objectives → Subject Matter → Procedure → Evaluation → Assignment.',
  'Semi':        'Less detailed than DLP. Suitable for experienced teachers.',
  'DLL':         'Weekly grid format. Required for experienced teachers per DepEd policy.',
};

// Short descriptions na lumalabas sa ilalim ng Curriculum dropdown.
// Dalawa lang — MATATAG (K-10) at K-12 SHS (11-12). Tinanggal na ang
// "ILAW" dito dahil ito ay academic calendar, hindi curriculum.
const currNotes = {
  'MATATAG': 'For Kindergarten to Grade 10. Uses the MATATAG competency standards.',
  'K-12':    'For Grades 11-12 (Senior High School). Based on DepEd DO 42, s. 2016.',
};

// Short descriptions na lumalabas sa ilalim ng Academic Calendar
// dropdown — hiwalay na dimensyon mula sa Curriculum.
const calendarNotes = {
  'FourQuarter': 'Traditional school year split into 4 quarters.',
  'ThreeTerm':   'New SY 2026-2027 calendar split into 3 terms, per DepEd DO 9, s. 2026.',
};

// Grades na gumagamit ng K-12 SHS curriculum (11-12). Lahat ng iba
// (Kinder-Grade 10) ay MATATAG.
const k12Grades = ['Grade 11','Grade 12'];

// Ipinapakita ang loading overlay habang nag-process ang generate
// request — kasi pwedeng tumagal ito (AI call + posibleng fallback
// retries), gusto nating malinaw sa teacher na hindi nag-freeze ang page
function handleGenSubmit() {
  document.getElementById('loadingOverlay').classList.add('active');
  return true;
}

// Pag-pili ng Grade, i-populate/i-reset ang Subject dropdown base sa
// subjectsMap, at i-clear ang Topic field (kasi iba na ang subjects)
function updateSubjects() {
  const grade  = document.getElementById('grade-sel').value;
  const subSel = document.getElementById('subject-sel');
  subSel.innerHTML = '<option value="">— Select Subject —</option>';
  (subjectsMap[grade] || []).forEach(s => {
    const o = document.createElement('option');
    o.value = s; o.textContent = s;
    subSel.appendChild(o);
  });
  document.getElementById('topic-inp').value = '';
  document.getElementById('topic-inp').placeholder = 'Select a subject first...';
}

// Pag-pili ng Subject, i-auto-fill ang Topic field gamit ang topicsMap
// (may visual highlight effect para makita ng teacher na auto-filled ito)
function updateTopicSuggestion() {
  const grade   = document.getElementById('grade-sel').value;
  const subject = document.getElementById('subject-sel').value;
  const t       = document.getElementById('topic-inp');
  if (grade && subject && topicsMap[grade]?.[subject]) {
    t.value = topicsMap[grade][subject];
    t.style.transition  = 'border-color 0.3s, box-shadow 0.3s';
    t.style.borderColor = '#818cf8';
    t.style.boxShadow   = '0 0 0 3px rgba(129,140,248,0.2)';
    setTimeout(() => { t.style.borderColor = ''; t.style.boxShadow = ''; }, 1800);
  } else {
    t.value = ''; t.placeholder = 'e.g., Photosynthesis, Solving Linear Equations...';
  }
}

// Pag-pili ng Grade, i-auto-set ang Curriculum AT Academic Calendar
// base sa grade level. HIWALAY na ngayon ang dalawang ito mula sa
// Lesson Plan Format — hindi sila dapat mag-trigger ng auto-switch
// sa isa't isa (dating bug: "ILAW curriculum" ay nag-auto-select ng
// "ILAW format," gayong magkaibang dimensyon sila).
function updateCurriculumAuto() {
  const grade = document.getElementById('grade-sel').value;
  const cs    = document.getElementById('curr-sel');
  const calS  = document.getElementById('calendar-sel');
  if (k12Grades.includes(grade)) {
    cs.value   = 'K-12';
    calS.value = 'FourQuarter';
  } else {
    cs.value   = 'MATATAG';
    calS.value = 'ThreeTerm';
  }
  updateCurrNote();
  updateCalendarNote();
}

// I-display ang short description sa ilalim ng Curriculum dropdown
function updateCurrNote() {
  document.getElementById('curr-note').textContent =
    currNotes[document.getElementById('curr-sel').value] || '';
}

// I-display ang short description sa ilalim ng Academic Calendar dropdown
function updateCalendarNote() {
  document.getElementById('calendar-note').textContent =
    calendarNotes[document.getElementById('calendar-sel').value] || '';
}

// I-display ang tamang note box depende sa pinili na Lesson Plan
// Format — may special, mas detalyadong box para sa ILAW, at simpleng
// one-liner para sa ibang 6 formats
function updateFormatNote() {
  const f        = document.getElementById('format-sel').value;
  const ilawBox  = document.getElementById('ilaw-format-note');
  const otherBox = document.getElementById('other-format-note');
  if (f === 'ILAW') {
    ilawBox.classList.add('active');
    otherBox.classList.remove('active');
    otherBox.textContent = '';
  } else {
    ilawBox.classList.remove('active');
    const note = formatNotes[f] || '';
    if (note) {
      otherBox.textContent = note;
      otherBox.classList.add('active');
    } else {
      otherBox.classList.remove('active');
    }
  }
}

// Init on load — siguraduhing tama ang nakikitang notes pagka-load ng
// page (halimbawa, kapag may existing values mula sa POST/regenerate)
updateFormatNote();
updateCurrNote();
updateCalendarNote();

// Auto-resize ng mga textarea base sa content nito, para makita
// agad ang buong lesson plan content nang walang i-scroll pa
document.querySelectorAll('.lp-section-content textarea').forEach(t => {
  t.style.height = 'auto';
  t.style.height = t.scrollHeight + 'px';
});

// ── EXPORT DROPDOWN MENU ─────────────────────────────────────
// Pinipindot ang "Export" button para buksan/isara ang menu
// na may dalawang choices: PDF o DOCX.
function toggleExportMenu(e) {
  e.stopPropagation();
  const menu = document.getElementById('export-menu');
  menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
}
// Isara ang export menu kapag pumindot sa kahit saan sa labas nito
document.addEventListener('click', () => {
  const menu = document.getElementById('export-menu');
  if (menu) menu.style.display = 'none';
});

// ── SAVE/EXPORT STATE — totoong "memory" ng kasalukuyang page ──
// Dating umaasa ang exportPlan() sa $_GET['id'] mula sa URL —
// pero kailanman hindi talaga naglalagay ng ?id= sa URL ang AI
// Generator page (laging "generator/index.php" lang, walang query
// string), kaya talagang HINDI gumagana ang export dito noon. Ngayon,
// ito ay isang JavaScript variable na nag-iimbak ng plan ID PAGKATAPOS
// ng successful save — hindi na umaasa sa URL.
let currentPlanId = null;
let intentionalAction = false;

// ── SAVE TO LIBRARY (AJAX — hindi na nag-re-redirect) ───────────
// Ito ang sagot sa pangunahing concern — dating ang Save button
// ay nag-POST sa save.php, na agad nagre-redirect papuntang Library
// page. Ngayon, gumagamit ng fetch() papuntang save.php, kinukuha
// ang JSON response, at ipinapakita ang resulta DITO sa parehong page
// gamit ang #save-banner — hindi na umaalis ang teacher sa Generator.
async function handleSaveToLibrary() {
  const btn      = document.getElementById('save-btn');
  const btnLabel = document.getElementById('save-btn-label');
  const banner   = document.getElementById('save-banner');
  const form     = document.getElementById('save-form');

  btn.disabled = true;
  btnLabel.textContent = 'Saving...';

  try {
    const formData = new FormData(form);
    const response = await fetch('save.php', { method: 'POST', body: formData });
    const data     = await response.json();

    if (data.success) {
      // I-store ang plan_id — ginagamit ito ng (1) export buttons
      // mula ngayon, at (2) susunod pang Save click, para malaman
      // ng save.php na UPDATE na ito, hindi bagong INSERT.
      currentPlanId = data.plan_id;
      document.getElementById('existing-plan-id').value = data.plan_id;

      // I-enable ang Export button — dati ay disabled kasi walang
      // valid na plan ID pa
      const exportBtn = document.getElementById('export-btn');
      exportBtn.disabled = false;
      exportBtn.style.cursor = 'pointer';
      exportBtn.style.opacity = '1';
      exportBtn.style.background = 'var(--color-background-secondary)';
      exportBtn.style.color = 'var(--color-text-primary)';
      exportBtn.removeAttribute('title');

      // Success banner na may "View in Library" link — hindi
      // automatic na pag-redirect, kundi OPTIONAL na link kung gusto
      // talaga ng teacher pumunta sa buong Library nila. Manatili sa
      // Generator page kung hindi nila i-click ito.
      banner.style.display = 'block';
      banner.innerHTML =
        '<div class="alert alert-success" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">' +
          '<span>✅ ' + data.message + '</span>' +
          '<a href="../library/index.php" style="font-weight:600;text-decoration:none;color:inherit;white-space:nowrap;">View in Library →</a>' +
        '</div>';

      btnLabel.textContent = 'Saved ✓';
      setTimeout(() => { btnLabel.textContent = 'Save to Library'; btn.disabled = false; }, 1500);

    } else {
      banner.style.display = 'block';
      banner.innerHTML = '<div class="alert alert-error">⚠️ ' + (data.error || 'Save failed. Please try again.') + '</div>';
      btnLabel.textContent = 'Save to Library';
      btn.disabled = false;
    }
  } catch (err) {
    banner.style.display = 'block';
    banner.innerHTML = '<div class="alert alert-error">⚠️ Network error. Please check your connection and try again.</div>';
    btnLabel.textContent = 'Save to Library';
    btn.disabled = false;
  }
}

// ── EXPORT ACTION ─────────────────────────────────────────────
// Gumagamit na ng currentPlanId (JS variable, naka-set lang
// pagkatapos ng successful save) imbes na sa $_GET['id'] na hindi
// naman talaga umiiral sa AI Generator page. Ang Export button ay
// naka-disable din sa HTML (disabled attribute) hangga't walang
// successful save — ito ang dobleng proteksyon (UI + JS check).
function exportPlan(type) {
  if (!currentPlanId) {
    alert('Please save this lesson plan to your Library first before exporting.');
    return;
  }
  intentionalAction = true; // hindi totoong "leaving" ang export
  const url = (type === 'pdf')
    ? '../export/pdf.php?id=' + currentPlanId
    : '../export/docx.php?id=' + currentPlanId;
  window.open(url, '_blank');
}

// ── REGENERATE ──────────────────────────────────────────────
// Isusumite ang #regen-form (hidden form na may EXACT na kopya ng
// grade/subject/topic/curriculum/calendar/format/duration/strategy
// ng kasalukuyang lesson plan) — kaya guaranteed na may kompletong
// data na ipoproseso, direktang mag-generate ulit gamit ng PAREHONG
// settings, walang kailangang i-adjust pa.
function handleRegenerate() {
  if (!confirm('Generate a new version using the same settings? Unsaved edits will be lost.')) {
    return;
  }
  intentionalAction = true; // sinasadyang i-resubmit ang form, hindi aksidenteng pag-iwan
  document.getElementById('loadingOverlay').classList.add('active');
  document.getElementById('regen-form').submit();
}

// Babalaan ang teacher kung susubukan niyang umalis sa page habang
// may unsaved na generated lesson plan — para hindi nawawala ang
// resulta nang hindi sinasadya.
//
// Idinagdag ang check na "!intentionalAction" — hindi na lalabas
// ang warning kung galing ito sa Export o Regenerate buttons, dahil
// alam na natin na sinadya ito, hindi aksidenteng pag-iwan sa page.
//
// Dating umaasa ito sa "submit" event ng #save-form, pero
// hindi na talaga "sumasubmit" ang form sa normal na paraan ngayon
// (ginagamit na ang fetch() sa loob ng handleSaveToLibrary()) — kaya
// hindi na kailanman magfire ang dating listener, laging "false" ang
// "saved" variable kahit matagumpay na ang save. Ngayon, direktang
// ginagamit ang currentPlanId — totoo lang ito kapag matagumpay na
// nai-save (set sa loob ng handleSaveToLibrary() pagkatapos ng
// successful response mula sa save.php).
<?php if ($generated): ?>
window.addEventListener('beforeunload', e => {
  if (!currentPlanId && !intentionalAction) { e.preventDefault(); e.returnValue = ''; }
});
<?php endif; ?>
</script>

<?php include '../../includes/footer.php'; ?>