<?php
// ============================================================
// FILE: modules/auth/verify_otp.php
// PURPOSE: OTP verification page — dito nini-verify ng teacher
//          ang kanyang school email pagkatapos mag-register.
//          Ito ang ika-2 layer ng authentication (MFA).
//
// IAS SECURITY CONTROL:
//   Control #4 — Email OTP / Multi-Factor Authentication (MFA)
//
// FLOW:
//   1. Verify na may pending_email sa session (galing sa register.php)
//   2. Ipakita ang OTP input form
//   3. Kung nag-submit:
//      a. Hanapin ang pinakabagong unused, hindi-expired na OTP
//      b. I-compare sa entered code
//      c. Kung tama — mark as verified, i-redirect sa login
//   4. Kung nag-resend:
//      a. I-invalidate ang lahat ng lumang OTP
//      b. Gumawa ng bagong OTP, i-send sa email
// ============================================================

require_once '../../config/session.php'; // Session settings
require_once '../../config/db.php';      // Database connection
require_once '../../config/mailer.php';  // Email sender (para sa resend)

// Kung walang pending_email sa session — ibig sabihin hindi galing
// sa register.php ang user. I-redirect sa login para maiwasan
// ang direktang pag-access sa page na ito.
if (!isset($_SESSION['pending_email'])) {
    header("Location: login.php");
    exit();
}

$email   = $_SESSION['pending_email'];
$error   = $success = '';

// Kunin ang teacher record base sa email sa session
$res     = mysqli_query($conn, "SELECT * FROM teachers WHERE email='$email'");
$teacher = mysqli_fetch_assoc($res);

// Kung hindi mahanap ang teacher — invalid session, i-redirect
if (!$teacher) {
    header("Location: login.php");
    exit();
}


// ── HANDLE OTP VERIFICATION ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {

    $entered = trim($_POST['otp_code']);

    // Hanapin ang pinakabagong OTP para sa teacher na ito
    // Conditions: hindi pa ginamit (is_used=0) at pinakabago
    $otp_res = mysqli_query($conn,
        "SELECT * FROM otp_tokens
         WHERE teacher_id='{$teacher['id']}'
         AND is_used=0
         ORDER BY created_at DESC LIMIT 1");

    if (mysqli_num_rows($otp_res) === 0) {
        // Walang valid na OTP — expired na o wala talaga
        $error = "OTP expired or invalid. Please request a new one.";

    } else {
        $row = mysqli_fetch_assoc($otp_res);

        // ── OTP EXPIRY CHECK ──────────────────────────────────
        // I-compare ang expiry time sa database vs current time
        // (8 * 3600) = Philippine Time offset (UTC+8)
        $expires_at = strtotime($row['expires_at']);
        $now        = time() + (8 * 3600);

        if ($now > $expires_at) {
            // Expired na ang OTP (lampas na sa 10 minuto)
            $error = "OTP expired. Please request a new one.";

        } elseif ($row['otp_code'] === $entered) {
            // ── CORRECT OTP ──────────────────────────────────
            // Mark ang OTP bilang used — hindi na pwedeng gamitin ulit
            // Ito ang single-use enforcement ng OTP
            mysqli_query($conn, "UPDATE otp_tokens SET is_used=1 WHERE id='{$row['id']}'");

            // Mark ang teacher bilang OTP-verified
            // Kailangan ito bago makapag-login
            mysqli_query($conn, "UPDATE teachers SET otp_verified=1 WHERE id='{$teacher['id']}'");

            // Alisin ang pending_email sa session — tapos na ang OTP flow
            unset($_SESSION['pending_email']);

            $success = "verified"; // Special value para sa UI display

        } else {
            // Mali ang OTP na na-enter
            $error = "Incorrect OTP. Please try again.";
        }
    }
}


// ── HANDLE OTP RESEND ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {

    // I-invalidate ang LAHAT ng lumang OTP ng teacher na ito
    // — para isa lang ang valid na OTP sa isang pagkakataon
    mysqli_query($conn,
        "UPDATE otp_tokens SET is_used=1
         WHERE teacher_id='{$teacher['id']}'");

    // Gumawa ng bagong OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $exp = date('Y-m-d H:i:s', time() + (8 * 3600) + (10 * 60)); // 10 min expiry

    // I-save ang bagong OTP
    mysqli_query($conn,
        "INSERT INTO otp_tokens (teacher_id, otp_code, expires_at)
         VALUES ('{$teacher['id']}', '$otp', '$exp')");

    // Ipadala ang bagong OTP sa email
    $sent   = sendEmail($email, $teacher['full_name'],
        "Gurong GabAI — New OTP Code",
        "<p>Your new OTP code is:</p>
         <h2 style='letter-spacing:8px;color:#1C3557;'>$otp</h2>
         <p>Expires in <b>10 minutes</b>.</p>");

    // Ipakita kung successful o hindi ang pag-send
    $resent = $sent
        ? "A new OTP has been sent to <b>$email</b>."
        : "Failed to send email. Please try again.";
}

$page_title = "Verify OTP — Gurong GabAI";
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
    <h1 class="glass-h1">Verify your email</h1>
    <p class="glass-sub">Enter the 6-digit code sent to<br><strong style="color:#93c5fd;"><?= htmlspecialchars($email) ?></strong></p>

    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (isset($resent)): ?>
      <div class="alert alert-success">✅ <?= $resent ?></div>
    <?php endif; ?>

    <?php if ($success === 'verified'): ?>
      <div class="alert alert-success">✅ Email verified! Your account is now pending admin approval. You'll be notified once approved.</div>
      <a href="login.php" class="glass-btn" style="display:block;text-align:center;text-decoration:none;margin-top:8px;">Go to Login →</a>

    <?php else: ?>
      <div class="alert alert-info">📬 Check your inbox — the OTP may take up to a minute to arrive.</div>
      <form method="POST">
        <div class="glass-field">
          <label>OTP Code</label>
          <input class="glass-input" name="otp_code" maxlength="6" placeholder="000000"
                 style="letter-spacing:12px;font-size:24px;font-weight:700;text-align:center;"
                 autocomplete="off" required autofocus>
        </div>
        <button type="submit" name="verify" class="glass-btn">Verify Code →</button>
      </form>
      <form method="POST" style="margin-top:10px;">
        <button type="submit" name="resend"
          style="width:100%;padding:11px;border:1px solid rgba(255,255,255,0.15);border-radius:10px;background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.6);font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.2s;"
          onmouseover="this.style.background='rgba(255,255,255,0.1)'"
          onmouseout="this.style.background='rgba(255,255,255,0.06)'">
          Resend OTP
        </button>
      </form>
    <?php endif; ?>

    <div class="glass-footer" style="margin-top:14px;"><a href="register.php">← Back to Register</a></div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
