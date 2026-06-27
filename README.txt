# GURONG GABAI — Sprint 1 Setup Instructions

## STEP 1: Extract this ZIP
I-extract ang folder na ito sa:
  C:\xampp\htdocs\gurong-gabai\

## STEP 2: Database Setup
1. Buksan ang phpMyAdmin: http://localhost/phpmyadmin
2. I-click ang gurong_gabai_db
3. I-click ang SQL tab
4. I-paste ang laman ng database_setup.sql
5. I-click ang Go

## STEP 3: Install PHPMailer
Sa VS Code terminal, i-run:
  cd C:\xampp\htdocs\gurong-gabai
  curl -sS https://getcomposer.org/installer | php
  php composer.phar require phpmailer/phpmailer

## STEP 4: I-update ang Mailtrap credentials
Buksan ang config/mailer.php
Palitan ang:
  YOUR_MAILTRAP_USERNAME  →  yung username mo sa Mailtrap
  YOUR_MAILTRAP_PASSWORD  →  yung password mo sa Mailtrap

## STEP 5: Test!
Buksan sa browser:
  http://localhost/gurong-gabai/modules/auth/register.php

## DEFAULT ADMIN LOGIN
  Email:    admin@guronggabai.com
  Password: Admin@123

## FILE STRUCTURE
gurong-gabai/
├── index.php                         ← Entry point
├── logout.php
├── database_setup.sql                ← I-run sa phpMyAdmin
├── assets/css/style.css              ← Global styles
├── config/
│   ├── db.php                        ← Database connection
│   ├── session.php                   ← Secure session config
│   └── mailer.php                    ← Email sender (update credentials!)
├── includes/
│   ├── header.php
│   └── footer.php
└── modules/
    ├── auth/
    │   ├── register.php              ← GG-001
    │   ├── verify_otp.php            ← GG-002
    │   ├── login.php                 ← GG-003
    │   ├── forgot.php                ← GG-004
    │   └── reset.php                 ← GG-004
    ├── admin/
    │   ├── dashboard.php             ← GG-005
    │   └── approvals.php             ← GG-005
    └── dashboard/
        └── index.php                 ← Placeholder (Sprint 4)

## IAS SECURITY CONTROLS IMPLEMENTED
1. Password Hashing     → register.php    → password_hash($p, PASSWORD_BCRYPT)
2. OTP / MFA            → verify_otp.php  → 6-digit, 10-min expiry, is_used flag
3. SQL Injection Prev.  → login.php       → mysqli_prepare() + bind_param()
4. Session Security     → session.php     → httponly, samesite, regenerate_id()
5. Role-Based Access    → session.php     → requireAdmin() / requireLogin()
6. Secure Token         → forgot.php      → bin2hex(random_bytes(32))