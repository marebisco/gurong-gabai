<?php
// ============================================================
// FILE: modules/auth/register.php
// PURPOSE: Registration page para sa mga bagong teacher.
//          Dito nagre-register ang mga guro gamit ang
//          kanilang school-issued email.
//
// IAS SECURITY CONTROLS NA NARITO:
//   Control #1 — BCrypt Password Hashing
//   Control #2 — SQL Injection Prevention (prepared statements)
//   Control #4 — OTP Generation (MFA setup)
//
// FLOW PAGKATAPOS MAG-SUBMIT:
//   1. Validate inputs (email format, password length, etc.)
//   2. Check kung hindi duplicate ang email
//   3. Hash ang password gamit ang BCrypt
//   4. I-save sa database (status = pending by default)
//   5. Gumawa ng 6-digit OTP, i-save, i-send sa email
//   6. I-redirect sa verify_otp.php
// ============================================================

require_once '../../config/session.php'; // Session settings
require_once '../../config/db.php';      // Database connection
require_once '../../config/mailer.php';  // Email sender

// FIX: Dating walang check dito kung naka-login na ang user — kaya
// kahit naka-login pa, totoong ma-a-access pa rin ang register.php
// (na dapat lang para sa mga BAGONG account). Idinagdag ang parehong
// redirect logic na gamit sa login.php, para consistent ang behavior
// sa LAHAT ng auth pages — kapag naka-login ka na, dadalhin ka sa
// dashboard mo agad, hindi na sa registration form.
if (isset($_SESSION['teacher_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin'
        ? '../admin/dashboard.php'
        : '../dashboard/index.php'));
    exit();
}

$error = $success = '';

// ── PROCESS REGISTRATION FORM ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Kunin ang inputs mula sa form
    // trim() — aalisin ang extra spaces sa simula at dulo
    $full_name   = trim($_POST['full_name']        ?? '');
    $school_name = trim($_POST['school_name']       ?? '');
    $email       = trim($_POST['email']             ?? '');
    $password    =      $_POST['password']          ?? '';
    $confirm     =      $_POST['confirm_password']  ?? '';

    // ── INPUT VALIDATION ─────────────────────────────────────
    if (!$full_name || !$school_name || !$email || !$password)
        $error = "All fields are required.";

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        // filter_var() ay built-in PHP function para ma-validate
        // ang format ng email (may @, may domain, etc.)
        $error = "Invalid email format.";

    elseif (strlen($password) < 8)
        $error = "Password must be at least 8 characters.";

    elseif ($password !== $confirm)
        $error = "Passwords do not match.";

    else {
        // ── IAS SECURITY CONTROL #2: SQL INJECTION PREVENTION ──
        // Prepared statement para mag-check ng duplicate email.
        // Ligtas kahit may special characters sa email input.
        $chk = mysqli_prepare($conn, "SELECT id FROM teachers WHERE email=? LIMIT 1");
        mysqli_stmt_bind_param($chk, "s", $email);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);

        if (mysqli_stmt_num_rows($chk) > 0) {
            // May existing account na may ganong email — hindi pwedeng mag-register ulit
            $error = "This email is already registered.";

        } else {
            // ── IAS SECURITY CONTROL #1: BCRYPT PASSWORD HASHING ──
            // HINDI kailanman sino-store ang plain text password!
            // password_hash() ay gumagawa ng one-way BCrypt hash.
            // Kahit makita ng hacker ang database, hindi niya
            // mababasa ang tunay na password.
            //
            // Halimbawa: "MyPassword123" → "$2y$10$xK9mN3pL..."
            // Hindi pwedeng i-reverse ang hash na ito.
            $hash = password_hash($password, PASSWORD_BCRYPT);

            // I-save ang bagong teacher sa database
            // TANDAAN: status ay 'pending' by default — kailangan
            // pang ma-approve ng admin bago makapag-login
            $ins = mysqli_prepare($conn, "INSERT INTO teachers (full_name, school_name, email, password) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($ins, "ssss", $full_name, $school_name, $email, $hash);

            if (mysqli_stmt_execute($ins)) {
                // Kunin ang ID ng bagong record para sa OTP
                $tid = mysqli_insert_id($conn);

                // ── IAS SECURITY CONTROL #4: OTP GENERATION ───────
                // Gumawa ng 6-digit OTP (One-Time Password)
                // str_pad() — sisiguruhing 6 digits palagi
                // (e.g. 83 → "000083")
                $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

                // I-set ang expiry — 10 minuto mula ngayon
                // (8 * 3600) = timezone offset para sa Philippine Time (UTC+8)
                $exp = date('Y-m-d H:i:s', time() + (8 * 3600) + (10 * 60));

                // I-save ang OTP sa database para ma-verify mamaya
                $otp_ins = mysqli_prepare($conn, "INSERT INTO otp_tokens (teacher_id, otp_code, expires_at) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($otp_ins, "iss", $tid, $otp, $exp);
                mysqli_stmt_execute($otp_ins);

                // Ipadala ang OTP sa email ng teacher
                sendEmail($email, $full_name, "Gurong GabAI — OTP Verification Code",
                    "<p>Hello <b>" . htmlspecialchars($full_name) . "</b>,</p>
                     <p>Your OTP code is:</p>
                     <h2 style='letter-spacing:8px;color:#1C3557;'>$otp</h2>
                     <p>This expires in <b>10 minutes</b>.</p>");

                // I-store ang email sa session para ma-access sa verify_otp.php
                $_SESSION['pending_email'] = $email;

                // I-redirect sa OTP verification page
                header("Location: verify_otp.php");
                exit();

            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}

$page_title = "Register — Gurong GabAI";
include '../../includes/header.php';
?>

<div class="auth-bg">
  <div class="auth-orb orb1"></div>
  <div class="auth-orb orb2"></div>
  <div class="auth-orb orb3"></div>
  <div class="glass-card" style="max-width:460px;">
    <div class="glass-logo">
      <div class="glass-logo-icon">🎓</div>
      <span>Gurong <em>GabAI</em></span>
    </div>
    <h1 class="glass-h1">Create an account</h1>
    <p class="glass-sub">Register using your school-issued email.</p>

    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="glass-field">
        <label>Full Name</label>
        <input class="glass-input" name="full_name" placeholder="Juan Dela Cruz"
               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
      </div>
      <div class="glass-field">
        <label>School Name</label>
        <input class="glass-input" name="school_name" placeholder="Bagong Pag-asa National High School"
               value="<?= htmlspecialchars($_POST['school_name'] ?? '') ?>" required>
      </div>
      <div class="glass-field">
        <label>School Email</label>
        <input class="glass-input" type="email" name="email" placeholder="juan@deped.edu.ph"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div class="glass-field">
          <label>Password</label>
          <input class="glass-input" type="password" name="password" placeholder="Min. 8 characters" required>
        </div>
        <div class="glass-field">
          <label>Confirm Password</label>
          <input class="glass-input" type="password" name="confirm_password" placeholder="Re-enter" required>
        </div>
      </div>
      <button type="submit" class="glass-btn" style="margin-top:4px;">Create Account</button>
    </form>

    <div class="glass-footer">Already have an account? <a href="login.php">Sign in</a></div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>