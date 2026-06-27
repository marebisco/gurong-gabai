<?php
// ============================================================
// FILE: config/gemini.php
// PURPOSE: Ito ang "isip" ng AI Lesson Plan Generator. Dito nakatira
//          ang lahat ng:
//          - Listahan ng Curriculum, Academic Calendar, at Lesson
//            Plan Format choices (mga dropdown sa Generator page)
//          - Pag-gawa ng prompt/instructions na ipapadala sa AI
//          - Pagtawag sa OpenRouter API gamit ang multi-model fallback
//          - Paglilinis ng AI response bago ito ipakita sa teacher
//
// ARCHITECTURE NOTE:
// Apat na HIWALAY na dimensyon ang ginagamit sa Generator, hindi dapat
// paghaluin:
//   1. CURRICULUM        — ANO ang itinuturo (MATATAG o K-12 SHS)
//   2. ACADEMIC CALENDAR — KAILAN/paano hinati ang school year
//                          (Four-Quarter o Three-Term/ILAW)
//   3. LESSON PLAN FORMAT— PAANO isusulat ang papel (ILAW, DLP, 4A's,
//                          5E's, Traditional, Semi, DLL)
//   4. TEACHING STRATEGY — PAANO ituturo sa klase (Discussion-Based,
//                          Activity-Based, atbp.)
// ============================================================

// ── API Configuration ──────────────────────────────────────
// PRIMARY: OpenRouter (isang gateway na nag-route sa maraming AI
// providers — Google, OpenAI, Anthropic, Meta — gamit ng ISANG API key)
// Palitan ang OPENROUTER_API_KEY ng totoong key mula sa openrouter.ai
define('OPENROUTER_API_KEY', 'Ilagay rito');
define('OPENROUTER_API_URL', 'Ilagay rito');


// ── DIMENSYON #1: Curriculum (ANO ang itinuturo) ────────────
// Dalawang TOTOONG curriculum lang — base sa aktwal na DepEd Orders.
// Tinanggal na ang "ILAW" dito dahil ito ay academic calendar/format,
// hindi curriculum (tingnan ang getAcademicCalendars() sa baba).
function getAvailableCurricula(): array {
    return [
        'MATATAG' => 'MATATAG Curriculum (K-10, SY 2024+)',
        'K-12'    => 'K-12 Senior High School Curriculum (Grades 11-12)',
    ];
}

// Auto-suggest ng default curriculum base sa pinili na grade level.
// Source: DepEd Order No. 10, s. 2024 (MATATAG phased rollout, K-10)
function getDefaultCurriculumForGrade(string $grade): string {
    if (in_array($grade, ['Grade 11', 'Grade 12'])) return 'K-12';
    return 'MATATAG'; // Kinder - Grade 10: MATATAG
}


// ── DIMENSYON #2: Academic Calendar (KAILAN/paano hinati ang taon) ──
// Hiwalay na ito sa curriculum. Ang "Three-Term" ay ang bagong ILAW
// calendar (DO 9, s. 2026), applicable lang sa MATATAG grades (K-10).
function getAcademicCalendars(): array {
    return [
        'FourQuarter' => 'Four-Quarter Calendar (Traditional)',
        'ThreeTerm'   => 'Three-Term Calendar (SY 2026-2027, per DO 9, s. 2026)',
    ];
}

// Auto-suggest ng default calendar base sa grade level. Ang Grade 11-12
// (K-12 SHS) ay hindi pa nasasakop ng Three-Term calendar.
function getDefaultCalendarForGrade(string $grade): string {
    if (in_array($grade, ['Grade 11', 'Grade 12'])) return 'FourQuarter';
    return 'ThreeTerm'; // Kinder - Grade 10: Three-Term/ILAW
}


// ── DIMENSYON #3: Lesson Plan Format (PAANO isusulat ang papel) ────
// Pitong format — kasama ang ILAW bilang FORMAT (hindi curriculum).
// Bawat isa ay may sariling AI prompt instructions (tingnan ang
// buildPrompt() sa baba) at sariling section labels (tingnan ang
// getSectionLabels() sa baba).
function getLessonPlanFormats(): array {
    return [
        'ILAW'         => 'ILAW Format (New — DO No. 16, s. 2026)',
        'DLP'          => 'Detailed Lesson Plan (DLP)',
        '4As'          => "4 A's Lesson Plan",
        '5Es'          => "5 E's Lesson Plan",
        'Traditional'  => 'Traditional Lesson Plan',
        'Semi'         => 'Semi-Detailed Lesson Plan',
        'DLL'          => 'Daily Lesson Log (DLL)',
    ];
}


