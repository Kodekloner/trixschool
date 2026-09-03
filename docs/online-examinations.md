# Online Examinations

Last reviewed: 2 September 2026 (Africa/Lagos)

This guide describes the compact Online Examination module used by this codebase. It covers the administrator, teacher, and student flow; how an assessment reaches an existing school result; the supported CBT question types; and the database changes required for every school.

## Scope

Online Examinations is the CBT layer for four assessment purposes already used by the schools:

| Purpose | Result destination |
|---|---|
| Continuous Assessment (CA) | An enabled CA component that is not reserved for midterm |
| Midterm Assessment | An enabled CA component marked for midterm use |
| Holiday Assessment | The matching Holiday Assessment subject and setting |
| Kindergarten Assessment | The matching Kindergarten assessment, subject, and concept mapping |

The destination is derived from the selected academic context. It is not a free-form choice and the creation screen does not expose internal adapter names.

New assessments are online only. They support Objective and Theory/Essay CBT papers. Paper/manual delivery, hybrid delivery, print-paper handling, upload responses, oral/aural responses, practical rubrics, project rubrics, practice quizzes, and multiple official attempts are not part of this compact flow.

Historical Online Examination data remains in the database for integrity and audit purposes. The old authoring and attempt workflow is not available for new work.

## Main flow

1. Create an assessment and select its session, term, class, arms, subject, and purpose.
2. Select the result component or mapping offered for that purpose.
3. Add one or more Objective or Theory/Essay CBT papers.
4. Add optional paper sections and their answer rules.
5. Select questions from the Question Bank or create a structured question in the assessment.
6. Confirm the candidate roster from the selected class arms.
7. Freeze and publish the assessment.
8. Students start or resume their one official timed attempt.
9. Objective answers are marked automatically. Teachers review Theory/Essay answers.
10. A completed result is posted to the existing school result record. Publishing the report card remains a separate existing action.

## Creating an assessment

The assessment form records:

- **Title**: the name shown to staff and candidates.
- **Session and term**: the academic period that owns the result.
- **Class and arms**: the student population from `student_session`.
- **Subject**: the existing class subject to which the result belongs.
- **Purpose**: CA, Midterm, Holiday, or Kindergarten.
- **Component or mapping**: a server-approved destination for the selected purpose.
- **Open and close time**: the interval during which candidates may begin or continue.
- **Duration**: the default examination time. A candidate accommodation may add extra minutes.
- **Pass percentage**: used for assessment reporting; it does not change the official result scale.
- **Randomize questions**: changes question order for candidates within the applicable paper or section.
- **Negative marking**: when enabled, applies only to attempted, wrong objective answers. Unanswered questions remain zero.
- **Display marks during review**: controls whether released online feedback shows marks. It does not publish the official report card.

Every assessment has one non-voided official attempt. Opening or refreshing the page does not create another attempt. An authorized void retains the incident and audit history and permits one replacement attempt.

### CA and Midterm component filtering

The server reads the school's existing `resultsetting` and `assigncatoclass` configuration.

- CA shows enabled CA slots that are not configured as midterm slots.
- Midterm shows enabled CA slots configured for midterm use.
- Disabled, zero-maximum, mismatched-class, or otherwise invalid components are rejected even if a crafted request submits them.

The selected component maximum is frozen with the published assessment. For example, a CA mapped to `ca1` with maximum 20 is displayed as `CA1 (max 20.00)`.

## Papers

An assessment can have multiple CBT papers. Each paper contains these fields:

| Field | Use |
|---|---|
| **Paper title** | Candidate-facing name, such as `English Objective` or `English Theory` |
| **Code** | Optional short identifier used by staff, such as `ENG-OBJ` |
| **Type** | `Objective` for automatically scored responses or `Theory / Essay` for teacher-reviewed responses |
| **Minutes** | Time allowed for that paper; server time is authoritative |
| **Raw max** | Total raw marks available from the paper's questions |
| **Contribution** | How much the paper contributes before the whole assessment is scaled into its result destination |
| **Display order** | Numeric order in which papers appear to staff and candidates; lower numbers appear first |

All papers use CBT/online delivery. The paper open/close window must fit within the assessment window.

The contribution formula is:

```text
paper contribution = earned raw mark / paper raw maximum * configured contribution
```

The combined contribution is then scaled into the selected result maximum and rounded to two decimal places.

## Sections and answer rules

A paper may contain ordered sections with separate instructions. A section can require:

- all questions;
- any configured number of questions; or
- compulsory questions plus a configured number from the remaining questions.

The server enforces the rule during scoring. The candidate page also shows progress and prevents an accidental answer beyond an `answer any N` limit unless the candidate clears or changes an earlier answer.

## Questions

The compact CBT flow supports:

- MCQ/single choice;
- multiple choice;
- true/false;
- short answer;
- numeric answer with tolerance;
- matching;
- ordering;
- grouped passage questions; and
- Theory/long answer.

Question difficulty remains an independent Question Bank property where historically required, but it is not part of Online Examination authoring or assessment behavior.

### Question Bank integration

A selected Question Bank item is assigned to a paper or section without weakening the original bank permissions. Questions that are already referenced by an online assessment cannot be deleted through single or bulk deletion.

When an assessment-owned structured question is edited, the assigned definition is updated in place. Copy-on-write is used only when a source is genuinely shared and changing it would alter another assessment. This prevents the previous behavior where an ordinary edit could leave an unexpected unassigned copy.

