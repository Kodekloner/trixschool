<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Parse question CSVs by header so optional/old columns never shift answers. */
function parse_question_import_csv($stream)
{
    $fields = array('question_type', 'question', 'opt_a', 'opt_b', 'opt_c', 'opt_d', 'opt_e', 'correct');
    $headers = fgetcsv($stream, 0, ',');
    if ($headers === false) {
        throw new InvalidArgumentException('The CSV is empty. Download and use the question import template.');
    }

    $positions = array();
    foreach ($headers as $index => $header) {
        $header = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)));
        // Earlier templates included usage instructions in these two headers.
        if (preg_match('/^question[ _]type(?:\s+like\b|$)/', $header)) {
            $header = 'question_type';
        } elseif (preg_match('/^correct(?:\s+like\b|$)/', $header)) {
            $header = 'correct';
        }
        if (in_array($header, $fields, true)) {
            if (isset($positions[$header])) {
                throw new InvalidArgumentException('The CSV contains a duplicate ' . $header . ' column.');
            }
            $positions[$header] = $index;
        }
    }
    if (count($positions) !== count($fields)) {
        throw new InvalidArgumentException('The CSV headers do not match the question import template. Download the current template and try again.');
    }

    $questions = array();
    $record_number = 1;
    while (($values = fgetcsv($stream, 0, ',')) !== false) {
        $record_number++;
        if (count($values) === 1 && trim((string) $values[0]) === '') {
            continue;
        }
        if (count($values) !== count($headers)) {
            throw new InvalidArgumentException('CSV record ' . $record_number . ' has the wrong number of columns. Check its commas and quotes.');
        }
        $question = array();
        foreach ($fields as $field) {
            $question[$field] = trim((string) $values[$positions[$field]]);
        }
        if ($question['question_type'] === '' || $question['question'] === '') {
            throw new InvalidArgumentException('CSV record ' . $record_number . ' needs a question type and question text.');
        }
        $questions[] = $question;
    }
    if (empty($questions)) {
        throw new InvalidArgumentException('The CSV contains no questions to import.');
    }
    return $questions;
}
