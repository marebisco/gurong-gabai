<?php
// ============================================================
// FILE: modules/generator/save.php
// PURPOSE: Bagong endpoint na ginagamit ng "Save to Library" button
//          sa AI Generator page. Hindi ito tradisyunal na form submit
//          (na nag-re-redirect papuntang ibang page) — JSON response
//          ito na binabasa ng JavaScript (fetch), para manatili ang
//          teacher sa parehong AI Generator screen pagkatapos i-save.
//
// BAGONG BEHAVIOR:
//   - Unang Save  → INSERT bagong row sa lesson_plans, ibalik ang
//                    bagong lesson_plan_id
//   - Susunod pang Save (parehong session, in-edit pa) → UPDATE sa
//                    parehong row gamit ang lesson_plan_id na ibinalik
//                    sa unang save (ipinapasa pabalik sa form)
//
// Ito ang sumasagot sa concern: "ayaw lumipat ng page pag-save, pero
// dapat may success message at ma-enable ang Export pagkatapos."
// ============================================================

require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();
header('Content-Type: application/json');

$tid = $_SESSION['teacher_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

// Kung may existing_id na ipinasa, ibig sabihin ito ay UPDATE
// (pangalawa o sumunod pang Save sa parehong lesson plan), hindi
// na bagong INSERT.
$existingId = (int)($_POST['existing_id'] ?? 0);

$grade    = trim($_POST['grade_level'] ?? '');
$subject  = trim($_POST['subject']     ?? '');
$topic    = trim($_POST['topic']       ?? '');
$curr     = trim($_POST['curriculum']  ?? 'MATATAG');
$format   = trim($_POST['format']      ?? 'ILAW');
$strategy = trim($_POST['strategy']    ?? '');
$title    = "$subject - $grade: $topic";

$sec = [];
foreach ([
    'learning_objectives', 'materials_needed', 'introduction_motivation',
    'lesson_body', 'learning_activities', 'assessment', 'closure'
] as $s) {
    $sec[$s] = trim($_POST[$s] ?? '');
}

if (!$grade || !$subject || !$topic) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields (grade, subject, or topic).']);
    exit();
}

// ── DETERMINE: INSERT (bagong plan) o UPDATE (existing plan) ────
// Kung may existing_id, i-verify MUNA na pag-aari talaga ito ng
// kasalukuyang naka-login na teacher, bago payagan ang UPDATE —
// proteksyon laban sa pag-edit ng lesson plan ng ibang teacher
// kung sakaling may mag-manipulate ng existing_id sa request.
$planId = 0;

if ($existingId > 0) {
    $check = mysqli_prepare($conn, "SELECT id FROM lesson_plans WHERE id=? AND teacher_id=?");
    mysqli_stmt_bind_param($check, 'ii', $existingId, $tid);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);

    if (mysqli_stmt_num_rows($check) > 0) {
        // ── UPDATE: i-update ang content ng existing record ──────
        $sql = "UPDATE lesson_plans SET
                    title=?, grade_level=?, subject=?, topic=?, curriculum=?, format=?, strategy=?,
                    learning_objectives=?, materials_needed=?, introduction_motivation=?,
                    lesson_body=?, learning_activities=?, assessment=?, closure=?,
                    is_saved=1, updated_at=NOW()
                WHERE id=? AND teacher_id=?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt, 'sssssssssssssiii',
                $title, $grade, $subject, $topic, $curr, $format, $strategy,
                $sec['learning_objectives'], $sec['materials_needed'], $sec['introduction_motivation'],
                $sec['lesson_body'], $sec['learning_activities'], $sec['assessment'], $sec['closure'],
                $existingId, $tid
            );
            if (mysqli_stmt_execute($stmt)) {
                $planId = $existingId;
            }
        }
    }
}

if ($planId === 0 && $existingId === 0) {
    // ── INSERT: unang pagkakataon na i-save ang lesson plan na ito ──
    $sql = "INSERT INTO lesson_plans
            (teacher_id, title, grade_level, subject, topic, curriculum, format, strategy,
             learning_objectives, materials_needed, introduction_motivation,
             lesson_body, learning_activities, assessment, closure,
             is_saved, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param(
            $stmt, 'issssssssssssss',
            $tid, $title, $grade, $subject, $topic, $curr, $format, $strategy,
            $sec['learning_objectives'], $sec['materials_needed'], $sec['introduction_motivation'],
            $sec['lesson_body'], $sec['learning_activities'], $sec['assessment'], $sec['closure']
        );
        if (mysqli_stmt_execute($stmt)) {
            $planId = mysqli_insert_id($conn);
        }
    }
}

if ($planId === 0) {
    echo json_encode(['success' => false, 'error' => 'Save failed: ' . mysqli_error($conn)]);
    exit();
}

// ── I-LOG SA lesson_plan_history ────────────────────────────────
// "saved" kung bagong INSERT, "updated" kung existing record na
// muling na-save — para malinaw sa history log kung ano talaga
// ang nangyari, hindi lang palaging "saved."
$action = ($existingId > 0) ? 'updated' : 'saved';
$histStmt = mysqli_prepare($conn,
    "INSERT INTO lesson_plan_history
     (teacher_id, lesson_plan_id, action, curriculum, format, strategy, created_at)
     VALUES (?,?,?,?,?,?,NOW())"
);
if ($histStmt) {
    mysqli_stmt_bind_param($histStmt, 'iissss', $tid, $planId, $action, $curr, $format, $strategy);
    mysqli_stmt_execute($histStmt); // hindi kritikal — hindi titigil ang save flow kung mabigo ito
}

// ── IBALIK ANG SUCCESS RESPONSE ──────────────────────────────────
// Ang plan_id ay babalik sa JavaScript, ititago sa form para kung
// magpindot ulit ng Save (after edit), malalaman na UPDATE na ito
// hindi na bagong INSERT. Ito rin ang ID na gagamitin ng Export
// buttons, na dati naka-disable bago ma-save.
echo json_encode([
    'success' => true,
    'plan_id' => $planId,
    'message' => ($existingId > 0)
        ? 'Lesson plan updated successfully!'
        : 'Lesson plan saved to your Resource Library!',
]);
exit();
?>