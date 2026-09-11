# Online Examinations: Complete Workflow Guide

Last reviewed against the current code: 10 September 2026 (Africa/Lagos)

This guide documents the current Nigerian-localised Online Examination workflow from setup to final result handling. It covers every page linked from the workflow, each supported assessment setup, the student experience, marking, missed examinations, rescheduling, manual recovery scores, automatic result posting, feedback, archiving, permissions, and the remaining legacy-only screens.

## 1. What the module does

Online Examinations is the school's CBT delivery layer. It does not replace Exam Setting, result entry, grading, broadsheets, report cards, comments, affective/psychomotor records, or result publication.

The working relationship is:

```text
Existing academic/result settings
        ↓
Session → Term → Class → Arm → Subject → CA/Exam/British/Holiday/Kindergarten destination
        ↓
One online assessment → one subject → one CBT paper
        ↓
Candidate roster → freeze and publish → student attempt
        ↓
Automatic marking and/or teacher marking
        ↓
Automatic score/outcome posting to the existing result record
        ↓
Existing result publication process releases the official result card
```

The module intentionally keeps these two releases separate:

- **Online feedback** controls whether the student can see the online score, answers, correct answers, and marking guidance in the student portal.
- **Official result publication** remains the existing school result-publication workflow. Posting an online score never publishes a report card, broadsheet, midterm result, term result, or cumulative result by itself.

## 2. Current workflow boundaries

The current workflow has the following fixed rules:

- One assessment belongs to one academic session, one term, one class, one subject, one result destination, and one or more compatible class arms.
- One assessment contains exactly one active CBT paper.
- The same paper may serve several selected arms only when the subject is assigned to every arm and all arms use the same result destination and maximum.
- To examine another subject, create another assessment.
- To use another CA component, such as CA2 after CA1, create another assessment.
- A student has one non-void official attempt. Opening, refreshing, leaving, or resuming the paper does not create another attempt.
- New papers are online CBT only and may be **Objective** or **Theory / Essay**.
- Paper printing, paper delivery, hybrid delivery, file uploads, oral/audio answers, practical/project responses, practice quizzes, and multiple routine attempts are not part of the compact workflow.
- The old Question Bank **Level** or difficulty field has been removed from the interface and database. It has no role anywhere in this workflow.
- Historical examinations remain readable where compatibility allows, but their retired authoring and attempt actions cannot be used for new work.

## 3. Page and link directory

The examples below omit the school's domain. Access also depends on the staff member's role privileges and, for teachers, their exact subject assignments.

### Main administration menu

| Menu or action | Route | What it is used for |
|---|---|---|
| **Online Exam** | `/admin/onlineexam` | Lists current assessments and opens their roster, builder, edit, review, or delete/archive action. |
| **Online Examination Review** | `/admin/onlineexam/review` | Class-arm matrix for attendance, scores, marking, missed papers, rescheduling, manual recovery scores, and result-posting conflicts. |
| **Question Bank** | `/admin/question` | Creates, imports, views, edits, and deletes reusable source questions. |
| **Add academic assessment** | `/admin/onlineexam/workflow` | Creates the academic context and result destination. |
| **Edit context** | `/admin/onlineexam/workflow/{assessment_id}` | Edits a draft assessment before it is frozen. |
| **Paper and section builder** | `/admin/onlineexam/builder/{assessment_id}` | Creates the one paper, optional sections, questions, mappings, publication snapshot, and online-feedback setting. |
| **Candidate roster** | `/admin/onlineexam/assign/{assessment_id}` | Assigns or excludes eligible students in each selected class arm. |
| **Review shortcut** | `/admin/onlineexam/operations/{assessment_id}` | Old bookmarked link retained as a redirect to the same assessment preselected in the simplified Review page. It is not a separate operations dashboard. |
| **Review one student/paper** | `/admin/onlineexam/reviewcell/{assessment_id}/{student_session_id}/{paper_id}` | Opens the Review modal for one matrix cell. It is normally reached by clicking a cell, not entered manually. |
| **Answer and Outcome Review** | `/admin/onlineexam/attemptmarking/{assessment_id}/{attempt_id}` | Displays responses that require manual marking and, for a teacher-selected British setup, records Emerging, Expected, or Exceeding. It is not a full objective-script viewer. |
| **Assessment analysis** | `/admin/onlineexam/analysis/{assessment_id}` | Shows frozen paper and question statistics. This is a protected contextual route and is not currently a primary sidebar link. |
| **View a Question Bank item** | `/admin/question/read/{question_id}` | Opens the full reusable source question. |

### Student menu

| Page | Route | What the student sees |
|---|---|---|
| **Online Assessments** | `/user/onlineexam` | Assigned assessments, academic period, schedule, duration, current status, released result, and **View Details**. |
| **Assessment details** | `/user/onlineexam/view/{assessment_id}` | Instructions, paper schedule and status, Start/Resume action, attempt interface, completion message, and released feedback. |

### Reports menu and historical compatibility

`/admin/onlineexam/report` is the old Online Examinations report. It still reads the legacy attempt/result structures and displays the old total-attempt, remaining-attempt, submitted, and legacy answer-detail information. It is retained for historical compatibility; it is not the authoritative operational report for new workflow-version-2 assessments.

The legacy report asks for Exam, Class, and Section. Its result table shows admission number, student, class, total attempts, remaining attempts, submitted status, and an eye action for the historical answer/result modal. Any print action inside that modal also belongs to the legacy report only.

For current assessments use:

- **Online Examination Review** for attendance, live status, marking, score, and recovery;
- the existing result-entry or broadsheet page for the official academic score; and
- the existing publish-result workflow for student result visibility.

### Assessment-list columns and actions

The `/admin/onlineexam` table shows Assessment, Purpose, Papers/Questions, Opens, Closes, Duration, Status, Feedback, and Actions.

Depending on privilege and lifecycle, the Actions column contains:

- the tag icon for Candidate roster;
- the builder icon for Paper and section builder;
- the pencil icon for Edit context, shown only on a Draft;
- the table icon for Review, shown after the Draft stage; and
- the remove icon for deleting an eligible Draft or soft-archiving an eligible Completed assessment.

The page header also contains **Review students and scores** and, when authorized, **Add academic assessment**. Workflow-version-1 records are labelled **Legacy read-only**. Unsupported older workflow-version-2 records are labelled **Historical read-only** and do not expose current executable actions.

## 4. Terms used in this guide

| Term | Meaning |
|---|---|
| **Assessment** | The academic record tying a session, term, class, arms, subject, purpose, result destination, schedule, and candidate roster together. |
| **Paper** | The single Objective or Theory/Essay CBT delivered to the student. |
| **Section** | An optional group of questions with its own instructions and answer rule. |
| **Result destination** | The existing CA/Exam field or specialist British, Holiday, or Kindergarten record that receives the completed score/outcome. |
| **Source question** | The editable Question Bank record used while building a draft. |
| **Frozen snapshot** | The immutable copy of the academic mapping, paper, sections, questions, answers, marks, and instructions used by official attempts. |
| **Candidate assignment** | The student's inclusion in the assessment roster. |
| **Official attempt** | The one active, non-void attempt belonging to the assigned student. |
| **Online feedback** | The online score and answer review released from the builder after completion. |
| **Official result** | The score/report produced by the school's existing result system and its separate publication process. |

