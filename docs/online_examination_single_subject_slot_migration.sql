-- SchoolLift Online Examination: one subject-paper academic slot (migration 138).
-- Select one school database in phpMyAdmin, then run/import the whole file.
-- Apply after the existing Online Examination migrations through 137.
-- Additive and rerunnable: no assessment, question, attempt or result is deleted.
-- Existing duplicates remain readable; the oldest assessment reserves the slot.
-- This file does not advance the CodeIgniter migrations ledger.
SELECT DATABASE() AS selected_school_database;

SET @onlineexam_138_ready := DATABASE() IS NOT NULL
  AND EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'onlineexam'
  )
  AND EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'onlineexam_class_sections'
  )
  AND EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'onlineexam'
      AND COLUMN_NAME = 'workflow_version'
  )
  AND EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'onlineexam'
      AND COLUMN_NAME = 'deleted_at'
  );

SET @onlineexam_138_sql := IF(
  @onlineexam_138_ready,
  'CREATE TABLE IF NOT EXISTS onlineexam_academic_slots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    onlineexam_id INT NOT NULL,
    session_id INT NOT NULL,
    term VARCHAR(10) NOT NULL,
    class_id INT NOT NULL,
    section_id INT NOT NULL,
    assessment_type VARCHAR(24) NOT NULL,
    component_key VARCHAR(32) NOT NULL,
    subject_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY onlineexam_academic_slot_unique
      (session_id,term,class_id,section_id,assessment_type,component_key,subject_id),
    UNIQUE KEY onlineexam_academic_slot_exam_section_unique
      (onlineexam_id,section_id),
    KEY onlineexam_academic_slot_exam_idx (onlineexam_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
  'SELECT * FROM SCHOOLLIFT_138_INSTALL_EXISTING_ONLINE_EXAM_MIGRATIONS_FIRST'
);
PREPARE onlineexam_138_stmt FROM @onlineexam_138_sql;
EXECUTE onlineexam_138_stmt;
DEALLOCATE PREPARE onlineexam_138_stmt;

SET @onlineexam_138_deleted_filter := IF(
  EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'onlineexam'
      AND COLUMN_NAME = 'deleted_at'
  ),
  ' AND e.deleted_at IS NULL',
  ''
);

SET @onlineexam_138_sql := IF(
  @onlineexam_138_ready,
  CONCAT(
    'INSERT IGNORE INTO onlineexam_academic_slots
      (onlineexam_id,session_id,term,class_id,section_id,assessment_type,component_key,subject_id,created_at,updated_at)
     SELECT e.id,e.session_id,LOWER(e.term),e.class_id,ecs.section_id,
       CASE WHEN e.purpose IN (''ca'',''kindergarten'') THEN ''term''
            WHEN e.purpose = ''midterm'' THEN ''midterm''
            WHEN e.purpose = ''holiday'' THEN ''holiday'' END,
       COALESCE(NULLIF(LOWER(e.target_component),''''),LOWER(e.purpose)),
       e.subject_id,NOW(),NOW()
     FROM onlineexam e
     INNER JOIN onlineexam_class_sections ecs ON ecs.onlineexam_id=e.id
     WHERE e.workflow_version=2
       AND e.session_id IS NOT NULL AND e.class_id IS NOT NULL
       AND e.subject_id IS NOT NULL
       AND LOWER(e.term) IN (''1st'',''2nd'',''3rd'')
       AND e.purpose IN (''ca'',''midterm'',''holiday'',''kindergarten'')
       AND COALESCE(NULLIF(LOWER(e.target_component),''''),LOWER(e.purpose))<>''''',
    @onlineexam_138_deleted_filter,
    ' ORDER BY e.id,ecs.section_id'
  ),
  'SELECT 1 AS onlineexam_138_backfill_skipped'
);
PREPARE onlineexam_138_stmt FROM @onlineexam_138_sql;
EXECUTE onlineexam_138_stmt;
DEALLOCATE PREPARE onlineexam_138_stmt;

-- A single editable paper always contributes the complete selected component.
SET @onlineexam_138_sql := IF(
  @onlineexam_138_ready
  AND EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'onlineexam_papers'
  )
  AND EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'onlineexam_candidate_attempts'
  ),
  'UPDATE onlineexam_papers p
   INNER JOIN onlineexam e ON e.id=p.onlineexam_id
   INNER JOIN (
     SELECT onlineexam_id FROM onlineexam_papers
     GROUP BY onlineexam_id HAVING COUNT(*)=1
   ) one_paper ON one_paper.onlineexam_id=e.id
   SET p.contribution_score=100.00,p.updated_at=NOW()
   WHERE e.workflow_version=2 AND e.lifecycle_status=''draft''
     AND e.frozen_at IS NULL
     AND NOT EXISTS (
       SELECT 1 FROM onlineexam_candidate_attempts a
       WHERE a.onlineexam_id=e.id AND a.revision=e.revision
     )',
  'SELECT 1 AS onlineexam_138_single_paper_alignment_skipped'
);
PREPARE onlineexam_138_stmt FROM @onlineexam_138_sql;
EXECUTE onlineexam_138_stmt;
DEALLOCATE PREPARE onlineexam_138_stmt;

-- This result set lists pre-existing overlapping assessments. Nothing is
-- deleted: the smallest onlineexam_id owns each duplicated academic slot.
SELECT
  e.id AS conflicting_onlineexam_id,
  e.exam,
  e.session_id,
  e.term,
  e.class_id,
  ecs.section_id,
  e.subject_id,
  e.purpose,
  e.target_component
FROM onlineexam e
INNER JOIN onlineexam_class_sections ecs ON ecs.onlineexam_id=e.id
LEFT JOIN onlineexam_academic_slots slot
  ON slot.onlineexam_id=e.id AND slot.section_id=ecs.section_id
WHERE @onlineexam_138_ready
  AND e.workflow_version=2
  AND slot.id IS NULL
  AND e.deleted_at IS NULL
ORDER BY e.id,ecs.section_id;

SELECT
  CASE WHEN EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='onlineexam_academic_slots'
  ) THEN 'OK: migration 138 academic slots are installed.'
    ELSE 'FAILED: migration 138 academic slots were not installed.'
  END AS migration_status;
