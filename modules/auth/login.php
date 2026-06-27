<?php
// ============================================================
// FILE: modules/auth/login.php
// PURPOSE: Ang login page ng system.
//          Dito nagse-sign in ang mga teacher at admin.
//
// IAS SECURITY CONTROLS NA NARITO:
//   Control #2 — SQL Injection Prevention (prepared statements)
//   Control #1 — BCrypt Password Verification
//   Control #5 — Session Security (session_regenerate_id)
//   Control #8 — Rate Limiting / Brute Force Protection
//
// FLOW:
//   1. Submit form → validate inputs
//   2. Check rate limiting (hindi ba naka-lockout?)
//   3. Hanapin ang account sa database (prepared statement)
//   4. Verify password (bcrypt)
//   5. Check OTP verification, account status
//   6. Mag-login — set session variables
// ============================================================

require_once '../../config/session.php'; // Session security settings
require_once '../../config/db.php';      // Database connection

// Kung naka-login na — i-redirect agad, hindi na kailangang mag-login ulit
if (isset($_SESSION['teacher_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin'
        ? '../admin/dashboard.php'
        : '../dashboard/index.php'));
    exit();
}

$error = '';

// Para maipakita ang "Logged out successfully" message
// Isinend ito ng logout.php bilang ?logout=1 sa URL
//
// FIX (June 2026): dati, ang $loggedOut ay base lang sa pagkaroon ng
// ?logout=1 sa URL — pero ang <form> sa ibaba ay WALANG action
// attribute, kaya kapag nag-submit ng bagong login attempt, ang POST
// request ay napupunta pa rin sa PAREHONG URL (login.php?logout=1).
// Kaya naiipreserve ang ?logout=1 query parameter, at nananatiling
// TRUE ang $loggedOut KAHIT may bagong (mali man o tama) na login
// attempt — kaya sabay nakikita ang green "logged out" message at
// ang red "invalid password" message, gayong luma na ang context ng
// green message sa sandaling magtangka ng panibagong pag-login.
//
// AYOS: idinagdag ang check na "hindi POST request" — sa sandaling
// mag-submit ng form (anuman ang resulta nito), itinuturing na agad
// na LUMA na ang logout message, kaya hindi na ito ipinapakita.
$loggedOut = isset($_GET['logout']) && $_SERVER['REQUEST_METHOD'] !== 'POST';

// ── PROCESS LOGIN FORM SUBMISSION ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';

    // Basic validation — siguraduhin may laman ang fields
    if (!$email || !$password) {
        $error = "All fields are required.";

    } else {

        // ── IAS SECURITY CONTROL #8: RATE LIMITING ───────────
        // Pinipigilan ang brute force attacks — kung 5 beses na
        // nag-wrong password, i-lock ang account ng 15 minuto.
        //
        // Ginagamit natin ang PHP session para i-track ang
        // attempts per email address — walang extra database table.

        // Unique key per email (gamit ang MD5 hash ng email)
        $attempts_key = 'login_attempts_' . md5($email);
        $lockout_key  = 'login_lockout_'  . md5($email);

        // I-initialize ang counters kung wala pa
        if (!isset($_SESSION[$attempts_key])) $_SESSION[$attempts_key] = 0;
        if (!isset($_SESSION[$lockout_key]))  $_SESSION[$lockout_key]  = 0;

        // Kung naka-lockout pa (hindi pa tapos ang 15 minuto)
        if ($_SESSION[$lockout_key] > time()) {
            $minutes = ceil(($_SESSION[$lockout_key] - time()) / 60);
            $error = "Too many failed attempts. Please try again in $minutes minute(s).";

        } else {

            // ── IAS SECURITY CONTROL #2: SQL INJECTION PREVENTION ─
            // Ginagamit ang prepared statement — ang email ay
            // ini-bind bilang parameter, hindi direktang isinama
            // sa SQL string. Kahit mag-type ng SQL injection payload
            // ang attacker (e.g. ' OR 1=1 --), hindi ito ia-execute
            // bilang SQL kundi ibibigay bilang plain text lang.
            $stmt = mysqli_prepare($conn, "SELECT * FROM teachers WHERE email=? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "s", $email); // "s" = string type
            mysqli_stmt_execute($stmt);
            $teacher = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$teacher) {
                // Specific message kung hindi registered ang email
                $error = "No account found with that email address. Please register first.";

            } elseif (!password_verify($password, $teacher['password'])) {
                // ── IAS SECURITY CONTROL #1: BCRYPT VERIFICATION ──
                // password_verify() ay nagco-compare ng entered password
                // sa BCrypt hash sa database — hindi kailanman nire-reverse
                // ang hash. Kung mali ang password, increment ang counter.

                $_SESSION[$attempts_key]++;

                if ($_SESSION[$attempts_key] >= 5) {
                    // 5 na beses na nagkamali — i-lockout ng 15 minuto
                    $_SESSION[$lockout_key]  = time() + (15 * 60);
                    $_SESSION[$attempts_key] = 0; // reset counter
                    $error = "Too many failed attempts. Please try again in 15 minutes.";
                } else {
                    // Ipakita kung ilang attempts pa ang natitira
                    $remaining = 5 - $_SESSION[$attempts_key];
                    $error = "Invalid password. $remaining attempt(s) remaining before lockout.";
                }

            } elseif (!$teacher['otp_verified']) {
                // Hindi pa na-verify ang email — i-send ulit sa OTP page
                $_SESSION['pending_email'] = $email;
                header("Location: verify_otp.php");
                exit();

            } elseif ($teacher['status'] === 'pending') {
                // Naka-register at verified, pero hindi pa na-approve ng admin
                $error = "Your account is pending admin approval.";

            } elseif ($teacher['status'] === 'rejected') {
                // Na-reject ng admin — hindi maka-login
                $error = "Your account has been rejected. Contact support.";

            // FIX: Dati, walang explicit check para sa 'deactivated' —
            // kaya nahuhulog ito sa generic na 'else' block (successful
            // login), at kapag na-catch ito ng status check sa
            // session.php, mali namang "pending admin approval" ang
            // ipinapakitang message — gayong deactivated na ito dati,
            // hindi pa basta pending. Idinagdag ang sariling check
            // dito para tama agad ang message bago pa man maka-login.
            } elseif ($teacher['status'] === 'deactivated') {
                $error = "Your account has been deactivated. Please contact the system administrator.";

            } else {
                // ── SUCCESSFUL LOGIN ──────────────────────────
                // I-reset ang rate limiting counters
                $_SESSION[$attempts_key] = 0;
                $_SESSION[$lockout_key]  = 0;

                // ── IAS SECURITY CONTROL #5: SESSION REGENERATION ─
                // Ginagawa ang bagong session ID pagkatapos ng login.
                // Pinipigilan nito ang session fixation attacks —
                // kahit may attacker na nagtatago ng lumang session ID,
                // hindi na ito valid pagkatapos ng login.
                session_regenerate_id(true);

                // I-store ang user info sa session
                $_SESSION['teacher_id']   = $teacher['id'];
                $_SESSION['teacher_name'] = $teacher['full_name'];
                $_SESSION['role']         = $teacher['role'];
                $_SESSION['status']       = $teacher['status']; // para sa status check sa session.php

                // I-redirect base sa role — admin o teacher
                header("Location: " . ($teacher['role'] === 'admin'
                    ? '../admin/dashboard.php'
                    : '../dashboard/index.php'));
                exit();
            }
        }
    }
}