// ── Section labels per format (maps to 7 DB columns) ───────
// Ang bawat lesson plan ay may 7 fields sa database (learning_objectives,
// materials_needed, atbp.) — pero ang LABEL na nakikita ng teacher para
// sa bawat field ay nagbabago depende sa format. Halimbawa, ang
// 'lesson_body' field ay "ACTIVITY + ANALYSIS" sa 4A's pero
// "EXPLORE + EXPLAIN" sa 5E's — parehong column, magkaibang pangalan.
//
// Bawat entry: [icon, display_label, AI_instruction_description]
//
// ILAW gamit ang bagong DO No. 16, s. 2026 framework:
//   I - Intentions, L - Learning Experiences, A - Assessment, W - Ways Forward
function getSectionLabels(string $format): array {
    $map = [
        // ─── ILAW FORMAT (BAGO — DepEd Order No. 16, s. 2026) ──────────────
        // Required starting Term 2, SY 2026-2027 (public schools).
        'ILAW' => [
            'learning_objectives'     => ['🎯', 'I. INTENTIONS', 'State the Learning Competency (from MATATAG Three-Term Budget of Work), Content Standard, Performance Standard, and specific session objectives. Include Learner Context — honest description of this group\'s strengths, interests, and barriers to learning.'],
            'materials_needed'        => ['📦', 'LEARNING RESOURCES', 'List all materials, textbooks (LM/TG page references), digital tools, manipulatives, and community-based materials needed for the lesson.'],
            'introduction_motivation' => ['🔄', 'L — PRE-LESSON (Review / Hook)', 'Brief warm-up or review activity (5-10 min). Connect prior knowledge to today\'s lesson. Include a hook or motivating question.'],
            'lesson_body'             => ['🎭', 'L — LEARNING EXPERIENCES (Main Lesson)', 'Detailed flow of teaching and learning activities guided by the 8 Evidence-Based Learning Design Principles. Include teacher moves, student tasks, key questions, and scaffolding. Reference chosen framework (4A\'s, 4I\'s, or 4C\'s if applicable).'],
            'learning_activities'     => ['🔗', 'L — INTEGRATION & DIFFERENTIATION', 'Cross-curricular connections (link to other subjects or real-life contexts). Strategies for inclusion and learner diversity. Note opportunities for learner agency and choice.'],
            'assessment'              => ['📝', 'A. ASSESSMENT', 'Formative assessment strategies integrated throughout the lesson (oral questioning, exit tickets, observation, seatwork). Summative assessment task if applicable. Describe how evidence of learning will be gathered and used.'],
            'closure'                 => ['🌟', 'W. WAYS FORWARD', 'Remediation plan for learners who did not meet the objective. Enrichment activities for advanced learners. Teacher\'s reflection notes on what worked and what to adjust next session.'],
        ],

        // ─── DLP ────────────────────────────────────────────────────────────
        'DLP' => [
            'learning_objectives'     => ['🎯', 'I. OBJECTIVES', 'Content Standard, Performance Standard, Learning Competencies/Objectives (with LC code). Use K.S.A. — Knowledge, Skills, Attitude.'],
            'materials_needed'        => ['📦', 'II. CONTENT & LEARNING RESOURCES', 'Topic/Lesson Title, References (Teacher\'s Guide, Learner\'s Materials, Textbook pages), Other Learning Resources.'],
            'introduction_motivation' => ['🔄', 'III. PROCEDURES — Review & Motivation', 'A. Review previous lesson. B. Establishing purpose. C. Presenting examples/instances.'],
            'lesson_body'             => ['📖', 'III. PROCEDURES — Lesson Proper', 'D. Discussing new concepts (Practice 1 & 2). E. Developing mastery (leads to Formative Assessment). F. Finding practical applications.'],
            'learning_activities'     => ['🎭', 'III. PROCEDURES — Guided Practice', 'G. Making generalizations and abstractions about the lesson. H. Evaluating Learning (Formative).'],
            'assessment'              => ['📝', 'IV. EVALUATING LEARNING', 'Summative assessment questions, quiz, or performance task to measure mastery of competencies.'],
            'closure'                 => ['🏠', 'V. ASSIGNMENT / AGREEMENT + REFLECTION', 'Assignment or enrichment activity. Add brief Remarks and Reflection on lesson delivery.'],
        ],

        // ─── 4A's ────────────────────────────────────────────────────────────
        '4As' => [
            'learning_objectives'     => ['🎯', 'OBJECTIVES (C.A.P.)', 'State objectives in terms of Cognitive (knowledge), Affective (attitude), and Psychomotor (skill/performance).'],
            'materials_needed'        => ['📚', 'SUBJECT MATTER & MATERIALS', 'Topic, Reference, Materials needed for the lesson.'],
            'introduction_motivation' => ['🙏', 'PRELIMINARY ACTIVITIES', 'Prayer, Greetings, Attendance checking, Classroom management, Review of previous lesson.'],
            'lesson_body'             => ['🎭', 'ACTIVITY + ANALYSIS', 'ACTIVITY: Engaging task or game to introduce the concept. ANALYSIS: Guided processing questions to help students understand the activity.'],
            'learning_activities'     => ['💡', 'ABSTRACTION + APPLICATION', 'ABSTRACTION: Key concepts, definitions, and generalizations. APPLICATION: Practical tasks applying new knowledge to real-life situations.'],
            'assessment'              => ['📝', 'ASSESSMENT', 'Formative assessment — quiz, recitation, seatwork, or performance task to check learning.'],
            'closure'                 => ['🏠', 'ASSIGNMENT / AGREEMENT', 'Take-home activity, enrichment task, or advance reading for the next lesson.'],
        ],

        // ─── 5E's ────────────────────────────────────────────────────────────
        '5Es' => [
            'learning_objectives'     => ['🎯', 'OBJECTIVES', 'Content Standard, Performance Standard, Learning Competency/Objectives in terms of Knowledge, Skills, and Attitude.'],
            'materials_needed'        => ['📦', 'LEARNING TASK & MATERIALS', 'Materials, references, and technology tools needed for the 5E activities.'],
            'introduction_motivation' => ['✨', 'ENGAGE', 'A hook activity (5 min) to capture interest and activate prior knowledge. Ask a thought-provoking question or show a surprising phenomenon.'],
            'lesson_body'             => ['🔭', 'EXPLORE + EXPLAIN', 'EXPLORE: Hands-on activity where students investigate the concept. EXPLAIN: Teacher clarifies concepts, introduces vocabulary, and students share findings.'],
            'learning_activities'     => ['🔗', 'ELABORATE', 'Extend understanding by applying the concept to new situations or connecting to other subjects/real life.'],
            'assessment'              => ['📊', 'EVALUATE', 'Formal and informal assessment to determine mastery — quiz, performance task, exit ticket, or portfolio entry.'],
            'closure'                 => ['🏠', 'CLOSURE & ASSIGNMENT', 'Summarize key concepts. Give a meaningful take-home task connected to the lesson.'],
        ],

        // ─── Traditional ─────────────────────────────────────────────────────
        'Traditional' => [
            'learning_objectives'     => ['🎯', 'I. OBJECTIVES', 'State objectives using Cognitive (C), Affective (A), and Psychomotor (P) domains using action verbs.'],
            'materials_needed'        => ['📚', 'II. SUBJECT MATTER', 'Topic, Reference (book, page number), Materials needed.'],
            'introduction_motivation' => ['💡', 'III. PROCEDURE — Motivation & Review', 'Opening activities: Prayer, Greetings, Checking of attendance, Review of previous lesson, Motivation activity.'],
            'lesson_body'             => ['📖', 'III. PROCEDURE — Presentation & Discussion', 'Present the new topic. Discussion, explanation, and demonstration of the new concept with examples.'],
            'learning_activities'     => ['🎭', 'III. PROCEDURE — Application & Generalization', 'Application exercises, practice activities, and drawing of generalizations/summary.'],
            'assessment'              => ['📝', 'IV. EVALUATION', 'Short quiz, seatwork, or test questions to evaluate mastery of the lesson.'],
            'closure'                 => ['🏠', 'V. ASSIGNMENT', 'Homework or advance study assignment for the next lesson.'],
        ],

        // ─── Semi-Detailed ───────────────────────────────────────────────────
        'Semi' => [
            'learning_objectives'     => ['🎯', 'OBJECTIVES', 'State the lesson objectives for the day using measurable action verbs.'],
            'materials_needed'        => ['📚', 'SUBJECT MATTER & RESOURCES', 'Topic, Reference, Materials.'],
            'introduction_motivation' => ['💡', 'MOTIVATION / DRILL', 'Brief warm-up, drill, or motivation activity to start the lesson.'],
            'lesson_body'             => ['📖', 'LESSON PROPER', 'Key teaching points and concept development for the lesson.'],
            'learning_activities'     => ['🎭', 'LEARNING ACTIVITIES', 'Guided and independent practice activities for students.'],
            'assessment'              => ['📝', 'EVALUATION', 'Short assessment to check for understanding.'],
            'closure'                 => ['🏠', 'ASSIGNMENT', 'Take-home activity or assignment.'],
        ],

        // ─── DLL ─────────────────────────────────────────────────────────────
        'DLL' => [
            'learning_objectives'     => ['🎯', 'I. OBJECTIVES', 'Content Standard, Performance Standard, Learning Competency/Objectives (LC Code) per day for the week.'],
            'materials_needed'        => ['📦', 'II. CONTENT & III. LEARNING RESOURCES', 'Learning area topic, References (TG/LM page numbers), Other materials.'],
            'introduction_motivation' => ['🔄', 'IV. PROCEDURES — Before the Lesson', 'Review/drill, motivational activity, presentation of objectives.'],
            'lesson_body'             => ['📖', 'IV. PROCEDURES — During the Lesson', 'Main teaching procedure: discussion, activity, explanation.'],
            'learning_activities'     => ['🎭', 'IV. PROCEDURES — Deepening', 'Application activities, practice exercises.'],
            'assessment'              => ['📝', 'IV. PROCEDURES — Assessing Learning', 'Formative assessment activity — oral, written, or performance.'],
            'closure'                 => ['✍️', 'V. REMARKS & VI. REFLECTION', 'Teacher\'s remarks on lesson delivery and reflection on what worked/needs improvement.'],
        ],
    ];

    return $map[$format] ?? $map['DLP'];
}


