<?php
// modules/generator/view.php 
require_once '../../config/session.php';
require_once '../../config/db.php';
require_once '../../config/gemini.php';
requireLogin();

$tid = $_SESSION['teacher_id'];
$id  = (int)($_GET['id'] ?? 0);

$lp = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM lesson_plans WHERE id='$id' AND teacher_id='$tid'"));
if (!$lp) { header("Location: ../library/index.php"); exit(); }

// Save edits
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sets = [];
    foreach (['learning_objectives','materials_needed','introduction_motivation',
              'lesson_body','learning_activities','assessment','closure'] as $s) {
        $v = mysqli_real_escape_string($conn, $_POST[$s] ?? '');
        $sets[] = "$s='$v'";
    }
    mysqli_query($conn,
        "UPDATE lesson_plans SET " . implode(',', $sets) . ", is_saved=1, updated_at=NOW()
         WHERE id='$id' AND teacher_id='$tid'");
    header("Location: view.php?id=$id&saved=1");
    exit();
}

// ── Format-aware section labels ────────────────────────────
// This is the KEY FIX: use the stored format to get correct labels
$stored_format = $lp['format'] ?? 'DLP';
$section_info  = getSectionLabels($stored_format);
$formats_list  = getLessonPlanFormats();
$format_label  = $formats_list[$stored_format] ?? $stored_format;

$is_ilaw = ($stored_format === 'ILAW');

$page_title = htmlspecialchars($lp['title']) . " — Gurong GabAI";
$active_nav = 'library';
include '../../includes/header.php';
?>

<style>
.lp-view-wrap { max-width: 760px; }