## 5. Supported assessment setups

### 5.1 Continuous Assessment (CA)

Choose **Continuous Assessment (CA)** when the online score should enter a normal CA component.

The system reads the class's existing `assigncatoclass` and `resultsetting` records. It offers only:

- enabled `CA1` to `CA10` slots;
- slots with a positive configured maximum; and
- slots that are not reserved in `MidTermCaToUse`.

Example: if CA1 is named “First Test” and has maximum 20, the form displays that component with maximum 20. The paper maximum is automatically 20 and the selectable question marks must also total 20.

### 5.2 Midterm Assessment

Choose **Midterm Assessment** when the score should enter a CA slot reserved for midterm use.

The system offers only enabled, positive-score CA slots named in the class's `MidTermCaToUse` setting. A CA slot reserved for midterm will not also appear under normal CA setup.

### 5.3 Terminal Examination (Exam)

Choose **Terminal Examination (Exam)** when the completed online score should enter the standard terminal `exam` field in the existing result record.

The system derives the Examination maximum from the class's current result setting:

```text
Examination maximum = 100 − total of all enabled CA maximums
```

For example, if the enabled CA components total 40, the Examination maximum is 60. The Exam purpose offers only the **Examination** result component; it cannot be used to select a CA field. The paper maximum and required selectable question-mark total are automatically set to the calculated Examination maximum.

The setup is rejected if the CA configuration exceeds 100 or leaves no positive Examination maximum. Correct the class's existing result setting before creating or publishing the Exam assessment. A completed result is posted to `score.exam` for the exact student, session, term, class, arm, and subject.

### 5.4 Holiday Assessment

Choose **Holiday Assessment** when the result belongs in the existing Holiday Assessment module.

Before this choice can be saved, the selected session, term, class arm, and subject must have exactly one enabled Holiday Assessment setting with a positive maximum. When several arms are selected, every arm must use the same maximum. If their maximums differ, create separate assessments for the different arm groups.

The arm-specific Holiday setting and subject identifiers are verified again and frozen when the assessment is published.

### 5.5 Kindergarten Assessment

Choose **Kindergarten Assessment** for a class configured with the existing Kindergarten assessment structure.

The class and subject must already have active Kindergarten assessment headers, subjects, concepts, and result labels. The online target scale is 100. In the builder, map either the whole paper or individual sections to existing concepts, then define percentage thresholds for that concept's existing result labels.

The thresholds must:

- begin at 0.00;
- finish at 100.00;
- follow the configured result-label order;
- contain no overlap; and
- contain no gap between one label and the next.

The completed percentage is converted to the configured result-label index and posted to `kindergarten_result`. Existing concept identifiers are retained so older Kindergarten results and online mappings do not break.

An aggregate manual paper score is not offered when the Kindergarten mapping uses separate paper sections for different concept outcomes, because one whole-paper total cannot safely determine those section results. In that case reschedule the CBT or enter each outcome in the existing Kindergarten result screen. A whole-paper Kindergarten mapping can use the supervised recovery score because one percentage can still be converted through that mapping.

### 5.6 British Assessment

Choose **British Assessment** only for a class whose Exam Setting assignment has `ResultType = british`. This mirrors the existing `/admin/britishMarkingSystem.php` workflow: one subject result is stored for the selected session, term, class, arm, and student as exactly one of:

- `Emerging`;
- `Expected`; or
- `Exceeding`.

The Online Examination uses a 100-point working scale to calculate the student's assessment percentage, but it does not write that number into a CA field. It posts the final qualitative value to `britishresult.Remark`. The existing `britishresult.AdditionalComments` value is deliberately left unchanged.

The builder provides two outcome methods:

- **Teacher selects the outcome** is the default and matches the existing British Computation page. After submission, an authorized teacher opens **Answer and Outcome Review**, completes any Theory marking, and selects Emerging, Expected, or Exceeding. Once the attempt is complete, the outcome posts automatically.
- **Convert the score automatically** converts the completed assessment percentage through school-defined, gap-free ranges covering 0–100. The ranges displayed initially are editable examples, not a national grading rule; the school must confirm and save its own approved thresholds before publication.

A British assessment has one academic slot per session, term, class arm, and subject. It appears in Online Examination Review under assessment type **Term** and component **British Outcome**.

If a matching British result row is absent, synchronization creates it. If a blank placeholder exists, it can be filled. If `Remark` already contains a manual/unrelated outcome, the system records a conflict instead of silently replacing it. Staff should verify or clear that value in British Computation and then retry; the numeric replacement shortcut is deliberately not offered for a qualitative British outcome.

### 5.7 Setups not offered for new assessments

- Unlinked practice quizzes are not offered.
- Paper, hybrid, practical, project, oral/aural, and uploaded-response assessments are retired from the current online flow.

## 6. Before creating an assessment

Confirm the following existing school data first:

1. The academic session exists.
2. The class has its correct arms/sections.
3. Students are enrolled in the correct `student_session` records for that session, class, and arm.
4. The subject is assigned to every intended arm through the school's subject/class assignment records.
5. Standard classes have the correct result setting, CA names, CA maximums, and `MidTermCaToUse` values. For an Exam purpose, the enabled CA maximums must leave a positive balance below 100.
6. British classes have `ResultType = british`, and Holiday or Kindergarten settings already exist when those purposes will be used.
7. Each subject teacher is assigned to the exact session, class arm, and subject in `teacher_subjects`.

This preparation controls what appears in the form. A missing subject or arm is normally an academic-assignment issue, not an Online Examination display fault.

## 7. Step 1 — create the academic assessment

Open **Online Examinations → Online Exam → Add academic assessment**.

### Fields on the page

| Field | Rule and effect |
|---|---|
| **Assessment title** | Candidate- and staff-facing name, for example `Primary 4 Mathematics CA1`. |
| **Purpose** | Continuous Assessment, Midterm, Terminal Examination (Exam), British, Holiday, or Kindergarten. It decides which existing result configuration is valid. |
| **Academic session** | Owns both the candidate roster and final result record. |
| **Term** | `1st`, `2nd`, or `3rd`; it is part of the assessment's unique identity and result destination. |
| **Class** | Filtered to classes available in the selected session and staff scope. |
| **Subject** | Filtered by the selected session, term, and class. Teachers see only subjects assigned to them. |
| **Class arms / sections** | Filtered after session, class, and subject selection. The subject must be valid in every checked arm. |
| **Result destination** | Loaded from the existing result setting for CA/Midterm/Exam. Exam offers only the derived Examination component. British, Holiday, and Kindergarten destinations are inferred from their existing specialist settings. |
| **Opens / Closes** | The outer period during which the assessment may run. |
| **Duration (minutes)** | Default paper time, from 1 to 1,439 minutes. |
| **Pass percentage** | Used for the online Pass/Fail label after feedback release; default 40. It does not alter official grading. |
| **Description / general instructions** | Directions shown before the paper. |
| **Randomize questions** | Gives each attempt a stable randomized order while keeping sections and passage groups together. |
| **Enable negative marking** | Permits configured deductions only for attempted, wrong, objective responses. |
| **Display marks during review** | Stored as a compatibility preference. Current workflow-version-2 feedback is released or hidden as one unit and does not separately suppress its Mark column. It never publishes the official result. |

