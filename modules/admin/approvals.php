<?php
// ============================================================
// FILE: modules/admin/approvals.php
// PURPOSE: Admin page para mag-approve o mag-reject ng
//          pending teacher registrations.
//
// IAS SECURITY CONTROLS NA NARITO:
//   Control #3 — Role-Based Access Control (requireAdmin)
//   Control #6 — Admin Approval Workflow
//
// FLOW:
//   1. requireAdmin() — sinisigurado na admin lang ang makakakita
//   2. Kung nag-POST (approve o reject):
//      a. I-update ang status ng teacher sa database
//      b. Mag-send ng email notification sa teacher
//   3. I-display ang listahan ng teachers base sa filter
//      (pending / approved / rejected / all)
// ============================================================

require_once '../../config/session.php'; // Session + RBAC functions
require_once '../../config/db.php';      // Database connection
require_once '../../config/mailer.php';  // Para sa email notifications

// ── IAS SECURITY CONTROL #3: ROLE-BASED ACCESS CONTROL ──────
// Tinatawag ito sa SIMULA ng page — bago pa man mag-load ang content.
// Kung hindi admin ang naka-login, i-redirect agad sa login page.
// Teacher accounts ay HINDI makaka-access ng page na ito.
requireAdmin();

$message = '';

// ── PROCESS APPROVE / REJECT ACTION ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // (int) casting — security measure para masigurado na
    // integer ang teacher_id at hindi pwedeng mag-inject ng SQL
    $tid_act = (int)$_POST['teacher_id'];
    $action  = $_POST['action'];

    // Kunin ang info ng teacher para sa email notification
    $t = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM teachers WHERE id='$tid_act'"));

    if ($action === 'approve') {

        // ── IAS SECURITY CONTROL #6: ADMIN APPROVAL ──────────
        // I-activate ang account — maaaring mag-login na ang teacher
        mysqli_query($conn,
            "UPDATE teachers SET status='approved' WHERE id='$tid_act'");

        // Mag-send ng approval email sa teacher
        // Para malaman niya na pwede na siyang mag-login
        sendEmail(
            $t['email'],
            $t['full_name'],
            "Gurong GabAI — Account Approved!",
            "<p>Hello <b>{$t['full_name']}</b>,</p>
             <p>Your account has been <b style='color:green;'>approved</b>!
             You can now log in.</p>
             <p><a href='http://localhost/gurong-gabai/modules/auth/login.php'>
             Login here →</a></p>"
        );
        $message = "Account approved successfully.";

    } elseif ($action === 'reject') {

        // I-mark ang account bilang rejected — hindi na makapag-login
        mysqli_query($conn,
            "UPDATE teachers SET status='rejected' WHERE id='$tid_act'");

        // Mag-send ng rejection email sa teacher
        sendEmail(
            $t['email'],
            $t['full_name'],
            "Gurong GabAI — Account Update",
            "<p>Hello <b>{$t['full_name']}</b>,</p>
             <p>Your account registration has been
             <b style='color:red;'>rejected</b>.</p>
             <p>Contact support if you believe this is a mistake.</p>"
        );
        $message = "Account rejected.";
    }
}

// ── FILTER LOGIC ─────────────────────────────────────────────
// Ang filter ay nagdedetermina kung anong teachers ang ipapakita:
// pending = naghihintay ng approval
// approved = na-approve na
// rejected = na-reject na
// all = lahat
$filter = $_GET['filter'] ?? 'pending'; // Default: pending

// Whitelist validation — tinitiyak na valid ang filter value
// para maiwasan ang SQL injection sa WHERE clause
if (!in_array($filter, ['all', 'pending', 'approved', 'rejected'])) {
    $filter = 'pending';
}

// Gumawa ng WHERE clause base sa filter
$where = "role='teacher'" . ($filter !== 'all' ? " AND status='$filter'" : '');

// Kunin ang listahan ng teachers base sa filter
$teachers = mysqli_query($conn,
    "SELECT * FROM teachers WHERE $where ORDER BY created_at DESC");

$page_title = "Approvals — Gurong GabAI";
$active_nav = 'approvals';
include '../../includes/header.php';
?>

<!-- ── HTML: APPROVALS PAGE LAYOUT ── -->
<div class="app-shell">
<?php include '../../includes/admin_sidenav.php'; ?>
<div class="main">
    <div class="page-title">Registration Approvals</div>
    <div class="page-sub">Review and manage pending teacher registrations.</div>

    <!-- Success message pagkatapos mag-approve o mag-reject -->
    <?php if ($message): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Filter tabs — para madaling makita ang bawat category -->
    <div class="filter-row">
        <?php foreach ([
            'pending'  => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'all'      => 'All'
        ] as $f => $l): ?>
            <a href="?filter=<?= $f ?>"
               class="fpill <?= $filter === $f ? 'on' : '' ?>">
                <?= $l ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Table ng teachers -->
    <div class="card">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Teacher</th>
                    <th>School</th>
                    <th>Email</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php $c = 0; while ($row = mysqli_fetch_assoc($teachers)): $c++; ?>
            <tr>
                <!-- htmlspecialchars() sa lahat ng output — IAS: XSS prevention -->
                <td><div class="name"><?= htmlspecialchars($row['full_name']) ?></div></td>
                <td><?= htmlspecialchars($row['school_name']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                <td>
                    <span class="badge b-<?= $row['status'] ?>">
                        <?= ucfirst($row['status']) ?>
                    </span>
                </td>
                <td>
                    <!-- Approve at Reject buttons — visible lang kung pending -->
                    <?php if ($row['status'] === 'pending'): ?>
                    <div class="acts">
                        <!-- Separate forms para sa approve at reject -->
                        <!-- confirm() dialog — nagtatanong muna bago mag-action -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="teacher_id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button class="act act-ok"
                                    onclick="return confirm('Approve this account?')">
                                Approve
                            </button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="teacher_id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <button class="act act-no"
                                    onclick="return confirm('Reject this account?')">
                                Reject
                            </button>
                        </form>
                    </div>
                    <?php else: ?>
                        <!-- Walang action kung hindi pending na -->
                        <span style="color:var(--text-3);font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>

            <!-- Empty state kung walang results -->
            <?php if ($c === 0): ?>
            <tr>
                <td colspan="6">
                    <div class="empty-state">
                        <div class="ico">✅</div>
                        <h3>No <?= $filter === 'all' ? '' : $filter ?> registrations</h3>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>