/* Document card */
.lp-view-doc {
  background: var(--color-background-primary);
  border: 1px solid var(--color-border-secondary);
  border-radius: 12px; overflow: hidden; margin-bottom: 16px;
}
.lp-view-header {
  background: linear-gradient(135deg, #060c18, #0f1e3d);
  color: white; text-align: center; padding: 20px 16px 16px;
}
.lp-view-header p { font-size: 11px; opacity: 0.75; margin: 0; line-height: 1.6; }
.lp-view-header strong { font-size: 12px; opacity: 0.9; }
.lp-view-header h2 {
  font-size: 16px; font-weight: 700; margin: 10px 0 0;
  letter-spacing: 1px; text-transform: uppercase; color: #60a5fa;
}
.lp-format-tag {
  display: inline-block;
  background: rgba(96,165,250,0.15);
  border: 1px solid rgba(96,165,250,0.3);
  color: #93c5fd; font-size: 10px; font-weight: 600;
  padding: 2px 10px; border-radius: 20px; margin-top: 6px;
}
<?php if ($is_ilaw): ?>
.lp-format-tag { background: rgba(167,139,250,0.2); border-color: rgba(167,139,250,0.4); color: #c4b5fd; }
<?php endif; ?>

/* Meta grid */
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
.lp-meta-lbl { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-text-secondary); margin-bottom: 2px; }
.lp-meta-val { font-size: 12px; font-weight: 500; }

/* Disclaimer */
.lp-disclaimer {
  background: var(--color-background-warning);
  border-bottom: 1px solid var(--color-border-warning);
  padding: 8px 14px; font-size: 11px; color: #92400e;
}

/* ILAW notice */
.ilaw-notice {
  background: #f5f3ff;
  border-bottom: 1px solid #c4b5fd;
  padding: 8px 14px; font-size: 11px; color: #4c1d95;
}

/* Sections */
.lp-section {
  border-bottom: 1px solid var(--color-border-secondary);
  display: grid; grid-template-columns: 180px 1fr;
}
.lp-section:last-child { border-bottom: none; }
.lp-section-head {
  background: var(--color-background-secondary);
  border-right: 1px solid var(--color-border-secondary);
  padding: 10px 14px;
  font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.5px;
  color: var(--color-text-secondary);
  display: flex; align-items: flex-start;
}
<?php if ($is_ilaw): ?>
.lp-section-head { color: #6d28d9; background: #f5f3ff; }
<?php endif; ?>
.lp-section-body {
  width: 100%; border: none; outline: none; padding: 10px 14px;
  resize: vertical; font-family: inherit; font-size: 12px; line-height: 1.7;
  color: var(--color-text-primary); background: var(--color-background-primary);
  min-height: 80px; transition: background 0.2s;
}
.lp-section-body:focus { background: var(--color-background-secondary); }

@media (max-width: 700px) {
  .lp-section { grid-template-columns: 1fr; }
  .lp-section-head { border-right: none; border-bottom: 1px solid var(--color-border-secondary); }
  .lp-meta-grid { grid-template-columns: 1fr; }
  .lp-meta-cell { border-right: none; }
}
@media print {
  nav, .btn, .gen-actions, .page-title, .page-sub, .alert { display: none !important; }
  .lp-section-body { height: auto !important; overflow: visible !important; border: none !important; background: transparent !important; }
}
</style>

<div class="app-shell">
<?php include '../../includes/sidenav.php'; ?>
<div class="main">

  <div class="lp-view-wrap">

    <!-- Breadcrumb + title -->
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:4px;flex-wrap:wrap;">
      <div class="page-title" style="margin-bottom:0;"><?= htmlspecialchars($lp['title']) ?></div>
      <span class="badge <?= $lp['is_saved'] ? 'b-saved' : 'b-pending' ?>">
        <?= $lp['is_saved'] ? 'Saved' : 'Unsaved' ?>
      </span>
      <?php if ($is_ilaw): ?>
        <span style="background:#7c3aed;color:#fff;font-size:9px;font-weight:800;
                     letter-spacing:1px;padding:2px 8px;border-radius:20px;">ILAW</span>
      <?php endif; ?>
    </div>
    <div class="page-sub">
      <?= htmlspecialchars($lp['grade_level']) ?> &middot;
      <?= htmlspecialchars($lp['subject']) ?>
      <?php if (!empty($lp['curriculum'])): ?>
        &middot; <?= htmlspecialchars($lp['curriculum']) ?> Curriculum
      <?php endif; ?>
      <?php if (!empty($lp['format'])): ?>
        &middot; <?= htmlspecialchars($format_label) ?>
      <?php endif; ?>
      &middot; Last updated <?= date('M d, Y', strtotime($lp['updated_at'])) ?>
    </div>

    <?php if (isset($_GET['saved'])): ?>
      <div class="alert alert-success" style="margin:12px 0;">Lesson plan saved successfully.</div>
    <?php endif; ?>

    <!-- ── ACTION BUTTONS (sa taas lang — tinanggal na ang
         duplicate na set ng buttons sa ibaba ng document) ── -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:14px 0;position:relative;">
      <button form="lpForm" type="submit" class="btn btn-blue">
        <i class="bi bi-save"></i> Save Changes
      </button>

      <!-- FIX: Idinagdag ang Regenerate button dito sa view.php.
           Dati, walang paraan para tawagan ulit ang AI gamit ang
           parehong settings ng isang NA-SAVE NA lesson plan — ang
           "Regenerate" ay nasa AI Generator form lang (index.php),
           hindi available pagkatapos i-save at buksan via Library.
           Ngayon, ipapadala ang saved settings papuntang isang
           hidden form na nag-submit sa generator/index.php, kasama
           ang "is_regenerate" flag para magkaroon ng tunay na
           pagkakaiba ang bagong resulta.
           LIMITASYON: walang stored na 'calendar' o 'duration' column
           sa lesson_plans table, kaya gumagamit ng sensible default
           (ThreeTerm, 45 minutes) — pwedeng i-adjust ng teacher ulit
           sa form bago talagang i-generate. -->
      <button type="button" onclick="if(confirm('Regenerate this lesson plan using the same settings? You will be taken to the AI Generator.')) document.getElementById('regenFromView').submit();" class="btn btn-outline">
        <i class="bi bi-arrow-repeat"></i> Regenerate
      </button>
      <form id="regenFromView" method="POST" action="index.php" style="display:none;">
        <input type="hidden" name="generate"      value="1">
        <input type="hidden" name="is_regenerate"  value="1">
        <!-- FIX: Ito ang nawawalang piraso ng bug — dating walang
             existing_id na ipinapasa, kaya kapag ang Regenerate dito
             sa view.php ay nag-punta sa AI Generator at na-save, lagi
             itong gumagawa ng BAGONG record imbes na i-update ang
             ORIHINAL na lesson plan na ito mismo. Ngayon, ipinapasa
             ang totoong ID ng lesson plan na ito, para malaman ng
             save.php na UPDATE ito, hindi bagong INSERT. -->
        <input type="hidden" name="existing_id"    value="<?= (int)$id ?>">
        <input type="hidden" name="grade"          value="<?= htmlspecialchars($lp['grade_level']) ?>">
        <input type="hidden" name="subject"        value="<?= htmlspecialchars($lp['subject']) ?>">
        <input type="hidden" name="topic"          value="<?= htmlspecialchars($lp['topic']) ?>">
        <input type="hidden" name="curriculum"     value="<?= htmlspecialchars($lp['curriculum'] ?? 'MATATAG') ?>">
        <input type="hidden" name="calendar"       value="ThreeTerm">
        <input type="hidden" name="format"         value="<?= htmlspecialchars($lp['format'] ?? 'ILAW') ?>">
        <input type="hidden" name="duration"       value="45 minutes">
        <input type="hidden" name="strategy"       value="<?= htmlspecialchars($lp['strategy'] ?? 'Discussion-Based') ?>">
      </form>

      <a href="../library/index.php" class="btn btn-outline">← Back to Library</a>

      <!-- Export — isang button, may dropdown menu ng PDF/DOCX choices -->
      <div style="position:relative;display:inline-block;">
        <button type="button" onclick="toggleExportMenu(event)" class="btn btn-outline">
          <i class="bi bi-download"></i> Export <i class="bi bi-chevron-down" style="font-size:10px;"></i>
        </button>
        <div id="export-menu"
          style="display:none;position:absolute;top:calc(100% + 4px);left:0;z-index:50;
                 background:var(--color-background-primary);border:1px solid var(--color-border-secondary);
                 border-radius:9px;box-shadow:0 8px 24px rgba(0,0,0,.12);overflow:hidden;min-width:160px;">
          <a href="../export/pdf.php?id=<?= $id ?>" target="_blank"
            style="display:block;padding:10px 14px;font-size:13px;color:var(--color-text-primary);text-decoration:none;">
            <i class="bi bi-file-pdf" style="color:#dc2626;"></i> Export as PDF
          </a>
          <a href="../export/docx.php?id=<?= $id ?>"
            style="display:block;padding:10px 14px;font-size:13px;color:var(--color-text-primary);text-decoration:none;border-top:1px solid var(--color-border-tertiary);">
            <i class="bi bi-file-word" style="color:#1d4ed8;"></i> Export as DOCX
          </a>
        </div>
      </div>
    </div>

    <!-- Lesson Plan Document -->
    <div class="lp-view-doc">

      <!-- Header -->
      <div class="lp-view-header">
        <p>Republic of the Philippines</p>
        <p><strong>Department of Education</strong></p>
        <h2><?= htmlspecialchars($format_label) ?></h2>
        <div class="lp-format-tag">
          <?= htmlspecialchars($lp['curriculum'] ?? 'MATATAG') ?> Curriculum
          <?php if ($is_ilaw): ?>
            &nbsp;·&nbsp; DepEd Order No. 16, s. 2026
          <?php endif; ?>
        </div>
      </div>

      <!-- ILAW notice -->
      <?php if ($is_ilaw): ?>
      <div class="ilaw-notice">
        <i class="bi bi-info-circle"></i>
        <strong> ILAW Format</strong> — DepEd Order No. 16, s. 2026.
        Sections: Intentions · Learning Experiences · Assessment · Ways Forward.
        Review all AI-generated content before classroom use.
      </div>
      <?php endif; ?>

      <!-- AI Disclaimer -->
      <div class="lp-disclaimer">
        <i class="bi bi-exclamation-triangle"></i>
        <strong> AI Disclaimer:</strong> Generated draft for teacher reference.
        Always review and revise before classroom use.
      </div>

      <!-- Metadata -->
      <div class="lp-meta-grid">
        <div class="lp-meta-cell">
          <div class="lp-meta-lbl">Teacher</div>
          <div class="lp-meta-val"><?= htmlspecialchars($_SESSION['teacher_name'] ?? '') ?></div>
        </div>
        <div class="lp-meta-cell">
          <div class="lp-meta-lbl">Grade Level</div>
          <div class="lp-meta-val"><?= htmlspecialchars($lp['grade_level']) ?></div>
        </div>
        <div class="lp-meta-cell">
          <div class="lp-meta-lbl">Learning Area</div>
          <div class="lp-meta-val"><?= htmlspecialchars($lp['subject']) ?></div>
        </div>
        <div class="lp-meta-cell">
          <div class="lp-meta-lbl">Topic</div>
          <div class="lp-meta-val"><?= htmlspecialchars($lp['topic']) ?></div>
        </div>
        <?php if (!empty($lp['strategy'])): ?>
        <div class="lp-meta-cell">
          <div class="lp-meta-lbl">Teaching Strategy</div>
          <div class="lp-meta-val"><?= htmlspecialchars($lp['strategy']) ?></div>
        </div>
        <div class="lp-meta-cell">
          <div class="lp-meta-lbl">Date Generated</div>
          <div class="lp-meta-val"><?= date('M d, Y', strtotime($lp['created_at'])) ?></div>
        </div>
        <?php endif; ?>
      </div>

      <!-- ── Format-specific sections (THE FIX) ── -->
      <form method="POST" id="lpForm">
        <?php foreach ($section_info as $key => [$ico, $label, $desc]): ?>
        <div class="lp-section">
          <div class="lp-section-head">
            <?= $ico ?>&nbsp;<?= htmlspecialchars($label) ?>
          </div>
          <textarea class="lp-section-body" name="<?= $key ?>"
            oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px';"
            placeholder="<?= htmlspecialchars($desc) ?>"><?= htmlspecialchars($lp[$key] ?? '') ?></textarea>
        </div>
        <?php endforeach; ?>
      </form>

    </div><!-- /lp-view-doc -->
    <!-- NOTE: tinanggal na ang pangalawang/duplicate na set ng
         Save/Back/Export buttons na dati nasa ibaba ng document.
         Iisa lang ang lugar ng buttons ngayon — sa taas. -->

  </div><!-- /lp-view-wrap -->
</div>
</div>

<script>
// ── EXPORT DROPDOWN MENU ─────────────────────────────────────
// Pinipindot ang "Export" button para buksan/isara ang menu
// na may dalawang choices: PDF o DOCX.
function toggleExportMenu(e) {
  e.stopPropagation();
  const menu = document.getElementById('export-menu');
  menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
}
document.addEventListener('click', () => {
  const menu = document.getElementById('export-menu');
  if (menu) menu.style.display = 'none';
});

let dirty = false;
document.querySelectorAll('textarea').forEach(t => {
  t.style.height = 'auto';
  t.style.height = t.scrollHeight + 'px';
  t.addEventListener('input', () => dirty = true);
});
document.getElementById('lpForm').addEventListener('submit', () => dirty = false);
window.addEventListener('beforeunload', e => {
  if (dirty) { e.preventDefault(); e.returnValue = ''; }
});
</script>

<?php include '../../includes/footer.php'; ?>