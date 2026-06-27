<?php
// modules/history/index.php — GG-032: Generated History
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();
$tid = $_SESSION['teacher_id'];

// ── MOVE TO TRASH (SOFT DELETE) ─────────────────────────────
// Ginawa itong SOFT DELETE — nilalagyan lang ng timestamp
// ang deleted_at column, kaya napupunta lang ito sa Trash page at
// pwede pang i-restore anumang oras. Pareho ito ng ginagawa ng
// "Move to Trash" button sa Resource Library
if (isset($_POST['delete_id'])) {
    $did = (int)$_POST['delete_id'];
    mysqli_query($conn, "UPDATE lesson_plans SET deleted_at=NOW() WHERE id='$did' AND teacher_id='$tid'");
    $_SESSION['flash_success'] = 'Lesson plan moved to Trash. You can restore it anytime.';
    header("Location: index.php"); exit();
}
// Iisa lang ang lugar ng Duplicate action ngayon para mas
// simple at hindi nalilito ang teacher kung saan ito gagamitin.

$per_page = 15;
$page     = max(1, (int)($_GET['p'] ?? 1));
$offset   = ($page - 1) * $per_page;
// NOTE: hindi na isinasama dito ang mga naka-Trash na plans
// (deleted_at IS NOT NULL) — makikita na lang sila sa Trash page.
$total    = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM lesson_plans
     WHERE teacher_id='$tid' AND deleted_at IS NULL"))['c'];
$pages    = ceil($total / $per_page);
$plans    = mysqli_query($conn,
    "SELECT * FROM lesson_plans
     WHERE teacher_id='$tid' AND deleted_at IS NULL
     ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");

$page_title = "History — Gurong GabAI";
$active_nav = 'history';
include '../../includes/header.php';
?>
<div class="app-shell">
<?php include '../../includes/sidenav.php'; ?>
<div class="main">

  <?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success" style="max-width:860px;margin-bottom:12px;">✅ <?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>

  <div class="page-title">🕐 Generated History</div>
  <div class="page-sub">Complete record of all your generated lesson plans (<?= $total ?> total).</div>

  <div class="card">
    <?php if ($total === 0): ?>
      <div class="card-body">
        <div class="empty-state">
          <div class="ico">🕐</div>
          <h3>No history yet</h3>
          <p>Your generated lesson plans will appear here once you start generating.</p>
        </div>
      </div>
    <?php else: ?>
      <?php while ($row = mysqli_fetch_assoc($plans)): ?>
      <div class="hist-item">
        <div class="hist-info">
          <div class="hist-title"><?= htmlspecialchars($row['title']) ?></div>
          <div class="hist-meta">
            <span><?= htmlspecialchars($row['grade_level']) ?></span>
            · <span><?= htmlspecialchars($row['subject']) ?></span>
            <?php if (!empty($row['format'])): ?>· <span><?= htmlspecialchars($row['format']) ?></span><?php endif; ?>
            <?php if (!empty($row['curriculum'])): ?>· <span><?= htmlspecialchars($row['curriculum']) ?></span><?php endif; ?>
            · <span><?= date('M d, Y \a\t g:i A', strtotime($row['created_at'])) ?></span>
            · <span class="badge <?= $row['is_saved'] ? 'b-saved' : 'b-pending' ?>"><?= $row['is_saved'] ? 'Saved' : 'Unsaved' ?></span>
          </div>
        </div>
        <div class="hist-acts">
          <a href="../generator/view.php?id=<?= $row['id'] ?>" class="act act-edit">View</a>
          <form method="POST" style="display:inline;" onsubmit="return confirm('Move this lesson plan to Trash? You can restore it anytime from the Trash page.');">
            <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
            <button class="act act-del" type="submit">Move to Trash</button>
          </form>
        </div>
      </div>
      <?php endwhile; ?>

      <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a href="?p=<?= $i ?>" class="pg-btn <?= $i === $page ? 'on' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>