The opening time must be earlier than the closing time, and the complete start-to-end interval must be at least the configured duration. The same rule is later enforced for the paper and every individual reschedule.

### Dependent filtering

The form deliberately loads choices in this order:

```text
Session
  → available classes
    → term + class subjects
      → subject-compatible arms
        → valid result component, outcome, or specialist mapping
```

This prevents the common error where a subject is selected even though it is not assigned to every chosen arm. The server repeats every check, so a crafted or stale browser request cannot bypass the filters.

### Teacher scope

A user whose role is Teacher can create, view, or manage only a subject explicitly assigned to that teacher for the selected session and class arm. When several arms are selected, the teacher must hold the exact subject assignment in every selected arm. Being a teacher of another subject in the same class does not grant access.

Non-teacher staff still require the relevant role privilege and valid curriculum mapping.

### One academic slot rule

The database reserves one live assessment slot for this exact identity:

```text
session + term + class + arm + assessment type + result component/outcome + subject
```

For this rule:

- CA, Exam, British, and Kindergarten belong to Review's **Term** type;
- Midterm belongs to **Midterm**;
- Holiday belongs to **Holiday**; and
- each selected arm reserves its own slot.

The rule prevents two live assessments from representing the same subject/component for the same class arm. A different subject or a different CA component is a different valid slot. Archiving a completed assessment releases its slot. Older duplicate data is not destroyed; the oldest canonical record owns the slot and newer duplicates remain historical until corrected.

### Validation errors and retained input

If normal validation fails, the form retains the values already entered, including the academic context and rules, so the user can correct the reported field without starting again. A staff member should still recheck dependent selections if an underlying academic setting was changed in another browser tab.

Saving successfully opens the builder.

## 8. Step 2 — build the paper

The builder header shows the session/term, class/arms, subject, result destination, lifecycle status, and revision number. Numeric destinations also show their maximum; a British destination shows its three qualitative outcomes. Its toolbar links back to the assessment list, the candidate roster, Review after publication, and draft context editing.

### Paper fields

Only one paper can be added. Its fields are:

| Field | Rule and effect |
|---|---|
| **Paper title** | Name shown to the candidate, such as `Mathematics CA1`. |
| **Code** | Optional short staff identifier such as `P1`. |
| **Paper type** | `Objective` or `Theory / Essay`. |
| **Minutes** | The server-enforced time for this paper. The assessment duration pre-fills it. |
| **Display order** | Retained for consistent display; with one paper it normally remains 0. |
| **Starts / Ends** | Paper window within the assessment's outer window. |
| **Instructions** | Directions displayed before and during the paper. |
| **Active** | Must be enabled for the paper to be included at publication. |

There is no Raw Max input. The server sets:

```text
paper raw maximum = selected result-component maximum
paper contribution = 100%
```

The paper window must lie inside the assessment window, its start must be before its end, and its interval must allow the full paper duration.

An older draft containing more than one paper displays a warning. Extra papers must be deleted before publication.

### Objective versus Theory/Essay

- An **Objective** paper contains only automatically marked response types and cannot contain a long answer.
- A **Theory / Essay** paper may combine automatically marked questions with long-answer questions. This is the supported way to create a mixed objective-and-theory assessment while retaining the one-paper rule.

### Draft builder actions

While the current revision is an unattempted Draft:

- the paper pencil expands its edit form;
- **Save** updates the paper and re-derives its maximum/contribution;
- **Delete paper** removes that draft paper, its section rows, its question assignments, and its draft Kindergarten mappings after confirmation, but does not delete the reusable source questions from Question Bank;
- the plus button adds a section;
- the section remove button moves its assigned questions back to “No named section” and removes that section's Kindergarten mapping; and
- a question remove action deletes the assessment assignment, not its reusable Question Bank source.

These actions are blocked after freezing or after an attempt exists for the current revision. Failed paper, section, and structured-question validation retains the submitted builder values so they can be corrected.

## 9. Optional sections and answer rules

A paper can work without named sections. Questions assigned directly to the paper are all required.

When sections are needed, each has a title, answer rule, number to answer where relevant, instructions, and display order.

| Answer rule | Candidate requirement | Selectable maximum used at publication |
|---|---|---|
| **Answer all** | Every question in the section is required. | Sum of all question marks. |
| **Answer any N** | The candidate may answer only N questions in the section. The Compulsory checkbox is ignored for this rule. | Sum of the highest N question marks. |
| **Compulsory + choice** | Every question marked Compulsory plus any N questions not marked Compulsory. | Sum of compulsory marks plus the highest N optional marks. |

Every newly authored or assigned question is Compulsory by default. Leave this unchanged for no-section and Answer all setups. For **Compulsory + choice**, uncheck Compulsory only on the optional questions. For **Answer any N**, the section rule makes all questions part of the choice set regardless of the checkbox.

The number to answer must be at least 1 for the two choice rules and cannot exceed the available optional questions. The student interface stops an additional optional answer once the limit is reached and asks the student to clear an earlier response first.

For a fair Answer any N section, optional questions should normally carry equal marks. The publication maximum uses the highest N marks, so unequal optional marks can make a candidate's chosen lower-mark combination worth less than the paper maximum.

### Paper-total requirement

Publication requires the selectable question total to equal the automatically derived paper maximum.

Example for a CA maximum of 20:

- ten Answer all questions at 2 marks each = 20; or
- six questions at 4 marks each under Answer any 5 = 20; or
- two compulsory questions at 5 marks each plus Answer any 2 of four optional questions at 5 marks each = 20.

If the selectable total is 18 or 22, the assessment cannot be frozen until the question marks or section rule is corrected.

## 10. Step 3 — add questions

The builder provides two supported paths.

### Path A: create a structured question in the builder

Use **Create a structured question** to create the reusable Question Bank source and assign it to this draft in one action. Select the paper, optional section, question type, text, response definition, marks, negative mark where allowed, display order, Compulsory status, and marking scheme where applicable.

The same panel lists assessment-authored questions and allows draft editing. If that source is used only here, it is updated in place. If it is genuinely shared with another assessment, copy-on-write prevents an edit from changing the other assessment unexpectedly.

### Path B: select an existing Question Bank item

The embedded Question Bank search is restricted on the server to the assessment's class, subject, and selected arms. Filter by keyword or question type, choose the paper and optional section, then enter marks, allowed negative mark, display order, marking scheme, and Compulsory status before assigning or updating it.

A question from another class, subject, or unselected arm is rejected even if its identifier is manually submitted.

Yes, an objective Question Bank item includes its answer key. The reusable `questions` record stores the question type, question text, options `opt_a` to `opt_e`, and `correct` value:

