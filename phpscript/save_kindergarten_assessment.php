<?php
include('../database/config.php');
require_once('kindergarten_guard.php');
kindergartenRequireStaff(true, true, 'can_edit');

$data = isset($_POST['data']) ? json_decode($_POST['data'], true) : null;
if (!is_array($data)) {
	http_response_code(422);
	echo '<div class="alert alert-danger">Invalid assessment data.</div>';
	exit;
}

function kindergartenSqlOrFail($link, $sql)
{
	$result = mysqli_query($link, $sql);
	if ($result === false) {
		throw new Exception(mysqli_error($link));
	}
	return $result;
}

function kindergartenStableKey($prefix)
{
	return $prefix . '-' . bin2hex(random_bytes(16));
}

function kindergartenConceptPayload($concept)
{
	if (is_array($concept)) {
		return array(
			'id' => isset($concept['id']) ? (int) $concept['id'] : 0,
			'text' => isset($concept['concept_text']) ? trim($concept['concept_text']) : (isset($concept['text']) ? trim($concept['text']) : ''),
		);
	}
	return array('id' => 0, 'text' => trim((string) $concept));
}

$assessment_id = !empty($data['assessment_id']) ? (int) $data['assessment_id'] : 0;
$assessment_name = mysqli_real_escape_string($link, trim((string) $data['assessment_name']));
$assessment_label = mysqli_real_escape_string($link, trim((string) $data['assessment_label']));
$num_result_labels = (int) $data['num_result_labels'];
$result_labels = isset($data['result_labels']) && is_array($data['result_labels']) ? $data['result_labels'] : array();
$subjects = isset($data['subjects']) && is_array($data['subjects']) ? $data['subjects'] : array();

if ($assessment_name === '' || $assessment_label === '' || $num_result_labels < 2 || $num_result_labels > 5 || count($result_labels) !== $num_result_labels) {
	http_response_code(422);
	echo '<div class="alert alert-danger">Complete the assessment name, label, and all result labels.</div>';
	exit;
}
$result_labels = array_map(function ($value) {
	return trim((string) $value);
}, $result_labels);
$result_labels_json = mysqli_real_escape_string($link, json_encode($result_labels, JSON_UNESCAPED_UNICODE));

