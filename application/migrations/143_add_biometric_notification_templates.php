<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Editable, channel-aware guardian notifications for biometric attendance.
 *
 * Existing attendance channel choices are copied into the new IN and OUT
 * templates. The new fee reminder is deliberately disabled until a school
 * reviews its wording and enables the required channels.
 */
class Migration_Add_biometric_notification_templates extends CI_Migration
{
    public function up()
    {
        $this->addColumn('notification_setting', 'is_whatsapp', array(
            'type' => 'VARCHAR', 'constraint' => 10, 'default' => '0',
        ));
        $this->addColumn('notification_setting', 'display_whatsapp', array(
            'type' => 'INT', 'constraint' => 11, 'default' => 0,
        ));
        $this->addColumn('biometric_notification_queue', 'notification_type', array(
            'type' => 'VARCHAR', 'constraint' => 40, 'default' => 'attendance_in',
            'after' => 'event_id',
        ));

        if ($this->db->table_exists('biometric_notification_queue')
            && $this->db->table_exists('biometric_events')) {
            $this->db->query("UPDATE `biometric_notification_queue` AS queue_row
                INNER JOIN `biometric_events` AS event_row ON event_row.`id` = queue_row.`event_id`
                SET queue_row.`notification_type` = CASE
                    WHEN event_row.`direction` = 'OUT' THEN 'attendance_out'
                    ELSE 'attendance_in'
                END
                WHERE queue_row.`notification_type` IS NULL
                   OR queue_row.`notification_type` = ''
                   OR queue_row.`notification_type` = 'attendance_in'");
            $this->dropIndex('biometric_notification_queue', 'uq_biometric_notification_event_channel');
            $this->addIndex(
                'biometric_notification_queue',
                'uq_biometric_notification_event_type_channel',
                '`event_id`, `notification_type`, `channel`',
                true
            );
        }

        $channelDefaults = array('is_mail' => '0', 'is_sms' => '0', 'is_whatsapp' => '0');
        if ($this->db->table_exists('biometric_settings')) {
            $settings = $this->db->select('notify_email, notify_sms, notify_whatsapp')
                ->where('id', 1)->get('biometric_settings')->row_array();
            if ($settings) {
                $channelDefaults = array(
                    'is_mail' => empty($settings['notify_email']) ? '0' : '1',
                    'is_sms' => empty($settings['notify_sms']) ? '0' : '1',
                    'is_whatsapp' => empty($settings['notify_whatsapp']) ? '0' : '1',
                );
            }
        }

        $attendanceVariables = '{{guardian_name}} {{student_name}} {{admission_no}} {{attendance_direction}} '
            . '{{attendance_action}} {{attendance_time}} {{attendance_date}} {{school_name}}';
        $feeVariables = '{{guardian_name}} {{student_name}} {{admission_no}} {{outstanding_amount}} '
            . '{{currency_symbol}} {{fee_item_count}} {{session_name}} {{school_name}}';

        $this->insertTemplateIfMissing(array_merge($channelDefaults, array(
            'type' => 'biometric_attendance_in',
            'is_notification' => 0,
            'display_notification' => 0,
            'display_sms' => 1,
            'display_whatsapp' => 1,
            'subject' => 'Student check-in - {{school_name}}',
            'template_id' => '',
            'template' => 'Dear {{guardian_name}}, {{student_name}} ({{admission_no}}) checked in at {{attendance_time}} on {{attendance_date}} at {{school_name}}.',
            'variables' => $attendanceVariables,
        )));
        $this->insertTemplateIfMissing(array_merge($channelDefaults, array(
            'type' => 'biometric_attendance_out',
            'is_notification' => 0,
            'display_notification' => 0,
            'display_sms' => 1,
            'display_whatsapp' => 1,
            'subject' => 'Student checkout - {{school_name}}',
            'template_id' => '',
            'template' => 'Dear {{guardian_name}}, {{student_name}} ({{admission_no}}) checked out at {{attendance_time}} on {{attendance_date}} at {{school_name}}.',
            'variables' => $attendanceVariables,
        )));
        $this->insertTemplateIfMissing(array(
            'type' => 'biometric_fees_due',
            'is_mail' => '0',
            'is_sms' => '0',
            'is_whatsapp' => '0',
            'is_notification' => 0,
            'display_notification' => 0,
            'display_sms' => 1,
            'display_whatsapp' => 1,
            'subject' => 'Outstanding fees for {{student_name}} - {{school_name}}',
            'template_id' => '',
            'template' => 'Dear {{guardian_name}}, our fees record shows an outstanding balance of {{currency_symbol}}{{outstanding_amount}} across {{fee_item_count}} fee item(s) for {{student_name}} ({{admission_no}}) in {{session_name}}. Please ignore this message if payment has just been made or contact {{school_name}} for clarification.',
            'variables' => $feeVariables,
        ));
    }

    public function down()
    {
        if ($this->db->table_exists('biometric_notification_queue')) {
            if ($this->db->field_exists('notification_type', 'biometric_notification_queue')) {
                $this->db->where('notification_type', 'biometric_fees_due')->delete('biometric_notification_queue');
            }
            $this->dropIndex('biometric_notification_queue', 'uq_biometric_notification_event_type_channel');
            $this->addIndex(
                'biometric_notification_queue',
                'uq_biometric_notification_event_channel',
                '`event_id`, `channel`',
                true
            );
            if ($this->db->field_exists('notification_type', 'biometric_notification_queue')) {
                $this->dbforge->drop_column('biometric_notification_queue', 'notification_type');
            }
        }
        if ($this->db->table_exists('notification_setting')) {
            $this->db->where_in('type', array(
                'biometric_attendance_in', 'biometric_attendance_out', 'biometric_fees_due',
            ))->delete('notification_setting');
            foreach (array('is_whatsapp', 'display_whatsapp') as $field) {
                if ($this->db->field_exists($field, 'notification_setting')) {
                    $this->dbforge->drop_column('notification_setting', $field);
                }
            }
        }
    }

    private function insertTemplateIfMissing(array $data)
    {
        if (!$this->db->table_exists('notification_setting')
            || $this->db->where('type', $data['type'])->count_all_results('notification_setting')) {
            return;
        }
        $this->db->insert('notification_setting', $data);
    }

    private function addColumn($table, $field, array $definition)
    {
        if ($this->db->table_exists($table) && !$this->db->field_exists($field, $table)) {
            $this->dbforge->add_column($table, array($field => $definition));
        }
    }

    private function addIndex($table, $name, $columns, $unique = false)
    {
        if (!$this->db->table_exists($table) || $this->indexExists($table, $name)) {
            return;
        }
        $this->db->query('ALTER TABLE `' . $table . '` ADD '
            . ($unique ? 'UNIQUE ' : '') . 'KEY `' . $name . '` (' . $columns . ')');
    }

    private function dropIndex($table, $name)
    {
        if ($this->db->table_exists($table) && $this->indexExists($table, $name)) {
            $this->db->query('ALTER TABLE `' . $table . '` DROP INDEX `' . $name . '`');
        }
    }

    private function indexExists($table, $name)
    {
        return (bool) $this->db->query(
            'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            array($table, $name)
        )->row_array();
    }
}
