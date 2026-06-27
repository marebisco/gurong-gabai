<?php
// modules/auth/forgot.php
// PURPOSE: Forgot Password page — nagpapadala ng secure
//          reset link sa registered school email ng teacher.
//
// IAS SECURITY CONTROL:
//   Control #7 — Secure Token-Based Password Reset
//
// FLOW:
//   1. Validate email format
//   2. Hanapin ang account sa database
//   3. Kung hindi registered — ipakita ang "No account found"
//   4. Kung registered:
//      a. I-invalidate ang lahat ng lumang reset tokens
//      b. Gumawa ng cryptographically secure token
//      c. I-save sa database na may 30-min expiry
//      d. Ipadala ang reset link sa email
// ============================================================

require_once '../../config/session.php';
require_once '../../config/db.php';
require_once '../../config/mailer.php';

// FIX: Dating walang check dito kung naka-login na ang user — kaya
// kahit naka-login pa, totoong ma-a-access pa rin ang forgot.php.
// Idinagdag ang parehong redirect logic na gamit sa login.php, para
// consistent ang behavior sa LAHAT ng auth pages — kung hindi ka
// naman naka-lock-out sa account mo, hindi ka dapat napupunta sa
// "Forgot Password" page habang naka-login ka pa.
if (isset($_SESSION['teacher_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin'
        ? '../admin/dashboard.php'
        : '../dashboard/index.php'));
    exit();
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    if (!$email)
        $error = "Please enter your email.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $error = "Invalid email format.";
    else {
        // Hanapin ang account gamit ang prepared statement
        $stmt = mysqli_prepare($conn, "SELECT * FROM teachers WHERE email=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $teacher = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        // Specific error kung hindi registered ang email
        // Dati ay laging nagpapakita ng "link sent" kahit walang account
        if (!$teacher) {
            $error = "No account found with that email address. Please register first.";

        // FIX: Dati, hindi tinitignan ang approval status ng account —
        // kaya kahit 'pending' o 'rejected' pa ang account, nakakakuha
        // pa rin ito ng valid na password reset link. Idinagdag ang
        // mga check na ito para hindi malito ang teacher (successfully
        // na-reset ang password, pero hindi pa rin siya makaka-login
        // dahil hindi pa naaprubahan o na-reject ang account).
        } elseif ($teacher['status'] === 'pending') {
            $error = "Your account is still pending admin approval. You'll be able to reset your password once your account is approved.";

        } elseif ($teacher['status'] === 'rejected') {
            $error = "This account's registration was not approved. Please contact the system administrator.";

        } elseif ($teacher['status'] === 'deactivated') {
            $error = "This account has been deactivated. Please contact the system administrator.";

        } else {
            // I-invalidate ang lahat ng dati pang reset tokens
            // para isa lang ang valid sa isang pagkakataon
            $inv = mysqli_prepare($conn, "UPDATE password_resets SET is_used=1 WHERE email=?");
            mysqli_stmt_bind_param($inv, "s", $email);
            mysqli_stmt_execute($inv);

            // ── IAS SECURITY CONTROL #7: SECURE TOKEN GENERATION ──
            // random_bytes(32) = gumagawa ng 32 bytes (256 bits) ng
            // cryptographically secure random data.
            // bin2hex() = kino-convert ito sa 64-character hex string.
            // Imposibleng hulaan o brute-force ang token na ito.
            $token = bin2hex(random_bytes(32));

            // I-set ang expiry — 30 minuto mula ngayon (Philippine Time)
            $exp = date('Y-m-d H:i:s', time() + (8 * 3600) + (30 * 60));

            // I-save ang token sa password_resets table
            $ins = mysqli_prepare($conn, "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($ins, "sss", $email, $token, $exp);
            mysqli_stmt_execute($ins);

            // Gumawa ng reset link na may token
            $link = "http://localhost/gurong-gabai/modules/auth/reset.php?token=$token";

            // Ipadala ang reset link sa email ng teacher
            sendEmail($email, $teacher['full_name'], "Gurong GabAI — Password Reset",
                "<p>Hello <b>" . htmlspecialchars($teacher['full_name']) . "</b>,</p>
                 <p>Click the button below to reset your password:</p>
                 <p style='margin:24px 0;'>
                   <a href='$link' style='background:#1C3557;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;'>Reset My Password</a>
                 </p>
                 <p style='color:#64748B;font-size:13px;'>Or copy this link: <a href='$link'>$link</a></p>
                 <p>Link expires in <b>30 minutes</b>. If you didn't request this, ignore this email.</p>");

            $success = "A password reset link has been sent to your school email. Check your inbox.";
        }
    }
}

$page_title = "Forgot Password — Gurong GabAI";
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
    <h1 class="glass-h1">Forgot password?</h1>
    <p class="glass-sub">Enter your school email and we'll send a reset link.</p>

    <?php if ($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
      <div class="glass-field">
        <label>School Email</label>
        <input class="glass-input" type="email" name="email" placeholder="juan@deped.edu.ph" required>
      </div>
      <button type="submit" class="glass-btn">Send Reset Link</button>
    </form>
    <?php endif; ?>

    <div class="glass-footer"><a href="login.php">Back to Sign In</a></div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>