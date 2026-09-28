-- Glory International School (gis.schoollift.com.ng) biometric go-live.
-- Prepared from the trixschool_gis dump generated on 2026-09-28 15:14 UTC.
--
-- This is a one-school operational data migration, not a numbered application
-- schema migration. Import it only into `trixschool_gis`, after taking and
-- verifying a fresh backup. It is intentionally rerunnable and does not alter
-- the CodeIgniter `migrations` ledger.
--
-- Outcome:
--   * retain the accepted physical student Shadow IN/OUT evidence;
--   * correct the one active legacy free-form identity to Admission No. GIS369;
--   * ignore the three known, non-Live commissioning exceptions with an audit;
--   * enable Live student projection with notifications off;
--   * keep staff projection off because the dump has no staff mappings or
--     completed staff Shadow session;
--   * restore the pre-test 08:00 student/staff lateness thresholds.

SELECT DATABASE() AS `selected_database_before_import`;

DELIMITER $$

DROP PROCEDURE IF EXISTS `gis_biometric_go_live_20260928`$$
CREATE PROCEDURE `gis_biometric_go_live_20260928`()
BEGIN
    DECLARE v_count BIGINT DEFAULT 0;
    DECLARE v_actor_id INT DEFAULT 1;
    DECLARE v_now DATETIME;
    DECLARE v_mapping_before VARCHAR(100) DEFAULT NULL;
    DECLARE v_settings_before LONGTEXT DEFAULT NULL;
    DECLARE v_settings_after LONGTEXT DEFAULT NULL;
    DECLARE v_settings_change TINYINT DEFAULT 0;

    IF DATABASE() <> 'trixschool_gis' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'STOP: select the trixschool_gis database before importing this file.';
    END IF;

    SELECT COUNT(*) INTO v_count
    FROM `INFORMATION_SCHEMA`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND (
        (`TABLE_NAME` = 'biometric_settings' AND `COLUMN_NAME` IN (
          'mode', 'project_students', 'project_staff', 'live_pilot_enabled',
          'notify_student_in', 'notify_student_out', 'notify_email',
          'notify_sms', 'notify_whatsapp', 'live_acknowledged_by',
          'live_acknowledged_at'
        ))
        OR (`TABLE_NAME` = 'biometric_identity_mappings' AND `COLUMN_NAME` = 'live_pilot')
      );
    IF v_count <> 12 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'STOP: biometric migration 140 is incomplete in this database.';
    END IF;

    SELECT COUNT(*) INTO v_count
    FROM `biometric_settings`
    WHERE `id` = 1 AND `mode` IN ('shadow', 'live');
    IF v_count <> 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'STOP: the GIS biometric settings row is missing or is not in Shadow/Live.';
    END IF;

    SELECT COUNT(*) INTO v_count
    FROM `sch_settings`
    WHERE `attendence_type` = 0;
    IF v_count <> 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'STOP: GIS is not configured for daily student attendance projection.';
    END IF;

    SELECT COUNT(*) INTO v_count
    FROM `biometric_integrations`
    WHERE `is_active` = 1;
    IF v_count <> 1 OR NOT EXISTS (
        SELECT 1 FROM `biometric_integrations`
        WHERE `id` = 2
          AND `name` = 'GIS School SenseFace 2A'
          AND `provider` = 'zkbio_time'
          AND `is_active` = 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'STOP: the expected single active GIS ZKBio integration was not found.';
    END IF;

    SELECT COUNT(*) INTO v_count
    FROM `biometric_devices`
    WHERE `is_active` = 1 AND `is_virtual` = 0 AND `device_type` = 'biometric';
    IF v_count <> 1 OR NOT EXISTS (
        SELECT 1 FROM `biometric_devices`
        WHERE `id` = 2
          AND `integration_id` = 2
          AND `serial_number` = 'NYU7261300059'
          AND `direction_mode` = 'bidirectional'
          AND `is_virtual` = 0
          AND `is_active` = 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'STOP: the expected single active GIS SenseFace terminal was not found.';
    END IF;

    SELECT COUNT(DISTINCT `direction`) INTO v_count
    FROM `biometric_punch_state_mappings`
    WHERE `integration_id` = 2
      AND ((`raw_punch_state` = '0' AND `direction` = 'IN')
        OR (`raw_punch_state` = '1' AND `direction` = 'OUT'));
    IF v_count <> 2 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'STOP: GIS must retain punch state 0=IN and 1=OUT.';
    END IF;

    SELECT COUNT(*) INTO v_count
    FROM `biometric_attendance_days` AS d
    INNER JOIN `biometric_events` AS ein
        ON ein.`attendance_day_id` = d.`id`
       AND ein.`integration_id` = 2
       AND ein.`device_id` = 2
       AND ein.`source` = 'gateway'
       AND ein.`operating_mode` = 'shadow'
       AND ein.`processing_status` = 'accepted'
       AND ein.`direction` = 'IN'
    INNER JOIN `biometric_events` AS eout
        ON eout.`attendance_day_id` = d.`id`
       AND eout.`integration_id` = 2
       AND eout.`device_id` = 2
       AND eout.`source` = 'gateway'
       AND eout.`operating_mode` = 'shadow'
       AND eout.`processing_status` = 'accepted'
       AND eout.`direction` = 'OUT'
    WHERE d.`id` = 1
      AND d.`subject_type` = 'student'
      AND d.`subject_id` = 21
      AND d.`record_scope` = 'shadow'
      AND d.`first_in_at` IS NOT NULL
      AND d.`last_out_at` IS NOT NULL
      AND d.`missing_checkout` = 0
      AND d.`official_attendance_id` IS NULL
      AND d.`official_table` IS NULL;
    IF v_count < 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'STOP: the accepted physical student Shadow IN/OUT proof is missing.';
    END IF;

    SELECT COUNT(*) INTO v_count
    FROM `student_attendences`
    WHERE `biometric_day_id` = 1;
    IF v_count <> 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'STOP: the Shadow proof is unexpectedly linked to official student attendance.';
    END IF;

    SELECT COUNT(*) INTO v_count
    FROM `student_session` AS ss
    INNER JOIN `students` AS s ON s.`id` = ss.`student_id`
    INNER JOIN `biometric_identity_mappings` AS m
        ON m.`subject_type` = 'student' AND m.`subject_id` = ss.`id`
    WHERE ss.`id` = 75
      AND s.`admission_no` = 'GIS369'
      AND m.`id` = 2
      AND m.`external_person_code` IN ('1', 'GIS369')
      AND m.`is_active` = 1;
    IF v_count <> 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'STOP: the reviewed legacy GIS369 mapping no longer matches the supplied snapshot.';
    END IF;

    SELECT COUNT(*) INTO v_count
    FROM `biometric_identity_mappings`
    WHERE `id` <> 2 AND UPPER(TRIM(`external_person_code`)) = 'GIS369';
    IF v_count <> 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'STOP: GIS369 is already reserved by another biometric mapping.';
    END IF;

    SELECT COUNT(*) INTO v_count
    FROM `student_session` AS ss
    INNER JOIN `students` AS s ON s.`id` = ss.`student_id`
    INNER JOIN `sch_settings` AS school ON school.`session_id` = ss.`session_id`
    INNER JOIN `biometric_identity_mappings` AS m
        ON m.`subject_type` = 'student'
       AND m.`subject_id` = ss.`id`
       AND m.`is_active` = 1
    WHERE m.`id` <> 2
      AND UPPER(TRIM(m.`external_person_code`)) <> UPPER(TRIM(s.`admission_no`));
    IF v_count <> 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'STOP: another active student mapping differs from its Admission No.';
    END IF;

    SELECT COUNT(*) INTO v_count
    FROM `biometric_exceptions` AS x
    INNER JOIN `biometric_events` AS e ON e.`id` = x.`event_id`
    WHERE (x.`id` IN (1, 2)
           AND x.`exception_code` = 'UNKNOWN_PUNCH_STATE'
           AND e.`raw_punch_state` = '255'
           AND e.`operating_mode` = 'shadow')
       OR (x.`id` = 3
           AND x.`exception_code` = 'UNKNOWN_PERSON'
           AND e.`person_code` = '10'
           AND e.`operating_mode` = 'shadow');
    IF v_count <> 3 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'STOP: the three reviewed commissioning exceptions no longer match the snapshot.';
    END IF;

    SELECT COUNT(*) INTO v_count
    FROM `biometric_exceptions`
    WHERE `status` = 'open' AND `id` NOT IN (1, 2, 3);
    IF v_count <> 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'STOP: new open biometric exceptions require review before go-live.';
    END IF;

    SET v_now = UTC_TIMESTAMP();
    START TRANSACTION;

    SELECT `external_person_code` INTO v_mapping_before
    FROM `biometric_identity_mappings`
    WHERE `id` = 2
    FOR UPDATE;

    IF v_mapping_before = '1' THEN
        INSERT INTO `biometric_audit_logs`
            (`actor_id`, `action`, `entity_type`, `entity_id`, `before_json`,
             `after_json`, `ip_address`, `created_at`)
        VALUES
            (v_actor_id, 'mapping.updated', 'mapping', '2',
             JSON_OBJECT('id', 2, 'subject_type', 'student', 'subject_id', 75,
                         'external_person_code', v_mapping_before, 'is_active', 1),
             JSON_OBJECT('id', 2, 'subject_type', 'student', 'subject_id', 75,
                         'external_person_code', 'GIS369', 'is_active', 1),
             NULL, v_now);

        UPDATE `biometric_identity_mappings`
        SET `external_person_code` = 'GIS369',
            `updated_by` = v_actor_id,
            `updated_at` = v_now
        WHERE `id` = 2 AND `external_person_code` = '1';
    END IF;

    INSERT INTO `biometric_reconciliation_actions`
        (`exception_id`, `action`, `details_json`, `actor_id`, `created_at`)
    SELECT x.`id`, 'ignore',
           JSON_OBJECT('note', CASE
             WHEN x.`id` IN (1, 2) THEN
               'Commissioning test: ZKBio returned raw state 255 (Unknown). Direction cannot be recovered; terminal was later proved with 0=IN and 1=OUT.'
             ELSE
               'Commissioning test: ZKBio identity 10 did not match a SchoolLift Admission No. A later physical student IN/OUT was accepted with the canonical identity.'
           END),
           v_actor_id, v_now
    FROM `biometric_exceptions` AS x
    WHERE x.`id` IN (1, 2, 3) AND x.`status` = 'open';

    INSERT INTO `biometric_audit_logs`
        (`actor_id`, `action`, `entity_type`, `entity_id`, `before_json`,
         `after_json`, `ip_address`, `created_at`)
    SELECT v_actor_id, 'exception.ignore', 'exception', CAST(x.`id` AS CHAR),
           JSON_OBJECT('id', x.`id`, 'event_id', x.`event_id`,
                       'exception_code', x.`exception_code`, 'status', x.`status`),
           JSON_OBJECT('id', x.`id`, 'event_id', x.`event_id`,
                       'exception_code', x.`exception_code`, 'status', 'ignored',
                       'resolution_action', 'ignore'),
           NULL, v_now
    FROM `biometric_exceptions` AS x
    WHERE x.`id` IN (1, 2, 3) AND x.`status` = 'open';

    UPDATE `biometric_exceptions`
    SET `status` = 'ignored',
        `resolution_action` = 'ignore',
        `resolution_note` = CASE
          WHEN `id` IN (1, 2) THEN
            'Commissioning test: ZKBio returned raw state 255 (Unknown). Direction cannot be recovered; terminal was later proved with 0=IN and 1=OUT.'
          ELSE
            'Commissioning test: ZKBio identity 10 did not match a SchoolLift Admission No. A later physical student IN/OUT was accepted with the canonical identity.'
        END,
        `resolved_by` = v_actor_id,
        `resolved_at` = v_now,
        `updated_at` = v_now
    WHERE `id` IN (1, 2, 3) AND `status` = 'open';

    SELECT JSON_OBJECT(
        'id', `id`, 'mode', `mode`, 'timezone', `timezone`,
        'student_late_after', `student_late_after`,
        'staff_late_after', `staff_late_after`,
        'project_students', `project_students`,
        'project_staff', `project_staff`,
        'live_pilot_enabled', `live_pilot_enabled`,
        'notify_student_in', `notify_student_in`,
        'notify_student_out', `notify_student_out`,
        'notify_email', `notify_email`, 'notify_sms', `notify_sms`,
        'notify_whatsapp', `notify_whatsapp`
    ) INTO v_settings_before
    FROM `biometric_settings`
    WHERE `id` = 1
    FOR UPDATE;

    SELECT (`mode` <> 'live'
        OR `student_late_after` <> '08:00:00'
        OR `staff_late_after` <> '08:00:00'
        OR `project_students` <> 1
        OR `project_staff` <> 0
        OR `live_pilot_enabled` <> 0
        OR `notify_student_in` <> 0
        OR `notify_student_out` <> 0
        OR `notify_email` <> 0
        OR `notify_sms` <> 0
        OR `notify_whatsapp` <> 0)
    INTO v_settings_change
    FROM `biometric_settings`
    WHERE `id` = 1;

    IF v_settings_change = 1 THEN
        UPDATE `biometric_settings`
        SET `mode` = 'live',
            `student_late_after` = '08:00:00',
            `staff_late_after` = '08:00:00',
            `project_students` = 1,
            `project_staff` = 0,
            `live_pilot_enabled` = 0,
            `notify_student_in` = 0,
            `notify_student_out` = 0,
            `notify_email` = 0,
            `notify_sms` = 0,
            `notify_whatsapp` = 0,
            `live_acknowledged_by` = v_actor_id,
            `live_acknowledged_at` = v_now,
            `updated_by` = v_actor_id,
            `updated_at` = v_now
        WHERE `id` = 1;

        SELECT JSON_OBJECT(
            'id', `id`, 'mode', `mode`, 'timezone', `timezone`,
            'student_late_after', `student_late_after`,
            'staff_late_after', `staff_late_after`,
            'project_students', `project_students`,
            'project_staff', `project_staff`,
            'live_pilot_enabled', `live_pilot_enabled`,
            'notify_student_in', `notify_student_in`,
            'notify_student_out', `notify_student_out`,
            'notify_email', `notify_email`, 'notify_sms', `notify_sms`,
            'notify_whatsapp', `notify_whatsapp`,
            'live_acknowledged_by', `live_acknowledged_by`,
            'live_acknowledged_at', `live_acknowledged_at`
        ) INTO v_settings_after
        FROM `biometric_settings`
        WHERE `id` = 1;

        INSERT INTO `biometric_audit_logs`
            (`actor_id`, `action`, `entity_type`, `entity_id`, `before_json`,
             `after_json`, `ip_address`, `created_at`)
        VALUES
            (v_actor_id, 'settings.updated', 'settings', '1',
             v_settings_before, v_settings_after, NULL, v_now);
    END IF;

    COMMIT;
END$$

CALL `gis_biometric_go_live_20260928`()$$
DROP PROCEDURE `gis_biometric_go_live_20260928`$$

DELIMITER ;

-- Verification. Expected values:
-- mode=live, project_students=1, project_staff=0, all notifications=0,
-- open_exceptions=0, active_staff_mappings=0, canonical_mapping=GIS369.
SELECT
    `mode`, `timezone`, `student_late_after`, `staff_late_after`,
    `project_students`, `project_staff`, `live_pilot_enabled`,
    `notify_student_in`, `notify_student_out`, `notify_email`, `notify_sms`,
    `notify_whatsapp`, `live_acknowledged_by`, `live_acknowledged_at`
FROM `biometric_settings`
WHERE `id` = 1;

SELECT
    (SELECT COUNT(*) FROM `biometric_exceptions` WHERE `status` = 'open')
        AS `open_exceptions`,
    (SELECT COUNT(*) FROM `biometric_identity_mappings`
      WHERE `subject_type` = 'staff' AND `is_active` = 1)
        AS `active_staff_mappings`,
    (SELECT `external_person_code` FROM `biometric_identity_mappings` WHERE `id` = 2)
        AS `canonical_mapping`,
    (SELECT COUNT(*) FROM `biometric_events`
      WHERE `operating_mode` = 'shadow' AND `processing_status` = 'accepted')
        AS `retained_accepted_shadow_events`,
    (SELECT COUNT(*) FROM `biometric_gateway_agents`
      WHERE `integration_id` = 2 AND `provider_reachable` = 1
        AND `last_sync_ok` = 1 AND `queue_pending` = 0
        AND `queue_retry` = 0 AND `queue_dead` = 0)
        AS `healthy_clear_connector_records`;

SELECT
    `gateway_id`, `gateway_version`, `provider_reachable`, `last_sync_ok`,
    `last_sync_at`, `queue_pending`, `queue_retry`, `queue_dead`,
    `last_heartbeat_at`
FROM `biometric_gateway_agents`
WHERE `integration_id` = 2;
