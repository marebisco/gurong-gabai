<?php
// ============================================================
// FILE: config/mailer.php
// PURPOSE: Ito ang email sender ng system.
//          Ginagamit ito para mag-send ng:
//            - OTP verification codes (pag nag-register)
//            - Admin approval/rejection notifications
//            - Password reset links
//
// TECH USED: PHPMailer library + Gmail SMTP
// GINAGAMIT SA: register.php, verify_otp.php, forgot.php,
//               admin/approvals.php
//
// HOW TO SETUP GMAIL APP PASSWORD (libre, walang bayad):
//   1. I-enable ang 2-Step Verification sa Gmail:
//      https://myaccount.google.com/security
//   2. Pumunta sa: https://myaccount.google.com/apppasswords
//   3. Pumili ng "Mail" at "Windows Computer"
//   4. I-copy ang 16-character App Password na lalabas
//   5. I-paste sa GMAIL_APP_PASSWORD below (walang spaces)
// ============================================================

// I-load ang PHPMailer library mula sa vendor folder
// (kasama na ito sa ZIP — hindi na kailangan ng composer)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/../vendor/autoload.php';

// ── CREDENTIALS ──────────────────────────────────────────────
// Ang Gmail address na gagamitin para mag-send ng emails
define('GMAIL_ADDRESS',      'Ilagay rito');

// Ang 16-character App Password mula sa Google Account settings
// TANDAAN: Hindi ito ang regular na Gmail password mo!
//          Ito ay espesyal na app password na ginawa para sa system
define('GMAIL_APP_PASSWORD', 'Ilagay rito');


// ── FUNCTION: sendEmail() ────────────────────────────────────
// PURPOSE: Nagpapadala ng HTML email gamit ang Gmail SMTP.
//          Tinatawag ito sa iba't ibang parts ng system
//          para sa OTP, approvals, at password reset.
//
// PARAMETERS:
//   $toEmail  — email address ng tatanggap
//   $toName   — pangalan ng tatanggap (para sa "Hello, Juan")
//   $subject  — paksa ng email
//   $body     — HTML content ng email (pwedeng may styling)
//
// RETURNS: true (success) o false (failed)
// ─────────────────────────────────────────────────────────────
function sendEmail($toEmail, $toName, $subject, $body) {

    // Gumawa ng bagong PHPMailer instance
    // true = enable exceptions para sa error handling
    $mail = new PHPMailer(true);

    try {
        // ── SMTP CONFIGURATION ───────────────────────────────
        // SMTP = Simple Mail Transfer Protocol
        // Ito ang standard na paraan ng pagpapadala ng emails

        $mail->isSMTP();                      // Gamitin ang SMTP para mag-send
        $mail->Host       = 'smtp.gmail.com'; // Gmail ang SMTP server natin
        $mail->SMTPAuth   = true;             // Kailangan ng authentication (username/password)
        $mail->Username   = GMAIL_ADDRESS;    // Ang Gmail address natin
        $mail->Password   = GMAIL_APP_PASSWORD; // Ang App Password (hindi regular password)
        $mail->SMTPSecure = 'tls';            // TLS encryption para secure ang connection
        $mail->Port       = 587;              // Port 587 = standard TLS port ng Gmail

        // ── EMAIL DETAILS ────────────────────────────────────
        $mail->setFrom(GMAIL_ADDRESS, 'Gurong GabAI'); // Galing sa: Gurong GabAI
        $mail->addAddress($toEmail, $toName);          // Padala sa: teacher/recipient

        // ── EMAIL CONTENT ────────────────────────────────────
        $mail->isHTML(true);    // HTML format ang email (may styling)
        $mail->Subject = $subject;

        // Template ng email — may consistent na design (blue header)
        // Ang $body ay ang actual na content na ibinibigay ng caller
        $mail->Body = "
            <div style='font-family:sans-serif;max-width:520px;margin:0 auto;'>
                <div style='background:#2563EB;padding:20px 24px;border-radius:8px 8px 0 0;'>
                    <h2 style='color:#fff;margin:0;font-size:18px;'>🎓 Gurong GabAI</h2>
                </div>
                <div style='background:#fff;padding:24px;border:1px solid #E2E8F0;border-top:none;border-radius:0 0 8px 8px;'>
                    $body
                    <hr style='border:none;border-top:1px solid #E2E8F0;margin:20px 0;'>
                    <p style='font-size:12px;color:#94A3B8;'>This is an automated message from Gurong GabAI.</p>
                </div>
            </div>
        ";

        // Ipadala ang email
        $mail->send();
        return true; // Email successfully sent

    } catch (Exception $e) {
        // Kung may error (e.g. wrong credentials, no internet)
        // — i-log ang error sa PHP error log para sa debugging
        // — huwag i-crash ang system, ibalik lang ang false
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>