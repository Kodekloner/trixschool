-- Support ticket migration for AWS SES inbound email.
-- Run this once per school database in phpMyAdmin if you are not using CodeIgniter migrations.

CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ticket_number` varchar(50) NOT NULL,
  `source` varchar(50) NOT NULL DEFAULT 'email',
  `requester_name` varchar(191) DEFAULT NULL,
  `requester_email` varchar(191) NOT NULL,
  `requester_phone` varchar(50) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'open',
  `priority` varchar(30) NOT NULL DEFAULT 'normal',
  `assigned_staff_id` int DEFAULT NULL,
  `incoming_email_id` int unsigned DEFAULT NULL,
  `last_incoming_email_id` int unsigned DEFAULT NULL,
  `message_id` varchar(255) DEFAULT NULL,
  `last_outgoing_message_id` varchar(255) DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `last_customer_message_at` datetime DEFAULT NULL,
  `last_staff_message_at` datetime DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_tickets_ticket_number_unique` (`ticket_number`),
  KEY `status` (`status`),
  KEY `requester_email` (`requester_email`),
  KEY `last_message_at` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `support_messages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_id` int unsigned NOT NULL,
  `incoming_email_id` int unsigned DEFAULT NULL,
  `direction` varchar(20) NOT NULL,
  `sender_type` varchar(20) NOT NULL,
  `sender_staff_id` int DEFAULT NULL,
  `sender_name` varchar(191) DEFAULT NULL,
  `sender_email` varchar(191) DEFAULT NULL,
  `recipients_json` longtext,
  `subject` varchar(255) DEFAULT NULL,
  `body_text` longtext,
  `body_html` longtext,
  `message_id` varchar(255) DEFAULT NULL,
  `in_reply_to` varchar(255) DEFAULT NULL,
  `references_header` text,
  `attachment_count` int NOT NULL DEFAULT 0,
  `attachment_names_json` longtext,
  `delivery_status` varchar(50) NOT NULL DEFAULT 'received',
  `error_message` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `support_ticket_id` (`support_ticket_id`),
  KEY `incoming_email_id` (`incoming_email_id`),
  KEY `message_id` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `permission_group` (`name`, `short_code`, `is_active`, `system`, `created_at`)
SELECT 'Front Office', 'front_office', 1, 0, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permission_group` WHERE `short_code` = 'front_office'
);

SET @support_front_office_group_id := (
    SELECT `id` FROM `permission_group` WHERE `short_code` = 'front_office' LIMIT 1
);

INSERT INTO `permission_category` (`perm_group_id`, `name`, `short_code`, `enable_view`, `enable_add`, `enable_edit`, `enable_delete`, `created_at`)
SELECT @support_front_office_group_id, 'Support Tickets', 'support_ticket', 1, 1, 1, 1, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permission_category` WHERE `short_code` = 'support_ticket'
);

SET @support_ticket_perm_id := (
    SELECT `id` FROM `permission_category` WHERE `short_code` = 'support_ticket' LIMIT 1
);

INSERT INTO `roles_permissions` (`role_id`, `perm_cat_id`, `can_view`, `can_add`, `can_edit`, `can_delete`, `created_at`)
SELECT `roles`.`id`, @support_ticket_perm_id, 1, 1, 1, 1, NOW()
FROM `roles`
WHERE `roles`.`name` IN ('Admin', 'Super Admin')
AND NOT EXISTS (
    SELECT 1
    FROM `roles_permissions`
    WHERE `roles_permissions`.`role_id` = `roles`.`id`
    AND `roles_permissions`.`perm_cat_id` = @support_ticket_perm_id
);
