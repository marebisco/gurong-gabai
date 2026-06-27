<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();
$tid = $_SESSION['teacher_id'];

$stats = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT
        COUNT(*) AS total_generated,
        SUM(is_saved = 1 AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')) AS saved,
        COALESCE(SUM(export_count), 0) AS exported,
        SUM(deleted_at IS NOT NULL AND deleted_at != '0000-00-00 00:00:00') AS deleted
     FROM lesson_plans WHERE teacher_id = '$tid'"));

$recent = mysqli_query($conn,
    "SELECT * FROM lesson_plans
     WHERE teacher_id='$tid' AND is_saved=1
       AND (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00')
     ORDER BY updated_at DESC LIMIT 5");

$page_title = "Dashboard — Gurong GabAI";
$active_nav = 'dashboard';
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

  <div class="page-title">Dashboard</div>
  <div class="page-sub">Welcome back, <?= htmlspecialchars($_SESSION['teacher_name']) ?>!</div>

  <!-- 4 Stat Cards — ALL CLICKABLE -->
  <div class="stats" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">

    <!-- Total Generated → History page -->
    <a href="../history/index.php" style="text-decoration:none;">
      <div class="scard" style="cursor:pointer;">
        <div class="scard-label">Total Generated</div>
        <div class="scard-num"><?= (int)$stats['total_generated'] ?></div>
        <div class="scard-sub">Click to view history</div>
        <div class="scard-bar"><div class="scard-bar-fill" style="width:100%;"></div></div>
      </div>
    </a>

    <!-- Saved to Library → Library page -->
    <a href="../library/index.php" style="text-decoration:none;">
      <div class="scard" style="border-top:3px solid #059669;cursor:pointer;">
        <div class="scard-label">Saved to Library</div>
        <div class="scard-num" style="color:#059669;"><?= (int)$stats['saved'] ?></div>
        <div class="scard-sub">Click to view library</div>
        <div class="scard-bar">
          <div class="scard-bar-fill" style="background:linear-gradient(90deg,#059669,#34d399);
            width:<?= $stats['total_generated'] > 0 ? min(100, round($stats['saved'] / $stats['total_generated'] * 100)) : 0 ?>%;">
          </div>
        </div>
      </div>
    </a>

    <!-- Exported → Library filtered by exported plans -->
    <a href="../library/index.php?filter=exported" style="text-decoration:none;">
      <div class="scard" style="border-top:3px solid #7c3aed;cursor:pointer;">
        <div class="scard-label">Exported</div>
        <div class="scard-num" style="color:#7c3aed;"><?= (int)$stats['exported'] ?></div>
        <div class="scard-sub">Click to view exported</div>
        <div class="scard-bar">
          <div class="scard-bar-fill" style="background:linear-gradient(90deg,#7c3aed,#a78bfa);
            width:<?= $stats['exported'] > 0 ? min(100, round($stats['exported'] / max((int)$stats['total_generated'], 1) * 100)) : 0 ?>%;">
          </div>
        </div>
      </div>
    </a>

    <!-- Deleted → Trash page -->
    <a href="../library/trash.php" style="text-decoration:none;">
      <div class="scard" style="border-top:3px solid #ef4444;cursor:pointer;">
        <div class="scard-label">Deleted</div>
        <div class="scard-num" style="color:#ef4444;"><?= (int)$stats['deleted'] ?></div>
        <div class="scard-sub">Click to view trash</div>
        <div class="scard-bar">
          <div class="scard-bar-fill" style="background:linear-gradient(90deg,#ef4444,#fca5a5);
            width:<?= $stats['deleted'] > 0 ? min(100, round($stats['deleted'] / max((int)$stats['total_generated'], 1) * 100)) : 0 ?>%;">
          </div>
        </div>
      </div>
    </a>

  </div>

  <!-- Recent Lesson Plans -->
  <div class="card">
    <div class="card-head">
      <h3>Recent Lesson Plans</h3>
      <a href="../library/index.php">View all →</a>
    </div>
    <div class="card-body">
      <?php $rc = 0; while ($row = mysqli_fetch_assoc($recent)): $rc++; ?>
      <a href="../generator/view.php?id=<?= $row['id'] ?>" class="list-row" style="text-decoration:none;">
        <div class="list-dot"></div>
        <div class="list-info">
          <div class="list-title"><?= htmlspecialchars($row['title']) ?></div>
          <div class="list-meta">
            <?= htmlspecialchars($row['grade_level']) ?> ·
            <?= htmlspecialchars($row['subject']) ?> ·
            <?= date('M d, Y', strtotime($row['updated_at'])) ?>
          </div>
        </div>
        <span class="list-tag"><?= htmlspecialchars($row['format'] ?? 'DLP') ?></span>
      </a>
      <?php endwhile; ?>
      <?php if ($rc === 0): ?>
      <div class="empty-state" style="padding:32px;">
        <i class="bi bi-file-earmark-text" style="font-size:32px;opacity:.3;display:block;margin-bottom:10px;"></i>
        <h3>No saved plans yet</h3>
        <p>Go to <a href="../generator/index.php">AI Generator</a> to create your first lesson plan.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>
</div>
<?php include '../../includes/footer.php'; ?>