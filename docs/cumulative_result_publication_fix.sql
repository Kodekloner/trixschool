-- Cumulative result publication compatibility cleanup
-- Date: 2026-07-30
--
-- Run this file once in phpMyAdmin for each school database after deploying
-- the matching PHP changes.
--
-- The PHP fix is sufficient for new publications and does not require an
-- ALTER TABLE. These statements only normalize known legacy values and remove
-- the one confirmed malformed Hameed Academy row without publishing anything.

START TRANSACTION;

-- Keep the legacy database spelling used by the application and anchor annual
-- cumulative metadata to third term. Cumulative visibility remains session-wide.
UPDATE `publishresult`
SET
    `ResultType` = 'cummulative',
    `Term` = '3rd'
WHERE LOWER(TRIM(`ResultType`)) IN ('cummulative', 'cumulative')
  AND (
      `ResultType` <> 'cummulative'
      OR `Term` <> '3rd'
  );

-- Hameed Academy dump row 43 was created with an empty result type and can
-- never be reached by a valid publication lookup. The school-name/email guard
-- prevents this targeted cleanup from affecting another school's row 43.
DELETE `publishresult`
FROM `publishresult`
WHERE `publishresult`.`id` = 43
  AND `publishresult`.`Session` = 10
  AND `publishresult`.`Term` = '3rd'
  AND `publishresult`.`ClassID` = 8
  AND `publishresult`.`SectionID` = 18
  AND TRIM(`publishresult`.`ResultType`) = ''
  AND `publishresult`.`Date` = '2026-07-23'
  AND EXISTS (
      SELECT 1
      FROM `sch_settings`
      WHERE LOWER(TRIM(`sch_settings`.`name`)) = 'hameed academy'
        AND LOWER(TRIM(`sch_settings`.`email`)) = 'info@hameedacademy.com.ng'
  );

COMMIT;

-- Audit only: this result set should normally be empty. Review any returned
-- row manually; do not automatically reinterpret an unknown type as cumulative.
SELECT
    `id`,
    `Session`,
    `Term`,
    `ClassID`,
    `SectionID`,
    `ResultType`,
    `Date`
FROM `publishresult`
WHERE TRIM(`ResultType`) = ''
   OR LOWER(TRIM(`ResultType`)) NOT IN (
       'midterm',
       'mid-term',
       'termly',
       'cummulative',
       'cumulative'
   )
ORDER BY `Session`, `ClassID`, `SectionID`, `id`;
