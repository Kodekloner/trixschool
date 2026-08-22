<?php
include('../database/config.php');
require_once('../helper/publishresult_helper.php');

function build_student_result_url(
    $isKindergarten,
    $classSection,
    $sectionId,
    $classId,
    $sessionId,
    $term,
    $studentId,
    $resultType,
    $assessmentId
) {
    $parameters = array(
        'classsection' => (int) $classSection,
        'classsectionactual' => (int) $sectionId,
        'classid' => (int) $classId,
        'session' => (int) $sessionId,
        'term' => (string) $term,
        'id' => (int) $studentId,
        'reltype' => (string) $resultType,
    );

    if ($isKindergarten) {
        $parameters['assessment_id'] = (int) $assessmentId;
    }

    return ($isKindergarten ? 'kindergarten_result_page.php' : 'resultPage.php')
        . '?'
        . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
}

function build_result_download_filename(array $parts, $extension, $fallback)
{
    $parts = array_values(array_filter(array_map(static function ($part) {
        return trim((string) $part);
    }, $parts), static function ($part) {
        return $part !== '';
    }));

    $filename = implode(' - ', $parts);
    $filename = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F\x7F]+/u', '-', $filename);
    $filename = preg_replace('/\s+/u', ' ', trim((string) $filename));
    $filename = trim((string) $filename, ". -\t\n\r\0\x0B");

    if ($filename === '') {
        $filename = $fallback;
    }

    $maximumStemLength = 170;
    if (function_exists('mb_substr')) {
        $filename = mb_substr($filename, 0, $maximumStemLength, 'UTF-8');
    } else {
        $filename = substr($filename, 0, $maximumStemLength);
    }

    return rtrim($filename, '. -') . '.' . ltrim(strtolower((string) $extension), '.');
}

function render_student_result_table_open()
{
    return '<div class="result-download-list" data-result-download-list>
        <div class="result-download-toolbar" data-result-download-toolbar>
            <div class="result-download-toolbar__summary">
                <i class="fa fa-download" aria-hidden="true"></i>
                <span>Result downloads</span>
                <small data-result-selected-count>0 selected</small>
            </div>
            <div class="result-download-toolbar__actions" aria-label="Bulk result downloads">
                <button type="button" class="btn btn-sm btn-outline-primary" data-result-download-selected disabled>
                    <i class="fa fa-download" aria-hidden="true"></i> Download selected
                </button>
                <button type="button" class="btn btn-sm btn-primary" data-result-download-all>
                    <i class="fa fa-download" aria-hidden="true"></i> Download all
                </button>
            </div>
            <div class="dropdown result-download-toolbar__dropdown">
                <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fa fa-download" aria-hidden="true"></i> Download
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <button type="button" class="dropdown-item" data-result-download-selected disabled>Download selected</button>
                    <button type="button" class="dropdown-item" data-result-download-all>Download all results</button>
                </div>
            </div>
            <div class="result-download-toolbar__message" data-result-download-message role="status" aria-live="polite"></div>
        </div>
        <table class="table table-striped table-bordered result-download-table" id="editable-datatable">
            <thead>
                <tr>
                    <th class="result-download-table__select">
                        <input type="checkbox" data-result-select-all aria-label="Select all results">
                    </th>
                    <th>S/N</th>
                    <th>Full Name</th>
                    <th>Admission No.</th>
                    <th>Class</th>
                    <th>Session</th>
                    <th>Term</th>
                    <th class="result-download-table__action">Action</th>
                </tr>
            </thead>
            <tbody>';
}

function render_student_result_row(
    array $student,
    $serialNumber,
    $className,
    $sessionName,
    $termLabel,
    $resultUrl
) {
    $studentId = (int) ($student['StudentID'] ?? 0);
    $fullName = trim(
        (string) ($student['lastname'] ?? '')
        . ' '
        . (string) ($student['middlename'] ?? '')
        . ' '
        . (string) ($student['firstname'] ?? '')
    );
    $escape = static function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };
    $pdfFilename = build_result_download_filename(
        array(
            $fullName,
            $student['admission_no'] ?? '',
            $className,
            $sessionName,
            $termLabel,
            'Result',
        ),
        'pdf',
        'student-result'
    );
    $archiveFilename = build_result_download_filename(
        array($className, $sessionName, $termLabel, 'Results'),
        'zip',
        'student-results'
    );

    return '<tr data-result-row data-result-student-id="' . $studentId . '">
        <td class="result-download-table__select">
            <input type="checkbox" data-result-select aria-label="Select result for ' . $escape($fullName) . '">
        </td>
        <td>' . (int) $serialNumber . '</td>
        <td>' . $escape($fullName) . '</td>
        <td>' . $escape($student['admission_no'] ?? '') . '</td>
        <td>' . $escape($className) . '</td>
        <td>' . $escape($sessionName) . '</td>
        <td>' . $escape($termLabel) . '</td>
        <td class="result-download-table__action">
            <div class="result-download-row-actions">
                <a href="' . $escape($resultUrl) . '" class="result-download-row-actions__view">View Result</a>
                <a href="' . $escape($resultUrl) . '" class="btn btn-sm btn-outline-primary result-download-row-actions__download" data-result-download-one data-result-filename="' . $escape($pdfFilename) . '" data-result-archive-filename="' . $escape($archiveFilename) . '" title="Download result as PDF" aria-label="Download PDF result for ' . $escape($fullName) . '">
                    <i class="fa fa-download" aria-hidden="true"></i>
                    <span class="sr-only">Download result</span>
                </a>
            </div>
        </td>
    </tr>';
}

