<?php

/**
 * Destructive migration regression check, confined to a generated test database.
 * Run against an isolated MySQL/MariaDB server with networking disabled:
 * ONLINEEXAM_MIGRATION_TEST_SOCKET=/absolute/path/mysql.sock php tests/onlineexam_migration_136_test.php
 * The supplied socket must permit a passwordless local root connection.
 */
$socket = getenv('ONLINEEXAM_MIGRATION_TEST_SOCKET');
if (!$socket) {
    echo "SKIP migration 136 database tests: set ONLINEEXAM_MIGRATION_TEST_SOCKET to an isolated test server." . PHP_EOL;
    exit(0);
}
if ($socket[0] !== '/' || !file_exists($socket)) {
    throw new RuntimeException('The isolated test-server socket must be an existing absolute path.');
}

define('BASEPATH', __DIR__);

function migration136Assert($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// Run the actual migration class through small database/forge adapters. DDL is
// executed by MySQL, including field-cache invalidation and repeated invocations.
class Migration136Database
{
    public $pdo;
    public $data_cache = array();

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function table_exists($table)
    {
        $query = $this->pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $query->execute(array($table));
        return (int) $query->fetchColumn() > 0;
    }

    public function field_exists($column, $table)
    {
        if (!isset($this->data_cache['field_names'][$table])) {
            $query = $this->pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $query->execute(array($table));
            $this->data_cache['field_names'][$table] = $query->fetchAll(PDO::FETCH_COLUMN);
        }
        return in_array($column, $this->data_cache['field_names'][$table], true);
    }
}

class Migration136Forge
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function drop_column($table, $column)
    {
        migration136Assert($table === 'questions' && $column === 'level', 'The migration must not drop unrelated columns.');
        return $this->pdo->exec('ALTER TABLE `questions` DROP COLUMN `level`') !== false;
    }

    public function add_column($table, array $fields)
    {
        migration136Assert($table === 'questions' && array_keys($fields) === array('level'), 'Rollback must restore only the legacy column.');
        $field = $fields['level'];
        migration136Assert($field['type'] === 'VARCHAR' && $field['constraint'] === 10 && $field['null'] === false && $field['default'] === '', 'Rollback must not invent historic difficulty labels.');
        return $this->pdo->exec("ALTER TABLE `questions` ADD COLUMN `level` VARCHAR(10) NOT NULL DEFAULT '' AFTER `question_type`") !== false;
    }
}

class CI_Migration
{
    public $db;
    public $dbforge;

    public function __construct(PDO $pdo)
    {
        $this->db = new Migration136Database($pdo);
        $this->dbforge = new Migration136Forge($pdo);
    }
}

require_once __DIR__ . '/../application/migrations/136_remove_question_level.php';

function migration136RunSql(PDO $pdo, $sql)
{
    $query = $pdo->query($sql);
    do {
        $query->fetchAll(PDO::FETCH_ASSOC);
    } while ($query->nextRowset());
    $query->closeCursor();
}

function migration136PreservedData(PDO $pdo)
{
    $snapshot = array();
    foreach (array('questions', 'question_options', 'question_answers', 'onlineexam_question_snapshots', 'onlineexam_attempt_answers', 'score', 'academic_levels') as $table) {
        $columns = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_COLUMN);
        $columns = array_values(array_diff($columns, $table === 'questions' ? array('level') : array()));
        $snapshot[$table] = array(
            'columns' => $columns,
            'rows' => $pdo->query('SELECT `' . implode('`,`', $columns) . '` FROM `' . $table . '` ORDER BY `id`')->fetchAll(PDO::FETCH_ASSOC),
        );
    }
    return $snapshot;
}

