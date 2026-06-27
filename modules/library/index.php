<?php
// modules/library/index.php — GG-012,016: Resource Library
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();
$tid = $_SESSION['teacher_id'];

$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$grade  = mysqli_real_escape_string($conn, $_GET['grade']  ?? '');
$subj   = mysqli_real_escape_string($conn, $_GET['subject'] ?? '');
$filter = $_GET['filter'] ?? '';

// handle both NULL and '0000-00-00 00:00:00' for deleted_at
$where = "teacher_id='$tid' AND is_saved=1
          AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";

if ($search) $where .= " AND (title LIKE '%$search%' OR subject LIKE '%$search%' OR topic LIKE '%$search%')";
if ($grade)  $where .= " AND grade_level='$grade'";
if ($subj)   $where .= " AND subject='$subj'";
// Handle exported filter from dashboard click
if ($filter === 'exported') $where .= " AND export_count > 0";

$plans = mysqli_query($conn, "SELECT * FROM lesson_plans WHERE $where ORDER BY updated_at DESC");

// Move to Trash (soft delete)
if (isset($_POST['delete_id'])) {
    $did = (int)$_POST['delete_id'];
    mysqli_query($conn, "UPDATE lesson_plans SET deleted_at=NOW() WHERE id='$did' AND teacher_id='$tid'");
    $_SESSION['flash_success'] = 'Lesson plan moved to Trash. You can restore it anytime.';
    header("Location: index.php"); exit();
}

