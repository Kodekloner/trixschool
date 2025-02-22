<?php
include('../database/config.php');

$classsectionactual = $_POST['classsectionactual'];

$classid = $_POST['classid'];

$session = $_POST['session'];

$term = $_POST['term'];

$RemarkType = $_POST['RemarkType'];

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

$sqlGetassigncatoclass = "SELECT * FROM `assigncatoclass` WHERE `ClassID`='$classid'";
$queryGetassigncatoclass = mysqli_query($link, $sqlGetassigncatoclass);
$rowGetassigncatoclass = mysqli_fetch_assoc($queryGetassigncatoclass);
$countGetassigncatoclass = mysqli_num_rows($queryGetassigncatoclass);

// $rowGetassigncatoclass['ResultType'];'numeric';

$reltype = $rowGetassigncatoclass['ResultType'];

if ($reltype == 'british') {
    $sqlGetstudent_session = "SELECT DISTINCT StudentID,lastname,middlename,firstname,admission_no FROM `britishresult` INNER JOIN students ON britishresult.StudentID=students.id AND `Session`='$session' AND ClassID = '$classid' AND SectionID = '$sectionnew' AND Term = '$term' AND britishresult.StudentID='$staffid'";

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
                        <th>Admission No.</th>
                        <th>Student Name</th>
                        <th>Remark</th>
                        <th>Additional Comments</th>
                        <th>Comment</th>
                    </tr>
                </thead>
                <tbody>';
        $cnt = 1;

        $sqlGetstudent_session = "SELECT DISTINCT StudentID,lastname,middlename,firstname,admission_no, CONCAT(students.lastname, ' ', COALESCE(students.middlename, ''), ' ', students.firstname) AS full_name FROM `britishresult` INNER JOIN students ON britishresult.StudentID=students.id AND `Session`='$session' AND ClassID = '$classid' AND SectionID = '$sectionnew' AND Term = '$term' AND students.is_active = 'yes' ORDER BY full_name ASC";
        $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
        $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
        $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

        if ($countGetstudent_session > 0) {
            do {

                $id = $rowGetstudent_session['StudentID'];

                $sqlGetremark = "SELECT * FROM `remark` WHERE StudentID='$id' AND `Session`='$session' AND Term = '$term' AND `RemarkType` = '$RemarkType'";
                $queryGetremark = mysqli_query($link, $sqlGetremark);
                $rowGetremark = mysqli_fetch_assoc($queryGetremark);
                $countGetremark = mysqli_num_rows($queryGetremark);

                echo '<tr>
                    			<td>' . $cnt++ . '</td>
                    			<td>' . $rowGetstudent_session['admission_no'] . '</td>
                    			<td>' . $rowGetstudent_session['lastname'] . ' ' . $rowGetstudent_session['middlename'] . ' ' . $rowGetstudent_session['firstname'] . '</td>
                    			<td>' . $$rowGetstudent_session['Remark'] . '</td>
                    			<td>' . $rowGetstudent_session['AdditionalComments'] . '</td>
                    			<td><textarea type="text" rows="4" class="form-control britishfield" data-id="' . $id . '" placeholder="input comment here">' . $rowGetremark['remark'] . '</textarea></td>';

                echo '</tr>';
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
                        <th>Admission No.</th>
                        <th>Student Name</th>
    					<th>Average</th>
    					<th>Grade</th>
                        <th>Comment</th>
                    </tr>
                </thead>
                <tbody>';
        $cnt = 1;

        $sqlGetstudent_session = "SELECT DISTINCT StudentID,lastname,middlename,firstname,admission_no, CONCAT(students.lastname, ' ', COALESCE(students.middlename, ''), ' ', students.firstname) AS full_name FROM `score`INNER JOIN students ON score.StudentID=students.id AND `Session`='$session' AND ClassID = '$classid' AND SectionID = '$sectionnew' AND Term = '$term' AND students.is_active = 'yes' ORDER BY full_name ASC";
        $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
        $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
        $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

        if ($countGetstudent_session > 0) {
            do {

                $id = $rowGetstudent_session['StudentID'];

                $sqlGetremark = "SELECT * FROM `remark` WHERE StudentID='$id' AND `Session`='$session' AND Term = '$term' AND `RemarkType` = '$RemarkType'";
                $queryGetremark = mysqli_query($link, $sqlGetremark);
                $rowGetremark = mysqli_fetch_assoc($queryGetremark);
                $countGetremark = mysqli_num_rows($queryGetremark);

                $sqlgetsubscore = ("SELECT * FROM `score` WHERE (`Exam` !='0' OR `CA1` !='0' OR `CA2` !='0' OR `CA3` !='0' OR `CA4` !='0' OR `CA5` !='0' OR `CA6` !='0' OR `CA7` !='0' OR `CA8` !='0' OR `CA9` !='0' OR `CA10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' AND SubjectID != 0");
                $resultgetsubscore = mysqli_query($link, $sqlgetsubscore);
                $rowgetsubscore = mysqli_fetch_assoc($resultgetsubscore);
                $row_cntgetsubscore = mysqli_num_rows($resultgetsubscore);

                $sqlgettotalgrade = "SELECT SUM(Exam + CA1 + CA2 + CA3 + CA4 + CA5 + CA6 + CA7 + CA8 + CA9 + CA10) AS average FROM `score` WHERE (`Exam` !='0' OR `CA1` !='0' OR `CA2` !='0' OR `CA3` !='0' OR `CA4` !='0' OR `CA5` !='0' OR `CA6` !='0' OR `CA7` !='0' OR `CA8` !='0' OR `CA9` !='0' OR `CA10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' AND SubjectID != 0";
                $resultgettotalgrade = mysqli_query($link, $sqlgettotalgrade);
                $rowgettotalgrade = mysqli_fetch_assoc($resultgettotalgrade);
                $row_cntgettotalgrade = mysqli_num_rows($resultgettotalgrade);

                $gettotgrade = floatval(round($rowgettotalgrade['average'] / $row_cntgetsubscore, 2));

                if ($row_cntgettotalgrade > 0) {
                    $sqlgettotgradstuc = ("SELECT * FROM `gradingstructure` INNER JOIN assigngradingtclass ON gradingstructure.GradingTitle = assigngradingtclass.GradingTitle WHERE $gettotgrade >= RangeStart AND $gettotgrade <= RangeEnd AND ClassID = '$classid'");
                    $resultgettotgradstuc = mysqli_query($link, $sqlgettotgradstuc);
                    $rowgettotgradstuc = mysqli_fetch_assoc($resultgettotgradstuc);
                    $row_cntgettotgradstuc = mysqli_num_rows($resultgettotgradstuc);

                    if ($row_cntgettotgradstuc > 0) {

                        $totscorgrade = $rowgettotgradstuc['Grade'];
                    } else {

                        $totscorgrade = 'NA';
                    }
                } else {
                    $gettotgrade = 'NA';
                }

                echo '<tr>
                    			<td>' . $cnt++ . '</td>
                    			<td>' . $rowGetstudent_session['admission_no'] . '</td>
                    			<td>' . $rowGetstudent_session['lastname'] . ' ' . $rowGetstudent_session['middlename'] . ' ' . $rowGetstudent_session['firstname'] . '</td>
                    			<td>' . $gettotgrade . '</td>
                    			<td>' . $totscorgrade . '</td>
                    			<td><textarea type="text" rows="4" class="form-control britishfield" data-id="' . $id . '" placeholder="input comment here">' . $rowGetremark['remark'] . '</textarea></td>';

                echo '</tr>';
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