// ── callAI() — AI caller na may multi-model fallback chain ─────
// Sinusubukan ang mga AI models nang isa-isa, base sa pagkasunod-sunod
// sa listahan. Kapag mabigo ang isa (timeout, walang credits, atbp.),
// automatic na susubukan ang susunod — hanggang sa magtagumpay ang
// isa, o maubos ang lahat ng 5. Ibinabalik ang content string, o null
// kung lahat ay nabigo.
function callAI(string $prompt, bool $isRegenerate = false): ?string {
    $models = [
        'openai/gpt-4o-mini',                 // Primary: CONFIRMED working in actual test (safest first choice)
        'google/gemini-3-flash-preview',      // Fallback 1: CONFIRMED working in actual test
        'meta-llama/llama-3.1-8b-instruct',   // Fallback 2 (free tier, weaker at structured JSON)
    ];
    $lastError = '';

    // Para sa Regenerate, tinataas ang temperature (mas creative/
    // variable ang output) — kasama ng explicit na variation instruction
    // na nilagay sa prompt mismo (generateLessonPlan()), para masigurado
    // na tunay na may kapansin-pansing pagkakaiba ang bagong resulta,
    // hindi lang umaasa sa default randomness ng AI model.
    $temperature = $isRegenerate ? 0.95 : 0.7;

    foreach ($models as $model) {
        // Mas maliit na model (Llama 3.1 8B) ay madalas humaba nang
        // sobra ang sagot bago matapos ang JSON — nauubos ang token budget
        // kalagitnaan ng huling field, kaya "cut off" ang resulta.
        // Solusyon: (1) mas malaking max_tokens reserve para dito, at
        // (2) idinagdag ang isang mahigpit, paulit-ulit na reminder sa
        // dulo ng prompt — mas tumutugon ang mahihinang models sa
        // simpleng, direktang instructions na nilagay sa pinakahuli.
        $isWeakerModel = ($model === 'meta-llama/llama-3.1-8b-instruct');
        $modelPrompt   = $isWeakerModel
            ? $prompt . "\n\nREMINDER: Output ONLY the JSON object. Keep each field concise (3-5 sentences max). Make sure the JSON is complete and properly closed with a final }."
            : $prompt;

        $data = json_encode([
            'model'       => $model,
            'messages'    => [['role' => 'user', 'content' => $modelPrompt]],
            'max_tokens'  => $isWeakerModel ? 6000 : 4096,
            'temperature' => $temperature,
        ]);

        $ch = curl_init(OPENROUTER_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . OPENROUTER_API_KEY,
                'HTTP-Referer: http://localhost/gurong-gabai',
                'X-Title: Gurong GabAI',
            ],
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            $lastError = "CURL error ($model): $curlError";
            error_log("[GabAI] $lastError");
            continue;
        }
        if (!$response) {
            $lastError = "Empty response from $model";
            error_log("[GabAI] $lastError");
            continue;
        }

        $result = json_decode($response, true);

        if (isset($result['error'])) {
            $lastError = "API error ($model): " . ($result['error']['message'] ?? json_encode($result['error']));
            error_log("[GabAI] $lastError");
            continue;
        }

        $content = $result['choices'][0]['message']['content'] ?? null;
        if ($content) {
            error_log("[GabAI] Success with model: $model");
            return $content;
        }

        $lastError = "No content in response from $model";
        error_log("[GabAI] $lastError — raw: " . substr($response, 0, 300));
    }

    error_log("[GabAI] ALL models failed. Last error: $lastError");
    return null;
}


