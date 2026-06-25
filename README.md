# Gurong GabAI 🎓

**An AI-Powered Lesson Plan Generator for Filipino Public School Teachers**

Gurong GabAI is a web-based application that builts to help the public school teachers to create a lesson plans that aligned with current DepEd curricula and formats in a fraction of the time it normally takes. Teachers can select a grade level, subject, topic, curriculum basis, academic calendar, and lesson plan format, and the system generates and regenerates a complete and editable draft using AI.

> **Academic Project Notice:**
> This is a student capstone project built for academic purposes only (BSIT). It currently runs on a local development environment (XAMPP) and has not been deployed to production. AI-generated lesson plans are drafts only and should always be reviewed by a teacher before using it in the classroom.

---

## Features

- Secure registration with email OTP verification and admin approval workflow
- AI-generated lesson plans in **7 formats**: ILAW, DLP, 4A's, 5E's, Traditional, Semi-Detailed, and DLL
- Support for **MATATAG** and **K-12 Senior High School** curricula, with separate Four-Quarter / Three-Term academic calendar selection
- Multi-model AI fallback that automatically retries with backup AI models if the primary one fails
- Resource Library with save, export (PDF/DOCX), Trash (soft-delete and hard-delete), and Generated History
- System Admin Panel for approving, rejecting, deactivating, or removing teacher accounts
- Light and dark mode

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML, CSS, JavaScript (no framework) |
| Backend | PHP (procedural, with MySQLi prepared statements) |
| Database | MySQL |
| AI Integration | [OpenRouter](https://openrouter.ai) API → *OpenAI's GPT-4o-Mini* (with backup models *Google's Gemini 3 Flash Preview* and *Meta's Llama 3.1 8B*) |
| Email | PHPMailer via Gmail SMTP |

No frontend/backend framework or dedicated AI/ML framework was used. AI access is a direct HTTP API call from the PHP backend to OpenRouter.

---

## Getting Started

### Requirements

- [XAMPP](https://www.apachefriends.org/) (or any Apache + MySQL + PHP stack)
- A free [OpenRouter](https://openrouter.ai/) account and API key
- A Gmail account with an [App Password](https://myaccount.google.com/apppasswords) generated (for sending OTP/notification emails)
- [Composer](https://getcomposer.org/) (to install PHPMailer)

### 1. Clone the repository

```bash
git clone https://github.com/<your-username>/gurong-gabai.git
```

Place the folder inside your XAMPP `htdocs` directory (e.g., `C:\xampp\htdocs\gurong-gabai`).

### 2. Install PHP dependencies

From inside the project folder:

```bash
composer install
```

This installs PHPMailer into the `vendor/` folder.

### 3. Set up the database

1. Start Apache and MySQL in the XAMPP Control Panel.
2. Open [phpMyAdmin](http://localhost/phpmyadmin) and create a new database named `gurong_gabai_db`.
3. Import `database_setup.sql` into that database.

### 4. Configure your API key and email credentials

This repo does **not** include real API keys or passwords — you'll need to create your own local config files from the provided templates.

1. Copy `config/gemini.example.php` → `config/gemini.php`
2. Open `config/gemini.php` and replace the placeholder with your own [OpenRouter API key](https://openrouter.ai/keys):
   ```php
   define('OPENROUTER_API_KEY', 'your-actual-key-here');
   ```
3. Copy `config/mailer.example.php` → `config/mailer.php`
4. Open `config/mailer.php` and fill in your own Gmail address and [App Password](https://myaccount.google.com/apppasswords):
   ```php
   define('GMAIL_ADDRESS', 'your-email@gmail.com');
   define('GMAIL_APP_PASSWORD', 'your-16-character-app-password');
   ```

> `config/gemini.php` and `config/mailer.php` are listed in `.gitignore` and will never be committed — only the `.example.php` templates are tracked in this repo. Never paste real keys into the `.example.php` files.

### 5. Run it

Visit `http://localhost/gurong-gabai/` in your browser. Register a teacher account, verify the OTP sent to your email, then manually approve that account in the database (set its `role` to `admin` and `status` to `approved` in the `teachers` table) so you have at least one working admin account to approve future teachers through the UI.

---

## Project Structure

```
gurong-gabai/
├── config/              # App configuration (gemini.php, mailer.php, db.php, session.php)
├── modules/
│   ├── auth/             # Registration, login, OTP, password reset
│   ├── generator/         # AI lesson plan generator
│   ├── library/           # Saved lesson plans
│   ├── history/           # Generation activity log
│   ├── trash/             # Soft-deleted lesson plans
│   ├── admin/             # Account approval and management
│   ├── export/            # PDF and DOCX export
│   └── profile/           # Teacher profile settings
├── includes/             # Shared header/footer/sidebar templates
├── assets/               # CSS and static files
└── database_setup.sql    # Database schema
```

---

## Security Notes

This project implements several security practices, including BCrypt password hashing, prepared statements (SQL injection protection), role-based access control, email OTP verification, secure session handling, and login rate limiting. See the project documentation for a full breakdown.

---

## Author

**Mary Anne B. Purawan**

Academic Project for Application Development and Emerging Technologies (ADET).

---

## License

This project was created for academic purposes. Feel free to explore the code, but please don't use it commercially without permission.
