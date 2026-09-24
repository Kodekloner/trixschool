# Biometric migration 140 verification report

**Test date:** 22 September 2026
**Purpose:** verify that the production biometric operational update is repeatable and does not alter historical attendance or existing ID-card layouts.

## Result

Migrations 130–132 and 140 passed when applied twice to disposable clones of all 18 supplied school database dumps.

The tests confirmed that:

- migration 140 creates all Live-pilot, notification, acknowledgement, and retention fields;
- the notification queue and its required indexes exist;
- rerunning the biometric migrations is safe;
- student attendance and staff attendance row signatures remain unchanged;
- existing student and staff ID-card rows remain unchanged;
- existing `layout_json` values, including the personal-shaped database, remain unchanged; and
- no supplied dump file was edited.

The complete consolidated migration file was also imported twice against a fresh clone of `trixschool_gis.sql`. That full run completed successfully through migration 140.

## Dumps covered

1. `schoollift_demo.sql`
2. `schoollift_lekced.sql`
3. `schoollift_mercygold.sql`
4. `schoollift_newschool.sql`
5. `schoollift_royalland.sql`
6. `trixschool_apexstaracademy.sql`
7. `trixschool_apexstaracademyaso.sql`
8. `trixschool_bjschool.sql`
9. `trixschool_boldstepschool.sql`
10. `trixschool_gis (1).sql`
11. `trixschool_gis.sql`
12. `trixschool_hameedacademy.sql`
13. `trixschool_ivbs.sql`
14. `trixschool_joyfoundationacademy.sql`
15. `trixschool_kencrestacademy.sql`
16. `trixschool_personal.sql`
17. `trixschool_purplinsschool.sql`
18. `trixschool_royalcreedacademy.sql`

## Pre-existing dump-integrity findings

These are not biometric migration failures. They already exist in the supplied historical data:

- `trixschool_hameedacademy.sql` has 47 `staff_roles` rows that refer to staff records that are no longer present. Its normal foreign-key rebuild stops for that reason.
- `trixschool_joyfoundationacademy.sql` has 142 exam-subject rows that refer to subject records that are no longer present. Its normal foreign-key rebuild stops for that reason.
- `trixschool_personal.sql` contains 5 orphan `staff_roles` rows, although its dump structure permits the normal import to finish.

For Hameed Academy and Joy Foundation Academy, the supplied rows were preserved in isolated test clones by disabling foreign-key enforcement during import. The biometric migrations then passed twice and did not change the orphan rows. Those historical relationships should be audited separately before attempting to recreate their old foreign-key constraints; this biometric deployment must not delete them automatically.

## Repeatable test command

The destructive part of the regression test is opt-in and accepts only an existing absolute socket for an isolated local MySQL server:

```bash
BIOMETRIC_DUMP_MIGRATION_TEST_SOCKET=/absolute/path/to/isolated/mysql.sock \
php tests/biometric_migration_140_dump_test.php
```

Optional environment variables allow a dump filename pattern, a dump limit, another dump directory, or another MySQL client. The test creates randomly named databases only on that isolated server and drops each one when finished.

## Deployment meaning

This report proves schema repeatability and preservation against the supplied dumps. It does not replace a fresh production backup or the supervised physical terminal test. There is no mandatory five-day Shadow wait: after migration 140 is installed, the school can enter Live immediately once the enforced checklist has a real physical IN and OUT for every enabled projection group, a healthy connector with an empty queue, correct pilot selection, and no open biometric exceptions.