// ── buildPrompt() — Gumagawa ng format-specific na instructions ───
// Ito ang pinaka-mahalagang function — gumagawa ng EKSAKTONG
// instructions na ipapadala sa AI, depende sa apat na dimensyon
// (curriculum, calendar, format, strategy) na pinili ng teacher.
// Bawat lesson plan FORMAT (ILAW, 4A's, 5E's, DLL) ay may sariling
// BUONG prompt template — sinasadya itong ginawang detalyado para
// SIGURADONG sumusunod ang AI sa eksaktong istraktura ng format,
// hindi lang basta generic na content na may ibang label.
function buildPrompt(
    string $grade,
    string $subject,
    string $topic,
    string $duration,
    string $strategy,
    string $curriculum,
    string $calendar,
    string $format
): string {

    // Tukuyin kung anong wika gagamitin sa nilalaman ng lesson plan
    $langNote = '';
    if (str_contains($subject, 'Filipino') || $subject === 'Araling Panlipunan' || $subject === 'ESP') {
        $langNote = 'LANGUAGE: Write all lesson content (not the JSON keys) entirely in FILIPINO/TAGALOG.';
    } elseif ($subject === 'Mother Tongue') {
        $langNote = 'LANGUAGE: Write lesson content in FILIPINO/TAGALOG. Note "Mother Tongue" activities where applicable.';
    } else {
        $langNote = 'LANGUAGE: Write all lesson content in ENGLISH.';
    }

    // DIMENSYON #1 NOTE: Curriculum (ANO ang itinuturo)
    switch ($curriculum) {
        case 'K-12':
            $curriculumNote = 'CURRICULUM: K-12 Senior High School Curriculum (DepEd Order No. 42, s. 2016). Align with K-12 content standards, performance standards, and learning competencies.';
            break;
        default: // MATATAG
            $curriculumNote = 'CURRICULUM: MATATAG Curriculum (DepEd Order No. 10, s. 2024). Align objectives with MATATAG competency standards. Emphasize decongested content, foundational skills, 21st-century skills, and values integration.';
    }

    // DIMENSYON #2 NOTE: Academic Calendar (KAILAN/paano hinati ang taon)
    // Hiwalay ito sa curriculum note sa itaas — pinagsasama lang sila
    // sa ibaba bilang dalawang magkahiwalay na linya sa prompt.
    switch ($calendar) {
        case 'ThreeTerm':
            $calendarNote = 'ACADEMIC CALENDAR: Three-Term School Calendar (DepEd Order No. 9, s. 2026). Align objectives with the MATATAG Three-Term Budget of Work competencies. The lesson is for the current Term of SY 2026-2027.';
            break;
        default: // FourQuarter
            $calendarNote = 'ACADEMIC CALENDAR: Traditional Four-Quarter Calendar. Align objectives with the standard quarterly Budget of Work.';
    }

    // Pagsamahin ang curriculum at calendar notes bilang dalawang
    // hiwalay na linya sa loob ng parehong prompt section
    $curriculumNote = "{$curriculumNote}\n{$calendarNote}";

    // DIMENSYON #3: Lesson Plan Format — bawat isa may sariling
    // detalyadong prompt template, dahil iba-iba talaga ang istraktura
    switch ($format) {

        case 'ILAW':
            return <<<PROMPT
You are an expert Filipino educator and DepEd curriculum specialist. Generate a complete ILAW-format lesson plan following DepEd Order No. 16, series of 2026.

{$curriculumNote}
FORMAT: ILAW Lesson Plan (Intentions, Learning Experiences, Assessment, Ways Forward)
Grade Level: {$grade}
Subject / Learning Area: {$subject}
Lesson Topic / Title: {$topic}
Duration: {$duration}
Teaching Strategy: {$strategy}
{$langNote}

ILAW FORMAT GUIDE (Follow strictly):

INTENTIONS (learning_objectives field):
- State the specific Learning Competency from the MATATAG Three-Term Budget of Work
- Write the Content Standard and Performance Standard
- List 2-3 specific, measurable session objectives using Bloom's verbs (Knowledge, Skills, Attitude/Values)
- Include a brief Learner Context (1-2 sentences describing typical learner profile, strengths, barriers)

LEARNING RESOURCES (materials_needed field):
- List all textbooks (LM/TG page references), visual aids, manipulatives, digital tools
- Include community-based or local materials where applicable

PRE-LESSON (introduction_motivation field):
- 5-10 minute warm-up or review connecting prior knowledge
- Engaging hook question or activity to spark curiosity
- Brief checking of assignment if applicable

LEARNING EXPERIENCES — Main Lesson (lesson_body field):
- Detailed, step-by-step teaching flow guided by evidence-based design (at least 3-4 clear teaching moves)
- Include teacher questions, student activities, and expected responses
- Show how the strategy ({$strategy}) is applied
- Include at least one collaborative or hands-on task
- Note how the lesson connects to learners' real-life context

INTEGRATION & DIFFERENTIATION (learning_activities field):
- Cross-curricular connection (link to 1-2 other subjects or real life)
- Differentiation: one strategy for struggling learners, one for advanced learners
- Inclusion notes (how to support learners with different needs)

ASSESSMENT (assessment field):
- 2-3 formative assessment checkpoints embedded throughout the lesson (oral questions, thumbs-up/down, exit ticket, seatwork)
- Describe the evidence of learning to be collected
- Optional: brief summative task if end-of-lesson assessment is needed

WAYS FORWARD (closure field):
- Specific remediation plan for learners who did not meet the objective (with activity description)
- Specific enrichment activity for advanced learners
- Teacher's reflection prompts (2-3 guiding questions for self-reflection after the lesson)
- AI Use Declaration: "This lesson plan was generated by AI (Gurong GabAI) as a draft. The teacher reviewed and adapted the content for their actual class context."

Return ONLY a valid JSON object with EXACTLY these 7 keys. No markdown, no backticks, no preamble:
{
  "learning_objectives": "detailed INTENTIONS content",
  "materials_needed": "learning resources list",
  "introduction_motivation": "pre-lesson / hook activity",
  "lesson_body": "detailed learning experiences / main lesson flow",
  "learning_activities": "integration and differentiation strategies",
  "assessment": "formative and summative assessment details",
  "closure": "ways forward — remediation, enrichment, reflection, AI declaration"
}
PROMPT;

        case '4As':
            return <<<PROMPT
You are an expert Filipino educator. Generate a complete 4A's lesson plan — the most popular format in Philippine public schools.

{$curriculumNote}
FORMAT: 4 A's Lesson Plan (Activity → Analysis → Abstraction → Application)
Grade Level: {$grade}
Subject / Learning Area: {$subject}
Lesson Topic / Title: {$topic}
Duration: {$duration}
Teaching Strategy: {$strategy}
{$langNote}

4A's FORMAT GUIDE (Follow strictly):

OBJECTIVES (learning_objectives):
- Write Cognitive (C), Affective (A), and Psychomotor (P/Performance) objectives
- Use Bloom's Taxonomy action verbs
- Reference the learning competency code if applicable

SUBJECT MATTER & MATERIALS (materials_needed):
- Topic, Reference books (TG p. XX, LM p. XX), Materials list

PRELIMINARY ACTIVITIES (introduction_motivation):
- Opening prayer/greetings, attendance, classroom management
- Brief review of the previous lesson
- Motivation activity (song, game, video, or question) to introduce the topic

ACTIVITY (first half of lesson_body):
- Design one engaging, concrete activity (group work, experiment, role play, game, or hands-on task)
- Provide step-by-step instructions for the activity
- Include processing/guide questions FOR the analysis step

ANALYSIS (second half of lesson_body):
- Series of questions that guide students to discover the concept from the activity
- Lead students from concrete experience to thinking about why/how

ABSTRACTION (first half of learning_activities):
- Teacher-led discussion of key concepts, definitions, rules, or generalizations
- Connect what students discovered in Activity/Analysis to the formal lesson content

APPLICATION (second half of learning_activities):
- Practical task where students APPLY the concept to a new situation
- Can be individual or group: problem-solving, scenario, real-life task

ASSESSMENT (assessment):
- Formative: quiz (5-10 items), recitation, seatwork, or performance task
- Provide at least 3 sample questions or task descriptions

ASSIGNMENT (closure):
- Meaningful take-home activity connecting the lesson to home or community
- Optional: advance reading or preparation for next topic

Return ONLY a valid JSON object with EXACTLY these 7 keys. No markdown, no backticks, no preamble:
{
  "learning_objectives": "...",
  "materials_needed": "...",
  "introduction_motivation": "...",
  "lesson_body": "...",
  "learning_activities": "...",
  "assessment": "...",
  "closure": "..."
}
PROMPT;

        case '5Es':
            return <<<PROMPT
You are an expert Filipino Science educator. Generate a complete 5E's lesson plan — commonly used for Science, Math, and inquiry-based subjects.

{$curriculumNote}
FORMAT: 5 E's Lesson Plan (Engage → Explore → Explain → Elaborate → Evaluate)
Grade Level: {$grade}
Subject / Learning Area: {$subject}
Lesson Topic / Title: {$topic}
Duration: {$duration}
Teaching Strategy: {$strategy}
{$langNote}

5E's FORMAT GUIDE (Follow strictly):

OBJECTIVES (learning_objectives):
- Content Standard, Performance Standard, Learning Competency with LC code
- Session objectives in Knowledge, Skills, and Attitude domains

MATERIALS (materials_needed):
- Full materials list including any lab/experiment materials, digital tools, references

ENGAGE (introduction_motivation):
- Hooking 5-minute activity to capture interest and surface prior knowledge
- Use a surprising demo, video clip, photo, question, or real-world problem
- End with a focusing question the lesson will answer

EXPLORE + EXPLAIN (lesson_body):
- EXPLORE: Structured hands-on activity where students discover the concept themselves (group work, experiment, investigation — step by step)
- EXPLAIN: Teacher formalizes the concepts, introduces vocabulary, students share findings
- Provide sample teacher questions and expected student responses

ELABORATE (learning_activities):
- Activity where students extend the concept to a new, more complex situation
- Connect to real-life application or other subjects
- Include at least one higher-order thinking (HOTs) task

EVALUATE (assessment):
- Formative: observation checklist, exit ticket, quick quiz during the lesson
- Summative: performance task, short written test, or lab report
- Provide at least 3 sample assessment items

CLOSURE & ASSIGNMENT (closure):
- Brief synthesis of key concepts (teacher + student summary)
- Meaningful assignment connecting the lesson to home, nature, or community

Return ONLY a valid JSON object with EXACTLY these 7 keys. No markdown, no backticks, no preamble:
{
  "learning_objectives": "...",
  "materials_needed": "...",
  "introduction_motivation": "...",
  "lesson_body": "...",
  "learning_activities": "...",
  "assessment": "...",
  "closure": "..."
}
PROMPT;

        case 'DLL':
            return <<<PROMPT
You are an expert Filipino educator. Generate a complete Daily Lesson Log (DLL) — the weekly grid format for experienced teachers.

{$curriculumNote}
FORMAT: Daily Lesson Log (DLL) — Weekly format per DepEd Order No. 42, s. 2016
Grade Level: {$grade}
Subject / Learning Area: {$subject}
Lesson Topic / Title: {$topic}
Duration: {$duration} per session
Teaching Strategy: {$strategy}
{$langNote}

DLL FORMAT GUIDE (Follow strictly):

OBJECTIVES (learning_objectives):
- Content Standard, Performance Standard
- Weekly learning competencies (Monday–Friday) with LC codes
- Day-by-day variation of objectives (e.g., Mon: introduce, Tue: practice, Wed: apply, Thu: deepen, Fri: assess)

CONTENT & LEARNING RESOURCES (materials_needed):
- Topic for each day, References (TG p. XX, LM p. XX), Supplementary materials

BEFORE THE LESSON (introduction_motivation):
- Monday: Review previous lesson + motivation activity
- Tue–Fri: Brief drill or review of previous day's lesson
- Include attendance checking and classroom management notes

DURING THE LESSON (lesson_body):
- Day-by-day teaching procedures
- Monday: Introduce concept with examples
- Tuesday: Guided practice
- Wednesday: Application activities
- Thursday: Deepening/enrichment task
- Friday: Review and preparation for assessment

DEEPENING (learning_activities):
- Higher-order activities per day
- Group work, collaborative tasks, real-life application
- Differentiated tasks for advanced learners

ASSESSING LEARNING (assessment):
- Formative assessment activities for each day (oral, written, performance)
- Friday: Summative quiz or performance task
- Note how results will guide next week's instruction

REMARKS & REFLECTION (closure):
- Space for teacher's daily remarks (number of learners who earned 80% above, remediation notes, etc.)
- Weekly reflection: what worked, what needs improvement, problems encountered

Return ONLY a valid JSON object with EXACTLY these 7 keys. No markdown, no backticks, no preamble:
{
  "learning_objectives": "...",
  "materials_needed": "...",
  "introduction_motivation": "...",
  "lesson_body": "...",
  "learning_activities": "...",
  "assessment": "...",
  "closure": "..."
}
PROMPT;

        default: // DLP, Traditional, Semi — shared prompt structure
            $sections = getSectionLabels($format);
            $sectionInstructions = '';
            foreach ($sections as $key => [$ico, $label, $desc]) {
                $sectionInstructions .= "  \"$key\": \"[DETAILED CONTENT FOR: $label — $desc]\",\n";
            }
            $formatFullName = [
                'DLP'         => 'Detailed Lesson Plan (DLP) — the most complete format per DepEd Order No. 42, s. 2016. Include all 5 parts: Objectives, Content & Learning Resources, Procedures (Review, Lesson Proper, Guided Practice), Evaluating Learning, and Assignment/Reflection.',
                'Traditional' => 'Traditional Lesson Plan — 5-part format: Objectives (C.A.P.), Subject Matter, Procedures (Motivation → Presentation → Application → Generalization), Evaluation, Assignment. Classic format used by many public school teachers.',
                'Semi'        => 'Semi-Detailed Lesson Plan — shorter version of DLP. Appropriate for experienced teachers. Less prescriptive but must still cover objectives, motivation, lesson proper, activities, evaluation, and assignment.',
            ][$format] ?? "$format Lesson Plan";

            return <<<PROMPT
You are an expert Filipino educator and DepEd curriculum specialist. Generate a complete, detailed, classroom-ready lesson plan.

{$curriculumNote}
FORMAT: {$formatFullName}
Grade Level: {$grade}
Subject / Learning Area: {$subject}
Lesson Topic / Title: {$topic}
Duration: {$duration}
Teaching Strategy: {$strategy}
{$langNote}

CRITICAL INSTRUCTIONS:
- Generate content SPECIFIC to the {$format} format — not generic content
- Use Bloom's Taxonomy action verbs for all objectives
- Content must be appropriate for {$grade} level Filipino students
- Include specific, concrete activities — not vague placeholders
- For Math/Science: include sample problems, equations, or step-by-step solutions
- For Filipino/AP/ESP: write content in Filipino; include Filipino cultural context
- This should be immediately usable by a Filipino classroom teacher

Section content guide:
{$sectionInstructions}

Return ONLY a valid JSON object with EXACTLY these 7 keys. No markdown, no backticks, no preamble:
{
  "learning_objectives": "detailed content",
  "materials_needed": "detailed content",
  "introduction_motivation": "detailed content",
  "lesson_body": "detailed content",
  "learning_activities": "detailed content",
  "assessment": "detailed content",
  "closure": "detailed content"
}
PROMPT;
    }
}