// Duplicate — with fallback for tables that may not have curriculum/format/strategy columns
if (isset($_POST['dup_id'])) {
    $dup = (int)$_POST['dup_id'];
    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM lesson_plans WHERE id='$dup' AND teacher_id='$tid'"));
    if ($row) {
        $t = mysqli_real_escape_string($conn, "Copy of " . $row['title']);

        // Try with all columns first
        $ok = @mysqli_query($conn, "INSERT INTO lesson_plans
            (teacher_id, title, grade_level, subject, topic,
             curriculum, format, strategy,
             learning_objectives, materials_needed, introduction_motivation,
             lesson_body, learning_activities, assessment, closure,
             is_saved, created_at, updated_at)
            SELECT teacher_id, '$t', grade_level, subject, topic,
             COALESCE(curriculum, 'MATATAG'), COALESCE(format, 'DLP'), COALESCE(strategy, ''),
             learning_objectives, materials_needed, introduction_motivation,
             lesson_body, learning_activities, assessment, closure,
             1, NOW(), NOW()
            FROM lesson_plans WHERE id='$dup'");

        // If failed (columns don't exist yet), fallback without extra columns
        if (!$ok) {
            $ok = mysqli_query($conn, "INSERT INTO lesson_plans
                (teacher_id, title, grade_level, subject, topic,
                 learning_objectives, materials_needed, introduction_motivation,
                 lesson_body, learning_activities, assessment, closure,
                 is_saved, created_at, updated_at)
                SELECT teacher_id, '$t', grade_level, subject, topic,
                 learning_objectives, materials_needed, introduction_motivation,
                 lesson_body, learning_activities, assessment, closure,
                 1, NOW(), NOW()
                FROM lesson_plans WHERE id='$dup'");
        }

        if ($ok) {
            $_SESSION['flash_success'] = 'Lesson plan duplicated! It now appears at the top of your library.';
        } else {
            $_SESSION['flash_error'] = 'Duplication failed: ' . mysqli_error($conn);
        }
    }
    header("Location: index.php"); exit();
}

$page_title = "Resource Library — Gurong GabAI";
$active_nav = 'library';
$grades   = ['Kinder','Grade 1','Grade 2','Grade 3','Grade 4','Grade 5','Grade 6',
             'Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12'];
$subjects = ['Filipino','English','Mathematics','Science','Araling Panlipunan',
             'MAPEH','ESP','TLE','Mother Tongue'];
include '../../includes/header.php';
?>
<div class="app-shell">
<?php include '../../includes/sidenav.php'; ?>
<div class="main">

  <?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success" style="max-width:900px;margin-bottom:12px;">
      <?= htmlspecialchars($_SESSION['flash_success']) ?>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>
  <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-error" style="max-width:900px;margin-bottom:12px;">
      <?= htmlspecialchars($_SESSION['flash_error']) ?>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>

  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:4px;">
    <div>
      <div class="page-title" style="margin-bottom:0;">Resource Library</div>
      <?php if ($filter === 'exported'): ?>
        <div class="page-sub" style="margin-bottom:0;">
          Showing exported lesson plans ·
          <a href="index.php" style="font-size:12px;">View all</a>
        </div>
      <?php else: ?>
        <div class="page-sub" style="margin-bottom:0;">All your saved lesson plans in one place.</div>
      <?php endif; ?>
    </div>
    <a href="../generator/index.php" class="btn btn-blue btn-sm">Generate New</a>
  </div>

  <div style="margin-bottom:16px;margin-top:16px;">
    <form method="GET">
      <div class="search-wrap">
        <i class="bi bi-search" style="color:var(--color-text-secondary);font-size:13px;"></i>
        <input name="search" placeholder="Search by title, subject, or topic..."
               value="<?= htmlspecialchars($search) ?>">
        <select name="grade" style="border:none;outline:none;font-family:inherit;font-size:13px;
                color:var(--color-text-secondary);background:transparent;cursor:pointer;">
          <option value="">All Grades</option>
          <?php foreach ($grades as $g): ?>
            <option value="<?= $g ?>" <?= $grade===$g?'selected':''?>><?= $g ?></option>
          <?php endforeach; ?>
        </select>
        <select name="subject" style="border:none;outline:none;font-family:inherit;font-size:13px;
                color:var(--color-text-secondary);background:transparent;cursor:pointer;">
          <option value="">All Subjects</option>
          <?php foreach ($subjects as $s): ?>
            <option value="<?= $s ?>" <?= $subj===$s?'selected':''?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-blue btn-sm">Filter</button>
        <?php if ($search||$grade||$subj||$filter): ?>
          <a href="index.php" class="btn btn-outline btn-sm">Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>Title</th>
            <th>Grade</th>
            <th>Subject</th>
            <th>Format</th>
            <th>Last Updated</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php
        // Tama na ngayon ang format na makikita dito. Kahit ano pang format pinili
        // (ILAW, 4As, 5Es, atbp.) noong gumawa ng lesson plan.
        $count = 0; while ($row = mysqli_fetch_assoc($plans)): $count++; ?>
        <tr>
          <td>
            <div class="name"><?= htmlspecialchars($row['title']) ?></div>
            <?php if (!empty($row['topic'])): ?>
              <div style="font-size:11px;color:var(--color-text-secondary);margin-top:2px;">
                <?= htmlspecialchars($row['topic']) ?>
              </div>
            <?php endif; ?>
          </td>
          <td><span class="badge b-saved"><?= htmlspecialchars($row['grade_level']) ?></span></td>
          <td style="font-size:13px;"><?= htmlspecialchars($row['subject']) ?></td>
          <td style="font-size:11px;color:var(--color-text-secondary);">
            <?php
            // Format ng lesson plan (e.g. ILAW, DLP, 4As, 5Es)
            echo htmlspecialchars($row['format'] ?? 'DLP');
            // Curriculum basis sa ibaba ng format, kung meron
            if (!empty($row['curriculum'])): ?>
              <div style="font-size:10px;color:var(--color-text-tertiary);margin-top:2px;">
                <?= htmlspecialchars($row['curriculum']) ?>
              </div>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--color-text-secondary);">
            <?= date('M d, Y', strtotime($row['updated_at'])) ?>
          </td>
          <td>
            <div class="acts">
              <a href="../generator/view.php?id=<?= $row['id'] ?>" class="act act-edit">
                View/Edit
              </a>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="dup_id" value="<?= $row['id'] ?>">
                <button class="act act-dup" type="submit">Duplicate</button>
              </form>
              <a href="../export/pdf.php?id=<?= $row['id'] ?>"
                 class="act" style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;"
                 target="_blank">PDF</a>
              <a href="../export/docx.php?id=<?= $row['id'] ?>"
                 class="act" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;">
                DOCX
              </a>
              <form method="POST" style="display:inline;"
                    onsubmit="return confirm('Move this lesson plan to Trash? You can restore it from Trash anytime.')">
                <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                <button class="act act-del" type="submit">Move to Trash</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
        <?php if ($count === 0): ?>
        <tr><td colspan="6">
          <div class="empty-state">
            <div class="ico"><i class="bi bi-collection" style="font-size:36px;opacity:.3;"></i></div>
            <h3><?= ($search||$grade||$subj||$filter) ? 'No results found' : 'No saved lesson plans yet' ?></h3>
            <p><?= ($search||$grade||$subj||$filter)
                ? 'Try different search terms or <a href="index.php">clear the filters</a>.'
                : 'Generate your first lesson plan to get started!' ?></p>
          </div>
        </td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</div>
<?php include '../../includes/footer.php'; ?>