Once the first official attempt exists, the published question snapshot and result mapping are immutable. Later authoring changes require a new revision.

## Candidate roster and publication

The roster comes from `student_session` for the chosen session, class, and arms. Staff may exclude a candidate, add an eligible late candidate, grant extra time, or void an invalid attempt so an audited replacement can be started, subject to role and assignment checks.

Publishing performs a complete server-side validation and freezes:

- academic and result mappings;
- papers, sections, instructions, and marks;
- question text, options, correct answers, and marking guidance;
- candidate eligibility; and
- timing and assessment rules.

Invalid totals, invalid time windows, missing mappings, unsupported paper/question types, and questions outside the selected subject are rejected before publication.

## Student attempt

The student list shows only assessments for which the student is eligible. Starting an assessment creates or resumes its one non-voided official attempt.

During the attempt:

- the server determines the remaining time;
- each answer is autosaved;
- failed saves are queued in the browser and retried after reconnection;
- final submission sends the complete current answer set in one transaction, so the paper is not closed if that packet cannot be stored;
- saved server answers are restored after refresh or device/network disruption;
- older save responses cannot overwrite newer answers;
- candidates can clear an answer or save and leave;
- timeout is finalized by the server, not by changing the browser clock; and
- duplicate and lost-response submission retries are handled idempotently without discarding the browser recovery queue.

Objective answers are scored from the frozen definition. Theory/Essay answers wait for authorized teacher review.

## Marking and feedback

Teachers see only assessments within their assigned class/subject scope. A Theory/Essay response is marked against its frozen maximum and marking scheme. Completion is recalculated after every valid marking action.

Online feedback is separate from official result publication. Releasing answers or marks to a candidate never publishes a termly, midterm, cumulative, British, Kindergarten, or Holiday report card.

## Automatic result posting

Posting occurs only after all required scoring is complete and runs transactionally. Repeating the same synchronization does not create a duplicate result. A repeated post also rechecks that the previously posted destination still belongs to the same academic context and still contains the value owned by the assessment; an external change is held as a conflict.

### Standard CA/Midterm result

The scaled score is written to the selected `ca1`–`ca10` column in the existing `score` row. The online assessment does not replace the school's grading, broadsheet, comments, affective/psychomotor ratings, or publish-result process.

If the destination contains an unrelated manual score, the online result is held as a conflict. It is never silently overwritten. A correction can change only a value previously posted by that same online assessment, with old/new values retained in the audit trail.

### Holiday Assessment

The assessment is mapped to an existing Holiday Assessment setting and subject. Online posting records its provenance in `holiday_assessment_scores` so a later staff-entered manual score is distinguishable from an online-owned score.

Manual Holiday score entry marks the row as manual and clears online ownership. Placeholder rows created by the bulk Holiday setup script are marked as placeholders. Historical rows are conservatively treated as manual/unknown during migration.

### Kindergarten Assessment

The mapping uses existing Kindergarten assessment, subject, and concept identifiers. Finalized performance is converted to the existing configured result-label index. Concept identifiers referenced by historical results are not deleted and recreated during normal editing.

## Security and integrity

New and changed endpoints enforce:

- role privileges and teacher assignment ownership;
- CSRF tokens on state-changing requests;
- numeric and relationship validation for submitted identifiers;
- assessment lifecycle and frozen-revision checks;
- allowed CBT paper and response-type lists;
- server-controlled timing and one-attempt rules;
- sanitized question content; and
- audit, incident, marking, and synchronization records.

## Responsive interface

The admin list, assessment form, builder, candidate roster, operations/Theory review, Question Bank, student list, and student attempt views use page-scoped responsive rules.

- Action buttons remain in one horizontal row and scroll inside their own container on narrow screens.
- Data tables keep their column relationships and use contained horizontal scrolling instead of converting every cell into a tall card.
- Paper editors use widths that keep numeric values visible on laptop screens and stack cleanly on small screens.
- Candidate metadata and question badges do not overlap.
- Controls remain usable down to a 320-pixel viewport.

## Deployment

CodeIgniter migration `135_compact_online_examination.php` contains the additive schema update. The equivalent standalone script is:

[`docs/online_examination_compact_cbt_migration.sql`](online_examination_compact_cbt_migration.sql)

The same migration is included in the all-school script:

[`docs/all_school_database_migrations.sql`](all_school_database_migrations.sql)

Run the all-school script once for each live school database in phpMyAdmin. It is designed to be safely repeatable and includes post-migration verification queries.

## Regression checklist

- Create each of the four purposes for valid school configurations.
- Confirm CA and Midterm offer only their respective configured CA slots.
- Reject crafted or stale destination mappings.
- Build Objective and Theory papers; reject every retired type and delivery mode server-side.
- Verify question selection, in-place editing, shared-question safety, and deletion guards.
- Verify paper/section totals, answer-any-N, scaling, and two-decimal rounding.
- Verify autosave, reconnect/resume, timeout, duplicate/lost-response submit, one non-voided official attempt, extra time, void, and audited replacement.
- Verify automatic posting and conflict behavior for standard, Holiday, and Kindergarten results.
- Verify existing British and historical result readers still work even though British is not offered for new compact assessments.
- Verify feedback release cannot publish the official report card.
- Verify privileges, ownership, CSRF, crafted identifiers, and stored content handling.
- Verify admin, Question Bank, and student screens at 320, 375, 768, 1024, and desktop widths.
- Run migration 135 twice on representative school database copies and confirm the second run is harmless.
