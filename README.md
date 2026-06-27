# Gurong GabAI — Update v3 (June 2026)
## AI-Powered Lesson Plan Generator — ADET Project

---

## 📦 Files in This Update Package

| File | Replace At | What Changed |
|---|---|---|
| `gemini.php` | `config/gemini.php` | ILAW format, 3 curricula, 5 backup AI models, format-specific prompts |
| `generator_index.php` | `modules/generator/index.php` | ILAW in dropdown, auto-curriculum detection, format-aware UI |
| `view.php` | `modules/generator/view.php` | Format-aware section labels (THE BIG FIX) |
| `database_setup.sql` | Replace your old one | ILAW in ENUMs, TEXT columns, history table, migration script |

---

## 🚀 Deployment Steps

### If this is a fresh install:
1. Open **phpMyAdmin** → create database `gurong_gabai_db`
2. Import `database_setup.sql` (only the top CREATE TABLE section)
3. Copy all PHP files to their correct locations (see table above)

### If you already have the database (MIGRATION):
1. Open **phpMyAdmin** → `gurong_gabai_db` → SQL tab
2. Run **ONLY the bottom migration section** of `database_setup.sql` (after the "MIGRATION SCRIPT" comment)
3. Replace the PHP files

---

## 🔬 Research Findings: DepEd Curricula & ILAW Format

### DepEd Curricula Currently in Use (SY 2026-2027)

| Curriculum | Grades | Status |
|---|---|---|
| **MATATAG + Three-Term (ILAW)** | Kinder – Grade 9 | ✅ CURRENT (SY 2026-2027) |
| **K-12** | Grades 10, 11, 12 | ✅ Still in use (Grade 10 transitions SY 2027-2028) |
| **MATATAG (old quarterly)** | Kinder – Grade 9 | Phased out; replaced by Three-Term SY 2026-2027 |

**What happened:** DepEd shifted to a **Three-Term School Calendar** (DO No. 9, s. 2026) starting SY 2026-2027. The old 4-quarter system is replaced with 3 terms. This required a new lesson plan format — the **ILAW Format**.

---

### 📋 The ILAW Format (DepEd Order No. 16, s. 2026)

**ILAW** = **I**ntentions · **L**earning Experiences · **A**ssessment · **W**ays Forward

The ILAW format is the **new official lesson plan format** for SY 2026-2027, replacing both the DLP and DLL for public school teachers teaching Kinder–Grade 9. Key features:

#### I — INTENTIONS
- Learning Competency (from MATATAG Three-Term Budget of Work)
- Content Standard & Performance Standard
- Session objectives (Knowledge, Skills, Attitude/Values)
- **Learner Context** — describe actual learners' strengths and barriers

#### L — LEARNING EXPERIENCES
- Pre-Lesson: warm-up / review / hook activity
- Main Lesson: detailed teaching-learning flow using evidence-based design
  - Guided by 8 Evidence-Based Learning Design Principles
  - Uses frameworks: 4A's, 4I's (Introduce, Interact, Integrate, Internalize), or 4C's
- Integration & Differentiation: cross-curricular connections, inclusion strategies

#### A — ASSESSMENT
- Formative assessment embedded throughout (not just at the end)
- Evidence of learning to be collected and used
- Summative if applicable

#### W — WAYS FORWARD
- **Remediation plan** for learners who did not meet the objective
- **Enrichment activity** for advanced learners
- Teacher's reflection prompts
- **AI Use Declaration** (new requirement: if AI was used to draft the plan, declare it!)

#### Key differences from DLP:
- The ILAW format is **shorter and more flexible** than DLP
- It requires a **Learner Context** section (DLP did not)
- It requires **Ways Forward** (remediation + enrichment) — DLP had this as optional
- It has an **AI Use Declaration** requirement

---

### What curricula do OTHER teachers use? (For your system's flexibility)

Many teachers — especially private schools, ALS, and SHS teachers — still use formats not tied to MATATAG or K-12:

