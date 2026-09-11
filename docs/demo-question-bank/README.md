# Demo SchoolLift question bank

This bundle contains import-ready questions for every valid class-arm and subject combination found in the audited `demo.schoollift.com.ng` database dump.

## What is included

- **23 reusable CSV files** containing 230 checked objective questions.
- **64 class-arm/subject mappings** in `manifest.csv`.
- **10 questions per import**: seven single-choice, two true/false and one multiple-choice.
- Lower-primary, Year 6 and SS1 question sets suited to the class level.
- A separate all-at-once phpMyAdmin installer: `../schoollift_demo_question_bank.sql`.

The CSV files intentionally contain objective questions only. The localized workflow does not accept the old Question Bank `descriptive` type. Create Theory / Long Answer, short answer, numeric, matching, ordering and grouped-passage questions directly in the assessment builder so their marking schemes and response rules are saved correctly.

## Fastest demonstration with the current session

The database dump has Session ID 21 as the active session. Its valid workflow scope is Primary 1, sections A and B, with French, English language, Mathematics, Social Studies and Cultural and Creative Art.

| Class | Section | Subject | CSV to import |
|---|---|---|---|
| Primary 1 | A | French | `csv/lower-primary/french.csv` |
| Primary 1 | A | English language | `csv/lower-primary/english-language.csv` |
| Primary 1 | A | Mathematics | `csv/lower-primary/mathematics.csv` |
| Primary 1 | A | Social Studies | `csv/lower-primary/social-studies.csv` |
| Primary 1 | A | Cultural and Creative Art | `csv/lower-primary/cultural-and-creative-art.csv` |
| Primary 1 | B | French | `csv/lower-primary/french.csv` |
| Primary 1 | B | English language | `csv/lower-primary/english-language.csv` |
| Primary 1 | B | Mathematics | `csv/lower-primary/mathematics.csv` |
| Primary 1 | B | Social Studies | `csv/lower-primary/social-studies.csv` |
| Primary 1 | B | Cultural and Creative Art | `csv/lower-primary/cultural-and-creative-art.csv` |

That is 10 imports in total. The same subject file is deliberately reused for A and B; the import screen attaches it to the class and section selected on the form.

## Import through the Question Bank screen

1. Sign in as Super Admin or as the teacher assigned to the exact class arm and subject.
2. Open **Online Examinations > Question Bank**.
3. Select **Import**.
4. Select the class, section and subject shown in `manifest.csv`.
5. Upload the matching CSV file.
6. Repeat for each scope you want to demonstrate.

Do not import the same CSV into the same class, section and subject more than once. The normal CSV importer creates another copy each time.

## Import everything through phpMyAdmin

Use `docs/schoollift_demo_question_bank.sql` when you want all 64 configured scopes populated in one operation.

1. Back up the demo database.
2. Select the `demo.schoollift.com.ng` database in phpMyAdmin.
3. Open the **Import** tab and select the SQL file, or paste it into the **SQL** tab.
4. Run it and check the final result message.

The SQL installer:

- runs only when `sch_settings.email` is `info@demo.schoollift.com.ng`;
- derives valid scopes from the existing subject-group assignments;
- verifies the audited class and subject names before matching IDs;
- uses an existing staff ID as the author;
- skips a question already present in the same class, section and subject, so it is safe to run again.

Use either the CSV method or the SQL method. Do not use both unless you intentionally want duplicate CSV-imported questions.

## Assigning marks in the assessment builder

Question Bank records do not contain marks. Marks are assigned when questions are added to a paper.

For the current Primary 1 result setting in the audited dump:

- CA1, CA2 and CA3 each have a maximum of 20: assign **2 marks to each of the 10 questions**.
- Exam has a calculated maximum of 40: assign **4 marks to each of the 10 questions**.

The builder will keep the paper raw maximum equal to the selected result component maximum. If a school's result settings differ from this dump, divide that component maximum by 10 and use the result for each question, or use different marks whose total equals the displayed paper maximum.

## Scope notes

`manifest.csv` is the source of truth for the full pack. Rows marked `yes` in `available_in_current_session_21` are available in the active demo session. Other rows reflect valid assignments in Sessions 18 or 19 and can be demonstrated when those sessions are selected and the necessary student/session records exist.

The content is demonstration data. Review and adapt it before using any question in a real assessment.
