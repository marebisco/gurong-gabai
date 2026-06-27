<?php
// ============================================================
// FILE: logout.php
// PURPOSE: Secure logout — tinitiyak na lahat ng session data
//          ay naalis bago mag-redirect sa login page.
//
// IAS SECURITY CONTROL:
//   Control #9 — Secure Logout with Full Session Destruction
//
// BAKIT KAILANGAN NG PROPER LOGOUT?
//   Kung hindi maayos na na-destroy ang session, pwedeng:
//   - Ma-reuse ng ibang tao ang session token (shared computer)
//   - Ma-steal ang session data sa memory
//   - Ma-access ulit ang system kahit "naka-logout" na
//
// TATLONG STEPS NG SECURE LOGOUT:
//   Step 1 — Alisin ang lahat ng session variables sa memory
//   Step 2 — Expire ang session cookie sa browser
//   Step 3 — Destroy ang server-side session
// ============================================================

require_once 'config/session.php'; // I-load ang session (kailangan para ma-destroy)

// ── STEP 1: CLEAR ALL SESSION VARIABLES ─────────────────────
// Ini-empty ang $_SESSION array — lahat ng naka-store na data
// (teacher_id, teacher_name, role, status, etc.) ay naaaalis.
// Ito ang pinaka-importanteng step — ina-alis ang actual na data.
$_SESSION = array();


// ── STEP 2: EXPIRE THE SESSION COOKIE IN THE BROWSER ────────
// Kahit na-destroy ang session sa server side, ang browser
// ay nagtatago pa rin ng session cookie.
// Kailangan nating sabihin sa browser na "expired na" ang cookie
// para hindi na ito maipadala sa susunod na request.
//
// Ginagawa ito sa pamamagitan ng pag-set ng expiry sa nakaraan
// (time() - 42000 = negative time = expired na agad)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params(); // Kunin ang cookie settings
    setcookie(
        session_name(),      // Pangalan ng session cookie (e.g. "PHPSESSID")
        '',                  // Blangko ang value — walang laman
        time() - 42000,      // Expiry sa nakaraan — expired na agad
        $params["path"],     // Parehong path ng original cookie
        $params["domain"],   // Parehong domain
        $params["secure"],   // HTTPS setting
        $params["httponly"]  // HttpOnly setting
    );
}


// ── STEP 3: DESTROY THE SERVER-SIDE SESSION ─────────────────
// Sinisigurado na ang session file sa server ay natanggal na.
// Pagkatapos nito, kahit may attacker na may lumang session ID,
// hindi na siya valid — wala nang session data sa server.
session_destroy();


// ── STEP 4: REDIRECT SA LOGIN PAGE ──────────────────────────
// I-redirect sa login page na may ?logout=1 para ipakita
// ang "You have been logged out successfully" message.
header("Location: /gurong-gabai/modules/auth/login.php?logout=1");
exit();
?>