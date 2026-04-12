<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_incoming_emails extends CI_Migration
{
    public function up()
    {
        if ($this->db->table_exists('incoming_emails')) {
            return;
        }

        $this->dbforge->add_field(array(
            'id' => array(
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ),
            'sns_type' => array(
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ),
            'sns_message_id' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ),
            'sns_topic_arn' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ),
            'ses_notification_type' => array(
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ),
            'ses_message_id' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ),
            'source' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ),
            'subject' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ),
            'destinations_json' => array(
                'type' => 'LONGTEXT',
                'null' => true,
            ),
            'recipients_json' => array(
                'type' => 'LONGTEXT',
                'null' => true,
            ),
            'headers_json' => array(
                'type' => 'LONGTEXT',
                'null' => true,
            ),
            'mail_timestamp' => array(
                'type' => 'DATETIME',
                'null' => true,
            ),
            'receipt_timestamp' => array(
                'type' => 'DATETIME',
                'null' => true,
            ),
            'action_type' => array(
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ),
            's3_bucket' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ),
            's3_object_key' => array(
                'type' => 'TEXT',
                'null' => true,
            ),
            'spam_status' => array(
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ),
            'virus_status' => array(
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ),
            'dkim_status' => array(
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ),
            'spf_status' => array(
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ),
            'dmarc_status' => array(
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ),
            'body_text' => array(
                'type' => 'LONGTEXT',
                'null' => true,
            ),
            'body_html' => array(
                'type' => 'LONGTEXT',
                'null' => true,
            ),
            'attachment_count' => array(
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ),
            'attachment_names_json' => array(
                'type' => 'LONGTEXT',
                'null' => true,
            ),
            'raw_content' => array(
                'type' => 'LONGTEXT',
                'null' => true,
            ),
            'raw_payload' => array(
                'type' => 'LONGTEXT',
                'null' => true,
            ),
            'status' => array(
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'received',
            ),
            'error_message' => array(
                'type' => 'TEXT',
                'null' => true,
            ),
            'created_at' => array(
                'type' => 'DATETIME',
                'null' => false,
            ),
            'updated_at' => array(
                'type' => 'DATETIME',
                'null' => false,
            ),
        ));

        $this->dbforge->add_key('id', true);
        $this->dbforge->add_key('sns_message_id');
        $this->dbforge->add_key('ses_message_id');
        $this->dbforge->create_table('incoming_emails', true);
    }

    public function down()
    {
        $this->dbforge->drop_table('incoming_emails', true);
    }
}

