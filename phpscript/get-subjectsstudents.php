<?php
include('../database/config.php');

$classsection = $_POST['classsection'];

$classsectionactual = $_POST['classsectionactual'];

$classid = $_POST['classid'];

$session = $_POST['session'];

$term = $_POST['term'];

$subjects = $_POST['subjects'];

$sqlGetclass_sections = "SELECT * FROM `class_sections` WHERE `id`='$classsection'";
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
        if ($rowGetGradingSystem['NumberOfCA'] == '1') {
            $ca1test = '<th>' . $rowGetGradingSystem['CA1Title'] . '(' . $rowGetGradingSystem['CA1Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberOfCA'] == '2') {
            $ca1test = '<th>' . $rowGetGradingSystem['CA1Title'] . '(' . $rowGetGradingSystem['CA1Score'] . ')</th> <th>' . $rowGetGradingSystem['CA2Title'] . '(' . $rowGetGradingSystem['CA2Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberOfCA'] == '3') {
            $ca1test = '<th>' . $rowGetGradingSystem['CA1Title'] . '(' . $rowGetGradingSystem['CA1Score'] . ')</th> <th>' . $rowGetGradingSystem['CA2Title'] . '(' . $rowGetGradingSystem['CA2Score'] . ')</th> <th>' . $rowGetGradingSystem['CA3Title'] . '(' . $rowGetGradingSystem['CA3Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberOfCA'] == '4') {
            $ca1test = '<th>' . $rowGetGradingSystem['CA1Title'] . '(' . $rowGetGradingSystem['CA1Score'] . ')</th> <th>' . $rowGetGradingSystem['CA2Title'] . '(' . $rowGetGradingSystem['CA2Score'] . ')</th> <th>' . $rowGetGradingSystem['CA3Title'] . '(' . $rowGetGradingSystem['CA3Score'] . ')</th> <th>' . $rowGetGradingSystem['CA4Title'] . '(' . $rowGetGradingSystem['CA4Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberOfCA'] == '5') {
            $ca1test = '<th>' . $rowGetGradingSystem['CA1Title'] . '(' . $rowGetGradingSystem['CA1Score'] . ')</th> <th>' . $rowGetGradingSystem['CA2Title'] . '(' . $rowGetGradingSystem['CA2Score'] . ')</th> <th>' . $rowGetGradingSystem['CA3Title'] . '(' . $rowGetGradingSystem['CA3Score'] . ')</th> <th>' . $rowGetGradingSystem['CA4Title'] . '(' . $rowGetGradingSystem['CA4Score'] . ')</th> <th>' . $rowGetGradingSystem['CA5Title'] . '(' . $rowGetGradingSystem['CA5Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberOfCA'] == '6') {
            $ca1test = '<th>' . $rowGetGradingSystem['CA1Title'] . '(' . $rowGetGradingSystem['CA1Score'] . ')</th> <th>' . $rowGetGradingSystem['CA2Title'] . '(' . $rowGetGradingSystem['CA2Score'] . ')</th> <th>' . $rowGetGradingSystem['CA3Title'] . '(' . $rowGetGradingSystem['CA3Score'] . ')</th> <th>' . $rowGetGradingSystem['CA4Title'] . '(' . $rowGetGradingSystem['CA4Score'] . ')</th> <th>' . $rowGetGradingSystem['CA5Title'] . '(' . $rowGetGradingSystem['CA5Score'] . ')</th> <th>' . $rowGetGradingSystem['CA6Title'] . '(' . $rowGetGradingSystem['CA6Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberOfCA'] == '7') {
            $ca1test = '<th>' . $rowGetGradingSystem['CA1Title'] . '(' . $rowGetGradingSystem['CA1Score'] . ')</th> <th>' . $rowGetGradingSystem['CA2Title'] . '(' . $rowGetGradingSystem['CA2Score'] . ')</th> <th>' . $rowGetGradingSystem['CA3Title'] . '(' . $rowGetGradingSystem['CA3Score'] . ')</th> <th>' . $rowGetGradingSystem['CA4Title'] . '(' . $rowGetGradingSystem['CA4Score'] . ')</th> <th>' . $rowGetGradingSystem['CA5Title'] . '(' . $rowGetGradingSystem['CA5Score'] . ')</th> <th>' . $rowGetGradingSystem['CA6Title'] . '(' . $rowGetGradingSystem['CA6Score'] . ')</th> <th>' . $rowGetGradingSystem['CA7Title'] . '(' . $rowGetGradingSystem['CA7Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberOfCA'] == '8') {
            $ca1test = '<th>' . $rowGetGradingSystem['CA1Title'] . '(' . $rowGetGradingSystem['CA1Score'] . ')</th> <th>' . $rowGetGradingSystem['CA2Title'] . '(' . $rowGetGradingSystem['CA2Score'] . ')</th> <th>' . $rowGetGradingSystem['CA3Title'] . '(' . $rowGetGradingSystem['CA3Score'] . ')</th> <th>' . $rowGetGradingSystem['CA4Title'] . '(' . $rowGetGradingSystem['CA4Score'] . ')</th> <th>' . $rowGetGradingSystem['CA5Title'] . '(' . $rowGetGradingSystem['CA5Score'] . ')</th> <th>' . $rowGetGradingSystem['CA6Title'] . '(' . $rowGetGradingSystem['CA6Score'] . ')</th> <th>' . $rowGetGradingSystem['CA7Title'] . '(' . $rowGetGradingSystem['CA7Score'] . ')</th> <th>' . $rowGetGradingSystem['CA8Title'] . '(' . $rowGetGradingSystem['CA8Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberOfCA'] == '9') {
            $ca1test = '<th>' . $rowGetGradingSystem['CA1Title'] . '(' . $rowGetGradingSystem['CA1Score'] . ')</th> <th>' . $rowGetGradingSystem['CA2Title'] . '(' . $rowGetGradingSystem['CA2Score'] . ')</th> <th>' . $rowGetGradingSystem['CA3Title'] . '(' . $rowGetGradingSystem['CA3Score'] . ')</th> <th>' . $rowGetGradingSystem['CA4Title'] . '(' . $rowGetGradingSystem['CA4Score'] . ')</th> <th>' . $rowGetGradingSystem['CA5Title'] . '(' . $rowGetGradingSystem['CA5Score'] . ')</th> <th>' . $rowGetGradingSystem['CA6Title'] . '(' . $rowGetGradingSystem['CA6Score'] . ')</th> <th>' . $rowGetGradingSystem['CA7Title'] . '(' . $rowGetGradingSystem['CA7Score'] . ')</th> <th>' . $rowGetGradingSystem['CA8Title'] . '(' . $rowGetGradingSystem['CA8Score'] . ')</th> <th>' . $rowGetGradingSystem['CA9Title'] . '(' . $rowGetGradingSystem['CA9Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberOfCA'] == '10') {
            $ca1test = '<th>' . $rowGetGradingSystem['CA1Title'] . '(' . $rowGetGradingSystem['CA1Score'] . ')</th> <th>' . $rowGetGradingSystem['CA2Title'] . '(' . $rowGetGradingSystem['CA2Score'] . ')</th> <th>' . $rowGetGradingSystem['CA3Title'] . '(' . $rowGetGradingSystem['CA3Score'] . ')</th> <th>' . $rowGetGradingSystem['CA4Title'] . '(' . $rowGetGradingSystem['CA4Score'] . ')</th> <th>' . $rowGetGradingSystem['CA5Title'] . '(' . $rowGetGradingSystem['CA5Score'] . ')</th> <th>' . $rowGetGradingSystem['CA6Title'] . '(' . $rowGetGradingSystem['CA6Score'] . ')</th> <th>' . $rowGetGradingSystem['CA7Title'] . '(' . $rowGetGradingSystem['CA7Score'] . ')</th> <th>' . $rowGetGradingSystem['CA8Title'] . '(' . $rowGetGradingSystem['CA8Score'] . ')</th> <th>' . $rowGetGradingSystem['CA9Title'] . '(' . $rowGetGradingSystem['CA9Score'] . ')</th> <th>' . $rowGetGradingSystem['CA10Title'] . '(' . $rowGetGradingSystem['CA10Score'] . ')</th>';
        } else {
            $ca1test = '';
        }
        echo '<span id="displaysmg" style="font-size:14px;"><div class="alert alert-primary" role="alert">
                    To input scores kindly click on the CA or Exam That you would like to input the score and input the score.
                </div></span>
                <table class="table table-striped table-bordered" id="editable-datatable" style="margin-top: 30px;">
                <thead>
                    <tr>
                        <th>S/N</th>
                        <th>Full Name</th>
                        <th>Admission No.</th>
                        ' . $ca1test . '
                        <th>Exam</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>';
        $cnt = 1;

        //$sqlGetstudent_session = "SELECT * FROM `score` INNER JOIN students ON score.StudentID=students.id AND `Session`='$session' AND ClassID = '$classid' AND SectionID = '$sectionnew' AND SubjectID = '$subjects' AND Term = '$term' AND Session = '$session'";
        $sqlGetstudent_session = "SELECT *, CONCAT(students.lastname, ' ', COALESCE(students.middlename, ''), ' ', students.firstname) AS full_name FROM `score` INNER JOIN students ON score.StudentID = students.id WHERE `Session` = '$session' AND ClassID = '$classid' AND SectionID = '$sectionnew' AND SubjectID = '$subjects' AND Term = '$term' AND students.is_active = 'yes' ORDER BY full_name ASC";

        $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
        $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
        $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

        if ($countGetstudent_session > 0) {
            do {
                echo '<tr id="' . $rowGetstudent_session["ID"] . '" class="edit_tr">
                        <td>' . $cnt++ . '</td>
                        <td>' . $rowGetstudent_session['lastname'] . ' ' . $rowGetstudent_session['middlename'] . ' ' . $rowGetstudent_session['firstname'] . '</td>
                        <td>' . $rowGetstudent_session['admission_no'] . '</td>';

                if ($rowGetGradingSystem['NumberOfCA'] == '1') {

                    echo '<td class="edit_td">
	                                <span id="ca1_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca1"] . '</span>
	                                <input type="text" value="' . $rowGetstudent_session["ca1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["ID"] . '"/>
	                            </td>
	                            <td style="display:none;">
	                                <span id="ca2_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca2"] . '</span>
	                                <input type="text" value="' . $rowGetstudent_session["ca2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["ID"] . '"/>
	                            </td>
	                            <td style="display:none;">
                                    <span id="ca3_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca3"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca4_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca4"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca5_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca5"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca6_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca6"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca7_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca7"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca8_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca8"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca9_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca9"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca10_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca10"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>';
                } elseif ($rowGetGradingSystem['NumberOfCA'] == '2') {
                    echo '<td class="edit_td">
                                    <span id="ca1_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca1"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td>
                                    <span id="ca2_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca2"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca3_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca3"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca4_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca4"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca5_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca5"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca6_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca6"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca7_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca7"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca8_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca8"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca9_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca9"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca10_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca10"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>';
                } elseif ($rowGetGradingSystem['NumberOfCA'] == '3') {
                    echo '<td class="edit_td">
                                    <span id="ca1_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca1"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td>
                                    <span id="ca2_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca2"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td>
                                    <span id="ca3_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca3"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca4_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca4"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca5_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca5"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca6_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca6"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca7_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca7"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca8_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca8"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca9_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca9"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca10_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca10"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>';
                } elseif ($rowGetGradingSystem['NumberOfCA'] == '4') {
                    echo '<td class="edit_td">
                                    <span id="ca1_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca1"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td>
                                    <span id="ca2_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca2"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td>
                                    <span id="ca3_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca3"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td>
                                    <span id="ca4_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca4"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca5_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca5"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca6_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca6"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca7_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca7"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca8_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca8"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca9_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca9"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca10_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca10"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>';
                } elseif ($rowGetGradingSystem['NumberOfCA'] == '5') {
                    echo '<td class="edit_td">
                                    <span id="ca1_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca1"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["ID"] . '"/>
                                    </td>
                                    <td>
                                    <span id="ca2_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca2"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["ID"] . '"/>
                                    </td>
                                    <td>
                                <span id="ca3_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca3"] . '</span>
                                <input type="text" value="' . $rowGetstudent_session["ca3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td>
                                    <span id="ca4_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca4"] . '</span>
                                <input type="text" value="' . $rowGetstudent_session["ca4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td>
                                    <span id="ca5_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca5"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca6_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca6"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["ID"] . '"/>
                                    </td>
                                    <td style="display:none;">
                                    <span id="ca7_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca7"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["ID"] . '"/>
                                    </td>
                                    <td style="display:none;">
                                <span id="ca8_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca8"] . '</span>
                                <input type="text" value="' . $rowGetstudent_session["ca8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca9_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca9"] . '</span>
                                <input type="text" value="' . $rowGetstudent_session["ca9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>
                                <td style="display:none;">
                                    <span id="ca10_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca10"] . '</span>
                                    <input type="text" value="' . $rowGetstudent_session["ca10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["ID"] . '"/>
                                </td>';
                } elseif ($rowGetGradingSystem['NumberOfCA'] == '6') {
                    echo '<td class="edit_td">
                                                        <span id="ca1_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca1"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["ID"] . '"/>
                                                        </td>
                                                        <td>
                                                        <span id="ca2_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca2"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["ID"] . '"/>
                                                        </td>
                                                        <td>
                                                    <span id="ca3_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca3"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td>
                                                        <span id="ca4_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca4"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td>
                                                        <span id="ca5_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca5"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td>
                                                        <span id="ca6_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca6"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                        <td style="display:none;">
                                                        <span id="ca7_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca7"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["ID"] . '"/>
                                                        </td>
                                                        <td style="display:none;">
                                                    <span id="ca8_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca8"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td style="display:none;">
                                                        <span id="ca9_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca9"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td style="display:none;">
                                                        <span id="ca10_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca10"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>';
                } elseif ($rowGetGradingSystem['NumberOfCA'] == '7') {
                    echo '<td class="edit_td">
                                                        <span id="ca1_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca1"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["ID"] . '"/>
                                                        </td>
                                                        <td>
                                                        <span id="ca2_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca2"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["ID"] . '"/>
                                                        </td>
                                                        <td>
                                                    <span id="ca3_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca3"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td>
                                                        <span id="ca4_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca4"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td>
                                                        <span id="ca5_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca5"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td>
                                                        <span id="ca6_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca6"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td>
                                                        <span id="ca7_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca7"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td style="display:none;">
                                                    <span id="ca8_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca8"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td style="display:none;">
                                                        <span id="ca9_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca9"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td style="display:none;">
                                                        <span id="ca10_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca10"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>';
                } elseif ($rowGetGradingSystem['NumberOfCA'] == '8') {
                    echo '<td class="edit_td">
                                                    <span id="ca1_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca1"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td>
                                                    <span id="ca2_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca2"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td>
                                                <span id="ca3_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca3"] . '</span>
                                                <input type="text" value="' . $rowGetstudent_session["ca3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["ID"] . '"/>
                                                </td>
                                                <td>
                                                    <span id="ca4_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca4"] . '</span>
                                                <input type="text" value="' . $rowGetstudent_session["ca4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["ID"] . '"/>
                                                </td>
                                                <td>
                                                    <span id="ca5_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca5"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["ID"] . '"/>
                                                </td>
                                                <td>
                                                    <span id="ca6_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca6"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["ID"] . '"/>
                                                </td>
                                                <td>
                                                    <span id="ca7_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca7"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["ID"] . '"/>
                                                </td>
                                                <td>
                                                    <span id="ca8_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca8"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["ID"] . '"/>
                                                </td>
                                                <td style="display:none;">
                                                    <span id="ca9_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca9"] . '</span>
                                                <input type="text" value="' . $rowGetstudent_session["ca9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["ID"] . '"/>
                                                </td>
                                                <td style="display:none;">
                                                    <span id="ca10_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca10"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["ID"] . '"/>
                                                </td>';
                } elseif ($rowGetGradingSystem['NumberOfCA'] == '9') {
                    echo '<td class="edit_td">
                                                    <span id="ca1_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca1"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td>
                                                    <span id="ca2_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca2"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td>
                                                <span id="ca3_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca3"] . '</span>
                                                <input type="text" value="' . $rowGetstudent_session["ca3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["ID"] . '"/>
                                                </td>
                                                <td>
                                                    <span id="ca4_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca4"] . '</span>
                                                <input type="text" value="' . $rowGetstudent_session["ca4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["ID"] . '"/>
                                                </td>
                                                <td>
                                                    <span id="ca5_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca5"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["ID"] . '"/>
                                                </td>
                                                <td>
                                                    <span id="ca6_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca6"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["ID"] . '"/>
                                                </td>
                                                <td>
                                                    <span id="ca7_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca7"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["ID"] . '"/>
                                                </td>
                                                <td>
                                                    <span id="ca8_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca8"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["ID"] . '"/>
                                                </td>
                                                <td>
                                                    <span id="ca9_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca9"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["ID"] . '"/>
                                                </td>
                                                <td style="display:none;">
                                                    <span id="ca10_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca10"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["ID"] . '"/>
                                                </td>';
                } elseif ($rowGetGradingSystem['NumberOfCA'] == '10') {
                    echo '<td class="edit_td">
                                                        <span id="ca1_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca1"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["ID"] . '"/>
                                                        </td>
                                                        <td>
                                                        <span id="ca2_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca2"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["ID"] . '"/>
                                                        </td>
                                                        <td>
                                                    <span id="ca3_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca3"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td>
                                                        <span id="ca4_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca4"] . '</span>
                                                    <input type="text" value="' . $rowGetstudent_session["ca4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td>
                                                        <span id="ca5_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca5"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td>
                                                        <span id="ca6_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca6"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td>
                                                        <span id="ca7_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca7"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td>
                                                        <span id="ca8_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca8"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td>
                                                        <span id="ca9_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca9"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>
                                                    <td>
                                                        <span id="ca10_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["ca10"] . '</span>
                                                        <input type="text" value="' . $rowGetstudent_session["ca10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["ID"] . '"/>
                                                    </td>';
                } else {
                    echo '';
                }

                if ($rowGetGradingSystem['NumberOfCA'] == '1') {
                    $ca1 = $rowGetstudent_session['ca1'];
                } elseif ($rowGetGradingSystem['NumberOfCA'] == '2') {
                    $ca1 = $rowGetstudent_session['ca1'] + $rowGetstudent_session['ca2'];
                } elseif ($rowGetGradingSystem['NumberOfCA'] == '3') {
                    $ca1 = $rowGetstudent_session['ca1'] + $rowGetstudent_session['ca2'] + $rowGetstudent_session['ca3'];
                } elseif ($rowGetGradingSystem['NumberOfCA'] == '4') {
                    $ca1 = $rowGetstudent_session['ca1'] + $rowGetstudent_session['ca2'] + $rowGetstudent_session['ca3'] + $rowGetstudent_session['ca4'];
                } elseif ($rowGetGradingSystem['NumberOfCA'] == '5') {
                    $ca1 = $rowGetstudent_session['ca1'] + $rowGetstudent_session['ca2'] + $rowGetstudent_session['ca3'] + $rowGetstudent_session['ca4'] + $rowGetstudent_session['ca5'];
                } elseif ($rowGetGradingSystem['NumberOfCA'] == '6') {
                    $ca1 = $rowGetstudent_session['ca1'] + $rowGetstudent_session['ca2'] + $rowGetstudent_session['ca3'] + $rowGetstudent_session['ca4'] + $rowGetstudent_session['ca5'] + $rowGetstudent_session['ca6'];
                } elseif ($rowGetGradingSystem['NumberOfCA'] == '7') {
                    $ca1 = $rowGetstudent_session['ca1'] + $rowGetstudent_session['ca2'] + $rowGetstudent_session['ca3'] + $rowGetstudent_session['ca4'] + $rowGetstudent_session['ca5'] + $rowGetstudent_session['ca6'] + $rowGetstudent_session['ca7'];
                } elseif ($rowGetGradingSystem['NumberOfCA'] == '8') {
                    $ca1 = $rowGetstudent_session['ca1'] + $rowGetstudent_session['ca2'] + $rowGetstudent_session['ca3'] + $rowGetstudent_session['ca4'] + $rowGetstudent_session['ca5'] + $rowGetstudent_session['ca6'] + $rowGetstudent_session['ca7'] + $rowGetstudent_session['ca8'];
                } elseif ($rowGetGradingSystem['NumberOfCA'] == '9') {
                    $ca1 = $rowGetstudent_session['ca1'] + $rowGetstudent_session['ca2'] + $rowGetstudent_session['ca3'] + $rowGetstudent_session['ca4'] + $rowGetstudent_session['ca5'] + $rowGetstudent_session['ca6'] + $rowGetstudent_session['ca7'] + $rowGetstudent_session['ca8'] + $rowGetstudent_session['ca9'];
                } elseif ($rowGetGradingSystem['NumberOfCA'] == '10') {
                    $ca1 = $rowGetstudent_session['ca1'] + $rowGetstudent_session['ca2'] + $rowGetstudent_session['ca3'] + $rowGetstudent_session['ca4'] + $rowGetstudent_session['ca5'] + $rowGetstudent_session['ca6'] + $rowGetstudent_session['ca7'] + $rowGetstudent_session['ca8'] + $rowGetstudent_session['ca9'] + $rowGetstudent_session['ca10'];
                } else {
                    $ca1 = 0;
                }
                $exam = $rowGetstudent_session['exam'];

                $total = $ca1 + $exam;

                echo '<td>
                                <span id="exam_' . $rowGetstudent_session["ID"] . '" class="text">' . $rowGetstudent_session["exam"] . '</span>
                                <input type="text" value="' . $rowGetstudent_session["exam"] . '" class="editbox" id="exam_input_' . $rowGetstudent_session["ID"] . '"/>
                            </td>
                            <td>
                                <span id="total_' . $rowGetstudent_session["ID"] . '" class="text">' . $total . '</span>
                            
                            </td>
                        
                            <td>
                                <a style="color: black; font-size: 15px; color:red;" href="#" data-toggle="modal" data-target="#delScore" data-id="' . $rowGetstudent_session["ID"] . '" data-name="' . $rowGetstudent_session['lastname'] . ' ' . $rowGetstudent_session['middlename'] . ' ' . $rowGetstudent_session['firstname'] . '\'s" id="delbtn">
                                    <i class="fa fa-close"></i>
                                </a>
                                <span style="display:none;"><input type="text" value="' . $rowGetstudent_session['lastname'] . ' ' . $rowGetstudent_session['middlename'] . ' ' . $rowGetstudent_session['firstname'] . '" class="editbox" id="studname_' . $rowGetstudent_session["ID"] . '"/></span>
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