| Format | Who Uses It | Curriculum Basis |
|---|---|---|
| **ILAW** | Public school teachers (Kinder-Gr.9), SY 2026-2027 | MATATAG Three-Term |
| **DLP** | New public school teachers, traditional schools | MATATAG or K-12 |
| **DLL** | Experienced public school teachers | MATATAG or K-12 |
| **4A's** | Most popular across all school types | Any curriculum |
| **5E's** | Science, Math teachers (inquiry-based) | Any curriculum |
| **Traditional** | Private schools, older teachers | Any curriculum |
| **Semi-Detailed** | Experienced teachers, private schools | Any curriculum |

**Our system now supports ALL of these with ALL curricula combinations.** A teacher can pick any combination — e.g., "4A's format with MATATAG curriculum" or "Traditional format with K-12" — and the AI will generate accordingly.

---

## 🐛 Bugs Fixed in This Update

### Bug 1: All formats showed the SAME section labels ❌ → ✅
**Problem:** `view.php` had hardcoded section labels:
```php
$sections = [
    'learning_objectives' => 'Learning Objectives',  // Always same!
    ...
];
```
A teacher who generated an ILAW plan would see "Learning Objectives" instead of "I. INTENTIONS". A 4A's plan would show "Lesson Body" instead of "ACTIVITY + ANALYSIS".

**Fix:** `view.php` now reads the stored `format` from the database and calls `getSectionLabels($stored_format)` to get the correct labels for each format.

### Bug 2: AI generated same generic content regardless of format ❌ → ✅
**Problem:** The old `generateLessonPlan()` function sent the same prompt structure for all formats. The AI received different section *labels* but the same *instructions*, so DLP and 4A's would generate nearly identical content.

**Fix:** `gemini.php` now has a `buildPrompt()` function with a **different prompt for each format**. ILAW gets an ILAW-specific prompt, 4A's gets a 4A's-specific prompt, etc. The AI now truly understands what each format requires.

### Bug 3: ILAW format not available ❌ → ✅
**Problem:** No ILAW format existed in the dropdown or database.

**Fix:** Added ILAW everywhere — `getLessonPlanFormats()`, `getSectionLabels()`, the database ENUM, and the generator UI.

### Bug 4: Curriculum dropdown only had 2 options, no ILAW ❌ → ✅
**Problem:** The old dropdown had `MATATAG` and `K-12` but not the new `ILAW/Three-Term` curriculum.

**Fix:** Added `ILAW` as the third curriculum option. It auto-selects when the teacher picks Kinder-Grade 9 (since these are the grades under the Three-Term system for SY 2026-2027).

### Bug 5: No backup AI models for reliability ❌ → ✅ (already done previously, now improved)
**Problem:** If the primary model (Gemini 2.0 Flash) failed, the system crashed.

**Fix:** Now tries 5 models in order:
1. `google/gemini-2.0-flash` (primary, fast)
2. `google/gemini-flash-1.5` (fallback)
3. `openai/gpt-4o-mini` (fallback)
4. `anthropic/claude-haiku` (fallback)
5. `meta-llama/llama-3.1-8b-instruct` (free tier fallback)

---

## 💡 For Your ADET Documentation

### Key Technical Points to Highlight
- **AI Integration:** Uses OpenRouter API as a unified gateway to multiple AI models (Google Gemini, OpenAI GPT-4o-mini, Anthropic Claude, Meta Llama)
- **Failover Architecture:** 5-model cascade ensures near-100% uptime even if one provider has downtime
- **Format-Specific Prompting:** Each lesson plan format has a custom-engineered AI prompt — not just different labels but truly different content structure
- **DepEd Alignment:** Supports the newest ILAW format (DO No. 16, s. 2026), MATATAG curriculum, and K-12 — covering all current Philippine public and private school needs
- **Curriculum-Aware Auto-Detection:** The system automatically suggests the correct curriculum based on the selected grade level

### Tagline / Pitch (for your marketing copy)
> "Gurong GabAI helps teachers create lesson plans in different formats, including the **ILAW Format** for the new DepEd Three-Term System. No more complicated prompts. No more copy-paste struggles."

---

## ⚠️ Important Reminder
Your **OpenRouter API key** is currently hardcoded in `config/gemini.php`. This is fine for development/localhost, but before going live or uploading to GitHub, move it to a `.env` file or to a separate `config/keys.php` that is in `.gitignore`.
