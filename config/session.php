<?php
// ============================================================
// FILE: config/session.php
// PURPOSE: Ito ang security configuration ng PHP sessions.
//          Ini-include ito sa LAHAT ng pages ng system —
//          kaya ang security settings dito ay nag-a-apply
//          sa buong application.
//
// IAS SECURITY CONTROLS NA NARITO:
//   Control #5 — Secure Session Management
//   Control #3 — Role-Based Access Control (RBAC)
//
// GINAGAMIT SA: Lahat ng .php files ng system
// ============================================================


// ── IAS SECURITY CONTROL #5: SESSION SECURITY ───────────────
// Tatlong settings na nagpo-protect sa session cookies
// bago pa man simulan ang session:

// 1. cookie_httponly = 1
//    Pinipigilan ang JavaScript na basahin ang session cookie.
//    Proteksyon laban sa XSS (Cross-Site Scripting) attacks —
//    kahit may malicious script sa page, hindi niya ma-access
//    ang session cookie ng user.
ini_set('session.cookie_httponly', 1);

// 2. use_only_cookies = 1
//    Tinitiyak na ang session ID ay nasa cookie LAMANG —
//    hindi ito lalabas sa URL (e.g. ?PHPSESSID=abc123).
//    Proteksyon laban sa session hijacking at session fixation.
ini_set('session.use_only_cookies', 1);

// 3. cookie_samesite = Strict
//    Pinipigilan ang session cookie na maipadala sa
//    cross-site requests (ibang website).
//    Proteksyon laban sa CSRF (Cross-Site Request Forgery) attacks.
ini_set('session.cookie_samesite', 'Strict');

// Simulan na ang session gamit ang secure settings sa itaas
session_start();


// ── IAS SECURITY CONTROL #3: ROLE-BASED ACCESS CONTROL ──────

// FUNCTION: requireLogin()
// PURPOSE:  Tinatawag sa simula ng bawat teacher page.
//           Sinisigurado na may naka-login na user at
//           ang account niya ay approved pa rin.
//
// GINAGAMIT SA: dashboard, generator, library, history, profile
// ─────────────────────────────────────────────────────────────
function requireLogin() {

    // Kung walang active session — ibig sabihin hindi naka-login
    // I-redirect agad sa login page
    if (!isset($_SESSION['teacher_id'])) {
        header("Location: /gurong-gabai/modules/auth/login.php");
        exit();
    }

    // ADDITIONAL CHECK: Kahit naka-login, i-verify pa rin na
    // approved pa rin ang account (hindi rejected, pending, o
    // deactivated). Ito ay nagpo-protekta sa case na na-revoke ang
    // access ng teacher habang naka-login pa siya (halimbawa,
    // na-deactivate siya ng admin habang bukas pa ang dashboard niya).
    if ($_SESSION['role'] !== 'admin' &&
        isset($_SESSION['status']) &&
        $_SESSION['status'] !== 'approved') {

        // FIX: Dati, palaging "?err=notapproved" lang ang ipinapasa,
        // kaya kahit totoong "deactivated" na ang dahilan, mali ang
        // nakikitang message sa login page (laging "pending approval").
        // Ngayon, ipinapasa ang TOTOONG status (pending/rejected/
        // deactivated) sa query string, para tama ang specific
        // error message na ipapakita sa teacher.
        $reasonStatus = $_SESSION['status'];

        // Destroy ang session — puwersahang i-logout
        session_destroy();

        // I-redirect sa login page na may TAMANG dahilan
        header("Location: /gurong-gabai/modules/auth/login.php?err=" . urlencode($reasonStatus));
        exit();
    }
}


// FUNCTION: requireAdmin()
// PURPOSE:  Tinatawag sa simula ng bawat admin page.
//           Sinisigurado na ang naka-login ay ADMIN —
//           hindi lang kahit sinong naka-login.
//
// GINAGAMIT SA: admin/dashboard, approvals, accounts, statistics
// ─────────────────────────────────────────────────────────────
function requireAdmin() {

    // Dalawang kondisyon ang sinisigurado:
    // 1. May naka-login na user (may session)
    // 2. Ang role ng user ay 'admin' — hindi 'teacher'
    if (!isset($_SESSION['teacher_id']) || $_SESSION['role'] !== 'admin') {

        // Kung hindi admin — i-redirect sa login
        // (Teachers na sumusubok mag-access ng admin pages
        //  ay mapupunta dito)
        header("Location: /gurong-gabai/modules/auth/login.php");
        exit();
    }
}
?>