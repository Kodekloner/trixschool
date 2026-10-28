-- Optional manual installation if the application's database account cannot CREATE tables.
-- Run in each school's own database. This does not copy legacy provider credentials.
-- If CodeIgniter dbprefix is configured, prepend that prefix to the table name below.
CREATE TABLE IF NOT EXISTS `whatsapp_twilio_config` (
  `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  `settings_json` MEDIUMTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