function render_student_result_table_close()
{
    return '</tbody></table></div>';
}

$classsectionactual = $_POST['classsectionactual'] ?? 0;

$classid = $_POST['classid'] ?? 0;

$session = $_POST['session'] ?? 0;

$term = $_POST['term'] ?? '';

$reltype = $_POST['reltype'] ?? '';

$rolefirst = $_POST['rolefirst'] ?? '';

$staffid = $_POST['staffid'] ?? 0;

$validationError = get_publishresult_validation_error(
    $session,
    $term,
    $reltype,
    $classid,
    $classsectionactual
);

if ($validationError !== '') {
    echo '<div class="alert alert-warning" role="alert">'
        . htmlspecialchars($validationError, ENT_QUOTES, 'UTF-8')
        . '</div>';
    exit;
}

$session = (int) $session;
$classid = (int) $classid;
$classsectionactual = (int) $classsectionactual;
$staffid = (int) $staffid;
$reltype = normalize_publishresult_reltype($reltype);
$term = $reltype === 'cummulative'
    ? '3rd'
    : normalize_publishresult_term($term, $reltype);

$reldate = date('Y-m-d');

// Check if this class has a kindergarten assessment assigned
$sql_kindergarten = "SELECT assessment_id FROM kindergarten_assignment WHERE class_id = '$classid' LIMIT 1";
$result_kindergarten = mysqli_query($link, $sql_kindergarten);
$is_kindergarten = (mysqli_num_rows($result_kindergarten) > 0);

$kindergarten_assessment_id = $is_kindergarten ? mysqli_fetch_assoc($result_kindergarten)['assessment_id'] : 0;

$sqlGetassigncatoclass = "SELECT * FROM `assigncatoclass` WHERE `ClassID`='$classid'";
$queryGetassigncatoclass = mysqli_query($link, $sqlGetassigncatoclass);
$rowGetassigncatoclass = mysqli_fetch_assoc($queryGetassigncatoclass);
$countGetassigncatoclass = mysqli_num_rows($queryGetassigncatoclass);

// $rowGetassigncatoclass['ResultType'];'numeric';

$reltypenew = $rowGetassigncatoclass['ResultType'];

if ($rolefirst == 'parent') {
    $student_id_sql = "SELECT student_id FROM student_session WHERE id = '$staffid'";
    $student_id_sql_2 = mysqli_query($link, $student_id_sql);
    $staffid = mysqli_fetch_assoc($student_id_sql_2)['student_id'];
}

