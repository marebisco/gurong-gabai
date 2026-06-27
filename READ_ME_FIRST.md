# Gurong GabAI — Mga Naayos na Files (June 2026, Round 2)

## Ano ang ginawa
Database fix (DONE na, sa phpMyAdmin mo): naidagdag ang `curriculum` at `format`
columns sa `lesson_plans` table. Ito ang root cause ng "DLP" na laging lumalabas.
CONFIRMED na gumagana na ito — nakita sa screenshot mo na tama na ang "ILAW" /
"4As" sa History at sa lesson_plans table mismo.

5 PHP files ang na-update dito. I-COPY at I-PASTE lang ang bawat isa sa eksaktong
parehong file path sa project mo (papalitan ang luma):

| File dito sa package | I-paste sa | Ano ang naayos |
|---|---|---|
| `modules/generator/save.php` | `gurong-gabai/modules/generator/save.php` | Prepared statement; tama na ang pag-save ng curriculum/format |
| `modules/library/index.php` | `gurong-gabai/modules/library/index.php` | Ipinapakita na ang curriculum bilang maliit na badge sa ilalim ng format |
| `modules/history/index.php` | `gurong-gabai/modules/history/index.php` | (1) Tinanggal ang Duplicate button. (2) Round 2: ang "Delete" ay SOFT DELETE na (Move to Trash) sa halip na permanenteng DELETE — consistent na sa Library. Hindi na rin ipinapakita ang naka-Trash na plans dito. |
| `modules/generator/index.php` | `gurong-gabai/modules/generator/index.php` | Tinanggal ang duplicate na buttons sa ibaba; ginawang "Export" dropdown (PDF/DOCX) ang "Print/PDF"; ginawang gumana ang "Regenerate". **May FIX din dito sa parse error (`<?php if ($generated)` na nawala) — siguraduhing ITO ang pinaka-bagong bersyon.** |
| `modules/generator/view.php` | `gurong-gabai/modules/generator/view.php` | ROUND 2 (BAGO): Tinanggal ang duplicate na "Save Changes"/"Back to Library" buttons sa ibaba ng lesson plan document. Tinanggal ang standalone "Print" button. Pinagsama ang "Export PDF" at "Export DOCX" sa isang "Export" dropdown button. |

## Mahalagang paalala
- Hindi nagalaw ang `config/gemini.php` — gumagana na talaga ang per-format
  na AI generation (ILAW, DLP, 4As, 5Es, Traditional, Semi, DLL).
- Ang "Export" sa Generator page (`generator/index.php`) ay gagana lang KUNG
  naka-save na ang plan (kailangan ng database ID). Kung subukan mong
  i-export bago ma-save, aabisuhan ka munang i-save muna — normal lang ito.
- Ang "Export" sa View page (`generator/view.php`) ay gagana agad dahil
  naka-save na talaga ang plan bago ito ma-view.
- Ang "Regenerate" ay gagamit ng EXACT na parehong settings ng kasalukuyang
  lesson plan — direktang mag-generate ulit, walang kailangang i-adjust.
- TUNGKOL SA "DELETE": ang "Move to Trash" (soft delete) ay paglagay lang ng
  marka (deleted_at = ngayon) — pwede pang i-restore sa Trash page. Ang
  "Delete Forever" sa Trash page mismo ang TUNAY na hard delete — saka na
  lang talaga mawawala sa database. Ang History at Library ay pareho na
  ngayon — soft delete lang, papunta sa Trash.

## Hindi pa kasama dito (puwede pang ayusin kung kailangan)
- Ang `modules/export/pdf.php` at `modules/export/docx.php` ay gumagamit pa
  rin ng generic na 7-section labels ("I. Learning Objectives", etc.) sa
  halip na format-aware na labels mula sa `getSectionLabels()`. Kaya kung
  i-export mo ang isang ILAW-format na lesson plan, makikita mo pa rin ang
  "I. Learning Objectives" sa exported file sa halip na "I. Intentions".
  Sabihin mo lang kung gusto mo ring ayusin ito.