mysqli_begin_transaction($link);
try {
	if ($assessment_id > 0) {
		$exists = kindergartenSqlOrFail($link, "SELECT id FROM kindergarten_assessment_header WHERE id='{$assessment_id}' FOR UPDATE");
		if (mysqli_num_rows($exists) !== 1) {
			throw new Exception('Assessment was not found.');
		}
		kindergartenSqlOrFail($link, "UPDATE kindergarten_assessment_header SET assessment_name='{$assessment_name}', assessment_label='{$assessment_label}', num_result_labels='{$num_result_labels}', result_labels_json='{$result_labels_json}' WHERE id='{$assessment_id}'");
	} else {
		kindergartenSqlOrFail($link, "INSERT INTO kindergarten_assessment_header (assessment_name, assessment_label, num_result_labels, result_labels_json) VALUES ('{$assessment_name}', '{$assessment_label}', '{$num_result_labels}', '{$result_labels_json}')");
		$assessment_id = mysqli_insert_id($link);
	}

	$existing_subjects = array();
	$subject_result = kindergartenSqlOrFail($link, "SELECT * FROM kindergarten_assessment_subjects WHERE assessment_id='{$assessment_id}' FOR UPDATE");
	while ($row = mysqli_fetch_assoc($subject_result)) {
		$existing_subjects[(int) $row['id']] = $row;
	}
	$used_subject_rows = array();
	$seen_subject_ids = array();
	$subject_order = 0;

	foreach ($subjects as $subject) {
		$subject_id = isset($subject['subject_id']) ? (int) $subject['subject_id'] : 0;
		if ($subject_id < 1 || isset($seen_subject_ids[$subject_id])) {
			continue;
		}
		$seen_subject_ids[$subject_id] = true;
		$requested_row_id = isset($subject['assessment_subject_id']) ? (int) $subject['assessment_subject_id'] : 0;
		$subject_row = null;
		if ($requested_row_id > 0 && isset($existing_subjects[$requested_row_id]) && (int) $existing_subjects[$requested_row_id]['subject_id'] === $subject_id) {
			$subject_row = $existing_subjects[$requested_row_id];
		} else {
			foreach ($existing_subjects as $candidate) {
				if ((int) $candidate['subject_id'] === $subject_id && !isset($used_subject_rows[(int) $candidate['id']])) {
					$subject_row = $candidate;
					break;
				}
			}
		}

		$now = date('Y-m-d H:i:s');
		if ($subject_row) {
			$assessment_subject_id = (int) $subject_row['id'];
			$stable_key = !empty($subject_row['stable_key']) ? $subject_row['stable_key'] : kindergartenStableKey('subject');
			$stable_key_sql = mysqli_real_escape_string($link, $stable_key);
			kindergartenSqlOrFail($link, "UPDATE kindergarten_assessment_subjects SET display_order='{$subject_order}', stable_key='{$stable_key_sql}', is_active=1, updated_at='{$now}' WHERE id='{$assessment_subject_id}'");
		} else {
			$stable_key_sql = mysqli_real_escape_string($link, kindergartenStableKey('subject'));
			kindergartenSqlOrFail($link, "INSERT INTO kindergarten_assessment_subjects (assessment_id, subject_id, display_order, stable_key, is_active, updated_at) VALUES ('{$assessment_id}', '{$subject_id}', '{$subject_order}', '{$stable_key_sql}', 1, '{$now}')");
			$assessment_subject_id = mysqli_insert_id($link);
		}
		$used_subject_rows[$assessment_subject_id] = true;

		$existing_concepts = array();
		$concept_result = kindergartenSqlOrFail($link, "SELECT * FROM kindergarten_assessment_concepts WHERE assessment_subject_id='{$assessment_subject_id}' FOR UPDATE");
		while ($row = mysqli_fetch_assoc($concept_result)) {
			$existing_concepts[(int) $row['id']] = $row;
		}
		$used_concepts = array();
		$concept_order = 0;
		foreach (isset($subject['concepts']) ? (array) $subject['concepts'] : array() as $raw_concept) {
			$concept = kindergartenConceptPayload($raw_concept);
			if ($concept['text'] === '') {
				continue;
			}
			$concept_row = null;
			if ($concept['id'] > 0 && isset($existing_concepts[$concept['id']])) {
				$concept_row = $existing_concepts[$concept['id']];
			} else {
				foreach ($existing_concepts as $candidate) {
					if (!isset($used_concepts[(int) $candidate['id']]) && strtolower(trim($candidate['concept_text'])) === strtolower($concept['text'])) {
						$concept_row = $candidate;
						break;
					}
				}
			}

			$text_sql = mysqli_real_escape_string($link, $concept['text']);
			if ($concept_row) {
				$concept_id = (int) $concept_row['id'];
				$stable_key = !empty($concept_row['stable_key']) ? $concept_row['stable_key'] : kindergartenStableKey('concept');
				$stable_key_sql = mysqli_real_escape_string($link, $stable_key);
				kindergartenSqlOrFail($link, "UPDATE kindergarten_assessment_concepts SET concept_text='{$text_sql}', display_order='{$concept_order}', stable_key='{$stable_key_sql}', is_active=1, updated_at='{$now}' WHERE id='{$concept_id}'");
			} else {
				$stable_key_sql = mysqli_real_escape_string($link, kindergartenStableKey('concept'));
				kindergartenSqlOrFail($link, "INSERT INTO kindergarten_assessment_concepts (assessment_subject_id, concept_text, display_order, stable_key, is_active, updated_at) VALUES ('{$assessment_subject_id}', '{$text_sql}', '{$concept_order}', '{$stable_key_sql}', 1, '{$now}')");
				$concept_id = mysqli_insert_id($link);
			}
			$used_concepts[$concept_id] = true;
			$concept_order++;
		}

		foreach ($existing_concepts as $concept_id => $unused) {
			if (!isset($used_concepts[$concept_id])) {
				kindergartenSqlOrFail($link, "UPDATE kindergarten_assessment_concepts SET is_active=0, updated_at='{$now}' WHERE id='{$concept_id}'");
			}
		}
		$subject_order++;
	}

	$now = date('Y-m-d H:i:s');
	foreach ($existing_subjects as $subject_row_id => $unused) {
		if (!isset($used_subject_rows[$subject_row_id])) {
			kindergartenSqlOrFail($link, "UPDATE kindergarten_assessment_subjects SET is_active=0, updated_at='{$now}' WHERE id='{$subject_row_id}'");
			kindergartenSqlOrFail($link, "UPDATE kindergarten_assessment_concepts SET is_active=0, updated_at='{$now}' WHERE assessment_subject_id='{$subject_row_id}'");
		}
	}

	mysqli_commit($link);
	echo '<div class="alert alert-success">Assessment saved successfully without changing existing concept identifiers.</div>';
} catch (Throwable $exception) {
	mysqli_rollback($link);
	http_response_code(422);
	echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
}