- Single Choice stores the correct option key, for example `opt_b`.
- Multiple Choice stores the complete set of correct option keys as JSON, for example `["opt_a","opt_c"]`.
- True/False stores `true` or `false`.
- A legacy Descriptive item has no automatic answer key and is not accepted as a current Theory question; create a structured Long answer in the builder instead.

Questions originally created through Path A also keep their richer reusable definition in `onlineexam_question_definitions`. Consequently, selecting those items later through Path B restores accepted short answers, numeric value/tolerance, matching pairs, ordering, grouped-passage data, or the Long-answer marking scheme as applicable.

When Path B assigns the bank item, the draft still points to that reusable source. **Freeze and publish** then copies its question text, options, correct answer, type, marks, negative mark, section, and marking information into the immutable revision snapshot. Publication is blocked if a traditional objective bank item has missing choices or an invalid answer key. During an active attempt, the candidate question query returns only the answerable question/options and deliberately omits the correct-answer field. On submission, the server compares the saved response with the frozen answer key. Editing the Question Bank source later therefore cannot change the correct answer for an assessment already taken or published.

The correct answer may be shown later only through the separate released-feedback view. Keeping feedback on **Held** hides both the result and expected-answer review; this does not affect the official report-card publication setting.

Marks are not stored on the reusable Question Bank item. They belong to its assignment in the paper, which is why Path B asks for marks when the question is selected.

### Supported question types

| Type | Author setup | Candidate action and marking |
|---|---|---|
| **Single choice** | Enter at least two options and one correct option. | Candidate selects one. Full mark only for the correct option. |
| **Multiple choice** | Enter options and every correct option. | Candidate selects several. Full mark requires the exact complete correct set; there is no partial mark. |
| **True/False** | Choose True or False as the correct answer. | Candidate selects one; marked automatically. |
| **Short answer** | Enter one or more accepted answers. | Comparison ignores leading/trailing spaces and letter case, but otherwise requires exact text. |
| **Numeric with tolerance** | Enter an expected numeric value and a non-negative tolerance. | Accepted when `absolute(candidate value − expected value) <= tolerance`. |
| **Matching** | Enter one `Left => Right` pair per line. | Candidate matches every left item to a right item. The complete map must be correct; there is no partial mark. |
| **Ordering** | Enter items in their correct order. | Candidate places/selects each unique item in position. The whole order must match; there is no partial mark. |
| **Grouped passage** | Enter a group key, passage title, passage text, and a supported child response type for each child question. | The passage is displayed once with its related questions together. Each child is marked according to its own response type. |
| **Long answer** | Enter the question and mandatory marking scheme. | Candidate types a response; an authorized teacher marks it manually. Only a Theory/Essay paper may contain it. |

#### Numeric with tolerance example

If the expected answer is `12.5` and tolerance is `0.1`, answers from `12.4` through `12.6`, inclusive, are accepted. A tolerance of `0` requires the exact numeric value. It is useful where rounding or measurement creates a small acceptable range.

#### Grouped passage behavior

Grouped passage is a presentation wrapper, not a separate marking method. Create each child question with the same group key, title, and passage text. Supported child types are single choice, multiple choice, true/false, short answer, numeric, and long answer. The same group key must always use identical passage content. Randomization keeps the passage and its children together.

### Marking scheme and rubric

A **marking scheme** is frozen teacher guidance explaining what earns the available marks. It is mandatory for long answers and optional for objective questions. Students cannot see it while taking the assessment.

On the Answer and Outcome Review page the marker records:

- a mark from 0 to the frozen question maximum;
- **Draft** or **Finalized** status;
- an optional marker's remark; and
- an optional JSON rubric breakdown, for example `{"accuracy":5,"method":3}`.

The rubric JSON is a structured note for audit and explanation; it does not calculate the mark. The numeric mark entered by the marker is authoritative and must remain within the question maximum. Each save creates marking history. A Draft mark keeps the attempt awaiting marking; only finalized required marks can complete it.

Unanswered long-answer questions receive zero and do not require a manual mark. Automatically marked answers cannot be changed through the Theory marking form.

## 11. The standalone Question Bank page

Open **Online Examinations → Question Bank** at `/admin/question`.

The table contains Question ID, Subject, Question Type, Question, and Action. Level/difficulty is not present.

Available actions, subject to privileges, are:

- **Add Question** for a reusable bank question;
- **Import** to download/use the CSV format and import questions;
- the eye icon to view the full question;
- the pencil icon to edit;
- the remove icon to delete; and
- **Bulk Delete** for selected eligible records.

The question form uses class, optional section/arm, and subject. Teachers see only their current-session assignments, and the school's “my question” configuration may further restrict them to their own questions.

Questions already referenced by an online assessment cannot be deleted singly or in bulk. A frozen attempt always uses its immutable snapshot even if the source Question Bank record is edited later.

The normal Question Bank retains its simpler legacy authoring types. Use the builder's structured-question form for numeric tolerance, matching, ordering, grouped passages, and the current long-answer definition. A legacy “Descriptive” bank item is not a supported compact CBT response; create a structured Long answer in a Theory/Essay paper instead.

## 12. Step 4 — prepare the candidate roster

Open **Candidate roster** from the assessment list or builder.

1. Select one of the class arms already attached to the assessment.
2. Search to load active students enrolled in that session, class, and arm.
3. Tick the candidates who should take the assessment, or use Select all.
4. Save.
5. Repeat for every selected arm.

The class is fixed to the assessment. A submitted student identifier outside that academic roster is rejected.

The roster can be changed while the assessment is Draft, Scheduled, Published, or In progress:

- checking an eligible new student records a late assignment;
- unchecking an unstarted student records an exclusion without deleting history; and
- rechecking an excluded student reassigns them.

The roster is locked in Marking and Completed states. A candidate with a non-void official attempt cannot be excluded through the roster. The current simplified interface does not provide a routine attempt-void button; an exceptional invalid-attempt case must be escalated to an authorized system administrator and must retain its audit reason rather than being removed directly from the database.

Students in the class arm who were never assigned still appear in Review as **Unassigned**.

## 13. Step 5 — freeze and publish

Use **Freeze and publish** at the bottom of the builder.

Publication is blocked until the server confirms all of the following:

- valid session, term, class, selected arms, subject, purpose, and teacher scope;
- no duplicate live academic slot;
- a still-valid result destination and positive maximum;
- exactly one active CBT paper and no extra paper records;
- Objective or Theory/Essay paper type;
- assessment and paper windows that allow the full duration;
- paper maximum equal to the selected result-component maximum;
- paper contribution fixed at 100%;
- at least one supported question;
- selectable question marks equal to the paper maximum;
- valid section answer counts;
- no long answer in an Objective paper;
- a marking scheme for every long answer; and
- a valid British outcome method, or complete Holiday/Kindergarten mapping, where applicable.

Publishing creates an immutable revision snapshot containing:

- academic and result configuration;
- paper schedule, duration, type, instructions, maximum, and contribution;
- sections, answer rules, instructions, and order;
- question text, options, response rules, correct answers, marks, negative marks, marking schemes, passage data, and order; and
- the revision checksum and audit record.

The assessment becomes active for eligible students. Its lifecycle may show Scheduled before its opening time and Published once open with no attempt.

