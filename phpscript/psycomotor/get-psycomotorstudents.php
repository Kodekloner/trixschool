<?php
include('../../database/config.php');

$classsectionactual = $_POST['classsectionactual'];

$classid = $_POST['classid'];

$session = $_POST['session'];

$term = $_POST['term'];

// $sqlGetclass_sections = "SELECT * FROM `class_sections` WHERE `id`='$classsectionactual'";
// $queryGetclass_sections = mysqli_query($link, $sqlGetclass_sections);
// $rowGetclass_sections = mysqli_fetch_assoc($queryGetclass_sections);
// $countGetclass_sections = mysqli_num_rows($queryGetclass_sections);

// if($countGetclass_sections > 0){

//     $sectionnew = $rowGetclass_sections['section_id'];

//     $class_id = $rowGetclass_sections['class_id'];

$sqlGetGradingSystem = "SELECT * FROM `assignspsycomotortoclass` INNER JOIN psycomotor_settings ON assignspsycomotortoclass.PsycomotorSettingsId=psycomotor_settings.id WHERE assignspsycomotortoclass.ClassID='$class_id'";
$queryGetGradingSystem = mysqli_query($link, $sqlGetGradingSystem);
$rowGetGradingSystem = mysqli_fetch_assoc($queryGetGradingSystem);
$countGetGradingSystem = mysqli_num_rows($queryGetGradingSystem);

