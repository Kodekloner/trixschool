<?php
include('../database/config.php');

$classsectionactual = $_POST['classsectionactual'];
$classid = $_POST['classid'];
$session = $_POST['session'];
$term = $_POST['term'];
$RemarkType = $_POST['RemarkType'];

// --- Check if this class has a kindergarten assessment assigned ---
$sql_kindergarten = "SELECT assessment_id FROM kindergarten_assignment WHERE class_id = '$classid' LIMIT 1";
$result_kindergarten = mysqli_query($link, $sql_kindergarten);
$is_kindergarten = (mysqli_num_rows($result_kindergarten) > 0);
$kindergarten_assessment_id = $is_kindergarten ? mysqli_fetch_assoc($result_kindergarten)['assessment_id'] : 0;

// --- Get the class section mapping ---
$sqlclasseschecker = "SELECT * FROM class_sections WHERE section_id = '$classsectionactual' AND class_id = '$classid'";
$resultclasseschecker = mysqli_query($link, $sqlclasseschecker);
$rowclasseschecker = mysqli_fetch_assoc($resultclasseschecker);
$classsection = $rowclasseschecker['id'];

$sqlGetclasses = "SELECT * FROM `classes` WHERE `id`='$classid'";
$queryGetclasses = mysqli_query($link, $sqlGetclasses);
$rowGetclasses = mysqli_fetch_assoc($queryGetclasses);

$sqlGetsessions = "SELECT * FROM `sessions` WHERE `id`='$session'";
$queryGetsessions = mysqli_query($link, $sqlGetsessions);
$rowGetsessions = mysqli_fetch_assoc($queryGetsessions);

$sqlGetclass_sections = "SELECT * FROM `class_sections` WHERE `id`='$classsection'";
$queryGetclass_sections = mysqli_query($link, $sqlGetclass_sections);
$rowGetclass_sections = mysqli_fetch_assoc($queryGetclass_sections);
$countGetclass_sections = mysqli_num_rows($queryGetclass_sections);

if ($countGetclass_sections == 0) {
    echo 'Class Section Not Found';
    exit;
}

$sectionnew = $rowGetclass_sections['section_id'];
$class_id = $rowGetclass_sections['class_id'];

// --- Determine result type from assigncatoclass ---
$sqlGetassigncatoclass = "SELECT * FROM `assigncatoclass` WHERE `ClassID`='$classid'";
$queryGetassigncatoclass = mysqli_query($link, $sqlGetassigncatoclass);
$rowGetassigncatoclass = mysqli_fetch_assoc($queryGetassigncatoclass);
$reltype = $rowGetassigncatoclass['ResultType'];   // 'british' or 'alphabetic'

// --------------------------------------------------------------------
//                            KINDERGARTEN BRANCH
// --------------------------------------------------------------------
if ($is_kindergarten) {
    // Build student list from kindergarten_result for this session/term/assessment
    $sqlGetstudent_session = "
        SELECT DISTINCT s.id AS StudentID, s.admission_no, s.lastname, s.middlename, s.firstname
        FROM kindergarten_result kr
        INNER JOIN students s ON kr.student_id = s.id
        INNER JOIN student_session ss ON ss.student_id = s.id 
            AND ss.session_id = '$session' 
            AND ss.class_id = '$classid' 
            AND ss.section_id = '$sectionnew'
        WHERE kr.session_id = '$session' 
          AND kr.term = '$term' 
          AND kr.assessment_id = '$kindergarten_assessment_id'
        ORDER BY s.lastname, s.firstname
    ";

    $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
    $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

    echo '<table class="table table-striped table-bordered" id="editable-datatable" style="margin-top: 30px;">
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Admission No.</th>
                    <th>Student Name</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>';
    $cnt = 1;

    if ($countGetstudent_session > 0) {
        while ($row = mysqli_fetch_assoc($queryGetstudent_session)) {
            $id = $row['StudentID'];
            // Fetch existing comment (if any) from remark table
            $sqlGetremark = "SELECT * FROM `remark` 
                             WHERE StudentID='$id' 
                               AND `Session`='$session' 
                               AND Term = '$term' 
                               AND `RemarkType` = '$RemarkType'";
            $queryGetremark = mysqli_query($link, $sqlGetremark);
            $rowGetremark = mysqli_fetch_assoc($queryGetremark);
            $comment = $rowGetremark['remark'] ?? '';

            echo '<tr>
                    <td>' . $cnt++ . '</td>
                    <td>' . htmlspecialchars($row['admission_no']) . '</td>
                    <td>' . htmlspecialchars($row['lastname'] . ' ' . $row['middlename'] . ' ' . $row['firstname']) . '</td>
                    <td>
                        <textarea type="text" rows="4" class="form-control britishfield" 
                                  data-id="' . $id . '" 
                                  placeholder="input comment here">' . htmlspecialchars($comment) . '</textarea>
                    </td>
                  </tr>';
        }
    } else {
        echo '<tr><td colspan="4">No Records Found</td></tr>';
    }

    echo '</tbody></table>';
    exit;   // Stop further execution
}