After freezing, context, paper, sections, questions, marks, and result mappings cannot be edited or deleted. There is no normal “unpublish and alter the same official paper” action.

### Creating a new revision

Use **Create new revision** only when there is no candidate currently in progress, submitted awaiting processing, timed out awaiting processing, or awaiting marking. The assessment becomes an inactive Draft with the revision number increased. Existing mutable paper/question rows seed the new draft, while older snapshots, completed attempts, results, and audits remain unchanged.

Do not use a new revision to erase a student's completed work.

## 14. Step 6 — student takes the assessment

### Student assessment list

The student opens **Online Assessments**. Each row shows the assessment, academic period, opening and closing times, duration, status, released result, and **View Details**.

Only an assigned, active, non-archived assessment is available. A student who is not on its roster cannot open it by changing the URL.

### Assessment details

The details page shows:

- assessment and subject information;
- general instructions;
- candidate identity;
- the single paper title and optional code;
- Objective or Theory/Essay type;
- effective normal or individual schedule;
- duration;
- current status; and
- Start, Resume, “Contact your teacher,” or no action, as appropriate.

The Start button is shown only while the paper is active and within the student's effective window. It is not shown after the paper closes. A closed missed or incomplete paper displays **Contact your teacher**.

### Starting, leaving, and resuming

Pressing **Start** creates the one official attempt and a stable question order. Pressing **Save & leave**, closing the modal, refreshing the browser, or returning later resumes that same attempt while time remains. It does not reset the timer or consume another attempt.

The deadline is the earliest permitted value after considering:

- the time the paper was started plus its duration;
- the paper closing time; and
- the assessment closing time.

An existing authorized extra-time accommodation adds minutes to the duration and scheduled hard stop. An individual reschedule replaces the normal paper window for that candidate. Server time is authoritative; changing the device clock cannot add time.

### During the paper

The attempt interface provides:

- the server-synchronized countdown;
- synchronization status;
- section and passage instructions;
- current/answered question navigation;
- Previous and Next controls;
- Clear answer;
- Save & leave; and
- final Submit.

Every changed answer is autosaved. Each save carries a client sequence so an older delayed response cannot overwrite a newer one. Failed saves remain in a browser retry queue. When the connection returns, they are retried.

Final submission sends the remaining queued answers as one checked packet. If the packet is invalid, too large, or cannot be stored, the paper is not closed and the browser queue is preserved. Repeating a submission after a lost response is idempotent and does not create a duplicate result.

The server uses the request-arrival time and a short 15-second final-save grace to avoid penalizing an answer merely because it waited for a database lock. The grace does not extend the visible examination duration.

### Timeout

When time ends, the server closes the paper. This happens when the student returns to the page and also through the configured cron process, so keeping the browser closed cannot avoid timeout.

- If the required answer rule was satisfied, saved answers are submitted and processed.
- If no required answer was recorded, Review shows **Missed**.
- If some answers exist but the required section rules were not satisfied, Review shows **Incomplete**.
- An Objective completion is calculated automatically.
- A Theory completion with answered long questions becomes **Awaiting marking**.

A timed-out Objective paper can therefore post the mark from the answers safely stored before timeout, including zero, even while Review labels the sitting Missed or Incomplete for recovery. If staff reschedule that recoverable paper, its later completed score updates the same online-owned result destination. Schools should resolve all Missed/Incomplete cells before publishing the official result.

## 15. Question marking and score calculation

### Automatic marking

Single choice, multiple choice, true/false, short answer, numeric, matching, and ordering are automatically marked from the frozen correct-answer definition.

Unanswered questions score zero. If negative marking is disabled, a wrong answer scores zero. If it is enabled, only an attempted wrong objective answer receives that question's configured deduction. The final assessment score is never allowed below zero.

### Theory/Essay marking and British outcome review

In Review, click an **Awaiting marking** cell and then **Mark answers**.

The Answer and Outcome Review page shows candidate details, attempt and revision, frozen question, candidate response, marking scheme, allowed maximum, current mark/status, remark, rubric JSON, and version history.

For each answered long question:

1. Enter the mark.
2. Add an optional remark/rubric.
3. Save as Draft if work is continuing, or Finalized when complete.
4. After all required marks are final, use **Finalize review and synchronize result**.

Saving the last finalized answer may also trigger finalization, but the explicit final button safely rechecks the whole attempt and result synchronization.

For a British assessment using **Teacher selects the outcome**, the same page provides a required Emerging/Expected/Exceeding selector after the student submits. Save the outcome before finalizing Theory marking. For an objective-only attempt that has already completed, saving the outcome immediately retries automatic posting. If the British profile uses thresholds, the page shows the frozen ranges and calculated outcome instead; no manual selector is available.

### Formula

The calculation retains a general scaling formula:

```text
paper contribution = earned raw mark ÷ paper raw maximum × 100
final score = paper contribution ÷ 100 × result-component maximum
```

Because the current workflow has one paper, 100% contribution, and a raw maximum equal to the component maximum, the final score will normally equal the bounded earned raw mark. Values are rounded to two decimal places for the official destination.

The online Pass/Fail label is based on:

```text
final score ÷ component maximum × 100
```

It is compared with the assessment's pass percentage. A pass percentage of 0 produces no Pass/Fail label. Standard assessments use this only as an online display label. A British assessment displays its Emerging/Expected/Exceeding outcome instead; the numeric online score remains available only through the separate feedback release.

## 16. Automatic result posting

There is no routine **Approve and Post** step.

- Objective-only results post after valid submission or timeout processing.
- A mixed Theory/Essay paper waits until every answered long response is finalized.
- A supervised manual recovery score posts when its attempt is finalized.
- A threshold-based British result posts its converted outcome when the attempt completes. A teacher-selection British result waits only for the authorized outcome selection, then posts automatically.
- The cron reconciliation retries a completed attempt if processing stopped between completion and posting.

Posting is transactional and idempotent. Retrying the same completed result does not create another academic score.

### Standard CA/Midterm/Exam destination

For CA or Midterm, the final value is written to the selected `ca1` through `ca10` field. For Terminal Examination, it is written to `exam`. Both use the existing `score` row identified by student, session, term, class, arm, and subject.

If no matching row exists, the adapter may create the correctly scoped row. If multiple matching score rows exist, synchronization stops as a conflict.

Any existing value—including an ambiguous zero—is treated conservatively when the online assessment does not own it. It is not silently overwritten.

### Holiday destination

The value is written to the exact frozen `holiday_assessment_scores` destination. Online ownership is recorded. A row explicitly marked as an unused placeholder may be claimed; a manual or unrelated score is held as a conflict.

### Kindergarten destination

The paper or section percentage is converted through each frozen concept threshold and written as the corresponding result-label index in `kindergarten_result`. Multi-concept synchronization is all-or-nothing: if one unrelated concept value conflicts, none of that attempt's concept results is partially posted.

### British destination

The finalized assessment percentage is either retained as evidence for the teacher's selected outcome or converted through the frozen school thresholds. The resulting `Emerging`, `Expected`, or `Exceeding` value is written to `britishresult.Remark` for the exact student, session, term, class, arm, and subject. `AdditionalComments` is never overwritten.