// ── humanizeValue() — Gawing readable, plain text ang anumang value ──
// PROBLEMA na nire-solve: minsan ang AI ay nagbabalik ng listahan o
// nested data (hal. Monday-Friday schedule) bilang TEXT na naglalaman
// ng JSON syntax, hindi totoong PHP array — kaya kung hindi ito
// ihahawakan, lumalabas ang raw {, }, [, ] sa lesson plan content.
//
// PAANO ITO NAGTATRABAHO:
//   1. Kung STRING ang value pero "mukhang" JSON (nagsisimula sa { o [),
//      susubukan munang i-decode ito bilang totoong array/object bago
//      ito i-convert sa readable text.
//   2. Kung ASSOCIATIVE ARRAY/OBJECT (may mga keys tulad ng "Monday"),
//      gagawing bullet-style "Key: Value" sa bawat linya.
//   3. Kung SIMPLE/INDEXED ARRAY (listahan lang, walang keys), gagawing
//      bullet list gamit ang "• " bilang prefix sa bawat item.
//   4. Kung NESTED (array sa loob ng array), recursive itong tatawagin
//      ang sarili nito para sa bawat antas.
function humanizeValue($val, int $depth = 0): string {
    // Kung string, suriin muna kung "mukhang" JSON syntax ito
    if (is_string($val)) {
        $trimmed = trim($val);
        if ((str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) ||
            (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'))) {
            $maybeDecoded = json_decode($trimmed, true);
            if (is_array($maybeDecoded)) {
                // Totoong JSON pala ito sa loob ng string — i-convert
                return humanizeValue($maybeDecoded, $depth);
            }
        }
        return $val; // Ordinaryong text lang, ibalik as-is
    }

    if (!is_array($val)) {
        return (string)$val;
    }

    // Tukuyin kung associative (may string keys, e.g. "Monday") o
    // indexed/sequential (0, 1, 2...) ang array
    $isAssoc = array_keys($val) !== range(0, count($val) - 1);
    $indent  = str_repeat('  ', $depth);
    $lines   = [];

    if ($isAssoc) {
        foreach ($val as $key => $v) {
            $readableKey = str_replace('_', ' ', (string)$key);
            if (is_array($v)) {
                $lines[] = "{$indent}{$readableKey}:\n" . humanizeValue($v, $depth + 1);
            } else {
                $lines[] = "{$indent}{$readableKey}: " . humanizeValue($v, $depth + 1);
            }
        }
    } else {
        foreach ($val as $v) {
            if (is_array($v)) {
                $lines[] = humanizeValue($v, $depth + 1);
            } else {
                $lines[] = "{$indent}\u{2022} " . humanizeValue($v, $depth + 1);
            }
        }
    }

    return implode("\n", $lines);
}


// ── parseAIResponse() — Robust JSON parser ─────────────────
// Kinukuha ang raw text mula sa AI, tinatanggal ang markdown fences
// (```json), hinahanap ang totoong JSON object kung may extra text
// sa paligid nito, tapos pinoproseso ang lahat ng 7 fields gamit ang
// humanizeValue() para guaranteed na malinis at readable ang lahat.
function parseAIResponse(?string $response): ?array {
    if (!$response) return null;

    $clean = $response;

    // Tanggalin ang markdown code fences kung meron
    $clean = preg_replace('/^```(?:json)?\s*/im', '', $clean);
    $clean = preg_replace('/\s*```\s*$/im', '', $clean);
    $clean = trim($clean);

    // Hanapin ang totoong JSON object kung may extra text sa paligid
    if (($start = strpos($clean, '{')) !== false &&
        ($end   = strrpos($clean, '}')) !== false) {
        $clean = substr($clean, $start, $end - $start + 1);
    }

    $parsed = json_decode($clean, true);

    if (!is_array($parsed)) {
        error_log('[GabAI] JSON parse failed. Raw: ' . substr($response, 0, 500));
        return null;
    }

    // Siguraduhing kompleto ang lahat ng 7 keys, at malinis/readable
    // text ang nilalaman ng bawat isa
    $keys = ['learning_objectives', 'materials_needed', 'introduction_motivation',
             'lesson_body', 'learning_activities', 'assessment', 'closure'];

    foreach ($keys as $k) {
        if (!isset($parsed[$k])) {
            $parsed[$k] = '';
        } else {
            $parsed[$k] = humanizeValue($parsed[$k]);
        }
    }

    if (empty($parsed['learning_objectives'])) {
        error_log('[GabAI] Parsed JSON missing learning_objectives.');
        return null;
    }

    return $parsed;
}


// ── generateLessonPlan() — Pangunahing function na tinatawag mula
//                           sa Generator page ─────────────────────
// Ito ang "entry point" — pinagsasama nito ang lahat: gumagawa ng
// prompt (buildPrompt), nagpapadala sa AI (callAI), at nililinis ang
// resulta (parseAIResponse) — tapos ibinabalik bilang ready-to-use
// PHP array na may 7 keys.
function generateLessonPlan(
    string $grade,
    string $subject,
    string $topic,
    string $duration   = '45 minutes',
    string $strategy   = 'Discussion-Based',
    string $curriculum = 'MATATAG',
    string $calendar   = 'ThreeTerm',
    string $format     = 'ILAW',
    bool   $isRegenerate = false
): ?array {
    $prompt = buildPrompt($grade, $subject, $topic, $duration, $strategy, $curriculum, $calendar, $format);

    // Dating EKSAKTONG parehong prompt ang ginagawa tuwing
    // parehong settings ang ginamit — kaya umaasa lang sa randomness
    // ng AI model ang anumang pagkakaiba sa output, na hindi sapat
    // na kapansin-pansin para sa teacher na hindi nagustuhan ang
    // unang resulta. Idinagdag ang explicit na variation instruction
    // sa dulo ng prompt kapag totoong "Regenerate" ang ginamit
    // (hindi sa unang generate), at tinaasan ang temperature para sa
    // mas malinaw na pagkakaiba ng bagong output.
    if ($isRegenerate) {
        $prompt .= "\n\nIMPORTANT: This is a regeneration request — the teacher was not satisfied with a previous draft. "
                 . "Produce a genuinely DIFFERENT version: use different example activities, different wording, and "
                 . "a different specific approach within the same teaching strategy, while still meeting all the "
                 . "requirements above. Do not simply rephrase the same content.";
    }

    $response = callAI($prompt, $isRegenerate);
    return parseAIResponse($response);
}
?>