// FIX: Dating tatlong magkahiwalay na error sources (POST $error,
// $_GET['err'], at $loggedOut) ang sabay-sabay nadidisplay sa HTML
// kung sakaling totoo silang lahat nang sabay (halimbawa, may
// natitirang ?err=... query param mula sa naunang redirect, tapos
// nag-submit ulit ng form sa parehong URL gamit ang maling password
// — kaya pareho lumalabas ang "Invalid password" AT "not approved").
//
// AYOS: pinagsama sa ISANG variable ($displayError) gamit ang
// priority order — POST result muna (pinakabago/pinaka-relevant),
// tapos lang ang query-string reason kung walang POST error.
// Tinitiyak nito na ISANG error lang ang lalabas sa screen.
//
// Idinagdag din ang specific na message para sa 'deactivated' —
// dati ay 'notapproved' lang ang posibleng value, na may generic
// na "pending approval" message kahit deactivated na pala.
$queryErrorMessages = [
    'notapproved' => "Your account is pending admin approval.",
    'pending'     => "Your account is pending admin approval.",
    'rejected'    => "Your account has been rejected. Contact support.",
    'deactivated' => "Your account has been deactivated. Please contact the system administrator.",
];
$queryError = $queryErrorMessages[$_GET['err'] ?? ''] ?? '';

// Kapag may bagong POST submission, ang resulta nito (tama man o
// mali) ang dapat manaig — hindi na dapat sabay lumabas ang lumang
// query-string reason mula sa naunang redirect.
$displayError = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $error : ($error ?: $queryError);

$page_title = "Login — Gurong GabAI";
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
    <h1 class="glass-h1">Welcome back</h1>
    <p class="glass-sub">Sign in using your school email to continue.</p>

    <?php if ($loggedOut): ?>
      <div class="alert alert-success">✅ You have been logged out successfully.</div>
    <?php endif; ?>
    <?php if ($displayError): ?>
      <div class="alert alert-error">⚠️ <?= htmlspecialchars($displayError) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="glass-field">
        <label>School Email</label>
        <input class="glass-input" type="email" name="email" placeholder="juan@deped.edu.ph"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <div class="glass-field">
        <label>Password</label>
        <input class="glass-input" type="password" name="password" placeholder="Enter your password" required>
      </div>
      <div style="text-align:right;margin-bottom:16px;">
        <a href="forgot.php" class="glass-link">Forgot password?</a>
      </div>
      <button type="submit" class="glass-btn">Sign In →</button>
    </form>

    <div class="glass-footer">No account yet? <a href="register.php">Create one</a></div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>