if ($rolefirst == 'student' || $rolefirst == 'parent') {
    $publishedResult = find_publishresult_record($link, $session, $term, $reltype, $classid, $classsectionactual, $reldate);
    $countGetstudent_session = !empty($publishedResult) ? 1 : 0;

    if ($countGetstudent_session > 0) {
        $sqlclasseschecker = "SELECT * FROM class_sections WHERE section_id = '$classsectionactual' AND class_id = '$classid'";
        $resultclasseschecker = mysqli_query($link, $sqlclasseschecker);
        $rowclasseschecker = mysqli_fetch_assoc($resultclasseschecker);
        $row_cntclasseschecker = mysqli_num_rows($resultclasseschecker);

        $classsection = $rowclasseschecker['id'];

        $sqlGetclasses = "SELECT * FROM `classes` WHERE `id`='$classid'";
        $queryGetclasses = mysqli_query($link, $sqlGetclasses);
        $rowGetclasses = mysqli_fetch_assoc($queryGetclasses);
        $countGetclasses = mysqli_num_rows($queryGetclasses);

        $sqlGetsessions = "SELECT * FROM `sessions` WHERE `id`='$session'";
        $queryGetsessions = mysqli_query($link, $sqlGetsessions);
        $rowGetsessions = mysqli_fetch_assoc($queryGetsessions);
        $countGetsessions = mysqli_num_rows($queryGetsessions);

        $sqlGetclass_sections = "SELECT * FROM `class_sections` WHERE `id`='$classsection'";
        $queryGetclass_sections = mysqli_query($link, $sqlGetclass_sections);
        $rowGetclass_sections = mysqli_fetch_assoc($queryGetclass_sections);
        $countGetclass_sections = mysqli_num_rows($queryGetclass_sections);

        if ($countGetclass_sections > 0) {

            $sectionnew = $rowGetclass_sections['section_id'];

            $class_id = $rowGetclass_sections['class_id'];

            echo render_student_result_table_open();
            $cnt = 1;
            // ---- START MODIFICATION ----
            if ($is_kindergarten) {
                $sqlGetstudent_session = "SELECT DISTINCT kr.student_id AS StudentID, 
                                                s.lastname, s.middlename, s.firstname, s.admission_no,
                                                CONCAT(s.lastname, ' ', COALESCE(s.middlename, ''), ' ', s.firstname) AS full_name
                                        FROM kindergarten_result kr
                                        INNER JOIN students s ON kr.student_id = s.id
                                        INNER JOIN student_session ss ON kr.student_id = ss.student_id 
                                                AND ss.session_id = kr.session_id 
                                                AND ss.class_id = '$classid' 
                                                AND ss.section_id = '$sectionnew'
                                        WHERE kr.session_id = '$session'
                                            AND kr.term = '$term'
                                            AND kr.assessment_id = '$kindergarten_assessment_id'
                                            AND kr.student_id = '$staffid'
                                            AND s.is_active = 'yes'
                                        ORDER BY full_name ASC";
            } elseif ($reltypenew == 'british') {
                $sqlGetstudent_session = "SELECT DISTINCT StudentID,lastname,middlename,firstname,admission_no, CONCAT(students.lastname, ' ', COALESCE(students.middlename, ''), ' ', students.firstname) AS full_name FROM `britishresult` INNER JOIN students ON britishresult.StudentID=students.id AND `Session`='$session' AND ClassID = '$classid' AND SectionID = '$sectionnew' AND Term = '$term' AND britishresult.StudentID='$staffid' AND students.is_active = 'yes' ORDER BY full_name ASC";
            } else {
                if ($reltype == 'cummulative') {
                    $sqlGetstudent_session = "SELECT DISTINCT StudentID,lastname,middlename,firstname,admission_no, CONCAT(students.lastname, ' ', COALESCE(students.middlename, ''), ' ', students.firstname) AS full_name FROM `score` INNER JOIN students ON score.StudentID=students.id AND `Session`='$session' AND ClassID = '$classid' AND SectionID = '$sectionnew' AND score.StudentID='$staffid' AND students.is_active = 'yes' ORDER BY full_name ASC";
                } else {
                    $sqlGetstudent_session = "SELECT DISTINCT 
                            StudentID, 
                            lastname, 
                            middlename, 
                            firstname, 
                            admission_no, 
                            CONCAT(students.lastname, ' ', COALESCE(students.middlename, ''), ' ', students.firstname) AS full_name 
                          FROM `score` 
                          INNER JOIN students ON score.StudentID = students.id 
                          WHERE 
                            `Session` = '$session' 
                            AND ClassID = '$classid' 
                            AND SectionID = '$sectionnew' 
                            AND Term = '$term' 
                            AND score.StudentID = '$staffid' 
                            AND students.is_active = 'yes' 
                          ORDER BY full_name ASC";
                }
            }

            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

            if ($countGetstudent_session > 0) {
                do {
                    $resultUrl = build_student_result_url(
                        $is_kindergarten,
                        $classsection,
                        $classsectionactual,
                        $classid,
                        $session,
                        $term,
                        $rowGetstudent_session['StudentID'],
                        $reltype,
                        $kindergarten_assessment_id
                    );
                    $termLabel = $reltype === 'cummulative' ? 'Cumulative' : $term . ' Term';
                    echo render_student_result_row(
                        $rowGetstudent_session,
                        $cnt++,
                        $rowGetclasses['class'],
                        $rowGetsessions['session'],
                        $termLabel,
                        $resultUrl
                    );
                } while ($rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session));
            } else {
                echo '<tr><td colspan="8">No Records Found</td></tr>';
            }

            echo render_student_result_table_close();
        } else {
            echo 'Class Section Not Found';
        }
    } else {
        echo '<div class="alert alert-primary" role="alert">
                Result has not been published
            </div>';
    }
} else {
    $sqlclasseschecker = "SELECT * FROM class_sections WHERE section_id = '$classsectionactual' AND class_id = '$classid'";
    $resultclasseschecker = mysqli_query($link, $sqlclasseschecker);
    $rowclasseschecker = mysqli_fetch_assoc($resultclasseschecker);
    $row_cntclasseschecker = mysqli_num_rows($resultclasseschecker);

    $classsection = $rowclasseschecker['id'];

    $sqlGetclasses = "SELECT * FROM `classes` WHERE `id`='$classid'";
    $queryGetclasses = mysqli_query($link, $sqlGetclasses);
    $rowGetclasses = mysqli_fetch_assoc($queryGetclasses);
    $countGetclasses = mysqli_num_rows($queryGetclasses);

    $sqlGetsessions = "SELECT * FROM `sessions` WHERE `id`='$session'";
    $queryGetsessions = mysqli_query($link, $sqlGetsessions);
    $rowGetsessions = mysqli_fetch_assoc($queryGetsessions);
    $countGetsessions = mysqli_num_rows($queryGetsessions);

    $sqlGetclass_sections = "SELECT * FROM `class_sections` WHERE `id`='$classsection'";
    $queryGetclass_sections = mysqli_query($link, $sqlGetclass_sections);
    $rowGetclass_sections = mysqli_fetch_assoc($queryGetclass_sections);
    $countGetclass_sections = mysqli_num_rows($queryGetclass_sections);

    if ($countGetclass_sections > 0) {

        $sectionnew = $rowGetclass_sections['section_id'];

        $class_id = $rowGetclass_sections['class_id'];

        echo render_student_result_table_open();
        $cnt = 1;
        // ---- START MODIFICATION ----
        if ($is_kindergarten) {
            $sqlGetstudent_session = "SELECT 
                                            s.id AS StudentID,
                                            s.lastname,
                                            s.middlename,
                                            s.firstname,
                                            s.admission_no,
                                            CONCAT(s.lastname, ' ', COALESCE(s.middlename, ''), ' ', s.firstname) AS full_name,
                                            (SELECT COUNT(*) FROM kindergarten_result kr 
                                            WHERE kr.student_id = s.id 
                                            AND kr.session_id = '$session' 
                                            AND kr.term = '$term' 
                                            AND kr.assessment_id = '$kindergarten_assessment_id') AS has_result
                                    FROM students s
                                    INNER JOIN student_session ss 
                                        ON s.id = ss.student_id 
                                        AND ss.session_id = '$session' 
                                        AND ss.class_id = '$classid' 
                                        AND ss.section_id = '$sectionnew'
                                    WHERE s.is_active = 'yes'
                                    ORDER BY full_name ASC";
        } elseif ($reltypenew == 'british') {
            $sqlGetstudent_session = "SELECT DISTINCT StudentID,lastname,middlename,firstname,admission_no, CONCAT(students.lastname, ' ', COALESCE(students.middlename, ''), ' ', students.firstname) AS full_name FROM `britishresult` INNER JOIN students ON britishresult.StudentID=students.id AND `Session`='$session' AND ClassID = '$classid' AND SectionID = '$sectionnew' AND Term = '$term' AND students.is_active = 'yes' ORDER BY full_name ASC";
        } else {
            if ($reltype == 'cummulative') {
                $sqlGetstudent_session = "SELECT DISTINCT StudentID,lastname,middlename,firstname,admission_no, CONCAT(students.lastname, ' ', COALESCE(students.middlename, ''), ' ', students.firstname) AS full_name FROM `score` INNER JOIN students ON score.StudentID=students.id AND `Session`='$session' AND ClassID = '$classid' AND SectionID = '$sectionnew' AND students.is_active = 'yes' ORDER BY full_name ASC";
            } else {
                $sqlGetstudent_session = "SELECT DISTINCT StudentID,lastname,middlename,firstname,admission_no, CONCAT(students.lastname, ' ', COALESCE(students.middlename, ''), ' ', students.firstname) AS full_name FROM `score` INNER JOIN students ON score.StudentID=students.id AND `Session`='$session' AND ClassID = '$classid' AND SectionID = '$sectionnew' AND Term = '$term' AND students.is_active = 'yes' ORDER BY full_name ASC";
            }
        }
        $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
        $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
        $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

        if ($countGetstudent_session > 0) {
            do {
                $resultUrl = build_student_result_url(
                    $is_kindergarten,
                    $classsection,
                    $classsectionactual,
                    $classid,
                    $session,
                    $term,
                    $rowGetstudent_session['StudentID'],
                    $reltype,
                    $kindergarten_assessment_id
                );
                $termLabel = $reltype === 'cummulative' ? 'Cumulative' : $term . ' Term';
                echo render_student_result_row(
                    $rowGetstudent_session,
                    $cnt++,
                    $rowGetclasses['class'],
                    $rowGetsessions['session'],
                    $termLabel,
                    $resultUrl
                );
            } while ($rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session));
        } else {
            echo '<tr><td colspan="8">No Records Found</td></tr>';
        }

        echo render_student_result_table_close();
    } else {
        echo 'Class Section Not Found';
    }
}