If no row exists, one is created. A single blank placeholder can be used. Multiple matching rows, or a nonblank outcome not owned by this online assessment, produce **Result needs attention**. Correct or clear the existing British outcome in the British Computation page and retry; an unrelated qualitative value is never silently replaced.

### Conflict and correction rules

If the destination already contains an unrelated manual value, Review displays **Result needs attention**. The school can:

- inspect the existing result in its normal result-entry screen;
- correct or clear the incorrect external value and choose **Retry result update**; or
- for a standard numeric or Holiday conflict, use **Use online score instead**, enter a reason, and confirm the replacement.

The replacement is allowed only if the destination still equals the value observed when the conflict was recorded. If another person changes it again, the conflict must be reviewed again.

British and Kindergarten conflicts cannot be overridden by one aggregate numeric action. Correct or clear the affected outcome/concept in its existing result screen, then retry.

If an online-owned value is later corrected by recalculating the same assessment, the adapter may update only the value it previously posted and only if nobody changed it externally. Old and new values remain in the synchronization/audit history.

## 17. Official result release versus online feedback

Automatic score/outcome posting makes the value available to the existing result system. It does not make the report card visible.

Use the school's normal result workflow for:

- midterm result publication;
- termly result publication;
- cumulative result publication;
- broadsheets;
- grades and remarks;
- teacher/principal comments; and
- affective or psychomotor records.

In the assessment builder, **Release online feedback** makes completed online information visible in the student portal. The current workflow-version-2 release includes the score and maximum, an applicable Pass/Fail label or British outcome, and the available answer-review rows with candidate answer, expected answer or marking scheme, and question mark. The stored **Display marks during review** checkbox does not currently create a second, marks-hidden feedback mode. Treat Release/Hide as an all-or-nothing online-feedback control. **Hide online feedback** removes that online review again without changing the posted academic score/outcome or report-card publication.

A supervised whole-paper recovery contains no online question responses, so released feedback shows its overall score but omits the answer-review table.

When a completed result is still hidden, the student sees:

> This assessment is complete. The result will be released when it is ready.

## 18. Online Examination Review page

Open **Online Examinations → Online Examination Review**.

### Select Criteria

Choose:

1. Session
2. Term
3. Assessment type: Term, Midterm, or Holiday
4. Class
5. Section/arm
6. Result component / outcome

**Term** includes CA, Exam, British, and Kindergarten assessments. Select **Term** and then **Exam** to review a Terminal Examination, or **British Outcome** for a British assessment. The component list is built only from active Scheduled, Published, In progress, Marking, or Completed assessments matching the preceding criteria. It can contain CA1–CA10, Exam, British Outcome, Kindergarten, or Holiday according to the selected assessment type.

If no component is offered, there is no published current assessment matching that exact selection or the current user does not have access.

### Matrix layout

Rows contain every active student enrolled in the selected session/class/arm. Columns contain subject names only. Each cell is a compact button showing the status or score. This makes unassigned students visible even though they do not see the assessment in their portal.

Teachers see only the arms and subject columns covered by their exact `teacher_subjects` assignment.

### Cell statuses

| Status | Meaning | Normal treatment |
|---|---|---|
| **Unassigned** | Active class student is not on this assessment's roster. | Click the cell to assign and schedule when authorized, or open Manage assigned students. |
| **Assigned** | Rostered but not started; the normal window has not ended. | No action is required, or give an individual schedule if necessary. |
| **Upcoming** | The normal opening time is in the future. | Wait for the window. |
| **Rescheduled** | A future individual window exists. | Student waits for that individual start time. |
| **In progress** | The student's official timer is running. | Do not reschedule; wait for submit/timeout. View/recovery is protected while time remains. |
| **Missed** | Window ended with no answer satisfying any required work. | Reschedule the same paper or record a supervised paper score where allowed. |
| **Incomplete** | Time ended with some answers, but the section requirements were not completed. | Reschedule while retaining saved answers, or record a supervised paper score where allowed. |
| **Awaiting marking** | Submitted work contains answered long responses not fully finalized. | Open Mark answers and finalize the review. |
| **Score / maximum or British outcome** | Paper and attempt are completed. | View answers/outcome; resolve result attention if shown. It is not recoverable as a missed paper. |

### Cell modal actions

Clicking a cell shows the student, assessment/paper, status, maximum, duration, effective open/close times, answer progress for an incomplete paper, and applicable actions.

- **View answers / Mark answers** opens Answer and Outcome Review. It lists answered responses requiring manual marking; an objective-only attempt displays that there are no submitted Theory answers rather than presenting a full objective script. A British teacher-selection assessment also places its qualitative outcome control there.
- **Manage assigned students** opens the roster.
- **Assign student and schedule paper** handles an Unassigned student when the user can edit the roster.
- **Reschedule this paper** creates a new individual window for an assigned recoverable student.
- **Record score from a supervised paper exam** records a whole-paper recovery score when that mapping supports it.
- **Retry result update** retries a pending, failed, error, or conflict synchronization after its cause is corrected.
- **Use online score instead** is the audited numeric conflict override described above.

The Review form retains its values if validation fails and reopens the same modal so the user can correct the problem.

### Reschedule requirements

A reschedule requires a new start, new end, and reason. The end must be in the future, start must precede end, and the interval must allow the full paper duration. It affects only that student and paper. Existing saved answers and history remain; the same official attempt resumes.

### Supervised paper recovery score

Use this only when the student actually completed an authorized supervised paper version outside the CBT interface. Enter a score between zero and the paper maximum and a reason. The system records the recovery source and audit trail, finalizes the one-paper assessment, and posts automatically if the result destination is ready.

It is not a shortcut for altering a legitimately completed online score.

## 19. Missed, incomplete, and exceptional scenarios

