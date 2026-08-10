# Online examinations: architecture, operation, and testing guide

Last reviewed: 10 August 2026 (Africa/Lagos)

This document explains the Online Examinations functionality in this repository. It covers the navigation history, the difference between traditional and online examinations, the Nigerian academic-assessment workflow, student attempts, marking, result synchronization, permissions, deployment requirements, testing, and known limitations.

## Executive summary

The codebase contains two related but separate examination areas:

| Navigation area | Main purpose |
|---|---|
| **Examinations** | Traditional exam groups, schedules, marks, results, admit cards, marksheets, and grades |
| **Online Examinations** | Computer-based assessments, question banks, timed student attempts, automatic and manual marking, and synchronization into the school's result tables |

The Online Examinations menu itself is not a recent addition. Git history traces both navigation areas to the initial repository commit from 5 November 2024. What was added much more recently is the expanded version-2 Nigerian academic-assessment workflow behind the existing Online Examinations menu.

The version-2 implementation supports:

- Continuous Assessment, midterm, terminal, promotion, mock, and practice assessments.
- Standard Nigerian score components, British outcomes, Kindergarten concepts, and unlinked practice results.
- Multiple papers, weighted contributions, sections, and answer-choice rules.
- CBT, physical-paper, and hybrid delivery.
- Objective, theory, numeric, matching, ordering, upload, oral, aural, practical, and project responses.
- Frozen published revisions so live examination content cannot be silently changed.
- Server-controlled time limits, autosave, temporary browser retry queues, and idempotent submission.
- Automatic scoring and versioned manual marking.
- Extra-time and makeup accommodations, incident records, voids, and an audit trail.
- Conflict-aware synchronization into the existing result system.

It does not currently provide webcam proctoring, a lockdown browser, plagiarism detection, or completely offline examination delivery.

## 1. Navigation history

### 1.1 The menu was not recently created

The relevant navigation locations are:

- Traditional Examinations: [`application/views/layout/sidebar.php`](../application/views/layout/sidebar.php#L514)
- Online Examinations: [`application/views/layout/sidebar.php`](../application/views/layout/sidebar.php#L569)
- Student Online Exam link: [`application/views/layout/student/header.php`](../application/views/layout/student/header.php#L331)
- Examination reports: [`application/views/layout/sidebar.php`](../application/views/layout/sidebar.php#L1339)

Git history attributes the original menu markup to:

- Commit: `47e56be3011baef5273296bd7213d8aad2150763`
- Subject: `Initial commit`
- Author: `Kcspex <kcspex@gmail.com>`
- Date: 5 November 2024

The module and role-based visibility wrappers date to commit `362c199ed6461e2341fa678492198eba6fecd6c5` from 5 December 2024.

Commit `adb28fe` from 25 July 2026 substantially expanded the Online Examinations functionality, but it did not add the sidebar or student menu. It added the Nigerian workflow, builder, operations dashboard, marking process, revision snapshots, and localized result adapters. It also changed the existing action into **Add Nigerian Academic Assessment** in [`application/views/admin/onlineexam/index.php`](../application/views/admin/onlineexam/index.php#L22).

The recent biometric, Monnify, and consolidated-migration commits also did not add or change the examination menu.

### 1.2 Why an old menu can appear recently

The traditional admin menu is shown only when:

1. The `examination` module is active; and
2. The logged-in role has at least one relevant examination privilege.

The online admin menu is shown only when:

1. The `online_examination` module is active; and
2. The role has `online_examination.can_view` or `question_bank.can_view`.

The student link depends on the student/parent role-specific `online_examination` module setting.

Consequently, importing a different tenant database, enabling a module, changing role permissions, or correcting broken permission records can make a long-existing menu visible for the first time.

## 2. Legacy and version-2 workflows

The original Online Examination implementation remains in place for historical records. The newer Nigerian workflow operates beside it rather than replacing or converting it.

The `onlineexam.workflow_version` value distinguishes them:

- A legacy value follows the original modal-based creation, attempt, result, and ranking behaviour.
- Version `2` follows the localized assessment, paper, revision, attempt, marking, and result-synchronization workflow described in this document.

The main admin list branches between the two implementations in [`application/controllers/admin/Onlineexam.php`](../application/controllers/admin/Onlineexam.php#L48). Existing legacy examinations are deliberately not converted automatically because their historical questions, scoring assumptions, and student attempts may not satisfy the version-2 rules.

Administrators must therefore be trained to identify which workflow an examination uses. The version-2 operations and analysis screens should be treated as authoritative for version-2 assessments; old ranking/report writers are primarily for legacy records.

## 3. High-level architecture

```text
Administrator or teacher
        |
        v
Assessment context -> papers -> sections -> questions -> candidate roster
        |                                                   |
        +---------------- validate and freeze --------------+
                                |
                                v
                       immutable published revision
                                |
                                v
Student eligibility -> timed paper -> autosaved answers -> submission
                                                       |
                       +-------------------------------+
                       |
                       v
            automatic scoring + manual marking
                       |
                       v
              completed official attempt
                       |
                       v
        conflict-aware result synchronization
                       |
             +---------+----------+
             |         |          |
             v         v          v
          `score`  `britishresult` `kindergarten_result`
                       |
                       v
           existing result review/publication process
```

The principal implementation files are:

| Responsibility | File |
|---|---|
| Admin creation, building, publication, operations, and marking endpoints | [`application/controllers/admin/Onlineexam.php`](../application/controllers/admin/Onlineexam.php) |
| Student list, attempt, autosave, upload, and submission endpoints | [`application/controllers/user/Onlineexam.php`](../application/controllers/user/Onlineexam.php) |
| Assessment validation, frozen revisions, lifecycle, and final totals | [`application/models/Onlineexamworkflow_model.php`](../application/models/Onlineexamworkflow_model.php) |
| Student attempts, response validation, autosave, and scoring | [`application/models/Onlineexamattempt_model.php`](../application/models/Onlineexamattempt_model.php) |
| Result synchronization and conflict handling | [`application/models/Onlineexamresultsync_model.php`](../application/models/Onlineexamresultsync_model.php) |
| Student examination browser interface | [`application/views/user/onlineexam/view_v2.php`](../application/views/user/onlineexam/view_v2.php) |
| Version-2 database schema | [`application/migrations/128_localize_online_examination.php`](../application/migrations/128_localize_online_examination.php) |
| Expiry and lifecycle scheduler | [`application/controllers/Cron.php`](../application/controllers/Cron.php#L38) |

## 4. Creating an assessment

The localized creation workflow begins in [`admin/Onlineexam.php`](../application/controllers/admin/Onlineexam.php#L184).

### 4.1 Academic context

The creator selects:

- Academic session.
- Term: first, second, or third term.
- Class.
- One or more class arms/sections.
- Subject.
- Assessment purpose.

Supported purposes are:

- Continuous Assessment.
- Midterm examination.
- Terminal examination.
- Promotion examination.
- Mock examination.
- Practice or quiz.

A teacher may only create an assessment for an assigned session, class arm, and subject. Administrative users with the appropriate access can work across assignments.

### 4.2 Examination window and rules

The assessment configuration includes:

- Opening date and time.
- Closing date and time.
- Duration.
- Pass percentage.
- Attempt allowance.
- Question and option randomization.
- Negative-marking policy.

Official result-bearing assessments use one official attempt. Multiple attempts are intended for practice-type assessments. Paper windows must remain inside the parent assessment window.

### 4.3 Result destination

Every version-2 assessment chooses a result adapter:

| Adapter | Destination and behaviour |
|---|---|
| `standard_component` | Writes the completed mark into a configured `ca1`–`ca10` or `exam` component in the existing `score` table |
| `british_outcome` | Writes a qualitative outcome into `britishresult.Remark` |
| `kindergarten_concept` | Maps paper or section performance to configured concepts in `kindergarten_result` |
| `unlinked_practice` | Retains results in Online Examinations without posting an official score |

The standard component and its maximum are derived from the school's existing result settings. The workflow does not assume that every school uses the same number of CAs or the same CA/exam weighting.

A result-bearing purpose cannot use `unlinked_practice`.

## 5. Building papers and sections

The builder begins in [`admin/Onlineexam.php`](../application/controllers/admin/Onlineexam.php#L402).

### 5.1 Papers

One assessment may contain several papers, such as:

- Objective.
- Theory.
- Practical.
- Oral.
- Aural.
- Project.
- A custom paper type.

Each paper defines:

- Its display order.
- Delivery mode.
- Opening and closing window.
- Duration.
- Raw maximum marks.
- Contribution to the overall assessment result.
- Any objective-paper negative mark.

Delivery modes are:

- `cbt`: delivered and answered online.
- `paper`: completed outside the browser and scored by a marker.
- `hybrid`: combines online and manually assessed work.

Raw marks and contribution marks allow papers with different marking scales to contribute correctly. For example, a 60-mark objective paper can contribute 40 marks while a 100-mark theory paper contributes 60 marks.

### 5.2 Paper sections

A paper can contain ordered sections. Supported answer rules include:

- Answer all.
- Answer any configured number.
- Compulsory questions plus a configured number of choices.

These rules are enforced when answers are submitted and scored. They should also be stated plainly in the section instructions so students understand the expected choice before starting.

## 6. Question authoring and response types

Questions may be selected from the existing question bank or authored directly for the assessment.

Supported response types include:

- Single choice.
- Multiple choice.
- True/false.
- Short answer.
- Numeric answer with tolerance.
- Matching.
- Ordering.
- Grouped passage.
- Long answer.
- File upload.
- Oral.
- Aural/audio.
- Practical rubric.
- Project rubric.
- Legacy descriptive response.

Objective response types require valid answer definitions. Manual response types require marking guidance, a marking scheme, or a rubric. Negative marks apply only where the paper and objective question configuration permits them.

### 6.1 British result profiles

British-result assessments support:

- Threshold conversion, in which a numeric total is converted into an outcome; or
- Teacher selection of an outcome such as Emerging, Expected, or Exceeding.

### 6.2 Kindergarten mappings

Kindergarten assessments map papers or sections to existing learning concepts. Configured thresholds determine the label recorded for each concept.

## 7. Candidate assignment

Candidate management is implemented in [`admin/Onlineexam.php`](../application/controllers/admin/Onlineexam.php#L2054).

Students are selected from the assessment's session, class, and class-arm roster. A candidate record is kept so eligibility is explicit and auditable rather than inferred only from current enrollment at attempt time.

Candidate states distinguish assigned, excluded, started, submitted, marking, completed, and voided situations. Once an official attempt has started, the student cannot simply be deleted from the roster. The attempt must be resolved or voided through an authorized operation so the history remains visible.

Candidates who never start still require administrative handling where the assessment is intended to produce a complete class result. The operator may need to record absence, create/resolve a paper attempt, grant a makeup, or exclude the candidate according to school policy.

## 8. Validation, freezing, and publication

Assessment validation and revision creation are implemented in [`Onlineexamworkflow_model.php`](../application/models/Onlineexamworkflow_model.php#L132).

Before publication, validation checks include:

- Complete academic context.
- At least one selected class arm.
- A compatible result destination.
- At least one active paper.
- Valid paper type and delivery mode.
- Positive raw and contribution scores.
- Required questions and valid answer definitions.
- Compatible assessment and paper windows.
- Required result profiles or concept mappings.

Publishing automatically freezes an immutable revision. The revision snapshots:

- Assessment and result configuration.
- Papers and sections.
- Questions and instructions.
- Options and correct answers.
- Marks and negative marks.
- Ordering and randomization information.

This is an important examination-integrity control: subsequent question-bank edits cannot silently alter what a student was assigned. If published content needs a material change, a new revision must be created. Existing attempts remain attached to their original frozen revision.

An assessment cannot safely move to a new revision while unresolved student attempts depend on the current one.

## 9. Student eligibility and attempt flow

The student implementation is in [`application/controllers/user/Onlineexam.php`](../application/controllers/user/Onlineexam.php).

### 9.1 Eligibility

Before starting or resuming, the server checks:

- The authenticated student owns the candidate assignment.
- The student's session, class, and arm match the assessment.
- The assessment and selected revision are published.
- The assessment and paper windows are open.
- The delivery mode permits an online attempt.
- The attempt allowance has not been exceeded.
- Any extra-time or makeup accommodation is valid.

A physical-paper-only paper is not opened as a CBT paper.

### 9.2 Starting and resuming

Starting a paper uses database locking so two near-simultaneous browser requests cannot safely create separate official attempts. The server records the authoritative start time and deadline. Reopening the page resumes the eligible attempt rather than resetting its timer.

Question randomization is stabilized for the attempt so refreshing the browser does not produce a different effective examination.

### 9.3 Answer autosave

The student interface in [`view_v2.php`](../application/views/user/onlineexam/view_v2.php#L131) autosaves changed answers after a short delay.

The server verifies the student, attempt, paper, frozen question, and ownership on every save. A client sequence number prevents a delayed older request from overwriting a newer answer. Answers are upserted against a unique attempt/question pair.

The browser also maintains a temporary `localStorage` retry queue:

- A failed save remains queued in that browser.
- Queued saves are retried periodically and when connectivity returns.
- Normal submission first attempts to flush pending saves.

This provides resilience to short Nigerian network interruptions, but it is not a full offline examination mode:

- The examination must initially load online.
- Queued answers exist only in that browser profile on that device.
- Clearing browser storage can remove the retry queue.
- Upload answers are not queued offline.
- Final synchronization and submission require the server.

### 9.4 File-upload answers

Question-level rules control permitted file extensions and sizes. The server validates extension, MIME information, size, student ownership, and examination state. Executable and web-script formats are rejected.

Files are stored outside the public web directory under `application/writable/onlineexam_answers/`. Authorized staff download them through a controlled endpoint rather than by guessing a public URL.

### 9.5 Submission and timeout

Submission uses a unique submission key and is designed to be idempotent. Retrying the same request should not create a second official submission.

During submission, the system:

1. Verifies the student and attempt state.
2. Records unanswered questions where required.
3. Applies section answer limits.
4. Scores eligible objective responses.
5. Places subjective responses in the manual-marking queue.
6. Updates paper and assessment lifecycle state.

If time expires, the server-authoritative deadline wins. Saved answers are used and remaining unanswered questions receive no marks. The production scheduler can process expired papers even if the candidate never reconnects.

## 10. Automatic and manual marking

Automatic scoring in [`Onlineexamattempt_model.php`](../application/models/Onlineexamattempt_model.php) handles:

- Single and multiple choice.
- True/false.
- Configured short-answer comparisons.
- Numeric answers with tolerance.
- Matching.
- Ordering.
- Negative marks where allowed.
- Unanswered questions and section answer limits.

Manual marking is used for theory, long answers, uploads, oral, aural, practical, project, and other rubric-based work.

Markers can:

- Save draft answer-level marks.
- Add marker comments.
- Finalize answer-level marks.
- Enter a whole-paper mark for a physical paper.
- Revise marking through a new marking version rather than silently destroying history.

An attempt is not finalized until all required manual marking is complete. Each paper's earned raw result is normalized to its configured contribution. Contributions are combined and scaled to the configured target where appropriate. The final result is prevented from becoming negative.

## 11. Operations and accommodations

The version-2 operations dashboard begins in [`admin/Onlineexam.php`](../application/controllers/admin/Onlineexam.php#L1497).

It provides visibility into:

- Candidate and attempt status.
- Started, submitted, marking, completed, expired, and voided papers.
- Extra-time accommodations.
- Makeup attempts.
- Incidents.
- Manual marking progress.
- Result-synchronization state and conflicts.
- Assessment analysis.

Operational actions should be accompanied by a meaningful reason where requested. Incidents, voids, accommodations, marking versions, publication actions, and result synchronization contribute to the audit trail.

## 12. Result synchronization

Synchronization is implemented in [`Onlineexamresultsync_model.php`](../application/models/Onlineexamresultsync_model.php#L19).

### 12.1 What synchronization does

After an official attempt is completed, the adapter prepares the corresponding school result write:

- Standard component: updates the selected CA or examination field in `score`.
- British outcome: creates or updates the appropriate `britishresult.Remark`.
- Kindergarten concept: writes the mapped concept label into `kindergarten_result`.
- Unlinked practice: records the online outcome without updating official report-card tables.

### 12.2 Conflict protection

Synchronization is transactional, target-locked, fingerprinted, and idempotent. It maintains a ledger describing what source attempt produced a destination value.

If the destination already contains an unrelated or manually entered value, the system records a conflict instead of silently overwriting it. An authorized administrator must inspect and explicitly approve a supported replacement. The override is audited.

Retries are safe where the same completed attempt and destination are involved.

### 12.3 Synchronization is not publication

Score synchronization and report-card publication are deliberately separate:

1. Online Examination calculates and finalizes an attempt.
2. Result synchronization places the value into the school's normal working result table.
3. The existing result review and publication process determines when the result/report card becomes official and visible.

Similarly, releasing Online Examination feedback to a student does not publish the school's report card. Feedback release is controlled separately in [`admin/Onlineexam.php`](../application/controllers/admin/Onlineexam.php#L1181).

## 13. Permissions and security controls

The workflow reuses these permission areas:

- `online_examination`
- `question_bank`
- `add_questions_in_exam`
- `online_assign_view_student`

Teachers are additionally scoped to their assigned academic context. State-changing actions use POST endpoints, ownership checks, CSRF controls, transactions, row locks, and audit records where appropriate.

Important integrity controls include:

- Immutable published revisions.
- Server-controlled deadlines.
- Stable attempt/question ownership checks.
- Sequence-protected autosaves.
- Idempotent submission and result synchronization.
- Versioned marking history.
- Conflict-aware result writes.
- Non-public answer-file storage.

One operational limitation is that several duties remain grouped under broad `online_examination.can_edit` access. The system does not currently define fully separated invigilator, marker, reviewer, and finalizer privileges or enforce two-person approval for high-stakes results.

## 14. Database migration

Migration 128 is [`application/migrations/128_localize_online_examination.php`](../application/migrations/128_localize_online_examination.php).

It extends the legacy `onlineexam`, `onlineexam_questions`, and `onlineexam_students` tables and creates these version-2 tables:

1. `onlineexam_class_sections`
2. `onlineexam_papers`
3. `onlineexam_paper_sections`
4. `onlineexam_question_definitions`
5. `onlineexam_revision_snapshots`
6. `onlineexam_question_snapshots`
7. `onlineexam_candidate_attempts`
8. `onlineexam_attempt_papers`
9. `onlineexam_attempt_answers`
10. `onlineexam_accommodations`
11. `onlineexam_marking`
12. `onlineexam_paper_marking`
13. `onlineexam_result_profiles`
14. `onlineexam_kindergarten_mappings`
15. `onlineexam_result_target_locks`
16. `onlineexam_result_sync`
17. `onlineexam_incidents`
18. `onlineexam_audit_log`

The migration is designed to be idempotent. The new tables do not declare database foreign-key constraints, so referential integrity depends primarily on the controller/model logic and application transactions.

The 17 school database dumps audited on 9 August 2026 all contained the complete migration-128 Online Examination structure. See [`docs/school-database-migration-audit-2026-08-09.md`](school-database-migration-audit-2026-08-09.md) for the wider migration audit.

## 15. Production scheduler requirement

[`application/controllers/Cron.php`](../application/controllers/Cron.php#L38) invokes expired-paper and lifecycle processing. The route is protected by the configured cron secret.

Production must call the cron endpoint regularly. A five-minute interval is a reasonable starting point unless operational testing establishes a different requirement.

Example shape only:

```cron
*/5 * * * * curl --fail --silent --show-error "https://school.example/cron/REPLACE_WITH_SECRET" >/dev/null
```

Do not commit the real cron secret or expose it in screenshots or documentation. Confirm that the endpoint returns successfully and that an expired test paper is processed without the student revisiting it.

Without the scheduler, some lifecycle processing occurs only during later application requests, and abandoned timed attempts can remain unresolved longer than intended.

## 16. Automated tests

Run the dedicated tests from the repository root:

```bash
php tests/onlineexam_scoring_test.php
php tests/onlineexam_response_types_test.php
```

Expected output:

```text
onlineexam scoring tests passed
onlineexam response type tests passed
```

These tests currently pass. They validate important scoring and response-type behaviour, but they are not a complete production certification. There are no comprehensive automated browser, controller, real-database, concurrent-request, upload, scheduler, or end-to-end result-publication tests.

## 17. Recommended staged test procedure

Never use a live terminal or promotion examination as the first end-to-end test.

### 17.1 Prepare an isolated tenant

1. Clone a school database to a test-only database.
2. Use a test-only hostname that cannot select a production tenant database.
3. Confirm migration 128 and all required tables are present.
4. Confirm `application/writable/onlineexam_answers/` is writable by the PHP process but not publicly served.
5. Configure the cron secret and scheduler in the test environment.
6. Create test administrator, teacher, marker, and student accounts.
7. Back up the test database before beginning the scenario.

### 17.2 Verify permissions and menu visibility

Test that:

- An administrator with access can see and manage Online Examinations.
- A permitted teacher sees only assigned class arms and subjects.
- An unassigned teacher cannot create or operate an assessment outside their allocation.
- A student sees only assigned, eligible assessments.
- Removing view/edit permission hides the relevant navigation and blocks direct endpoint access.

### 17.3 Create a safe practice assessment

Start with `unlinked_practice` so testing cannot alter official marks.

Create:

- One CBT objective paper.
- One theory/manual paper.
- At least two sections with different answer rules.
- A mix of single choice, multiple choice, numeric tolerance, matching, ordering, long answer, and file upload.
- Two or more test students.
- A short but realistic opening window and duration.

Confirm validation rejects incomplete or contradictory configuration.

### 17.4 Verify frozen revisions

1. Publish the assessment.
2. Start it as one test student.
3. Edit the source question-bank item.
4. Confirm the student's frozen question and correct-answer definition do not change.
5. Confirm material published changes require the appropriate new-revision process.

### 17.5 Test normal student completion

Verify:

- Starting creates only one official attempt.
- Refreshing resumes the same attempt and deadline.
- Question order remains stable for that attempt.
- Answers autosave and remain after refresh.
- Section answer limits work.
- Submission cannot accidentally create a duplicate.
- Objective marks are calculated as expected.
- Manual responses enter the marking queue.

### 17.6 Test interrupted connectivity

1. Answer several questions normally.
2. Disconnect the browser temporarily.
3. Change text/objective answers and confirm they enter the local retry queue.
4. Reconnect and confirm the queue drains successfully.
5. Refresh only after confirming server synchronization.
6. Confirm upload behaviour is communicated clearly because uploads are not queued offline.

Also test a complete browser/device loss. Document to staff that an unsynchronized local queue on a lost device cannot be recovered from the server.

### 17.7 Test time expiry and cron

1. Allow one paper to expire while the browser remains open.
2. Confirm the server deadline prevents additional valid work.
3. Start another test attempt, close the browser, and let it expire.
4. Confirm the cron job processes the abandoned attempt without a student revisit.
5. Confirm saved answers are retained and unanswered questions receive no marks.

### 17.8 Test manual operations

Verify:

- Draft and final answer marking.
- A physical-paper score.
- Extra-time accommodation.
- Makeup attempt authorization.
- Incident creation and resolution.
- Attempt voiding and any supported reversal.
- Audit records and operator identities.

### 17.9 Test result adapters

Use separate controlled assessments to test:

- A standard CA component.
- A standard examination component.
- A British outcome.
- A Kindergarten concept mapping.
- An unlinked practice result.

For each result-bearing adapter, compare the final online result with the exact destination row before and after synchronization.

Create a deliberate destination conflict by placing an unrelated test mark in the target field. Confirm synchronization reports a conflict and does not overwrite it silently. Then test the authorized resolution procedure and audit record.

Finally, confirm that synchronization alone does not publish a report card and that the normal result-publication workflow still controls official visibility.

### 17.10 Pilot under realistic load

Before a live high-stakes examination:

1. Run a low-stakes supervised pilot with one class.
2. Use the same devices, browsers, network, power backup, and room arrangement planned for production.
3. Start students in realistic batches rather than one at a time.
4. Monitor PHP, web-server, database, disk, and network behaviour.
5. Exercise the invigilation, incident, makeup, and manual-marking procedures.
6. Reconcile every pilot candidate and result.
7. Record defects and repeat the pilot after fixes.

## 18. Known limitations and operational risks

The following are not currently implemented or fully automated:

- Webcam or live proctoring.
- Identity verification beyond the authenticated student session.
- Lockdown browser or prevention of other applications/tabs.
- Plagiarism detection.
- IP-address or examination-centre network restriction.
- Automatic suspicious-behaviour detection.
- Fully offline examination delivery.
- Offline queueing for file uploads.
- Object-storage integration for uploaded answers.
- Malware scanning, formal retention rules, or automatic orphan-file cleanup.
- Separate invigilator, marker, reviewer, and finalizer RBAC duties.
- Enforced two-person approval for high-stakes mark changes or synchronization overrides.
- Comprehensive browser/database/concurrency/end-to-end automated tests.

Some superseded legacy code remains in the repository, and legacy and version-2 records share parts of the original module. Changes must therefore be regression-tested against both workflows.

## 19. Production readiness checklist

Do not approve a live high-stakes assessment until all applicable items are complete:

- [ ] Migration 128 verified on the exact tenant database.
- [ ] Database and uploaded-answer backups tested.
- [ ] Module and role permissions reviewed.
- [ ] Teacher academic assignments verified.
- [ ] Result adapter and component maximum verified.
- [ ] Papers, sections, question marks, and contribution totals independently reviewed.
- [ ] Correct answers and marking rubrics independently reviewed.
- [ ] Candidate roster reconciled with enrollment.
- [ ] Assessment and paper windows checked in Africa/Lagos time.
- [ ] Cron expiry processing tested.
- [ ] Upload directory permissions and disk capacity checked.
- [ ] Browser/device compatibility tested.
- [ ] Network and power backup tested under load.
- [ ] Autosave and interruption recovery demonstrated to invigilators.
- [ ] Incident, extra-time, makeup, and void procedures documented.
- [ ] Manual marking and moderation process assigned.
- [ ] Result synchronization tested on a non-production result.
- [ ] Conflict handling and audit trail tested.
- [ ] Staff understand that synchronization is not report-card publication.
- [ ] One-class low-stakes pilot completed and reconciled.
- [ ] Rollback, support, and emergency communication contacts assigned.

## 20. Recommended operating policy

For a normal school deployment:

1. Use unlinked practice assessments for student orientation and technical rehearsal.
2. Require a second staff member to review every high-stakes assessment before publication, even though this is not yet enforced by software.
3. Freeze and publish only after questions, answers, weights, timing, and roster have been independently checked.
4. Keep an invigilator incident log during every sitting.
5. Reconcile all candidate states before finalizing the assessment.
6. Complete manual marking and moderation before result synchronization.
7. Investigate every synchronization conflict rather than automatically replacing an existing mark.
8. Use the normal school result review and publication process after synchronization.
9. Retain database backups, uploaded answers, audit logs, and marking records according to an approved school retention policy.
10. Run a post-examination reconciliation covering candidates, attempts, marks, incidents, sync records, and published results.

The current implementation provides a strong foundation for localized online assessment, but reliable operation still depends on correct tenant configuration, a functioning scheduler, stable infrastructure, trained staff, and a properly supervised pilot.