$pdo = new PDO('mysql:unix_socket=' . $socket . ';charset=utf8mb4', 'root', '', array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
));
$database = 'question_level_136_test_' . bin2hex(random_bytes(6));
$pdo->exec('CREATE DATABASE `' . $database . '`');
try {
    $pdo->exec('USE `' . $database . '`');
    migration136RunSql($pdo, "
        CREATE TABLE questions (id INT PRIMARY KEY AUTO_INCREMENT, question_type VARCHAR(100) NOT NULL, level VARCHAR(10) NOT NULL, class_id INT NOT NULL, section_id INT NOT NULL, question TEXT, correct TEXT);
        INSERT INTO questions (question_type, level, class_id, section_id, question, correct) VALUES ('singlechoice', 'medium', 1, 2, '2 + 2?', 'opt_a'), ('descriptive', 'high', 2, 3, 'Explain evaporation.', 'Rubric');
        CREATE TABLE question_options (id INT PRIMARY KEY, question_id INT, value TEXT);
        INSERT INTO question_options VALUES (10, 1, '4');
        CREATE TABLE question_answers (id INT PRIMARY KEY, question_id INT, option_id INT);
        INSERT INTO question_answers VALUES (20, 1, 10);
        CREATE TABLE onlineexam_question_snapshots (id INT PRIMARY KEY, source_question_id INT, question_text TEXT, checksum VARCHAR(64));
        INSERT INTO onlineexam_question_snapshots VALUES (30, 1, '2 + 2?', 'frozen-checksum');
        CREATE TABLE onlineexam_attempt_answers (id INT PRIMARY KEY, question_snapshot_id INT, response_json TEXT, final_mark DECIMAL(10,2));
        INSERT INTO onlineexam_attempt_answers VALUES (40, 30, '{\"value\":\"opt_a\"}', 2.00);
        CREATE TABLE score (id INT PRIMARY KEY, exam DECIMAL(10,2));
        INSERT INTO score VALUES (50, 60.25);
        CREATE TABLE academic_levels (id INT PRIMARY KEY, level VARCHAR(50));
        INSERT INTO academic_levels VALUES (60, 'Primary');
    ");
    $before = migration136PreservedData($pdo);
    $standalone = file_get_contents(__DIR__ . '/../docs/online_examination_remove_question_level_migration.sql');
    $bundle = file_get_contents(__DIR__ . '/../docs/all_school_database_migrations.sql');
    $start = strpos($bundle, '-- Migration 136:');
    $end = strpos($bundle, '-- Consolidated verification', $start);
    migration136Assert($start !== false && $end !== false, 'Consolidated migration 136 must be isolated before verification.');
    $consolidated136 = substr($bundle, $start, $end - $start);

    foreach (array('standalone' => $standalone, 'consolidated block' => $consolidated136) as $name => $sql) {
        migration136RunSql($pdo, $sql);
        migration136RunSql($pdo, $sql);
        $schema = new Migration136Database($pdo);
        migration136Assert(!$schema->field_exists('level', 'questions'), $name . ' must remove difficulty and tolerate reruns.');
        migration136Assert(migration136PreservedData($pdo) === $before, $name . ' must preserve all other columns, records, results, and academic levels.');
        $pdo->exec("ALTER TABLE questions ADD COLUMN level VARCHAR(10) NOT NULL DEFAULT 'medium' AFTER question_type");
    }

    $migration = new Migration_Remove_question_level($pdo);
    $migration->up();
    $migration->up();
    migration136Assert(!$migration->db->field_exists('level', 'questions'), 'CodeIgniter migration must remove difficulty and clear its schema cache.');
    migration136Assert(migration136PreservedData($pdo) === $before, 'CodeIgniter migration must preserve question and examination data.');
    $pdo->exec("INSERT INTO questions (question_type, class_id, section_id, question, correct) VALUES ('singlechoice', 1, 2, '3 + 3?', 'opt_b')");
    migration136Assert((int) $pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn() === 3, 'New questions must save without a difficulty field.');

    $migration->down();
    $migration->down();
    migration136Assert($migration->db->field_exists('level', 'questions'), 'An explicit rollback must restore the legacy column once.');
    migration136Assert((int) $pdo->query("SELECT COUNT(*) FROM questions WHERE level <> ''")->fetchColumn() === 0, 'Rollback must not fabricate old difficulty values.');
    $migration->up();

    $pdo->exec('RENAME TABLE questions TO preserved_question_bank');
    $migration->up();
    $migration->down();
    $rejected = false;
    try {
        migration136RunSql($pdo, $standalone);
    } catch (PDOException $exception) {
        $rejected = true;
    }
    migration136Assert($rejected, 'Standalone SQL must reject a database without the Question Bank.');
    migration136Assert((int) $pdo->query('SELECT COUNT(*) FROM preserved_question_bank')->fetchColumn() === 3, 'A missing-table check must not alter stored questions.');

    echo "Migration 136 database tests passed: both SQL paths rerun, CodeIgniter up/down, preserved records, creation without level, and wrong-schema preflight." . PHP_EOL;
} finally {
    // Only the database generated by this test is removed.
    $pdo->exec('DROP DATABASE `' . $database . '`');
}