| Scenario | What the system records | What staff should do |
|---|---|---|
| Student was never placed on the roster | Unassigned | Click the Review cell to assign and schedule, or add through Candidate roster. |
| Student was assigned but never opened the paper before close | Missed, with no Start button in the student portal | Reschedule, or record an authorized supervised paper score. |
| Student started but saved no answers before timeout | Missed | Reschedule the same official attempt, or record a supervised paper score. |
| Student answered some questions but did not meet Answer all/choice requirements before timeout | Incomplete, with answered/required count | Reschedule; previous saved answers remain. Alternatively record a supervised paper score if appropriate. |
| Student met the required rule exactly as time ended | Submitted/Completed or Awaiting marking | Allow automatic marking or complete Theory marking; do not reschedule as missed. |
| Student is currently working | In progress | Wait. Recovery actions are disabled until submission or server timeout. |
| Student lost network temporarily | Same In-progress attempt with browser retry queue | Reconnect and Resume. Do not create another assessment. |
| Student changed device or browser | Server-saved answers resume; unsent local-only queue remains on the original browser | Resume on the original device if possible. Staff may reschedule if the deadline passes incomplete. |
| Student submitted Objective work | Completed and automatically posted, unless conflict/error | Check Review only if Result needs attention appears. |
| Student submitted a long answer | Awaiting marking | Mark every answered long response and finalize. |
| British assessment uses teacher selection | Completed or Awaiting marking, with British outcome not yet selected | Open Answer and Outcome Review, select Emerging/Expected/Exceeding, and finalize any Theory review. A completed Objective attempt posts as soon as the outcome is saved. |
| British assessment uses thresholds | Completed percentage and frozen threshold profile | The system selects and posts Emerging/Expected/Exceeding automatically; inspect Result needs attention only if synchronization is held. |
| Student completed an authorized paper sitting outside CBT | Recoverable Missed/Incomplete record | Use Record score from a supervised paper exam with a reason. |
| Student has a Kindergarten paper with section-specific concept mappings but missed it | Missed, aggregate manual recovery unavailable | Reschedule, or enter each concept through Kindergarten results. |
| Existing manual CA/Exam/Holiday value blocks posting | Completed attempt plus Result needs attention | Verify the existing score, then clear/correct and Retry or authorize the online numeric replacement with a reason. |
| Existing Kindergarten concept blocks posting | Completed attempt plus Result needs attention | Correct/clear the concept in Kindergarten results, then Retry. |
| Existing British `Remark` blocks posting | Completed attempt plus Result needs attention | Verify/correct or clear the outcome in British Computation, then Retry. `AdditionalComments` remains unchanged. |
| Result sync failed because a setting/table was wrong | Completed attempt plus failed/error/pending status | Correct the underlying configuration/database issue and select Retry result update. |
| Staff tries to exclude a student who started | Roster save is rejected | Resolve the official attempt. Exceptional voiding requires authorized audited administration; do not delete rows manually. |
| Completed score is wrong because marking is unfinished | Awaiting marking, not completed | Correct/finalize the question marks. |
| Completed online-owned result needs a legitimate correction | Existing attempt and ownership history remain | Correct the authorized mark/recalculation path and resynchronize; external changes are protected as conflicts. |
| Student completed but result is hidden | Completed; score already may be posted | Release online feedback if desired. Publish the official report separately when approved. |
| Assessment close time passes with assigned students missed/incomplete | Assessment moves to Marking, not Completed | Resolve every assigned student's paper through reschedule/marking/manual recovery before archiving. |
| Student deliberately presses Submit before answering every required question | Normal submitted completion; unanswered items receive zero | The confirmation is final. Review can show the work, but the missed/incomplete recovery action is not offered for a voluntary submission. |
| Final queued answers reach the server more than 15 seconds after the deadline | Paper stays protected and the local queue is retained; only earlier server-saved answers can be timed out | Keep the original device/browser data, contact the invigilator, and use an authorized recovery path if Review classifies the paper Missed/Incomplete. |
| Student's login/session token expires while working | Save requests fail and unsent answers remain in the browser queue | Restore the valid login/session, reopen the same paper, and let the queue retry before the effective deadline. |
| Assessment was published with no assigned candidates | No student sees it; after close it cannot satisfy Completed/archive rules | Assign and schedule intended students from Review. If it should be abandoned and no attempt is active, open a new editable revision and delete the Draft. |
| Assigned student becomes inactive or is moved to another class arm | The active-student Review row may disappear while the historical candidate assignment remains | Correct enrollment and roster data before publication where possible. If it happens after an attempt or roster lock, escalate for audited administrative resolution rather than deleting examination rows. |
| An excluded student needs the assessment after the roster has locked | Excluded; Review will not reschedule until reassigned, while the Marking/Completed roster cannot be edited | Resolve before the normal close whenever possible; otherwise escalate for audited administration. |

## 20. Assessment lifecycle

| Lifecycle | Meaning |
|---|---|
| **Draft** | Academic context, one paper, sections, questions, roster, and mappings are editable. Not visible as an active student assessment. |
| **Scheduled** | Frozen/published assessment whose opening time is still in the future. |
| **Published** | Open/current assessment with no official attempt started. |
| **In progress** | At least one non-void official attempt has started while a valid window remains. |
| **Marking** | The effective windows have ended but not every assigned candidate is completed, or manual marking/recovery remains. |
| **Completed** | Every assigned candidate has a completed non-void attempt. |
| **Cancelled / Legacy / Historical read-only** | Preserved compatibility state; not executable through the compact workflow. |

The cron process finalizes expired in-progress papers, reconciles interrupted result posting, and refreshes assessment lifecycles. It must remain configured with the school's valid cron key.

## 21. Delete and archive behavior

The assessment list shows a delete action only for Draft or Completed assessments and only to users with delete permission.

### Draft deletion

An unattempted Draft is removed with its draft paper, sections, and assignments. A Draft that already has attempt history is rejected.

### Completed deletion is a soft archive

A frozen assessment can be removed from working lists only after:

- at least one candidate is assigned;
- every assigned candidate has a completed non-void attempt;
- every paper and required Theory mark is complete; and
- every completed result synchronizes successfully with no unresolved conflict.

The action then sets the assessment inactive and records who archived it. It releases the unique academic slot and removes the assessment from normal staff/student working lists, but retains posted academic results, frozen questions, attempts, marking history, synchronization ledger, and audit history.

It is not a permanent erase. Missed or incomplete assigned students must first be resolved by rescheduling, marking, or an authorized recovery score.

## 22. Assessment analysis

The protected `/admin/onlineexam/analysis/{assessment_id}` page uses frozen data and excludes void attempts. It shows:

- candidates and submitted counts by revision/paper;
- average raw score and average contribution; and
- per-question candidate count, answered count, correct/incorrect count, average mark, and facility information.

Because it is not currently a main sidebar or Review button, it should be treated as an optional authorized contextual report, not a required daily workflow step.

## 23. Permissions and ownership

| Privilege | Main effect |
|---|---|
| `online_examination` view | View assessment list, builder information, Review, attempts, and allowed analysis. |
| `online_examination` add | Create an assessment. |
| `online_examination` edit | Edit drafts, publish, manage feedback, reschedule/recovery where applicable, mark, finalize, and resolve result posting. |
| `online_examination` delete | Delete an unattempted draft or archive an eligible completed assessment. |
| `add_questions_in_exam` view/edit | View or change draft paper question assignments. |
| `online_assign_view_student` view/edit | View or change candidate rosters; edit also permits assigning an Unassigned student from Review. |
| `question_bank` view/add/edit/delete | Manage reusable source questions. |
| `import_question` view | Access Question Bank CSV import. |

Privileges never bypass academic ownership. Teacher access is additionally restricted to exact session, class-arm, and subject assignments on list, setup, builder, roster, Review, marking, and analysis paths.

State-changing actions use normal CSRF protection plus the Online Examination workflow token. Submitted identifiers are checked against their parent assessment and teacher scope. Question content and answers are sanitized, timing is enforced by the server, and lifecycle, marking, result synchronization, recovery, and archive actions are audited.

## 24. Retired and read-only actions

The following old capabilities are deliberately unavailable for the compact workflow and return a retired/read-only response instead of executing old code:

