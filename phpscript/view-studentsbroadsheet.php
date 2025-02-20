<?php
include('../database/config.php');

$classsectionactual = $_POST['classsectionactual'];

$classid = $_POST['classid'];

$session = $_POST['session'];

$term = $_POST['term'];


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

    $sqlgetteachremark = ("SELECT * FROM `staff` INNER JOIN class_teacher ON staff.id=class_teacher.staff_id WHERE class_id = '$classid' AND section_id = '$sectionnew' AND session_id = '$session'");
    $resultgetteachremark = mysqli_query($link, $sqlgetteachremark);
    $rowgetteachremark = mysqli_fetch_assoc($resultgetteachremark);
    $row_cntgetteachremark = mysqli_num_rows($resultgetteachremark);

    if ($row_cntgetteachremark > 0) {
        $teacherRemark = $rowgetteachremark['surname'] . ' ' . $rowgetteachremark['name'];
    } else {
        $teacherRemark = 'N/A';
    }

    echo '<table class="rotate-table-grid" style="font-size: 13px;">
                            
                              
                    <tr>
                        <th colspan="2" style="width: 100px;">    
                            <p style="color: black; margin-top: 20px; font-size: 10px; margin-left: -5px;"><b>SESSION: ' . $rowGetsessions['session'] . '</b></p>
                            <p style="color: black; margin-left: -5px; font-size: 10px;"><b>TERM: ' . $term . '</b></p>
                            <p style="color: black; margin-left: -5px; font-size: 10px;"><b>CLASS: ' . $rowGetclasses['class'] . '</b></p>
                            <p style="color: black; margin-left: -5px; font-size: 10px;"><b>FORM TEACHER: ' . $teacherRemark . '</b></p>
                        </th>';

    $subjectList = [];

    $sqlsub = ("SELECT subjects.name AS name, subjects.id as id FROM `subject_group_class_sections` INNER JOIN subject_group_subjects ON subject_group_class_sections.subject_group_id=subject_group_subjects.subject_group_id INNER JOIN subjects ON subject_group_subjects.subject_id=subjects.id WHERE subject_group_class_sections.class_section_id = '$classsection' AND subject_group_class_sections.session_id='$session' AND subject_group_subjects.session_id='$session' ORDER BY name ASC");
    $resultsub = mysqli_query($link, $sqlsub);
    $rowGetsub = mysqli_fetch_assoc($resultsub);
    $row_cntsub = mysqli_num_rows($resultsub);

    if ($row_cntsub > 0) {

        do {
            $subjectList[] = $rowGetsub;
            $subname = $rowGetsub['name'];

            echo '<th><span><b>' . $subname . '</b></span></th>';
        } while ($rowGetsub = mysqli_fetch_assoc($resultsub));
    } else {
        echo '<tr><td align="center" colspan="15" style="font-size: calc(12px + (18 - 12) * ((100vw - 300px) / (1600 - 300)))"><div class="alert alert-info alert-dismissible mb-2" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>No Result Yet</div></tr></td>';
    }
    echo '
                      <th><span><b>TOTAL SCORE</span></b></th>
                      <th><span><b>AVERAGE SCORE</span></b></th>
                      <th><span><b>GRADE</span></b></th>
                      <th><span><b>REMARK</span></b></th>
                      </tr>
                  
                  <tbody>
                      <tr>
                          <th style="width: 20px;"><b>S/N</b></th>
                            <th style="width: 300px;"><b>NAMES OF STUDENTS</b></th>';
    $cnt = 0;
    do {
        $cnt++;
        echo '<td> </td>';
    } while ($row_cntsub > $cnt);

    echo '<td> </td>
                              <td> </td>
                              <td> </td>
                              <td> </td>
                              
                        </tr>';

    $studentsData = []; // Array to store all student data

    $sqlGetstudent_session = "SELECT DISTINCT StudentID,lastname,middlename,firstname,admission_no FROM `score`INNER JOIN students ON score.StudentID=students.id AND `Session`='$session' AND ClassID = '$classid' AND SectionID = '$sectionnew' AND Term = '$term' AND students.is_active = 'yes'";
    $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
    $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
    $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

    if ($countGetstudent_session > 0) {
        do {

            $id = $rowGetstudent_session['StudentID'];

            // Array to store each subject's score for this student
            $subjectScores = [];

            // Loop through each subject in the list and get the student's score
            foreach ($subjectList as $subject) {

                $subid = $subject['id'];

                $sqlgetscore = ("SELECT SUM(`Exam` + `CA1` + `CA2` + `CA3` + `CA4` + `CA5` + `CA6` + `CA7` + `CA8` + `CA9` + `CA10`) AS Total FROM `score` WHERE (`Exam` !='0' OR `CA1` !='0' OR `CA2` !='0' OR `CA3` !='0' OR `CA4` !='0' OR `CA5` !='0' OR `CA6` !='0' OR `CA7` !='0' OR `CA8` !='0' OR `CA9` !='0' OR `CA10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                $resultgetscore = mysqli_query($link, $sqlgetscore);
                $rowgetscore = mysqli_fetch_assoc($resultgetscore);
                $subjectScore = (!empty($rowgetscore['Total'])) ? $rowgetscore['Total'] : 0;
                $subjectScores[] = $subjectScore;
                // $row_cntgetscore = mysqli_num_rows($resultgetscore);
            }

            $sqlgetscoretotal = ("SELECT SUM(`Exam` + `CA1` + `CA2` + `CA3` + `CA4` + `CA5` + `CA6` + `CA7` + `CA8` + `CA9` + `CA10`) AS Total FROM `score` WHERE (`Exam` !='0' OR `CA1` !='0' OR `CA2` !='0' OR `CA3` !='0' OR `CA4` !='0' OR `CA5` !='0' OR `CA6` !='0' OR `CA7` !='0' OR `CA8` !='0' OR `CA9` !='0' OR `CA10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SubjectID != 0 AND SectionID = '$classsectionactual'");
            $resultgetscoretotal = mysqli_query($link, $sqlgetscoretotal);
            $rowgetscoretotal = mysqli_fetch_assoc($resultgetscoretotal);
            $totalScore = (!empty($rowgetscoretotal['Total'])) ? $rowgetscoretotal['Total'] : 0;

            $sqlgetscoreAVG = ("SELECT AVG(`Exam` + `CA1` + `CA2` + `CA3` + `CA4` + `CA5` + `CA6` + `CA7` + `CA8` + `CA9` + `CA10`) AS Total FROM `score` WHERE (`Exam` !='0' OR `CA1` !='0' OR `CA2` !='0' OR `CA3` !='0' OR `CA4` !='0' OR `CA5` !='0' OR `CA6` !='0' OR `CA7` !='0' OR `CA8` !='0' OR `CA9` !='0' OR `CA10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SubjectID != 0 AND SectionID = '$classsectionactual'");
            $resultgetscoreAVG = mysqli_query($link, $sqlgetscoreAVG);
            $rowgetscoreAVG = mysqli_fetch_assoc($resultgetscoreAVG);
            $averageScore = (!empty($rowgetscoreAVG['Total'])) ? $rowgetscoreAVG['Total'] : 0;

            // Get grade and remark based on the average score
            if ($averageScore != 0) {
                $sqlgetgradstuc = "SELECT * 
                                                    FROM `gradingstructure` 
                                                    INNER JOIN assigngradingtclass 
                                                        ON gradingstructure.GradingTitle = assigngradingtclass.GradingTitle 
                                                    WHERE $averageScore >= RangeStart 
                                                        AND $averageScore <= RangeEnd 
                                                        AND ClassID = '$classid'";
                $resultgetgradstuc = mysqli_query($link, $sqlgetgradstuc);
                $rowgetgradstuc = mysqli_fetch_assoc($resultgetgradstuc);
                if ($rowgetgradstuc) {
                    $grade  = $rowgetgradstuc['Grade'];
                    $remark = $rowgetgradstuc['Remark'];
                } else {
                    $grade  = 'NA';
                    $remark = 'NA';
                }
            } else {
                $grade  = 'NA';
                $remark = 'NA';
            }

            // Store all data for this student
            $studentsData[] = [
                'student'       => $rowGetstudent_session,
                'subjectScores' => $subjectScores,
                'totalScore'    => $totalScore,
                'averageScore'  => $averageScore,
                'grade'         => $grade,
                'remark'        => $remark
            ];
        } while ($rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session));
    }

    // Sort the studentsData array by averageScore in descending order
    usort($studentsData, function ($a, $b) {
        return $b['averageScore'] <=> $a['averageScore'];
    });

    // Output the sorted data
    $count = 1;

    foreach ($studentsData as $data) {
        $student = $data['student'];

        echo '<tr>
                                
                            <th><b>' . $count++ . '</b></th>
                                
                            <td><b>' . $student['lastname'] . ' ' . $student['middlename'] . ' ' . $student['firstname'] . ' (' . $student['admission_no'] . ')</b></td>';

        // Display each subject's score
        foreach ($data['subjectScores'] as $score) {
            echo '<td><b>' . $score . '</b></td>';
        }

        // Display the total score
        echo '<td><b>' . $data['totalScore'] . '</b></td>';

        // Display the average score formatted to one decimal place
        echo '<td><b>' . number_format($data['averageScore'], 1) . '</b></td>';

        // Display grade and remark
        echo '<td><b>' . $data['grade'] . '</b></td>';
        echo '<td><b>' . $data['remark'] . '</b></td>';
        echo '</tr>';
    }
    if (empty($studentsData)) {
        echo '<tr><td colspan="10"><b>No students found.</b></td></tr>';
    }
    echo '</tbody>
                </table>';
} else {
    echo 'Class Section Not Found';
}
