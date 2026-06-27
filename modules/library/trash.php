<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();
$tid = $_SESSION['teacher_id'];

if (isset($_POST['restore_id'])) {
    $rid = (int)$_POST['restore_id'];
    mysqli_query($conn, "UPDATE lesson_plans SET deleted_at=NULL WHERE id='$rid' AND teacher_id='$tid'");
    $_SESSION['flash_success'] = 'Lesson plan restored to your Library!';
    header("Location: trash.php"); exit();
}
if (isset($_POST['perm_delete_id'])) {
    $pid = (int)$_POST['perm_delete_id'];
    mysqli_query($conn, "DELETE FROM lesson_plans WHERE id='$pid' AND teacher_id='$tid' AND deleted_at IS NOT NULL");
    $_SESSION['flash_success'] = 'Lesson plan permanently deleted.';
    header("Location: trash.php"); exit();
}
if (isset($_POST['empty_trash'])) {
    mysqli_query($conn, "DELETE FROM lesson_plans WHERE teacher_id='$tid' AND deleted_at IS NOT NULL");
    $_SESSION['flash_success'] = 'Trash emptied successfully.';
    header("Location: trash.php"); exit();
}

$plans      = mysqli_query($conn, "SELECT * FROM lesson_plans WHERE teacher_id='$tid' AND deleted_at IS NOT NULL ORDER BY deleted_at DESC");
$trashCount = mysqli_num_rows($plans);
$page_title = "Trash — Gurong GabAI";
$active_nav = 'trash'; 
include '../../includes/header.php';
?>
<div class="app-shell">
<?php include '../../includes/sidenav.php'; ?>
<div class="main">

  <?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success" style="max-width:860px;margin-bottom:12px;">
      <?= htmlspecialchars($_SESSION['flash_success']) ?>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>

  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:4px;">
    <div>
      <div class="page-title" style="margin-bottom:0;">Trash</div>
      <div class="page-sub">
        <?= $trashCount ?> deleted lesson plan(s) · Restore or permanently delete.
      </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="index.php" class="btn btn-outline btn-sm">← Back to Library</a>
      <?php if ($trashCount > 0): ?>
      <form method="POST" onsubmit="return confirm('Empty trash? All plans will be permanently deleted and cannot be recovered.')">
        <button name="empty_trash" class="btn btn-sm"
          style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;">
          Empty Trash
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>Title</th>
            <th>Grade</th>
            <th>Subject</th>
            <th>Date Deleted</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php
        mysqli_data_seek($plans, 0);
        $c = 0;
        while ($row = mysqli_fetch_assoc($plans)): $c++;
        ?>
        <tr>
          <td>
            <div class="name" style="color:var(--color-text-secondary);">
              <?= htmlspecialchars($row['title']) ?>
            </div>
            <?php if (!empty($row['topic'])): ?>
              <div style="font-size:11px;color:var(--color-text-secondary);margin-top:2px;">
                <?= htmlspecialchars($row['topic']) ?>
              </div>
            <?php endif; ?>
          </td>
          <td><span class="badge b-rejected"><?= htmlspecialchars($row['grade_level']) ?></span></td>
          <td style="font-size:13px;"><?= htmlspecialchars($row['subject']) ?></td>
          <td style="font-size:12px;color:var(--color-text-secondary);">
            <?= date('M d, Y', strtotime($row['deleted_at'])) ?>
          </td>
          <td>
            <div class="acts">
              <form method="POST" style="display:inline;">
                <input type="hidden" name="restore_id" value="<?= $row['id'] ?>">
                <button class="act act-ok">Restore</button>
              </form>
              <form method="POST" style="display:inline;"
                    onsubmit="return confirm('Permanently delete this lesson plan? This CANNOT be undone.')">
                <input type="hidden" name="perm_delete_id" value="<?= $row['id'] ?>">
                <button class="act act-del">Delete Forever</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
        <?php if ($c === 0): ?>
        <tr>
          <td colspan="5">
            <div class="empty-state">
              <div class="ico" style="font-size:32px;opacity:.3;">—</div>
              <h3>Trash is empty</h3>
              <p>Deleted lesson plans will appear here and can be restored anytime.</p>
            </div>
          </td>
        </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</div>
<?php include '../../includes/footer.php'; ?>