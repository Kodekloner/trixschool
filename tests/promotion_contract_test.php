<?php

function promotion_contract_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function promotion_contract_php_without_comments($source)
{
    $clean = '';
    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                continue;
            }
            $clean .= $token[1];
        } else {
            $clean .= $token;
        }
    }

    return $clean;
}

function promotion_contract_has_student_session_mutation($source, $isPhp = true)
{
    if ($isPhp) {
        $source = promotion_contract_php_without_comments($source);
    } else {
        $source = preg_replace('/^\s*--.*$/m', '', $source);
        $source = preg_replace('/\/\*.*?\*\//s', '', $source);
    }

    $sqlMutation = '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?student_session`?/i';
    $queryBuilderMutation = '/->\s*(?:insert|update|delete)\s*\(\s*[\'\"]student_session[\'\"]/i';

    return preg_match($sqlMutation, $source) === 1
        || preg_match($queryBuilderMutation, $source) === 1;
}

$root = dirname(__DIR__);
$migrationPath = $root . '/application/migrations/133_add_promotion_system.php';
$modelPath = $root . '/application/models/Promotioncriteria_model.php';
$controllerPath = $root . '/application/controllers/admin/Promotioncriteria.php';
$helperPath = $root . '/helper/promotion_helper.php';
$manualSqlPath = $root . '/docs/promotion_system_migration.sql';
$consolidatedSqlPath = $root . '/docs/all_school_database_migrations.sql';
$summaryPartialPath = $root . '/admin/partials/result-summary-panel.php';
$resultPagePath = $root . '/admin/resultPage.php';
$kindergartenPagePath = $root . '/admin/kindergarten_result_page.php';

foreach (array(
    $migrationPath,
    $modelPath,
    $controllerPath,
    $helperPath,
    $manualSqlPath,
    $consolidatedSqlPath,
    $summaryPartialPath,
    $resultPagePath,
    $kindergartenPagePath,
) as $requiredFile) {
    promotion_contract_assert(is_file($requiredFile), 'Missing promotion implementation file: ' . $requiredFile);
}

$migration = file_get_contents($migrationPath);
foreach (array(
    'promotion_criteria',
    'promotion_criteria_classes',
    'promotion_criteria_subjects',
    'promotion_note_overrides',
) as $table) {
    promotion_contract_assert(
        strpos($migration, "CREATE TABLE `{$table}`") !== false,
        'Migration 133 is missing table ' . $table . '.'
    );
}

foreach (array(
    '`session_id` INT NOT NULL',
    '`minimum_average` DECIMAL(5,2) NOT NULL',
    'UNIQUE KEY `promotion_criteria_class_unique` (`session_id`, `class_id`)',
    'UNIQUE KEY `promotion_criteria_subject_unique` (`criteria_id`, `subject_id`)',
    '`action` VARCHAR(10) NOT NULL',
    '`decision` VARCHAR(20) DEFAULT NULL',
    '`reason` TEXT NOT NULL',
    '`automatic_decision` VARCHAR(20) NOT NULL',
    '`automatic_note` VARCHAR(255) NOT NULL',
    '`created_by` INT NOT NULL',
) as $schemaContract) {
    promotion_contract_assert(
        strpos($migration, $schemaContract) !== false,
        'Migration 133 is missing schema contract: ' . $schemaContract
    );
}

foreach (array('manage_promotion_criteria', 'override_promotion_note') as $permission) {
    promotion_contract_assert(
        strpos($migration, "'{$permission}'") !== false,
        'Migration 133 must seed permission ' . $permission . '.'
    );
}
foreach (array('Teacher', 'Admin', 'Head Teacher', 'Super Admin') as $role) {
    promotion_contract_assert(
        strpos($migration, "'{$role}'") !== false,
        'Migration 133 is missing the expected default role ' . $role . '.'
    );
}

$migrationConfig = file_get_contents($root . '/application/config/migration.php');
promotion_contract_assert(
    strpos($migrationConfig, "migration_version'] = 133") !== false,
    'The configured migration target must include promotion migration 133.'
);

$manualSql = file_get_contents($manualSqlPath);
$consolidatedSql = file_get_contents($consolidatedSqlPath);
foreach (array('promotion_criteria', 'promotion_criteria_classes', 'promotion_criteria_subjects', 'promotion_note_overrides') as $table) {
    promotion_contract_assert(
        strpos($manualSql, "CREATE TABLE IF NOT EXISTS `{$table}`") !== false,
        'The per-school promotion SQL is missing ' . $table . '.'
    );
    promotion_contract_assert(
        strpos($consolidatedSql, "CREATE TABLE IF NOT EXISTS `{$table}`") !== false,
        'The consolidated all-school SQL is missing ' . $table . '.'
    );
}

