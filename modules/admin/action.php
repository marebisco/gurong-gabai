<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
require_once '../../config/mailer.php';
requireAdmin();

$action = $_POST['action'] ?? '';
$uid    = (int)($_POST['user_id'] ?? 0);
if (!$uid || !$action) { header("Location: dashboard.php"); exit(); }

$user = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT * FROM teachers WHERE id='$uid' AND role='teacher'"));
if (!$user) { header("Location: dashboard.php"); exit(); }

$name  = $user['full_name'];
$email = $user['email'];

switch ($action) {
    case 'approve':
        mysqli_query($conn, "UPDATE teachers SET status='approved' WHERE id='$uid'");
        // FIX (June 2026): sendEmail() ay nangangailangan ng 4 arguments
        // ($toEmail, $toName, $subject, $body) — dati 3 lang ang ipinasa
        // (walang $name), kaya nagdudulot ng ArgumentCountError sa lahat
        // ng 5 actions dito. Catch din ay pinalitan sa \Throwable para
        // masakop din ang TypeError/ArgumentCountError, hindi lang Exception.
        try { sendEmail($email, $name, 'Your Gurong GabAI Account Has Been Approved!', emailTemplate('approved', $name)); } catch (\Throwable $e) { error_log('[GabAI] sendEmail failed (approve): ' . $e->getMessage()); }
        $_SESSION['flash_success'] = "$name's account approved. Notification sent.";
        break;

    case 'reject':
        mysqli_query($conn, "UPDATE teachers SET status='rejected' WHERE id='$uid'");
        try { sendEmail($email, $name, 'Update on Your Gurong GabAI Registration', emailTemplate('rejected', $name)); } catch (\Throwable $e) { error_log('[GabAI] sendEmail failed (reject): ' . $e->getMessage()); }
        $_SESSION['flash_error'] = "$name's account rejected. Notification sent.";
        break;

    case 'deactivate':
        // Update DB first (this must work even if email fails)
        $result = mysqli_query($conn, "UPDATE teachers SET status='deactivated' WHERE id='$uid'");
        if ($result && mysqli_affected_rows($conn) > 0) {
            try { sendEmail($email, $name, 'Your Gurong GabAI Account Has Been Deactivated', emailTemplate('deactivated', $name)); } catch (\Throwable $e) { error_log('[GabAI] sendEmail failed (deactivate): ' . $e->getMessage()); }
            $_SESSION['flash_error'] = "$name's account has been deactivated.";
        } else {
            $_SESSION['flash_error'] = "Failed to deactivate. Run the SQL fix first (see PROBLEM 2a).";
        }
        break;

    case 'reactivate':
        mysqli_query($conn, "UPDATE teachers SET status='approved' WHERE id='$uid'");
        try { sendEmail($email, $name, 'Your Gurong GabAI Account Has Been Reactivated!', emailTemplate('reactivated', $name)); } catch (\Throwable $e) { error_log('[GabAI] sendEmail failed (reactivate): ' . $e->getMessage()); }
        $_SESSION['flash_success'] = "$name's account reactivated.";
        break;

    case 'delete':
        mysqli_query($conn, "DELETE FROM teachers WHERE id='$uid' AND role='teacher'");
        try { sendEmail($email, $name, 'Your Gurong GabAI Account Has Been Removed', emailTemplate('deleted', $name)); } catch (\Throwable $e) { error_log('[GabAI] sendEmail failed (delete): ' . $e->getMessage()); }
        $_SESSION['flash_error'] = "$name's account permanently deleted.";
        break;
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'accounts.php'));
exit();

function emailTemplate(string $type, string $name): string {
    $adminEmail = 'admin@gurong-gabai.edu.ph';
    $loginUrl   = 'http://localhost/gurong-gabai/modules/auth/login.php';
    $configs = [
        'approved'    => ['color'=>'#059669','heading'=>'Account Approved!',
            'body'=>"Your <strong>Gurong GabAI</strong> account has been approved! You can now log in.",
            'btn'=>'Log In Now','url'=>$loginUrl],
        'rejected'    => ['color'=>'#dc2626','heading'=>'Registration Not Approved',
            'body'=>"Your registration was not approved. Contact admin: <a href='mailto:$adminEmail'>$adminEmail</a>",
            'btn'=>null,'url'=>null],
        'deactivated' => ['color'=>'#d97706','heading'=>'Account Deactivated',
            'body'=>"Your account has been temporarily deactivated. Contact: <a href='mailto:$adminEmail'>$adminEmail</a>",
            'btn'=>null,'url'=>null],
        'reactivated' => ['color'=>'#059669','heading'=>'Account Reactivated!',
            'body'=>"Your <strong>Gurong GabAI</strong> account is active again. You can now log in.",
            'btn'=>'Log In Now','url'=>$loginUrl],
        'deleted'     => ['color'=>'#64748b','heading'=>'Account Removed',
            'body'=>"Your account has been permanently removed. Contact: <a href='mailto:$adminEmail'>$adminEmail</a>",
            'btn'=>null,'url'=>null],
    ];
    $c   = $configs[$type];
    $btn = $c['btn'] ? "<div style='text-align:center;margin:20px 0;'>
        <a href='{$c['url']}' style='background:{$c['color']};color:#fff;padding:11px 26px;
           border-radius:8px;text-decoration:none;font-weight:700;'>{$c['btn']}</a></div>" : '';
    return "<div style='font-family:Arial,sans-serif;max-width:500px;margin:auto;
                border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;'>
        <div style='background:{$c['color']};padding:22px;text-align:center;'>
          <h1 style='color:#fff;margin:0;font-size:18px;'>{$c['heading']}</h1></div>
        <div style='padding:26px 30px;'>
          <p>Hello, <strong>" . htmlspecialchars($name) . "</strong>!</p>
          <p style='line-height:1.7;'>{$c['body']}</p>$btn</div>
        <div style='background:#f8fafc;padding:12px;text-align:center;border-top:1px solid #e2e8f0;'>
          <p style='color:#94a3b8;font-size:11px;margin:0;'>Automated message from Gurong GabAI.</p>
        </div></div>";
}