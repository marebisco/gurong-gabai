<?php
// ============================================================
// FILE: modules/admin/dashboard.php
// PURPOSE: Admin Dashboard — nagpapakita ng system-wide
//          statistics at quick links sa admin features.
//          UPDATED: Clickable stats cards para makita agad
//          ang related list kapag na-click ang stat.
//
// IAS SECURITY CONTROL:
//   Control #3 — Role-Based Access Control (requireAdmin)
//
// DATA DISPLAYED:
//   - Total registered teachers
//   - Pending approvals count
//   - Approved accounts count
//   - Rejected accounts count
//   - Total lesson plans generated (count lang — walang content)
//
// PRIVACY NOTE: Admin CANNOT view the actual content of
//   lesson plans — privacy ng teachers ay protektado.
//   Nakikita lang niya ang count, hindi ang content.
// ============================================================

require_once '../../config/session.php'; // Session + RBAC
require_once '../../config/db.php';      // Database connection

// ── IAS SECURITY CONTROL #3: ROLE-BASED ACCESS CONTROL ──────
// Tinatawag bago pa man mag-load ang kahit anong content.
// Kung hindi admin ang naka-login — i-redirect agad sa login.
requireAdmin();

// ── FETCH SYSTEM-WIDE STATISTICS ────────────────────────────
// Bawat query ay nagco-count ng specific na grupo ng teachers.
// mysqli_fetch_assoc()['c'] — kinukuha ang COUNT(*) result

// Total na registered teachers (hindi kasama ang admin)
$total_teachers = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM teachers WHERE role='teacher'"))['c'];

// Teachers na naghihintay ng approval
$pending = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM teachers WHERE role='teacher' AND status='pending'"))['c'];

// Teachers na na-approve at active na
$approved = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM teachers WHERE role='teacher' AND status='approved'"))['c'];

// Teachers na na-reject
$rejected = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM teachers WHERE role='teacher' AND status='rejected'"))['c'];

// Total na lesson plans na na-generate sa buong system
// COUNT lang — hindi kinukuha ang actual content (privacy)
$total_plans = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM lesson_plans"))['c'];

$page_title = "Admin Dashboard — Gurong GabAI";
$active_nav = 'dashboard';
include '../../includes/header.php';
?>

<!-- ── HTML: ADMIN DASHBOARD LAYOUT ── -->
<div class="app-shell">
<?php include '../../includes/admin_sidenav.php'; ?>
<div class="main">
    <div class="page-title">Admin Dashboard</div>
    <div class="page-sub">System-wide overview and statistics.</div>

    <!-- ── STATS CARDS — CLICKABLE ──────────────────────────
         UPDATED: Bawat stat card ay naka-wrap sa <a> tag
         para ma-click at mapunta sa related page.
         Halimbawa: "Pending" card → approvals.php?filter=pending
    ─────────────────────────────────────────────────────── -->
    <div class="stats" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">

        <!-- Total Teachers → All Accounts page -->
        <a href="accounts.php" class="scard scard-link" style="text-decoration:none;">
            <div class="scard-label">Total Teachers</div>
            <div class="scard-num"><?= $total_teachers ?></div>
            <div class="scard-sub">Click to view all accounts</div>
        </a>

        <!-- Pending → Approvals page (filtered: pending) -->
        <a href="approvals.php?filter=pending" class="scard scard-link" style="text-decoration:none;">
            <div class="scard-label">Pending</div>
            <div class="scard-num" style="color:#92400E;"><?= $pending ?></div>
            <div class="scard-sub">Click to review pending</div>
        </a>

        <!-- Approved → Approvals page (filtered: approved) -->
        <a href="approvals.php?filter=approved" class="scard scard-link" style="text-decoration:none;">
            <div class="scard-label">Approved</div>
            <div class="scard-num" style="color:var(--success);"><?= $approved ?></div>
            <div class="scard-sub">Click to view approved</div>
        </a>

        <!-- Rejected → Approvals page (filtered: rejected) -->
        <a href="approvals.php?filter=rejected" class="scard scard-link" style="text-decoration:none;">
            <div class="scard-label">Rejected</div>
            <div class="scard-num" style="color:var(--error);"><?= $rejected ?></div>
            <div class="scard-sub">Click to view rejected</div>
        </a>

        <!-- Lesson Plans → Statistics page -->
        <!-- NOTE: Admin CANNOT view individual lesson plans (privacy) -->
        <!-- Nakikita lang niya ang total count — hindi ang content -->
        <a href="statistics.php" class="scard scard-link" style="text-decoration:none;">
            <div class="scard-label">Lesson Plans</div>
            <div class="scard-num" style="color:var(--blue);"><?= $total_plans ?></div>
            <div class="scard-sub">Click to view statistics</div>
        </a>
    </div>

    <!-- Quick Action Buttons — para mabilis na ma-navigate -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
        <a href="approvals.php" class="btn btn-blue">
            ✅ Pending Approvals
            <?php if ($pending > 0): ?>(<?= $pending ?>)<?php endif; ?>
        </a>
        <a href="accounts.php"  class="btn btn-outline">👥 All Accounts</a>
        <a href="statistics.php" class="btn btn-outline">📈 Statistics</a>
    </div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>