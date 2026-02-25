<?php
include('../database/config.php');

$classsectionactual = $_POST['classsectionactual'];
$classid = $_POST['classid'];
$session = $_POST['session'];
$term = $_POST['term'];
$RemarkType = $_POST['RemarkType'];

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

// --- Check if this class has a kindergarten assessment assigned ---
$sql_kindergarten = "SELECT assessment_id FROM kindergarten_assignment WHERE class_id = '$classid' LIMIT 1";
$result_kindergarten = mysqli_query($link, $sql_kindergarten);
$is_kindergarten = (mysqli_num_rows($result_kindergarten) > 0);
$kindergarten_assessment_id = $is_kindergarten ? mysqli_fetch_assoc($result_kindergarten)['assessment_id'] : 0;

// --------------------------------------------------------------------
//                            KINDERGARTEN BRANCH
// --------------------------------------------------------------------
if ($is_kindergarten) {
    // Base student list from student_session
    $sqlGetstudent_session = "
        SELECT 
            s.id AS StudentID,
            s.admission_no,
            s.lastname,
            s.middlename,
            s.firstname,
            CONCAT(s.lastname, ' ', COALESCE(s.middlename, ''), ' ', s.firstname) AS full_name
        FROM student_session ss
        INNER JOIN students s ON ss.student_id = s.id
        WHERE ss.session_id = '$session'
          AND ss.class_id = '$classid'
          AND ss.section_id = '$sectionnew'
          AND s.is_active = 'yes'
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
    exit;
}

// --------------------------------------------------------------------
//                    BRITISH RESULT BRANCH
// --------------------------------------------------------------------
if ($reltype == 'british') {
    // Cleanup stale rows (optional, keep as is)
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
    mysqli_query($link, $sqlDeleteStale);   // no echo needed in production

    // Base student list from student_session
    $sqlGetstudent_session = "
        SELECT 
            s.id AS StudentID,
            s.admission_no,
            s.lastname,
            s.middlename,
            s.firstname,
            CONCAT(s.lastname, ' ', COALESCE(s.middlename, ''), ' ', s.firstname) AS full_name,
            br.Remark,
            br.AdditionalComments
        FROM student_session ss
        INNER JOIN students s ON ss.student_id = s.id
        LEFT JOIN britishresult br ON br.StudentID = s.id 
            AND br.Session = '$session' 
            AND br.ClassID = '$classid' 
            AND br.SectionID = '$sectionnew' 
            AND br.Term = '$term'
        WHERE ss.session_id = '$session'
          AND ss.class_id = '$classid'
          AND ss.section_id = '$sectionnew'
          AND s.is_active = 'yes'
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
        while ($row = mysqli_fetch_assoc($queryGetstudent_session)) {
            $id = $row['StudentID'];

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
                    <td>' . htmlspecialchars($row['Remark'] ?? '') . '</td>
                    <td>' . htmlspecialchars($row['AdditionalComments'] ?? '') . '</td>
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

// Cleanup stale rows (optional)
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
mysqli_query($link, $sqlDeleteStale);

// Base student list from student_session
$sqlGetstudent_session = "
    SELECT 
        s.id AS StudentID,
        s.admission_no,
        s.lastname,
        s.middlename,
        s.firstname,
        CONCAT(s.lastname, ' ', COALESCE(s.middlename, ''), ' ', s.firstname) AS full_name
    FROM student_session ss
    INNER JOIN students s ON ss.student_id = s.id
    WHERE ss.session_id = '$session'
      AND ss.class_id = '$classid'
      AND ss.section_id = '$sectionnew'
      AND s.is_active = 'yes'
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
    while ($row = mysqli_fetch_assoc($queryGetstudent_session)) {
        $id = $row['StudentID'];

        // Fetch existing comment (if any)
        $sqlGetremark = "SELECT * FROM `remark` 
                         WHERE StudentID='$id' 
                           AND `Session`='$session' 
                           AND Term = '$term' 
                           AND `RemarkType` = '$RemarkType'";
        $queryGetremark = mysqli_query($link, $sqlGetremark);
        $rowGetremark = mysqli_fetch_assoc($queryGetremark);
        $comment = $rowGetremark['remark'] ?? '';

        // Calculate average from score table (only if scores exist)
        $sqlgetsubscore = "SELECT COUNT(*) AS subj_count FROM `score` 
                           WHERE StudentID = '$id' 
                             AND ClassID = '$classid' 
                             AND Session = '$session' 
                             AND Term = '$term' 
                             AND SectionID = '$classsectionactual' 
                             AND SubjectID != 0
                             AND (Exam != 0 OR CA1 != 0 OR CA2 != 0 OR CA3 != 0 OR CA4 != 0 OR CA5 != 0 OR CA6 != 0 OR CA7 != 0 OR CA8 != 0 OR CA9 != 0 OR CA10 != 0)";
        $resultgetsubscore = mysqli_query($link, $sqlgetsubscore);
        $rowsubj = mysqli_fetch_assoc($resultgetsubscore);
        $subject_count = $rowsubj['subj_count'];

        $sqlgettotalgrade = "SELECT SUM(Exam + CA1 + CA2 + CA3 + CA4 + CA5 + CA6 + CA7 + CA8 + CA9 + CA10) AS total 
                             FROM `score` 
                             WHERE StudentID = '$id' 
                               AND ClassID = '$classid' 
                               AND Session = '$session' 
                               AND Term = '$term' 
                               AND SectionID = '$classsectionactual' 
                               AND SubjectID != 0
                               AND (Exam != 0 OR CA1 != 0 OR CA2 != 0 OR CA3 != 0 OR CA4 != 0 OR CA5 != 0 OR CA6 != 0 OR CA7 != 0 OR CA8 != 0 OR CA9 != 0 OR CA10 != 0)";
        $resultgettotalgrade = mysqli_query($link, $sqlgettotalgrade);
        $rowgettotalgrade = mysqli_fetch_assoc($resultgettotalgrade);

        if ($subject_count > 0 && $rowgettotalgrade['total'] !== null) {
            $average = round($rowgettotalgrade['total'] / $subject_count, 2);
            // Fetch grade from grading structure
            $sqlgettotgradstuc = "SELECT * FROM gradingstructure 
                                  INNER JOIN assigngradingtclass ON gradingstructure.GradingTitle = assigngradingtclass.GradingTitle 
                                  WHERE $average >= RangeStart AND $average <= RangeEnd 
                                    AND assigngradingtclass.ClassID = '$classid'";
            $resultgettotgradstuc = mysqli_query($link, $sqlgettotgradstuc);
            $rowgettotgradstuc = mysqli_fetch_assoc($resultgettotgradstuc);
            $grade = $rowgettotgradstuc['Grade'] ?? 'NA';
        } else {
            $average = 'NA';
            $grade = 'NA';
        }

        echo '<tr>
                <td>' . $cnt++ . '</td>
                <td>' . htmlspecialchars($row['admission_no']) . '</td>
                <td>' . htmlspecialchars($row['lastname'] . ' ' . $row['middlename'] . ' ' . $row['firstname']) . '</td>
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