if ($countGetGradingSystem > 0) {
    if ($rowGetGradingSystem['NumberofP'] == '1') {
        $ca1test = '<th>' . $rowGetGradingSystem['P1Title'] . '(' . $rowGetGradingSystem['P1Score'] . ')</th>';
    } elseif ($rowGetGradingSystem['NumberofP'] == '2') {
        $ca1test = '<th>' . $rowGetGradingSystem['P1Title'] . '(' . $rowGetGradingSystem['P1Score'] . ')</th> <th>' . $rowGetGradingSystem['P2Title'] . '(' . $rowGetGradingSystem['P2Score'] . ')</th>';
    } elseif ($rowGetGradingSystem['NumberofP'] == '3') {
        $ca1test = '<th>' . $rowGetGradingSystem['P1Title'] . '(' . $rowGetGradingSystem['P1Score'] . ')</th> <th>' . $rowGetGradingSystem['P2Title'] . '(' . $rowGetGradingSystem['P2Score'] . ')</th> <th>' . $rowGetGradingSystem['P3Title'] . '(' . $rowGetGradingSystem['P3Score'] . ')</th>';
    } elseif ($rowGetGradingSystem['NumberofP'] == '4') {
        $ca1test = '<th>' . $rowGetGradingSystem['P1Title'] . '(' . $rowGetGradingSystem['P1Score'] . ')</th> <th>' . $rowGetGradingSystem['P2Title'] . '(' . $rowGetGradingSystem['P2Score'] . ')</th> <th>' . $rowGetGradingSystem['P3Title'] . '(' . $rowGetGradingSystem['P3Score'] . ')</th> <th>' . $rowGetGradingSystem['P4Title'] . '(' . $rowGetGradingSystem['P4Score'] . ')</th>';
    } elseif ($rowGetGradingSystem['NumberofP'] == '5') {
        $ca1test = '<th>' . $rowGetGradingSystem['P1Title'] . '(' . $rowGetGradingSystem['P1Score'] . ')</th> <th>' . $rowGetGradingSystem['P2Title'] . '(' . $rowGetGradingSystem['P2Score'] . ')</th> <th>' . $rowGetGradingSystem['P3Title'] . '(' . $rowGetGradingSystem['P3Score'] . ')</th> <th>' . $rowGetGradingSystem['P4Title'] . '(' . $rowGetGradingSystem['P4Score'] . ')</th> <th>' . $rowGetGradingSystem['P5Title'] . '(' . $rowGetGradingSystem['P5Score'] . ')</th>';
    } elseif ($rowGetGradingSystem['NumberofP'] == '6') {
        $ca1test = '<th>' . $rowGetGradingSystem['P1Title'] . '(' . $rowGetGradingSystem['P1Score'] . ')</th> <th>' . $rowGetGradingSystem['P2Title'] . '(' . $rowGetGradingSystem['P2Score'] . ')</th> <th>' . $rowGetGradingSystem['P3Title'] . '(' . $rowGetGradingSystem['P3Score'] . ')</th> <th>' . $rowGetGradingSystem['P4Title'] . '(' . $rowGetGradingSystem['P4Score'] . ')</th> <th>' . $rowGetGradingSystem['P5Title'] . '(' . $rowGetGradingSystem['P5Score'] . ')</th> <th>' . $rowGetGradingSystem['P6Title'] . '(' . $rowGetGradingSystem['P6Score'] . ')</th>';
    } elseif ($rowGetGradingSystem['NumberofP'] == '7') {
        $ca1test = '<th>' . $rowGetGradingSystem['P1Title'] . '(' . $rowGetGradingSystem['P1Score'] . ')</th> <th>' . $rowGetGradingSystem['P2Title'] . '(' . $rowGetGradingSystem['P2Score'] . ')</th> <th>' . $rowGetGradingSystem['P3Title'] . '(' . $rowGetGradingSystem['P3Score'] . ')</th> <th>' . $rowGetGradingSystem['P4Title'] . '(' . $rowGetGradingSystem['P4Score'] . ')</th> <th>' . $rowGetGradingSystem['P5Title'] . '(' . $rowGetGradingSystem['P5Score'] . ')</th> <th>' . $rowGetGradingSystem['P6Title'] . '(' . $rowGetGradingSystem['P6Score'] . ')</th> <th>' . $rowGetGradingSystem['P7Title'] . '(' . $rowGetGradingSystem['P7Score'] . ')</th>';
    } elseif ($rowGetGradingSystem['NumberofP'] == '8') {
        $ca1test = '<th>' . $rowGetGradingSystem['P1Title'] . '(' . $rowGetGradingSystem['P1Score'] . ')</th> <th>' . $rowGetGradingSystem['P2Title'] . '(' . $rowGetGradingSystem['P2Score'] . ')</th> <th>' . $rowGetGradingSystem['P3Title'] . '(' . $rowGetGradingSystem['P3Score'] . ')</th> <th>' . $rowGetGradingSystem['P4Title'] . '(' . $rowGetGradingSystem['P4Score'] . ')</th> <th>' . $rowGetGradingSystem['P5Title'] . '(' . $rowGetGradingSystem['P5Score'] . ')</th> <th>' . $rowGetGradingSystem['P6Title'] . '(' . $rowGetGradingSystem['P6Score'] . ')</th> <th>' . $rowGetGradingSystem['P7Title'] . '(' . $rowGetGradingSystem['P7Score'] . ')</th> <th>' . $rowGetGradingSystem['P8Title'] . '(' . $rowGetGradingSystem['P8Score'] . ')</th>';
    } elseif ($rowGetGradingSystem['NumberofP'] == '9') {
        $ca1test = '<th>' . $rowGetGradingSystem['P1Title'] . '(' . $rowGetGradingSystem['P1Score'] . ')</th> <th>' . $rowGetGradingSystem['P2Title'] . '(' . $rowGetGradingSystem['P2Score'] . ')</th> <th>' . $rowGetGradingSystem['P3Title'] . '(' . $rowGetGradingSystem['P3Score'] . ')</th> <th>' . $rowGetGradingSystem['P4Title'] . '(' . $rowGetGradingSystem['P4Score'] . ')</th> <th>' . $rowGetGradingSystem['P5Title'] . '(' . $rowGetGradingSystem['P5Score'] . ')</th> <th>' . $rowGetGradingSystem['P6Title'] . '(' . $rowGetGradingSystem['P6Score'] . ')</th> <th>' . $rowGetGradingSystem['P7Title'] . '(' . $rowGetGradingSystem['P7Score'] . ')</th> <th>' . $rowGetGradingSystem['P8Title'] . '(' . $rowGetGradingSystem['P8Score'] . ')</th> <th>' . $rowGetGradingSystem['P9Title'] . '(' . $rowGetGradingSystem['P9Score'] . ')</th>';
    } elseif ($rowGetGradingSystem['NumberofP'] == '10') {
        $ca1test = '<th>' . $rowGetGradingSystem['P1Title'] . '(' . $rowGetGradingSystem['P1Score'] . ')</th> <th>' . $rowGetGradingSystem['P2Title'] . '(' . $rowGetGradingSystem['P2Score'] . ')</th> <th>' . $rowGetGradingSystem['P3Title'] . '(' . $rowGetGradingSystem['P3Score'] . ')</th> <th>' . $rowGetGradingSystem['P4Title'] . '(' . $rowGetGradingSystem['P4Score'] . ')</th> <th>' . $rowGetGradingSystem['P5Title'] . '(' . $rowGetGradingSystem['P5Score'] . ')</th> <th>' . $rowGetGradingSystem['P6Title'] . '(' . $rowGetGradingSystem['P6Score'] . ')</th> <th>' . $rowGetGradingSystem['P7Title'] . '(' . $rowGetGradingSystem['P7Score'] . ')</th> <th>' . $rowGetGradingSystem['P8Title'] . '(' . $rowGetGradingSystem['P8Score'] . ')</th> <th>' . $rowGetGradingSystem['P9Title'] . '(' . $rowGetGradingSystem['P9Score'] . ')</th> <th>' . $rowGetGradingSystem['P10Title'] . '(' . $rowGetGradingSystem['P10Score'] . ')</th>';
    } elseif ($rowGetGradingSystem['NumberofP'] == '11') {
        $ca1test = '<th>' . $rowGetGradingSystem['P1Title'] . '(' . $rowGetGradingSystem['P1Score'] . ')</th> <th>' . $rowGetGradingSystem['P2Title'] . '(' . $rowGetGradingSystem['P2Score'] . ')</th> <th>' . $rowGetGradingSystem['P3Title'] . '(' . $rowGetGradingSystem['P3Score'] . ')</th> <th>' . $rowGetGradingSystem['P4Title'] . '(' . $rowGetGradingSystem['P4Score'] . ')</th> <th>' . $rowGetGradingSystem['P5Title'] . '(' . $rowGetGradingSystem['P5Score'] . ')</th> <th>' . $rowGetGradingSystem['P6Title'] . '(' . $rowGetGradingSystem['P6Score'] . ')</th> <th>' . $rowGetGradingSystem['P7Title'] . '(' . $rowGetGradingSystem['P7Score'] . ')</th> <th>' . $rowGetGradingSystem['P8Title'] . '(' . $rowGetGradingSystem['P8Score'] . ')</th> <th>' . $rowGetGradingSystem['P9Title'] . '(' . $rowGetGradingSystem['P9Score'] . ')</th> <th>' . $rowGetGradingSystem['P10Title'] . '(' . $rowGetGradingSystem['P10Score'] . ')</th> <th>' . $rowGetGradingSystem['P11Title'] . '(' . $rowGetGradingSystem['P11Score'] . ')</th>';
    } elseif ($rowGetGradingSystem['NumberofP'] == '12') {
        $ca1test = '<th>' . $rowGetGradingSystem['P1Title'] . '(' . $rowGetGradingSystem['P1Score'] . ')</th> <th>' . $rowGetGradingSystem['P2Title'] . '(' . $rowGetGradingSystem['P2Score'] . ')</th> <th>' . $rowGetGradingSystem['P3Title'] . '(' . $rowGetGradingSystem['P3Score'] . ')</th> <th>' . $rowGetGradingSystem['P4Title'] . '(' . $rowGetGradingSystem['P4Score'] . ')</th> <th>' . $rowGetGradingSystem['P5Title'] . '(' . $rowGetGradingSystem['P5Score'] . ')</th> <th>' . $rowGetGradingSystem['P6Title'] . '(' . $rowGetGradingSystem['P6Score'] . ')</th> <th>' . $rowGetGradingSystem['P7Title'] . '(' . $rowGetGradingSystem['P7Score'] . ')</th> <th>' . $rowGetGradingSystem['P8Title'] . '(' . $rowGetGradingSystem['P8Score'] . ')</th> <th>' . $rowGetGradingSystem['P9Title'] . '(' . $rowGetGradingSystem['P9Score'] . ')</th> <th>' . $rowGetGradingSystem['P10Title'] . '(' . $rowGetGradingSystem['P10Score'] . ')</th> <th>' . $rowGetGradingSystem['P11Title'] . '(' . $rowGetGradingSystem['P11Score'] . ')</th> <th>' . $rowGetGradingSystem['P12Title'] . '(' . $rowGetGradingSystem['P12Score'] . ')</th>';
    } elseif ($rowGetGradingSystem['NumberofP'] == '13') {
        $ca1test = '<th>' . $rowGetGradingSystem['P1Title'] . '(' . $rowGetGradingSystem['P1Score'] . ')</th> <th>' . $rowGetGradingSystem['P2Title'] . '(' . $rowGetGradingSystem['P2Score'] . ')</th> <th>' . $rowGetGradingSystem['P3Title'] . '(' . $rowGetGradingSystem['P3Score'] . ')</th> <th>' . $rowGetGradingSystem['P4Title'] . '(' . $rowGetGradingSystem['P4Score'] . ')</th> <th>' . $rowGetGradingSystem['P5Title'] . '(' . $rowGetGradingSystem['P5Score'] . ')</th> <th>' . $rowGetGradingSystem['P6Title'] . '(' . $rowGetGradingSystem['P6Score'] . ')</th> <th>' . $rowGetGradingSystem['P7Title'] . '(' . $rowGetGradingSystem['P7Score'] . ')</th> <th>' . $rowGetGradingSystem['P8Title'] . '(' . $rowGetGradingSystem['P8Score'] . ')</th> <th>' . $rowGetGradingSystem['P9Title'] . '(' . $rowGetGradingSystem['P9Score'] . ')</th> <th>' . $rowGetGradingSystem['P10Title'] . '(' . $rowGetGradingSystem['P10Score'] . ')</th> <th>' . $rowGetGradingSystem['P11Title'] . '(' . $rowGetGradingSystem['P11Score'] . ')</th> <th>' . $rowGetGradingSystem['P12Title'] . '(' . $rowGetGradingSystem['P12Score'] . ')</th> <th>' . $rowGetGradingSystem['P13Title'] . '(' . $rowGetGradingSystem['P13core'] . ')</th>';
    } elseif ($rowGetGradingSystem['NumberofP'] == '14') {
        $ca1test = '<th>' . $rowGetGradingSystem['P1Title'] . '(' . $rowGetGradingSystem['P1Score'] . ')</th> <th>' . $rowGetGradingSystem['P2Title'] . '(' . $rowGetGradingSystem['P2Score'] . ')</th> <th>' . $rowGetGradingSystem['P3Title'] . '(' . $rowGetGradingSystem['P3Score'] . ')</th> <th>' . $rowGetGradingSystem['P4Title'] . '(' . $rowGetGradingSystem['P4Score'] . ')</th> <th>' . $rowGetGradingSystem['P5Title'] . '(' . $rowGetGradingSystem['P5Score'] . ')</th> <th>' . $rowGetGradingSystem['P6Title'] . '(' . $rowGetGradingSystem['P6Score'] . ')</th> <th>' . $rowGetGradingSystem['P7Title'] . '(' . $rowGetGradingSystem['P7Score'] . ')</th> <th>' . $rowGetGradingSystem['P8Title'] . '(' . $rowGetGradingSystem['P8Score'] . ')</th> <th>' . $rowGetGradingSystem['P9Title'] . '(' . $rowGetGradingSystem['P9Score'] . ')</th> <th>' . $rowGetGradingSystem['P10Title'] . '(' . $rowGetGradingSystem['P10Score'] . ')</th> <th>' . $rowGetGradingSystem['P11Title'] . '(' . $rowGetGradingSystem['P11Score'] . ')</th> <th>' . $rowGetGradingSystem['P12Title'] . '(' . $rowGetGradingSystem['P12Score'] . ')</th> <th>' . $rowGetGradingSystem['P13Title'] . '(' . $rowGetGradingSystem['P13core'] . ')</th> <th>' . $rowGetGradingSystem['P14Title'] . '(' . $rowGetGradingSystem['P14Score'] . ')</th>';
    } elseif ($rowGetGradingSystem['NumberofP'] == '15') {
        $ca1test = '<th>' . $rowGetGradingSystem['P1Title'] . '(' . $rowGetGradingSystem['P1Score'] . ')</th> <th>' . $rowGetGradingSystem['P2Title'] . '(' . $rowGetGradingSystem['P2Score'] . ')</th> <th>' . $rowGetGradingSystem['P3Title'] . '(' . $rowGetGradingSystem['P3Score'] . ')</th> <th>' . $rowGetGradingSystem['P4Title'] . '(' . $rowGetGradingSystem['P4Score'] . ')</th> <th>' . $rowGetGradingSystem['P5Title'] . '(' . $rowGetGradingSystem['P5Score'] . ')</th> <th>' . $rowGetGradingSystem['P6Title'] . '(' . $rowGetGradingSystem['P6Score'] . ')</th> <th>' . $rowGetGradingSystem['P7Title'] . '(' . $rowGetGradingSystem['P7Score'] . ')</th> <th>' . $rowGetGradingSystem['P8Title'] . '(' . $rowGetGradingSystem['P8Score'] . ')</th> <th>' . $rowGetGradingSystem['P9Title'] . '(' . $rowGetGradingSystem['P9Score'] . ')</th> <th>' . $rowGetGradingSystem['P10Title'] . '(' . $rowGetGradingSystem['P10Score'] . ')</th> <th>' . $rowGetGradingSystem['P11Title'] . '(' . $rowGetGradingSystem['P11Score'] . ')</th> <th>' . $rowGetGradingSystem['P12Title'] . '(' . $rowGetGradingSystem['P12Score'] . ')</th> <th>' . $rowGetGradingSystem['P13Title'] . '(' . $rowGetGradingSystem['P13core'] . ')</th> <th>' . $rowGetGradingSystem['P14Title'] . '(' . $rowGetGradingSystem['P14Score'] . ')</th> <th>' . $rowGetGradingSystem['P15Title'] . '(' . $rowGetGradingSystem['P15Score'] . ')</th>';
    } else {
        $ca1test = '';
    }
    echo '<span id="displaysmg" style="font-size:14px;"><div class="alert alert-primary" role="alert">
                    To input scores kindly click on the field That you would like to input the score and input the score.
                </div></span>
              <table class="table table-striped table-bordered" id="editable-datatable" style="margin-top: 30px;">
                <thead>
                    <tr>
                        <th>S/N</th>
                        <th>Full Name</th>
                        <th>Admission No.</th>
                        ' . $ca1test . '
                    </tr>
                </thead>
                <tbody>';
    $cnt = 1;

    $sqlGetstudent_session = "SELECT * FROM `students` INNER JOIN psycomotor_score ON students.id=psycomotor_score.studentid AND psycomotor_score.session='$session' AND psycomotor_score.classid = '$classid' AND psycomotor_score.sectionid = '$classsectionactual' AND psycomotor_score.term = '$term'";
    $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
    $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
    $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

    if ($countGetstudent_session > 0) {
        do {
            echo '<tr id="' . $rowGetstudent_session["id"] . '" class="edit_tr">
                    			<td>' . $cnt++ . '</td>
                    			<td>' . $rowGetstudent_session['lastname'] . ' ' . $rowGetstudent_session['middlename'] . ' ' . $rowGetstudent_session['firstname'] . '</td>
                    			<td>' . $rowGetstudent_session['admission_no'] . '</td>';

            if ($rowGetGradingSystem['NumberofP'] == '1') {
                echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                              <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor6"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                               <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor7"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                           <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor8"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor9"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
            } elseif ($rowGetGradingSystem['NumberofP'] == '2') {
                echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                              <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor6"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                               <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor7"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                           <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor8"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor9"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
            } elseif ($rowGetGradingSystem['NumberofP'] == '3') {
                echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                              <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor6"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                               <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor7"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                           <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor8"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor9"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
            } elseif ($rowGetGradingSystem['NumberofP'] == '4') {
                echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                              <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor6"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                               <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor7"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                           <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor8"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor9"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
            } elseif ($rowGetGradingSystem['NumberofP'] == '5') {
                echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                              <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor6"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                               <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor7"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                           <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor8"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor9"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
            } elseif ($rowGetGradingSystem['NumberofP'] == '6') {
                echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                            <td style="display:none;">
                                               <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor7"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                           <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor8"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor9"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
            } elseif ($rowGetGradingSystem['NumberofP'] == '7') {
                echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor7"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                           <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor8"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor9"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
            } elseif ($rowGetGradingSystem['NumberofP'] == '8') {
                echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor7"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor8"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor9"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
            } elseif ($rowGetGradingSystem['NumberofP'] == '9') {
                echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor7"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor8"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor9"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
            } elseif ($rowGetGradingSystem['NumberofP'] == '10') {
                echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor7"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor8"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor9"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
            } elseif ($rowGetGradingSystem['NumberofP'] == '11') {
                echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor7"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor8"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor9"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
            } elseif ($rowGetGradingSystem['NumberofP'] == '12') {
                echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor7"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor8"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor9"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
            } elseif ($rowGetGradingSystem['NumberofP'] == '13') {
                echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor7"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor8"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor9"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
            } elseif ($rowGetGradingSystem['NumberofP'] == '14') {
                echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor7"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor8"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor9"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
            } elseif ($rowGetGradingSystem['NumberofP'] == '15') {
                echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["psycomotor1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["psycomotor2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["psycomotor4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor7"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor8"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor9"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["psycomotor15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["psycomotor15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
            } else {
                echo '';
            }

            // if($rowGetGradingSystem['NumberofAD'] =='1')
            // {
            //     $ca1 = $rowGetstudent_session['psycomotor1'];
            // }
            // elseif($rowGetGradingSystem['NumberofAD'] =='2')
            // {
            //     $ca1 = $rowGetstudent_session['psycomotor1'] + $rowGetstudent_session['psycomotor2'];
            // }
            // elseif($rowGetGradingSystem['NumberofAD'] =='3')
            // {
            //     $ca1 = $rowGetstudent_session['psycomotor1'] + $rowGetstudent_session['psycomotor2'] + $rowGetstudent_session['psycomotor3'];
            // }
            // elseif($rowGetGradingSystem['NumberofAD'] =='4')
            // {
            //     $ca1 = $rowGetstudent_session['psycomotor1'] + $rowGetstudent_session['psycomotor2'] + $rowGetstudent_session['psycomotor3'] + $rowGetstudent_session['psycomotor4'];
            // }
            // elseif($rowGetGradingSystem['NumberofAD'] =='5')
            // {
            //     $ca1 = $rowGetstudent_session['psycomotor1'] + $rowGetstudent_session['psycomotor2'] + $rowGetstudent_session['psycomotor3'] + $rowGetstudent_session['psycomotor4'] + $rowGetstudent_session['psycomotor5'];
            // }
            // elseif($rowGetGradingSystem['NumberofAD'] =='6')
            // {
            //     $ca1 = $rowGetstudent_session['psycomotor1'] + $rowGetstudent_session['psycomotor2'] + $rowGetstudent_session['psycomotor3'] + $rowGetstudent_session['psycomotor4'] + $rowGetstudent_session['psycomotor5'] + $rowGetstudent_session['psycomotor6'];
            // }
            // elseif($rowGetGradingSystem['NumberofAD'] =='7')
            // {
            //     $ca1 = $rowGetstudent_session['psycomotor1'] + $rowGetstudent_session['psycomotor2'] + $rowGetstudent_session['psycomotor3'] + $rowGetstudent_session['psycomotor4'] + $rowGetstudent_session['psycomotor5'] + $rowGetstudent_session['psycomotor6'] + $rowGetstudent_session['psycomotor7'];
            // }
            // elseif($rowGetGradingSystem['NumberofAD'] =='8')
            // {
            //     $ca1 = $rowGetstudent_session['psycomotor1'] + $rowGetstudent_session['psycomotor2'] + $rowGetstudent_session['psycomotor3'] + $rowGetstudent_session['psycomotor4'] + $rowGetstudent_session['psycomotor5'] + $rowGetstudent_session['psycomotor6'] + $rowGetstudent_session['psycomotor7'] + $rowGetstudent_session['psycomotor8'];
            // }
            // elseif($rowGetGradingSystem['NumberofAD'] =='9')
            // {
            //     $ca1 = $rowGetstudent_session['psycomotor1'] + $rowGetstudent_session['psycomotor2'] + $rowGetstudent_session['psycomotor3'] + $rowGetstudent_session['psycomotor4'] + $rowGetstudent_session['psycomotor5'] + $rowGetstudent_session['psycomotor6'] + $rowGetstudent_session['psycomotor7'] + $rowGetstudent_session['psycomotor8'] + $rowGetstudent_session['psycomotor9'];
            // }
            // elseif($rowGetGradingSystem['NumberofAD'] =='10')
            // {
            //     $ca1 = $rowGetstudent_session['psycomotor1'] + $rowGetstudent_session['psycomotor2'] + $rowGetstudent_session['psycomotor3'] + $rowGetstudent_session['psycomotor4'] + $rowGetstudent_session['psycomotor5'] + $rowGetstudent_session['psycomotor6'] + $rowGetstudent_session['psycomotor7'] + $rowGetstudent_session['psycomotor8'] + $rowGetstudent_session['psycomotor9'] + $rowGetstudent_session['psycomotor10'];
            // }
            // else{
            //     $ca1 = 0;
            // }

            echo '
                    			
                    			<td>
                                    <a style="color: black; font-size: 15px; color:red;" href="#" data-toggle="modal" data-target="#delScore" data-id="' . $rowGetstudent_session["id"] . '" data-name="' . $rowGetstudent_session['lastname'] . ' ' . $rowGetstudent_session['middlename'] . ' ' . $rowGetstudent_session['firstname'] . '\'s" id="delbtn">
                                        <i class="fa fa-close"></i>
                                    </a>
                                        <span style="display:none;"><input type="text" value="' . $rowGetstudent_session['lastname'] . ' ' . $rowGetstudent_session['middlename'] . ' ' . $rowGetstudent_session['firstname'] . '" class="editbox" id="studname_' . $rowGetstudent_session["id"] . '"/></span>
                                </td>       
                    		</tr>';
        } while ($rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session));
    } else {
        echo '<tr><td>No Records Found</td></tr>';
    }

    echo '</tbody>
            </table>';
} else {
    echo 'No Psycomotor has been set for this class';
}
// }
// else
// {
//     echo 'Class Section Not Found';
// }
