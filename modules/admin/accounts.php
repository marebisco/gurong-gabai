<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireAdmin();
$tid = $_SESSION['teacher_id'];

$search = mysqli_real_escape_string($conn, $_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';

$where = "role='teacher'";
if (in_array($status, ['pending','approved','rejected','deactivated'])) $where .= " AND status='$status'";
if ($search) $where .= " AND (full_name LIKE '%$search%' OR email LIKE '%$search%' OR school_name LIKE '%$search%')";

$teachers = mysqli_query($conn, "SELECT * FROM teachers WHERE $where ORDER BY created_at DESC");
$counts   = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT
        COUNT(*) total,
        SUM(status='pending') pending,
        SUM(status='approved') approved,
        SUM(status='rejected') rejected,
        SUM(status='deactivated') deactivated
     FROM teachers WHERE role='teacher'"));

$page_title = "Account Management — Gurong GabAI";
$active_nav = 'accounts';
include '../../includes/header.php';
?>
<div class="app-shell">
<?php include '../../includes/admin_sidenav.php'; ?>
<div class="main">
  <?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>
  <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>

  <div class="page-title">👥 Account Management</div>
  <div class="page-sub">Manage all registered teacher accounts.</div>

  <!-- Filter pills -->
  <div class="filter-row">
    <?php foreach(['all'=>'All ('.$counts['total'].')','pending'=>'Pending ('.$counts['pending'].')','approved'=>'Approved ('.$counts['approved'].')','rejected'=>'Rejected ('.$counts['rejected'].')','deactivated'=>'Deactivated ('.$counts['deactivated'].')'] as $val=>$lbl): ?>
      <a href="?status=<?=$val?>&q=<?=urlencode($search)?>" class="fpill <?=$status===$val?'on':''?>"><?=$lbl?></a>
    <?php endforeach; ?>
  </div>

  <!-- Search -->
  <form method="GET" style="margin-bottom:16px;">
    <input type="hidden" name="status" value="<?=htmlspecialchars($status)?>">
    <div class="search-bar">
      <span>🔍</span>
      <input name="q" placeholder="Search by name, email, or school..." value="<?=htmlspecialchars($search)?>">
      <button type="submit" class="btn btn-blue btn-sm">Search</button>
      <?php if($search): ?><a href="?status=<?=$status?>" class="btn btn-outline btn-sm">✕</a><?php endif; ?>
    </div>
  </form>

  <div class="card">
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Teacher</th><th>School</th><th>Email</th><th>Status</th><th>Registered</th><th>Actions</th></tr></thead>
        <tbody>
        <?php $c=0; while($row=mysqli_fetch_assoc($teachers)): $c++; ?>
        <tr>
          <td>
            <div class="name"><?=htmlspecialchars($row['full_name'])?></div>
          </td>
          <td style="font-size:12px;"><?=htmlspecialchars($row['school_name'])?></td>
          <td>
            <!-- Clickable email — opens Gmail/email app directly -->
            <a href="mailto:<?=htmlspecialchars($row['email'])?>"
               style="color:var(--blue);font-size:13px;display:flex;align-items:center;gap:4px;"
               title="Send email to this teacher">
              ✉️ <?=htmlspecialchars($row['email'])?>
            </a>
          </td>
          <td><span class="badge b-<?=$row['status']?>"><?=ucfirst($row['status'])?></span></td>
          <td style="font-size:12px;color:var(--color-text-secondary);"><?=date('M d, Y',strtotime($row['created_at']))?></td>
          <td>
            <div class="acts" style="flex-wrap:wrap;">
              <?php if($row['status']==='pending'): ?>
                <form method="POST" action="action.php" style="display:inline;" onsubmit="return confirm('Approve <?=htmlspecialchars($row['full_name'])?>?')">
                  <input type="hidden" name="action" value="approve"><input type="hidden" name="user_id" value="<?=$row['id']?>">
                  <button class="act act-ok">✓ Approve</button>
                </form>
                <form method="POST" action="action.php" style="display:inline;" onsubmit="return confirm('Reject this account?')">
                  <input type="hidden" name="action" value="reject"><input type="hidden" name="user_id" value="<?=$row['id']?>">
                  <button class="act act-no">✗ Reject</button>
                </form>
              <?php elseif($row['status']==='approved'): ?>
                <form method="POST" action="action.php" style="display:inline;" onsubmit="return confirm('Deactivate this account?')">
                  <input type="hidden" name="action" value="deactivate"><input type="hidden" name="user_id" value="<?=$row['id']?>">
                  <button class="act" style="background:#fef9c3;color:#92400e;border:1px solid #fde68a;">⏸ Deactivate</button>
                </form>
              <?php elseif($row['status']==='deactivated'): ?>
                <form method="POST" action="action.php" style="display:inline;" onsubmit="return confirm('Reactivate this account?')">
                  <input type="hidden" name="action" value="reactivate"><input type="hidden" name="user_id" value="<?=$row['id']?>">
                  <button class="act act-ok">▶ Reactivate</button>
                </form>
              <?php endif; ?>
              <form method="POST" action="action.php" style="display:inline;" onsubmit="return confirm('PERMANENTLY delete <?=htmlspecialchars($row['full_name'])?>? This cannot be undone.')">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?=$row['id']?>">
                <button class="act act-del">🗑 Delete</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endwhile; if($c===0): ?>
        <tr><td colspan="6"><div class="empty-state"><div class="ico">👥</div><h3>No accounts found</h3></div></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div></div>
<?php include '../../includes/footer.php'; ?>