<?php
// modules/admin/statistics.php — Admin Statistics Page
// NOTE: Admin can ONLY see aggregated statistics — NOT individual lesson plan content
// This is to protect teacher privacy (Principle of Least Privilege)
require_once '../../config/session.php';
require_once '../../config/db.php';
requireAdmin();

// ── Overall Stats ──────────────────────────────────────────
$total_teachers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM teachers WHERE role='teacher'"))['c'];
$total_approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM teachers WHERE role='teacher' AND status='approved'"))['c'];
$total_pending  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM teachers WHERE role='teacher' AND status='pending'"))['c'];
$total_rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM teachers WHERE role='teacher' AND status='rejected'"))['c'];
$total_plans    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM lesson_plans"))['c'];
$total_saved    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM lesson_plans WHERE is_saved=1"))['c'];

// ── Most Active Teachers (by generated plans count only — no content access) ──
$active_teachers = mysqli_query($conn,
    "SELECT t.full_name, t.school_name, COUNT(lp.id) as plan_count
     FROM teachers t
     LEFT JOIN lesson_plans lp ON lp.teacher_id = t.id
     WHERE t.role = 'teacher'
     GROUP BY t.id
     ORDER BY plan_count DESC
     LIMIT 10"
);

// ── Plans Generated Per Subject ──
$by_subject = mysqli_query($conn,
    "SELECT subject, COUNT(*) c FROM lesson_plans GROUP BY subject ORDER BY c DESC"
);

// ── Plans Generated Per Grade Level ──
$by_grade = mysqli_query($conn,
    "SELECT grade_level, COUNT(*) c FROM lesson_plans GROUP BY grade_level ORDER BY c DESC"
);

// ── Recent Registrations ──
$recent_regs = mysqli_query($conn,
    "SELECT full_name, school_name, email, status, created_at
     FROM teachers
     WHERE role='teacher'
     ORDER BY created_at DESC
     LIMIT 10"
);

