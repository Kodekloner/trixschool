<?php
include('../database/config.php');

$classsectionactual = $_POST['classsectionactual'];

$classid = $_POST['classid'];

$session = $_POST['session'];

$term = $_POST['term'];

$reltype = $_POST['reltype'];

$rolefirst = $_POST['rolefirst'];

$staffid = $_POST['staffid'];

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

    if ($reltype == 'cummulative') {
        $sqlGetstudent_session = "SELECT * FROM `publishresult` WHERE `Session`='$session' AND ResultType= '$reltype' AND Date <= '$reldate'";
    } else {
        $sqlGetstudent_session = "SELECT * FROM `publishresult` WHERE `Session`='$session' AND ResultType= '$reltype' AND Term = '$term' AND Date <= '$reldate'";
    }

    $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
    $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
    $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

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

            echo '<table class="table table-striped table-bordered" id="editable-datatable" style="margin-top: 30px;">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Full Name</th>
                            <th>Admission No.</th>
                            <th>Class</th>
                            <th>Session</th>
                            <th>Term</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>';
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
                    echo '<tr>
                        			<td>' . $cnt++ . '</td>
                        			<td>' . $rowGetstudent_session['lastname'] . ' ' . $rowGetstudent_session['middlename'] . ' ' . $rowGetstudent_session['firstname'] . '</td>
                        			<td>' . $rowGetstudent_session['admission_no'] . '</td>
                        			<td>' . $rowGetclasses['class'] . '</td>
                        			<td>' . $rowGetsessions['session'] . '</td>';

                    if ($reltype == 'cummulative') {
                        echo '<td>Cummulative</td>';
                    } else {
                        echo '<td>' . $term . ' Term</td>';
                    }
                    if ($is_kindergarten) {
                        echo '<td>
                                        <a href="kindergarten_result_page.php?classsection=' . $classsection . '&classsectionactual=' . $classsectionactual . '&classid=' . $classid . '&session=' . $session . '&term=' . $term . '&id=' . $rowGetstudent_session['StudentID'] . '&reltype=' . $reltype . '&assessment_id=' . $kindergarten_assessment_id . '" style="font-size: 15px;text-decoration:underline;">
                                            View Result
                                        </a>
                                    </td>';

                        echo '</tr>';
                    } else {
                        echo '<td>
                                        <a href="resultPage.php?classsection=' . $classsection . '&classsectionactual=' . $classsectionactual . '&classid=' . $classid . '&session=' . $session . '&term=' . $term . '&id=' . $rowGetstudent_session['StudentID'] . '&reltype=' . $reltype . '" style="font-size: 15px;text-decoration:underline;">
                                            View Result
                                        </a>
                                    </td>';

                        echo '</tr>';
                    }
                } while ($rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session));
            } else {
                echo '<tr><td>No Records Found</td></tr>';
            }

            echo '</tbody>
                </table>';
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

        echo '<table class="table table-striped table-bordered" id="editable-datatable" style="margin-top: 30px;">
                <thead>
                    <tr>
                        <th>S/N</th>
                        <th>Full Name</th>
                        <th>Admission No.</th>
                        <th>Class</th>
                        <th>Session</th>
                        <th>Term</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>';
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
                echo '<tr>
                    			<td>' . $cnt++ . '</td>
                    			<td>' . $rowGetstudent_session['lastname'] . ' ' . $rowGetstudent_session['middlename'] . ' ' . $rowGetstudent_session['firstname'] . '</td>
                    			<td>' . $rowGetstudent_session['admission_no'] . '</td>
                    			<td>' . $rowGetclasses['class'] . '</td>
                    			<td>' . $rowGetsessions['session'] . '</td>';

                if ($reltype == 'cummulative') {
                    echo '<td>Cummulative</td>';
                } else {
                    echo '<td>' . $term . ' Term</td>';
                }

                if ($is_kindergarten) {
                    echo '<td>
                                    <a href="kindergarten_result_page.php?classsection=' . $classsection . '&classsectionactual=' . $classsectionactual . '&classid=' . $classid . '&session=' . $session . '&term=' . $term . '&id=' . $rowGetstudent_session['StudentID'] . '&reltype=' . $reltype . '&assessment_id=' . $kindergarten_assessment_id . '" style="font-size: 15px;text-decoration:underline;">
                                        View Result
                                    </a>
                                </td>';

                    echo '</tr>';
                } else {

                    echo '<td>
                                        <a href="resultPage.php?classsection=' . $classsection . '&classsectionactual=' . $classsectionactual . '&classid=' . $classid . '&session=' . $session . '&term=' . $term . '&id=' . $rowGetstudent_session['StudentID'] . '&reltype=' . $reltype . '" style="font-size: 15px;text-decoration:underline;">
                                            View Result
                                        </a>
                                    </td>';

                    echo '</tr>';
                }
            } while ($rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session));
        } else {
            echo '<tr><td>No Records Found</td></tr>';
        }

        echo '</tbody>
            </table>';
    } else {
        echo 'Class Section Not Found';
    }
}
