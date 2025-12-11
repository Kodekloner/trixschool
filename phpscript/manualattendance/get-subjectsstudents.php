<?php
include('../../database/config.php');

$classsection = $_POST['classsection'];

$classid = $_POST['classid'];

$session = $_POST['session'];

$term = $_POST['term'];

$sqlGetclass_sections = "SELECT * FROM `class_sections` WHERE `section_id`='$classsection' AND `class_id`='$classid'";

$queryGetclass_sections = mysqli_query($link, $sqlGetclass_sections);
$rowGetclass_sections = mysqli_fetch_assoc($queryGetclass_sections);
$countGetclass_sections = mysqli_num_rows($queryGetclass_sections);

if ($countGetclass_sections > 0) {

    $sectionnew = $rowGetclass_sections['section_id'];

    $class_id = $rowGetclass_sections['class_id'];

    $sqlGetGradingSystem = "SELECT * FROM `assigncatoclass` INNER JOIN resultsetting ON assigncatoclass.ResultSettingID=resultsetting.ResultSettingID WHERE assigncatoclass.ClassID='$class_id'";
    $queryGetGradingSystem = mysqli_query($link, $sqlGetGradingSystem);
    $rowGetGradingSystem = mysqli_fetch_assoc($queryGetGradingSystem);
    $countGetGradingSystem = mysqli_num_rows($queryGetGradingSystem);

    if ($countGetGradingSystem > 0) {

        echo '<span id="displaysmg" style="font-size:14px;"><div class="alert alert-primary" role="alert">
                    To input attendance kindly click on the field That you would like to input the score and input the score.
                </div></span>
              <table class="table table-striped table-bordered" id="editable-datatable" style="margin-top: 30px;">
                <thead>
                    <tr>
                        <th>S/N</th>
                        <th>Full Name</th>
                        <th>Admission No.</th>
                        <th>Total Att</th>
                        <th>Present Att</th>
                        <th>Late Att</th>
                        <th>Absent Att</th>

                    </tr>
                </thead>
                <tbody>';
        $cnt = 1;

        // $sqlGetstudent_session = "SELECT * FROM `score` INNER JOIN students ON score.StudentID=students.id AND `Session`='$session' AND ClassID = '$classid' AND SectionID = '$sectionnew' AND SubjectID = '0' AND Term = '$term' AND Session = '$session'";
        $sqlGetstudent_session = "
                        SELECT ss.student_id, students.lastname, students.middlename, students.firstname, students.admission_no,
                            CONCAT(students.lastname, ' ', COALESCE(students.middlename, ''), ' ', students.firstname) AS full_name,
                            score.ID as scoreID, score.exam, score.ca1, score.ca2, score.ca3, score.ca4, score.ca5, score.ca6,
                            score.ca7, score.ca8, score.ca9, score.ca10
                        FROM student_session ss
                        INNER JOIN students ON ss.student_id = students.id
                        LEFT JOIN score ON score.StudentID = students.id
                            AND score.Session = '$session'
                            AND score.ClassID = '$class_id'
                            AND score.SectionID = '$sectionnew'
                            AND score.SubjectID = '0'
                            AND score.Term = '$term'
                        WHERE ss.session_id = '$session'
                        AND ss.class_id = '$class_id'
                        AND ss.section_id = '$sectionnew'
                        AND students.is_active = 'yes'
                        ORDER BY full_name ASC
                    ";
        $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
        $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
        $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);
        if ($countGetstudent_session > 0) {
            echo $rowGetstudent_session["CA"];
            do {
                echo '<tr id="' . $rowGetstudent_session["scoreID"] . '" class="edit_tr">
                    			<td>' . $cnt++ . '</td>
                    			<td>' . $rowGetstudent_session['lastname'] . ' ' . $rowGetstudent_session['middlename'] . ' ' . $rowGetstudent_session['firstname'] . '</td>
                    			<td>' . $rowGetstudent_session['admission_no'] . '</td>';


                echo '
                                <td>
                                    <span id="ca1_' . $rowGetstudent_session["scoreID"] . '" class="text">' . $rowGetstudent_session["ca1"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["scoreID"] . '"/>
                                    
                                </td>
                                
                                <td>
                                    <span id="ca2_' . $rowGetstudent_session["scoreID"] . '" class="text">' . $rowGetstudent_session["ca2"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["scoreID"] . '"/>
                                    
                                </td>
                                
                                <td>
                                    <span id="ca3_' . $rowGetstudent_session["scoreID"] . '" class="text">' . $rowGetstudent_session["ca3"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["scoreID"] . '"/>
                                    
                                </td>
                                
                                <td>
                                    <span id="ca4_' . $rowGetstudent_session["scoreID"] . '" class="text">' . $rowGetstudent_session["ca4"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["scoreID"] . '"/>
                                    
                                </td>
                                
                    			
                    		</tr>';
            } while ($rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session));
        } else {
            echo '<tr><td>No Records Found</td></tr>';
        }

        echo '</tbody>
            </table>';
    } else {
        echo 'No CA has been set for this class';
    }
} else {
    echo 'Class Section Not Found';
}