$productionPhp = array(
    $migrationPath => $migration,
    $modelPath => file_get_contents($modelPath),
    $controllerPath => file_get_contents($controllerPath),
    $helperPath => file_get_contents($helperPath),
);
foreach ($productionPhp as $path => $source) {
    promotion_contract_assert(
        !promotion_contract_has_student_session_mutation($source, true),
        'Promotion code must never mutate student_session: ' . $path
    );
}
foreach (array($manualSqlPath => $manualSql, $consolidatedSqlPath => $consolidatedSql) as $path => $source) {
    promotion_contract_assert(
        !promotion_contract_has_student_session_mutation($source, false),
        'Promotion deployment SQL must never mutate student_session: ' . $path
    );
}

$model = $productionPhp[$modelPath];
promotion_contract_assert(
    substr_count($model, "insert('promotion_note_overrides'") === 1,
    'Promotion-note changes must have one append-only insert path.'
);
promotion_contract_assert(
    preg_match('/->\s*(?:update|delete)\s*\(\s*[\'\"]promotion_note_overrides[\'\"]/', promotion_contract_php_without_comments($model)) !== 1,
    'Promotion-note audit history must not have update or delete paths.'
);

$controller = $productionPhp[$controllerPath];
foreach (array(
    "hasPrivilege('manage_promotion_criteria'",
    "hasPrivilege('override_promotion_note'",
    'teacherHasScope(',
    'studentMatchesScope(',
    'classSectionExists(',
    'requirePostAndToken()',
) as $authorizationContract) {
    promotion_contract_assert(
        strpos($controller, $authorizationContract) !== false,
        'Promotion controller is missing authorization contract: ' . $authorizationContract
    );
}

$helper = $productionPhp[$helperPath];
promotion_contract_assert(
    strpos($helper, "['promoted', 'not_promoted', 'pending']") !== false,
    'The evaluator must expose only the canonical three automatic decisions.'
);
promotion_contract_assert(
    strpos($helper, "ORDER BY pno.id DESC") !== false,
    'Override precedence must use the latest append-only audit row.'
);
promotion_contract_assert(
    strpos($helper, "strtolower((string) (\$override['action'] ?? '')) === 'clear'") !== false,
    'A clear audit action must restore the current automatic outcome.'
);
promotion_contract_assert(
    strpos($helper, "TRIM(COALESCE(result_rows.Remark, '')) != ''") !== false,
    'British result-bearing counts must exclude blank roster placeholders.'
);
promotion_contract_assert(
    strpos($helper, 'get_result_midterm_ca_indices($link, $classId)') !== false,
    'Midterm result-bearing counts and summaries must use configured CA columns.'
);

$summaryPartial = file_get_contents($summaryPartialPath);
$summaryPartialLower = strtolower($summaryPartial);
foreach (array('no. in class:', 'grade summary:', 'cumulative average score:', 'promotion status', 'key to grades') as $label) {
    promotion_contract_assert(
        strpos($summaryPartialLower, $label) !== false,
        'The shared result summary is missing ' . $label
    );
}

$resultPage = file_get_contents($resultPagePath);
promotion_contract_assert(
    strpos($resultPage, "require_once('../helper/promotion_helper.php')") !== false,
    'The numeric/British result page must load the shared promotion service.'
);
promotion_contract_assert(
    strpos($resultPage, "=== 'british' ? 'british' : 'numeric'") !== false,
    'The result page must distinguish British and numeric summary contracts.'
);
promotion_contract_assert(
    strpos($resultPage, "partials/result-summary-panel.php") !== false,
    'The numeric/British result page must render the shared summary panel.'
);

$kindergartenPage = file_get_contents($kindergartenPagePath);
promotion_contract_assert(
    strpos($kindergartenPage, "'kindergarten'") !== false,
    'The kindergarten result must request its qualitative summary format.'
);
promotion_contract_assert(
    strpos($kindergartenPage, "partials/result-summary-panel.php") !== false,
    'The kindergarten result page must render the shared summary panel.'
);

echo "promotion schema, immutability, and result-format contract tests passed" . PHP_EOL;