- legacy examination creation/edit/delete;
- legacy student attempt and submission pages;
- old whole-paper/rank/evaluation actions;
- printed/offline Online Examination paper delivery;
- answer-file upload;
- direct legacy answer-attachment download;
- legacy paper raw-score marking;
- unsupported historical British profile/outcome routes that are not attached to the current `british` purpose; and
- the former complex Operations dashboard, incident-register form, and standalone posting-ledger screen.

Do not train schools to use a hidden or bookmarked retired route. Use the assessment list, builder, roster, simplified Review matrix, Answer and Outcome Review, and existing result modules described above.

## 25. Practical setup recipes

### One Objective CA for one arm

1. Create a CA assessment for session, term, class, one arm, subject, and CA component.
2. Set the window, duration, and pass percentage.
3. Add one Objective paper.
4. Add auto-marked questions whose selectable marks equal the component maximum.
5. Assign candidates.
6. Freeze and publish.
7. Review completion and result-posting status.

### One CA shared by several arms

Select all compatible arms in one assessment only when the same subject is assigned in every arm and the selected component has the same applicable maximum. Build one paper and manage the roster separately for each arm. Review each arm separately.

### CA1 and CA2 for the same subject

Create two assessments: one targeting CA1 and one targeting CA2. The different component keys make them separate valid academic slots.

### Terminal examination

Confirm that the class's enabled CA maximums leave the intended Examination balance. Create the assessment with **Terminal Examination (Exam)** purpose; the form then offers only the calculated Examination component. Build the one Objective or Theory/Essay CBT paper, make its selectable question marks equal that maximum, assign candidates, and publish. Completed scores post automatically to `score.exam`. In Review, select assessment type **Term** and component **Exam**.

### All subjects for one class arm

Create one assessment per subject for the same session, term, class arm, assessment type, and component. The Review page then displays those subjects as separate columns beside the same student list.

### Mixed objective and essay assessment

Create one Theory/Essay paper. Add the auto-marked questions and structured Long answer questions to that paper. Objective parts are marked automatically; the final score waits for the answered long responses to be finalized.

### Answer any N examination

Create a named section, choose Answer any N, set N, and assign the optional questions to it. Prefer equal marks. Ensure the highest N marks plus any unsectioned required questions equal the paper maximum.

### Compulsory plus choice examination

Create a section with Compulsory + choice, set the optional number N, leave required questions checked Compulsory, and uncheck only optional questions. Ensure compulsory marks plus the highest N optional marks equal the paper maximum.

### Comprehension or source passage

Create one grouped-passage child per question using the same valid group key, title, and passage. Choose the correct child response type for each. The candidate sees the passage once with its questions kept together.

### Weak-network sitting

Keep the normal CBT setup. The student uses Start/Resume, autosave, and Save & leave. If the network returns before the deadline, queued saves retry. If the paper closes incomplete, use Review to reschedule the same paper; saved server answers remain.

### Holiday assessment

Configure Holiday Assessment for every intended arm and subject first. Then create the Online Examination with Holiday purpose. If arm maximums differ, separate them into different online assessments.

### British assessment with teacher outcome

Confirm that Exam Setting assigns `ResultType = british` to the class. Create one **British Assessment** for the session, term, class arm, and subject. The builder defaults to **Teacher selects the outcome**. Build the one CBT paper, assign candidates, and publish. After a student submits, open Review → British Outcome → the student's cell → View/Mark answers. Complete any Theory marking, select Emerging, Expected, or Exceeding, and finalize if needed. The outcome posts to `britishresult.Remark`; any existing additional comment remains unchanged.

### British assessment with automatic outcome

Follow the same setup, but change **How is the outcome decided?** to **Convert the score automatically**. Replace the displayed example ranges with the school's approved continuous ranges covering 0–100, save them, then publish. Completion converts and posts the outcome automatically. The frozen ranges used for that sitting remain auditable even if a later assessment uses different ranges.

### Kindergarten concepts

Configure the Kindergarten assessment, subject, concepts, and labels first. Create the Kindergarten Online Examination, build the paper/sections, map each required whole-paper/section outcome to a concept, and define continuous 0–100 label thresholds before publication.

## 26. Database deployment

The current application migration version is **138**. The Online Examination workflow is delivered across:

- migration 128 — Nigerian-localised assessment records, snapshots, attempts, marking, adapters, audit, and synchronization foundation;
- migration 135 — compact CBT and Holiday posting alignment;
- migration 136 — permanent removal of Question Bank Level/difficulty;
- migration 137 — candidate-paper Review, rescheduling, supervised recovery score, and completed-assessment archive support; and
- migration 138 — one paper alignment and unique subject/component academic slots per class arm.

The phpMyAdmin-compatible all-school bundle includes these migrations:

[docs/all_school_database_migrations.sql](all_school_database_migrations.sql)

Back up each school database, test on a copy/staging database, select the intended school database in phpMyAdmin, run the all-school bundle, and review its verification output. The bundle is designed to be rerunnable.

[docs/schoollift_demo_legacy_online_examination_cleanup.sql](schoollift_demo_legacy_online_examination_cleanup.sql) is a destructive data cleanup intended only for `demo.schoollift.com.ng`. It is not a migration and must never be run across all school databases.

## 27. End-to-end verification checklist

- Create CA, Midterm, Exam, British, Holiday, and Kindergarten assessments where the school configuration supports them.
- Confirm session/class/subject/arm filtering and exact teacher ownership.
- Confirm a duplicate subject/component slot in the same arm is rejected.
- Confirm another subject or CA component remains allowed.
- Confirm only one paper can be created and its maximum is derived from the selected destination.
- Reject an assessment or paper interval shorter than its duration.
- Confirm all questions default to Compulsory.
- Test no section, Answer all, Answer any N, and Compulsory + choice totals.
- Test all supported response types, including numeric boundaries and grouped passages.
- Confirm Objective rejects Long answer and Theory permits mixed question types.
- Confirm invalid totals and missing Theory marking schemes block publication.
- Confirm freeze protects snapshots from later Question Bank edits.
- Test assigned, unassigned, upcoming, rescheduled, in-progress, missed, incomplete, awaiting-marking, and completed Review cells.
- Test autosave, retry queue, refresh/resume, timeout, final packet failure, and duplicate submit.
- Verify closed papers have no Start button.
- Verify rescheduling preserves the official attempt and saved answers.
- Verify supervised recovery scoring and its audit reason.
- Verify objective automatic posting and Theory posting only after final marking.
- Verify CA/Midterm values post to their configured `ca1`–`ca10` fields, Exam posts to `score.exam`, British posts Emerging/Expected/Exceeding to `britishresult.Remark` without changing `AdditionalComments`, and Holiday/Kindergarten use their specialist destinations.
- Verify British teacher-selection waits for an authorized outcome and threshold mode converts automatically from the frozen school ranges.
- Verify manual-score conflicts are never silently overwritten.
- Verify Retry and the audited standard/Holiday replacement flow.
- Verify feedback release does not publish an official result.
- Verify official result publication still controls report-card and cumulative visibility.
- Verify Draft deletion and Completed soft archive rules.
- Verify historical/legacy examinations remain unchanged and executable legacy actions stay retired.
- Verify admin and student pages on phone, tablet, laptop, and desktop widths.