$page_title = "Statistics — Gurong GabAI";
$active_nav = 'statistics';
include '../../includes/header.php';
?>
<div class="app-shell">
<?php include '../../includes/admin_sidenav.php'; ?>
<div class="main">
  <div class="page-title">📈 System Statistics</div>
  <div class="page-sub">System-wide overview. Individual lesson plan content is private to each teacher.</div>

  <!-- PRIVACY NOTE -->
  <div class="disclaimer">
    🔒 Admin privacy policy: Only aggregated statistics are shown here.
    Individual lesson plan content belongs to each teacher and is not accessible to the admin.
  </div>

  <!-- OVERALL STATS -->
  <div class="stats" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:24px;">
    <div class="scard">
      <div class="scard-label">Total Teachers</div>
      <div class="scard-num"><?= $total_teachers ?></div>
      <div class="scard-sub">Registered</div>
    </div>
    <div class="scard">
      <div class="scard-label">Approved</div>
      <div class="scard-num" style="color:var(--success);"><?= $total_approved ?></div>
      <div class="scard-sub">Active accounts</div>
    </div>
    <div class="scard">
      <div class="scard-label">Pending</div>
      <div class="scard-num" style="color:#92400E;"><?= $total_pending ?></div>
      <div class="scard-sub">Awaiting approval</div>
    </div>
    <div class="scard">
      <div class="scard-label">Rejected</div>
      <div class="scard-num" style="color:var(--error);"><?= $total_rejected ?></div>
      <div class="scard-sub">All time</div>
    </div>
    <div class="scard">
      <div class="scard-label">Total Plans</div>
      <div class="scard-num" style="color:var(--blue);"><?= $total_plans ?></div>
      <div class="scard-sub">Generated</div>
    </div>
    <div class="scard">
      <div class="scard-label">Saved Plans</div>
      <div class="scard-num" style="color:var(--blue);"><?= $total_saved ?></div>
      <div class="scard-sub">In libraries</div>
    </div>
  </div>

  <div class="two-col" style="grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">

    <!-- PLANS BY SUBJECT -->
    <div class="card">
      <div class="card-head"><h3>📚 Plans by Subject</h3></div>
      <div class="card-body" style="padding:0;">
        <?php if (mysqli_num_rows($by_subject) === 0): ?>
        <div class="empty-state"><div class="ico">📊</div><h3>No data yet</h3></div>
        <?php else: ?>
        <table class="tbl">
          <thead>
            <tr><th>Subject</th><th style="text-align:right;">Plans</th></tr>
          </thead>
          <tbody>
          <?php while ($row = mysqli_fetch_assoc($by_subject)): ?>
          <tr>
            <td><?= htmlspecialchars($row['subject'] ?: 'Unknown') ?></td>
            <td style="text-align:right;">
              <span class="badge b-saved"><?= $row['c'] ?></span>
            </td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- PLANS BY GRADE LEVEL -->
    <div class="card">
      <div class="card-head"><h3>🎓 Plans by Grade Level</h3></div>
      <div class="card-body" style="padding:0;">
        <?php if (mysqli_num_rows($by_grade) === 0): ?>
        <div class="empty-state"><div class="ico">📊</div><h3>No data yet</h3></div>
        <?php else: ?>
        <table class="tbl">
          <thead>
            <tr><th>Grade Level</th><th style="text-align:right;">Plans</th></tr>
          </thead>
          <tbody>
          <?php while ($row = mysqli_fetch_assoc($by_grade)): ?>
          <tr>
            <td><?= htmlspecialchars($row['grade_level'] ?: 'Unknown') ?></td>
            <td style="text-align:right;">
              <span class="badge b-saved"><?= $row['c'] ?></span>
            </td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- MOST ACTIVE TEACHERS -->
  <div class="card" style="margin-bottom:20px;">
    <div class="card-head">
      <h3>🏆 Most Active Teachers</h3>
      <span style="font-size:12px;color:var(--text-3);">By number of generated plans only</span>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (mysqli_num_rows($active_teachers) === 0): ?>
      <div class="empty-state"><div class="ico">👥</div><h3>No data yet</h3></div>
      <?php else: ?>
      <table class="tbl">
        <thead>
          <tr><th>#</th><th>Teacher</th><th>School</th><th style="text-align:right;">Plans Generated</th></tr>
        </thead>
        <tbody>
        <?php $rank = 1; while ($row = mysqli_fetch_assoc($active_teachers)): ?>
        <tr>
          <td style="color:var(--text-3);font-weight:700;"><?= $rank++ ?></td>
          <td><div class="name"><?= htmlspecialchars($row['full_name']) ?></div></td>
          <td style="color:var(--text-2);font-size:12px;"><?= htmlspecialchars($row['school_name']) ?></td>
          <td style="text-align:right;">
            <span class="badge b-saved"><?= $row['plan_count'] ?></span>
          </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- RECENT REGISTRATIONS -->
  <div class="card">
    <div class="card-head">
      <h3>🆕 Recent Registrations</h3>
      <a href="accounts.php">View all →</a>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (mysqli_num_rows($recent_regs) === 0): ?>
      <div class="empty-state"><div class="ico">👤</div><h3>No registrations yet</h3></div>
      <?php else: ?>
      <table class="tbl">
        <thead>
          <tr><th>Teacher</th><th>School</th><th>Email</th><th>Status</th><th>Registered</th></tr>
        </thead>
        <tbody>
        <?php while ($row = mysqli_fetch_assoc($recent_regs)): ?>
        <tr>
          <td><div class="name"><?= htmlspecialchars($row['full_name']) ?></div></td>
          <td style="font-size:12px;color:var(--text-2);"><?= htmlspecialchars($row['school_name']) ?></td>
          <td style="font-size:12px;"><?= htmlspecialchars($row['email']) ?></td>
          <td><span class="badge b-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
          <td style="font-size:12px;color:var(--text-3);"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

</div>
</div>
<?php include '../../includes/footer.php'; ?>