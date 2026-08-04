<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_monnify_payments extends CI_Migration
{
    public function up()
    {
        if ($this->db->table_exists('monnify_payments')) {
            return;
        }

        $this->db->query("CREATE TABLE `monnify_payments` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `payment_reference` varchar(100) NOT NULL,
            `transaction_reference` varchar(150) DEFAULT NULL,
            `payment_context` varchar(30) NOT NULL,
            `context_id` int NOT NULL,
            `amount` decimal(15,2) NOT NULL,
            `currency` varchar(10) NOT NULL DEFAULT 'NGN',
            `customer_email` varchar(191) NOT NULL,
            `customer_name` varchar(191) NOT NULL,
            `context_data` longtext NOT NULL,
            `gateway_mode` tinyint(1) NOT NULL DEFAULT '0',
            `gateway_response` longtext,
            `status` varchar(30) NOT NULL DEFAULT 'pending',
            `processing_started_at` datetime DEFAULT NULL,
            `paid_at` datetime DEFAULT NULL,
            `processed_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_monnify_payment_reference` (`payment_reference`),
            UNIQUE KEY `uq_monnify_transaction_reference` (`transaction_reference`),
            KEY `idx_monnify_status` (`status`),
            KEY `idx_monnify_context` (`payment_context`, `context_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");
    }

    public function down()
    {
        $this->dbforge->drop_table('monnify_payments', true);
    }
}