// --------------------------------------------------------------------
//                    BRITISH RESULT BRANCH
// --------------------------------------------------------------------
if ($reltype == 'british') {

    // 2) CLEANUP: delete stale score rows for this session/class/subject/term where the student is NOT in the current student_session
    $sqlDeleteStale = "
        DELETE FROM britishresult
        WHERE `Session` = '$session'
            AND ClassID = '$classid'
            AND Term = '$term'
            AND SectionID = '$sectionnew'
            AND StudentID NOT IN (
                SELECT student_id FROM student_session
                WHERE session_id = '$session' AND class_id = '$classid' AND section_id = '$sectionnew'
            )
    ";
    if (mysqli_query($link, $sqlDeleteStale)) {
        echo "cleanup done: stale scores removed<br>";
    } else {
        echo "cleanup failed: " . mysqli_error($link) . '<br>';
    }

    $sqlGetstudent_session = "
        SELECT DISTINCT StudentID, lastname, middlename, firstname, admission_no,
               CONCAT(students.lastname, ' ', COALESCE(students.middlename, ''), ' ', students.firstname) AS full_name
        FROM britishresult
        INNER JOIN students ON britishresult.StudentID = students.id
        WHERE `Session` = '$session'
          AND ClassID = '$classid'
          AND SectionID = '$sectionnew'
          AND Term = '$term'
          AND students.is_active = 'yes'
        ORDER BY full_name ASC
    ";

    $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
    $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

    echo '<table class="table table-striped table-bordered" id="editable-datatable" style="margin-top: 30px;">
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Admission No.</th>
                    <th>Student Name</th>
                    <th>Remark</th>
                    <th>Additional Comments</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>';
    $cnt = 1;

    if ($countGetstudent_session > 0) {
        while ($rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session)) {
            $id = $rowGetstudent_session['StudentID'];

            $sqlGetremark = "SELECT * FROM `remark` 
                             WHERE StudentID='$id' 
                               AND `Session`='$session' 
                               AND Term = '$term' 
                               AND `RemarkType` = '$RemarkType'";
            $queryGetremark = mysqli_query($link, $sqlGetremark);
            $rowGetremark = mysqli_fetch_assoc($queryGetremark);
            $comment = $rowGetremark['remark'] ?? '';

            echo '<tr>
                    <td>' . $cnt++ . '</td>
                    <td>' . htmlspecialchars($rowGetstudent_session['admission_no']) . '</td>
                    <td>' . htmlspecialchars($rowGetstudent_session['lastname'] . ' ' . $rowGetstudent_session['middlename'] . ' ' . $rowGetstudent_session['firstname']) . '</td>
                    <td>' . htmlspecialchars($rowGetstudent_session['Remark']) . '</td>
                    <td>' . htmlspecialchars($rowGetstudent_session['AdditionalComments']) . '</td>
                    <td>
                        <textarea type="text" rows="4" class="form-control britishfield" 
                                  data-id="' . $id . '" 
                                  placeholder="input comment here">' . htmlspecialchars($comment) . '</textarea>
                    </td>
                  </tr>';
        }
    } else {
        echo '<tr><td colspan="6">No Records Found</td></tr>';
    }

    echo '</tbody></table>';
    exit;
}

// --------------------------------------------------------------------
//                    NORMAL (SCORE) BRANCH
// --------------------------------------------------------------------
// This branch handles non‑british, non‑kindergarten results

$sqlGetclass_sections = "SELECT * FROM `class_sections` WHERE `id`='$classsection'";
$queryGetclass_sections = mysqli_query($link, $sqlGetclass_sections);
$rowGetclass_sections = mysqli_fetch_assoc($queryGetclass_sections);
$countGetclass_sections = mysqli_num_rows($queryGetclass_sections);

