<?php
// ============================================================
// FILE: modules/auth/reset.php
// PURPOSE: Reset Password page — dito nagre-reset ng password
//          ang teacher pagkatapos i-click ang reset link sa email.
//
// IAS SECURITY CONTROL:
//   Control #7 — Secure Token-Based Password Reset
//   Control #1 — BCrypt Password Hashing (sa new password)
//
// FLOW:
//   1. Kunin ang token mula sa URL (?token=...)
//   2. Hanapin ang token sa database — valid, unused, at hindi expired
//   3. Kung invalid/expired — ipakita ang error
//   4. Kung valid at nag-submit ng form:
//      a. Validate ang bagong password
//      b. Hash gamit ang BCrypt
//      c. I-update ang password sa database
//      d. Mark ang token bilang used (single-use)
//      e. I-redirect sa login
// ============================================================

require_once '../../config/session.php';
require_once '../../config/db.php';

// FIX: Para sa consistency sa LAHAT ng auth pages (login, register,
// forgot) — kung naka-login na ang user, i-redirect papuntang
// dashboard imbes na hayaan silang manatili sa isang auth page.
if (isset($_SESSION['teacher_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin'
        ? '../admin/dashboard.php'
        : '../dashboard/index.php'));
    exit();
}

$error = $success = '';
$token = $_GET['token'] ?? ''; // Kunin ang token mula sa URL

if (!$token) {
    $error = "Invalid reset link.";
    $reset = null;

} else {
    // Hanapin ang token sa database
    // Conditions:
    //   - token = ang token sa URL
    //   - is_used = 0 (hindi pa ginamit — single-use enforcement)
    $stmt = mysqli_prepare($conn,
        "SELECT * FROM password_resets WHERE token=? AND is_used=0 LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $reset = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$reset) {
        // Token hindi mahanap o ginamit na
        $error = "This reset link is invalid or has already expired.";

    } else {
        // ── EXPIRY CHECK ──────────────────────────────────────
        // I-compare ang expiry time sa database vs current time
        $expires_at = strtotime($reset['expires_at']);
        $now        = time() + (8 * 3600); // Philippine Time

        if ($now > $expires_at) {
            // Expired na ang reset link (lampas na sa 30 minuto)
            $error = "This reset link is invalid or has already expired.";
            $reset = null;
        }
    }
}

// ── PROCESS NEW PASSWORD FORM ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {

    $pw = $_POST['password']         ?? '';
    $cf = $_POST['confirm_password'] ?? '';

    if (!$pw || !$cf)
        $error = "All fields are required.";
    elseif (strlen($pw) < 8)
        $error = "Password must be at least 8 characters.";
    elseif ($pw !== $cf)
        $error = "Passwords do not match.";
    else {
        // ── IAS SECURITY CONTROL #1: BCRYPT HASHING ──────────
        // Hash ang bagong password bago i-save — katulad ng sa registration
        $hash  = password_hash($pw, PASSWORD_BCRYPT);
        $email = $reset['email'];

        // I-update ang password ng teacher sa database
        $upd = mysqli_prepare($conn, "UPDATE teachers SET password=? WHERE email=?");
        mysqli_stmt_bind_param($upd, "ss", $hash, $email);
        mysqli_stmt_execute($upd);

        // ── SINGLE-USE ENFORCEMENT ────────────────────────────
        // Mark ang token bilang used — hindi na pwedeng gamitin ulit
        // Kahit may attacker na nakuha ang link, hindi na siya valid
        $used = mysqli_prepare($conn, "UPDATE password_resets SET is_used=1 WHERE token=?");
        mysqli_stmt_bind_param($used, "s", $token);
        mysqli_stmt_execute($used);

        $success = "Password updated! You can now log in with your new password.";
    }
}

$page_title = "Reset Password — Gurong GabAI";
include '../../includes/header.php';
?>

<div class="auth-bg">
  <div class="auth-orb orb1"></div>
  <div class="auth-orb orb2"></div>
  <div class="auth-orb orb3"></div>
  <div class="glass-card">
    <div class="glass-logo">
      <div class="glass-logo-icon">🎓</div>
      <span>Gurong <em>GabAI</em></span>
    </div>
    <h1 class="glass-h1">Reset password</h1>
    <p class="glass-sub">Enter your new password below.</p>

    <?php if ($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
      <a href="login.php" class="glass-btn" style="display:block;text-align:center;text-decoration:none;margin-top:8px;">Go to Login →</a>
    <?php elseif ($reset): ?>
      <form method="POST">
        <div class="glass-field">
          <label>New Password</label>
          <input class="glass-input" type="password" name="password" placeholder="Minimum 8 characters" required>
        </div>
        <div class="glass-field">
          <label>Confirm New Password</label>
          <input class="glass-input" type="password" name="confirm_password" placeholder="Re-enter new password" required>
        </div>
        <button type="submit" class="glass-btn">Change Password →</button>
      </form>
    <?php endif; ?>

    <div class="glass-footer"><a href="login.php">← Back to Sign In</a></div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>