if ($countGetclass_sections > 0) {

    $sectionnew = $rowGetclass_sections['section_id'];
    $class_id = $rowGetclass_sections['class_id'];

    // 2) CLEANUP: delete stale score rows
    $sqlDeleteStale = "
        DELETE FROM score
        WHERE `Session` = '$session'
            AND ClassID = '$classid'
            AND Term = '$term'
            AND SectionID = '$sectionnew'
            AND StudentID NOT IN (
                SELECT student_id FROM student_session
                WHERE session_id = '$session' AND class_id = '$classid' AND section_id = '$sectionnew'
            )
    ";
    if (mysqli_query($link, $sqlDeleteStale)) {
        echo "cleanup done: stale scores removed<br>";
    } else {
        echo "cleanup failed: " . mysqli_error($link) . '<br>';
    }

    $sqlGetstudent_session = "
        SELECT DISTINCT StudentID, lastname, middlename, firstname, admission_no,
               CONCAT(students.lastname, ' ', COALESCE(students.middlename, ''), ' ', students.firstname) AS full_name
        FROM score
        INNER JOIN students ON score.StudentID = students.id
        WHERE `Session` = '$session'
          AND ClassID = '$classid'
          AND SectionID = '$sectionnew'
          AND Term = '$term'
          AND students.is_active = 'yes'
        ORDER BY full_name ASC
    ";

    $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
    $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

    echo '<table class="table table-striped table-bordered" id="editable-datatable" style="margin-top: 30px;">
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Admission No.</th>
                    <th>Student Name</th>
                    <th>Average</th>
                    <th>Grade</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>';
    $cnt = 1;

    if ($countGetstudent_session > 0) {
        while ($rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session)) {
            $id = $rowGetstudent_session['StudentID'];

            $sqlGetremark = "SELECT * FROM `remark` 
                             WHERE StudentID='$id' 
                               AND `Session`='$session' 
                               AND Term = '$term' 
                               AND `RemarkType` = '$RemarkType'";
            $queryGetremark = mysqli_query($link, $sqlGetremark);
            $rowGetremark = mysqli_fetch_assoc($queryGetremark);
            $comment = $rowGetremark['remark'] ?? '';

            // Calculate average
            $sqlgetsubscore = "SELECT * FROM `score` 
                               WHERE (`Exam` != '0' OR `CA1` != '0' OR `CA2` != '0' OR `CA3` != '0' OR `CA4` != '0' OR `CA5` != '0' OR `CA6` != '0' OR `CA7` != '0' OR `CA8` != '0' OR `CA9` != '0' OR `CA10` != '0')
                               AND StudentID = '$id' 
                               AND ClassID = '$classid' 
                               AND Session = '$session' 
                               AND Term = '$term' 
                               AND SectionID = '$classsectionactual' 
                               AND SubjectID != 0";
            $resultgetsubscore = mysqli_query($link, $sqlgetsubscore);
            $row_cntgetsubscore = mysqli_num_rows($resultgetsubscore);

            $sqlgettotalgrade = "SELECT SUM(Exam + CA1 + CA2 + CA3 + CA4 + CA5 + CA6 + CA7 + CA8 + CA9 + CA10) AS total 
                                 FROM `score` 
                                 WHERE (`Exam` != '0' OR `CA1` != '0' OR `CA2` != '0' OR `CA3` != '0' OR `CA4` != '0' OR `CA5` != '0' OR `CA6` != '0' OR `CA7` != '0' OR `CA8` != '0' OR `CA9` != '0' OR `CA10` != '0')
                                 AND StudentID = '$id' 
                                 AND ClassID = '$classid' 
                                 AND Session = '$session' 
                                 AND Term = '$term' 
                                 AND SectionID = '$classsectionactual' 
                                 AND SubjectID != 0";
            $resultgettotalgrade = mysqli_query($link, $sqlgettotalgrade);
            $rowgettotalgrade = mysqli_fetch_assoc($resultgettotalgrade);
            $row_cntgettotalgrade = mysqli_num_rows($resultgettotalgrade);

            if ($row_cntgettotalgrade > 0 && $row_cntgetsubscore > 0) {
                $average = round($rowgettotalgrade['total'] / $row_cntgetsubscore, 2);
                $sqlgettotgradstuc = "SELECT * FROM gradingstructure 
                                      INNER JOIN assigngradingtclass ON gradingstructure.GradingTitle = assigngradingtclass.GradingTitle 
                                      WHERE $average >= RangeStart AND $average <= RangeEnd 
                                      AND ClassID = '$classid'";
                $resultgettotgradstuc = mysqli_query($link, $sqlgettotgradstuc);
                $rowgettotgradstuc = mysqli_fetch_assoc($resultgettotgradstuc);
                $grade = $rowgettotgradstuc['Grade'] ?? 'NA';
            } else {
                $average = 'NA';
                $grade = 'NA';
            }

            echo '<tr>
                    <td>' . $cnt++ . '</td>
                    <td>' . htmlspecialchars($rowGetstudent_session['admission_no']) . '</td>
                    <td>' . htmlspecialchars($rowGetstudent_session['lastname'] . ' ' . $rowGetstudent_session['middlename'] . ' ' . $rowGetstudent_session['firstname']) . '</td>
                    <td>' . $average . '</td>
                    <td>' . $grade . '</td>
                    <td>
                        <textarea type="text" rows="4" class="form-control britishfield" 
                                  data-id="' . $id . '" 
                                  placeholder="input comment here">' . htmlspecialchars($comment) . '</textarea>
                    </td>
                  </tr>';
        }
    } else {
        echo '<tr><td colspan="6">No Records Found</td></tr>';
    }

    echo '</tbody></table>';
} else {
    echo 'Class Section Not Found';
}
