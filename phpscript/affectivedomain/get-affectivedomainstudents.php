<?php
include('../../database/config.php');

$classsectionactual = $_POST['classsectionactual'];

$classid = $_POST['classid'];

$session = $_POST['session'];

$term = $_POST['term'];

$sqlGetclass_sections = "SELECT * FROM `class_sections` WHERE `section_id`='$classsectionactual'";
$queryGetclass_sections = mysqli_query($link, $sqlGetclass_sections);
$rowGetclass_sections = mysqli_fetch_assoc($queryGetclass_sections);
$countGetclass_sections = mysqli_num_rows($queryGetclass_sections);

if ($countGetclass_sections > 0) {

    $sectionnew = $rowGetclass_sections['section_id'];

    $class_id = $rowGetclass_sections['class_id'];

    $sqlGetGradingSystem = "SELECT * FROM `assignsaftoclass` INNER JOIN affective_domain_settings ON assignsaftoclass.AffectiveDomainSettingsId=affective_domain_settings.id WHERE assignsaftoclass.ClassID='$class_id'";
    $queryGetGradingSystem = mysqli_query($link, $sqlGetGradingSystem);
    $rowGetGradingSystem = mysqli_fetch_assoc($queryGetGradingSystem);
    $countGetGradingSystem = mysqli_num_rows($queryGetGradingSystem);

    if ($countGetGradingSystem > 0) {
        if ($rowGetGradingSystem['NumberofAD'] == '1') {
            $ca1test = '<th>' . $rowGetGradingSystem['AD1Title'] . '(' . $rowGetGradingSystem['AD1Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberofAD'] == '2') {
            $ca1test = '<th>' . $rowGetGradingSystem['AD1Title'] . '(' . $rowGetGradingSystem['AD1Score'] . ')</th> <th>' . $rowGetGradingSystem['AD2Title'] . '(' . $rowGetGradingSystem['AD2Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberofAD'] == '3') {
            $ca1test = '<th>' . $rowGetGradingSystem['AD1Title'] . '(' . $rowGetGradingSystem['AD1Score'] . ')</th> <th>' . $rowGetGradingSystem['AD2Title'] . '(' . $rowGetGradingSystem['AD2Score'] . ')</th> <th>' . $rowGetGradingSystem['AD3Title'] . '(' . $rowGetGradingSystem['AD3Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberofAD'] == '4') {
            $ca1test = '<th>' . $rowGetGradingSystem['AD1Title'] . '(' . $rowGetGradingSystem['AD1Score'] . ')</th> <th>' . $rowGetGradingSystem['AD2Title'] . '(' . $rowGetGradingSystem['AD2Score'] . ')</th> <th>' . $rowGetGradingSystem['AD3Title'] . '(' . $rowGetGradingSystem['AD3Score'] . ')</th> <th>' . $rowGetGradingSystem['AD4Title'] . '(' . $rowGetGradingSystem['AD4Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberofAD'] == '5') {
            $ca1test = '<th>' . $rowGetGradingSystem['AD1Title'] . '(' . $rowGetGradingSystem['AD1Score'] . ')</th> <th>' . $rowGetGradingSystem['AD2Title'] . '(' . $rowGetGradingSystem['AD2Score'] . ')</th> <th>' . $rowGetGradingSystem['AD3Title'] . '(' . $rowGetGradingSystem['AD3Score'] . ')</th> <th>' . $rowGetGradingSystem['AD4Title'] . '(' . $rowGetGradingSystem['AD4Score'] . ')</th> <th>' . $rowGetGradingSystem['AD5Title'] . '(' . $rowGetGradingSystem['AD5Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberofAD'] == '6') {
            $ca1test = '<th>' . $rowGetGradingSystem['AD1Title'] . '(' . $rowGetGradingSystem['AD1Score'] . ')</th> <th>' . $rowGetGradingSystem['AD2Title'] . '(' . $rowGetGradingSystem['AD2Score'] . ')</th> <th>' . $rowGetGradingSystem['AD3Title'] . '(' . $rowGetGradingSystem['AD3Score'] . ')</th> <th>' . $rowGetGradingSystem['AD4Title'] . '(' . $rowGetGradingSystem['AD4Score'] . ')</th> <th>' . $rowGetGradingSystem['AD5Title'] . '(' . $rowGetGradingSystem['AD5Score'] . ')</th> <th>' . $rowGetGradingSystem['AD6Title'] . '(' . $rowGetGradingSystem['AD6Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberofAD'] == '7') {
            $ca1test = '<th>' . $rowGetGradingSystem['AD1Title'] . '(' . $rowGetGradingSystem['AD1Score'] . ')</th> <th>' . $rowGetGradingSystem['AD2Title'] . '(' . $rowGetGradingSystem['AD2Score'] . ')</th> <th>' . $rowGetGradingSystem['AD3Title'] . '(' . $rowGetGradingSystem['AD3Score'] . ')</th> <th>' . $rowGetGradingSystem['AD4Title'] . '(' . $rowGetGradingSystem['AD4Score'] . ')</th> <th>' . $rowGetGradingSystem['AD5Title'] . '(' . $rowGetGradingSystem['AD5Score'] . ')</th> <th>' . $rowGetGradingSystem['AD6Title'] . '(' . $rowGetGradingSystem['AD6Score'] . ')</th> <th>' . $rowGetGradingSystem['AD7Title'] . '(' . $rowGetGradingSystem['AD7Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberofAD'] == '8') {
            $ca1test = '<th>' . $rowGetGradingSystem['AD1Title'] . '(' . $rowGetGradingSystem['AD1Score'] . ')</th> <th>' . $rowGetGradingSystem['AD2Title'] . '(' . $rowGetGradingSystem['AD2Score'] . ')</th> <th>' . $rowGetGradingSystem['AD3Title'] . '(' . $rowGetGradingSystem['AD3Score'] . ')</th> <th>' . $rowGetGradingSystem['AD4Title'] . '(' . $rowGetGradingSystem['AD4Score'] . ')</th> <th>' . $rowGetGradingSystem['AD5Title'] . '(' . $rowGetGradingSystem['AD5Score'] . ')</th> <th>' . $rowGetGradingSystem['AD6Title'] . '(' . $rowGetGradingSystem['AD6Score'] . ')</th> <th>' . $rowGetGradingSystem['AD7Title'] . '(' . $rowGetGradingSystem['AD7Score'] . ')</th> <th>' . $rowGetGradingSystem['AD8Title'] . '(' . $rowGetGradingSystem['AD8Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberofAD'] == '9') {
            $ca1test = '<th>' . $rowGetGradingSystem['AD1Title'] . '(' . $rowGetGradingSystem['AD1Score'] . ')</th> <th>' . $rowGetGradingSystem['AD2Title'] . '(' . $rowGetGradingSystem['AD2Score'] . ')</th> <th>' . $rowGetGradingSystem['AD3Title'] . '(' . $rowGetGradingSystem['AD3Score'] . ')</th> <th>' . $rowGetGradingSystem['AD4Title'] . '(' . $rowGetGradingSystem['AD4Score'] . ')</th> <th>' . $rowGetGradingSystem['AD5Title'] . '(' . $rowGetGradingSystem['AD5Score'] . ')</th> <th>' . $rowGetGradingSystem['AD6Title'] . '(' . $rowGetGradingSystem['AD6Score'] . ')</th> <th>' . $rowGetGradingSystem['AD7Title'] . '(' . $rowGetGradingSystem['AD7Score'] . ')</th> <th>' . $rowGetGradingSystem['AD8Title'] . '(' . $rowGetGradingSystem['AD8Score'] . ')</th> <th>' . $rowGetGradingSystem['AD9Title'] . '(' . $rowGetGradingSystem['AD9Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberofAD'] == '10') {
            $ca1test = '<th>' . $rowGetGradingSystem['AD1Title'] . '(' . $rowGetGradingSystem['AD1Score'] . ')</th> <th>' . $rowGetGradingSystem['AD2Title'] . '(' . $rowGetGradingSystem['AD2Score'] . ')</th> <th>' . $rowGetGradingSystem['AD3Title'] . '(' . $rowGetGradingSystem['AD3Score'] . ')</th> <th>' . $rowGetGradingSystem['AD4Title'] . '(' . $rowGetGradingSystem['AD4Score'] . ')</th> <th>' . $rowGetGradingSystem['AD5Title'] . '(' . $rowGetGradingSystem['AD5Score'] . ')</th> <th>' . $rowGetGradingSystem['AD6Title'] . '(' . $rowGetGradingSystem['AD6Score'] . ')</th> <th>' . $rowGetGradingSystem['AD7Title'] . '(' . $rowGetGradingSystem['AD7Score'] . ')</th> <th>' . $rowGetGradingSystem['AD8Title'] . '(' . $rowGetGradingSystem['AD8Score'] . ')</th> <th>' . $rowGetGradingSystem['AD9Title'] . '(' . $rowGetGradingSystem['AD9Score'] . ')</th> <th>' . $rowGetGradingSystem['AD10Title'] . '(' . $rowGetGradingSystem['AD10Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberofAD'] == '11') {
            $ca1test = '<th>' . $rowGetGradingSystem['AD1Title'] . '(' . $rowGetGradingSystem['AD1Score'] . ')</th> <th>' . $rowGetGradingSystem['AD2Title'] . '(' . $rowGetGradingSystem['AD2Score'] . ')</th> <th>' . $rowGetGradingSystem['AD3Title'] . '(' . $rowGetGradingSystem['AD3Score'] . ')</th> <th>' . $rowGetGradingSystem['AD4Title'] . '(' . $rowGetGradingSystem['AD4Score'] . ')</th> <th>' . $rowGetGradingSystem['AD5Title'] . '(' . $rowGetGradingSystem['AD5Score'] . ')</th> <th>' . $rowGetGradingSystem['AD6Title'] . '(' . $rowGetGradingSystem['AD6Score'] . ')</th> <th>' . $rowGetGradingSystem['AD7Title'] . '(' . $rowGetGradingSystem['AD7Score'] . ')</th> <th>' . $rowGetGradingSystem['AD8Title'] . '(' . $rowGetGradingSystem['AD8Score'] . ')</th> <th>' . $rowGetGradingSystem['AD9Title'] . '(' . $rowGetGradingSystem['AD9Score'] . ')</th> <th>' . $rowGetGradingSystem['AD10Title'] . '(' . $rowGetGradingSystem['AD10Score'] . ')</th> <th>' . $rowGetGradingSystem['AD11Title'] . '(' . $rowGetGradingSystem['AD11Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberofAD'] == '12') {
            $ca1test = '<th>' . $rowGetGradingSystem['AD1Title'] . '(' . $rowGetGradingSystem['AD1Score'] . ')</th> <th>' . $rowGetGradingSystem['AD2Title'] . '(' . $rowGetGradingSystem['AD2Score'] . ')</th> <th>' . $rowGetGradingSystem['AD3Title'] . '(' . $rowGetGradingSystem['AD3Score'] . ')</th> <th>' . $rowGetGradingSystem['AD4Title'] . '(' . $rowGetGradingSystem['AD4Score'] . ')</th> <th>' . $rowGetGradingSystem['AD5Title'] . '(' . $rowGetGradingSystem['AD5Score'] . ')</th> <th>' . $rowGetGradingSystem['AD6Title'] . '(' . $rowGetGradingSystem['AD6Score'] . ')</th> <th>' . $rowGetGradingSystem['AD7Title'] . '(' . $rowGetGradingSystem['AD7Score'] . ')</th> <th>' . $rowGetGradingSystem['AD8Title'] . '(' . $rowGetGradingSystem['AD8Score'] . ')</th> <th>' . $rowGetGradingSystem['AD9Title'] . '(' . $rowGetGradingSystem['AD9Score'] . ')</th> <th>' . $rowGetGradingSystem['AD10Title'] . '(' . $rowGetGradingSystem['AD10Score'] . ')</th> <th>' . $rowGetGradingSystem['AD11Title'] . '(' . $rowGetGradingSystem['AD11Score'] . ')</th> <th>' . $rowGetGradingSystem['AD12Title'] . '(' . $rowGetGradingSystem['AD12Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberofAD'] == '13') {
            $ca1test = '<th>' . $rowGetGradingSystem['AD1Title'] . '(' . $rowGetGradingSystem['AD1Score'] . ')</th> <th>' . $rowGetGradingSystem['AD2Title'] . '(' . $rowGetGradingSystem['AD2Score'] . ')</th> <th>' . $rowGetGradingSystem['AD3Title'] . '(' . $rowGetGradingSystem['AD3Score'] . ')</th> <th>' . $rowGetGradingSystem['AD4Title'] . '(' . $rowGetGradingSystem['AD4Score'] . ')</th> <th>' . $rowGetGradingSystem['AD5Title'] . '(' . $rowGetGradingSystem['AD5Score'] . ')</th> <th>' . $rowGetGradingSystem['AD6Title'] . '(' . $rowGetGradingSystem['AD6Score'] . ')</th> <th>' . $rowGetGradingSystem['AD7Title'] . '(' . $rowGetGradingSystem['AD7Score'] . ')</th> <th>' . $rowGetGradingSystem['AD8Title'] . '(' . $rowGetGradingSystem['AD8Score'] . ')</th> <th>' . $rowGetGradingSystem['AD9Title'] . '(' . $rowGetGradingSystem['AD9Score'] . ')</th> <th>' . $rowGetGradingSystem['AD10Title'] . '(' . $rowGetGradingSystem['AD10Score'] . ')</th> <th>' . $rowGetGradingSystem['AD11Title'] . '(' . $rowGetGradingSystem['AD11Score'] . ')</th> <th>' . $rowGetGradingSystem['AD12Title'] . '(' . $rowGetGradingSystem['AD12Score'] . ')</th> <th>' . $rowGetGradingSystem['AD13Title'] . '(' . $rowGetGradingSystem['AD13core'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberofAD'] == '14') {
            $ca1test = '<th>' . $rowGetGradingSystem['AD1Title'] . '(' . $rowGetGradingSystem['AD1Score'] . ')</th> <th>' . $rowGetGradingSystem['AD2Title'] . '(' . $rowGetGradingSystem['AD2Score'] . ')</th> <th>' . $rowGetGradingSystem['AD3Title'] . '(' . $rowGetGradingSystem['AD3Score'] . ')</th> <th>' . $rowGetGradingSystem['AD4Title'] . '(' . $rowGetGradingSystem['AD4Score'] . ')</th> <th>' . $rowGetGradingSystem['AD5Title'] . '(' . $rowGetGradingSystem['AD5Score'] . ')</th> <th>' . $rowGetGradingSystem['AD6Title'] . '(' . $rowGetGradingSystem['AD6Score'] . ')</th> <th>' . $rowGetGradingSystem['AD7Title'] . '(' . $rowGetGradingSystem['AD7Score'] . ')</th> <th>' . $rowGetGradingSystem['AD8Title'] . '(' . $rowGetGradingSystem['AD8Score'] . ')</th> <th>' . $rowGetGradingSystem['AD9Title'] . '(' . $rowGetGradingSystem['AD9Score'] . ')</th> <th>' . $rowGetGradingSystem['AD10Title'] . '(' . $rowGetGradingSystem['AD10Score'] . ')</th> <th>' . $rowGetGradingSystem['AD11Title'] . '(' . $rowGetGradingSystem['AD11Score'] . ')</th> <th>' . $rowGetGradingSystem['AD12Title'] . '(' . $rowGetGradingSystem['AD12Score'] . ')</th> <th>' . $rowGetGradingSystem['AD13Title'] . '(' . $rowGetGradingSystem['AD13core'] . ')</th> <th>' . $rowGetGradingSystem['AD14Title'] . '(' . $rowGetGradingSystem['AD14Score'] . ')</th>';
        } elseif ($rowGetGradingSystem['NumberofAD'] == '15') {
            $ca1test = '<th>' . $rowGetGradingSystem['AD1Title'] . '(' . $rowGetGradingSystem['AD1Score'] . ')</th> <th>' . $rowGetGradingSystem['AD2Title'] . '(' . $rowGetGradingSystem['AD2Score'] . ')</th> <th>' . $rowGetGradingSystem['AD3Title'] . '(' . $rowGetGradingSystem['AD3Score'] . ')</th> <th>' . $rowGetGradingSystem['AD4Title'] . '(' . $rowGetGradingSystem['AD4Score'] . ')</th> <th>' . $rowGetGradingSystem['AD5Title'] . '(' . $rowGetGradingSystem['AD5Score'] . ')</th> <th>' . $rowGetGradingSystem['AD6Title'] . '(' . $rowGetGradingSystem['AD6Score'] . ')</th> <th>' . $rowGetGradingSystem['AD7Title'] . '(' . $rowGetGradingSystem['AD7Score'] . ')</th> <th>' . $rowGetGradingSystem['AD8Title'] . '(' . $rowGetGradingSystem['AD8Score'] . ')</th> <th>' . $rowGetGradingSystem['AD9Title'] . '(' . $rowGetGradingSystem['AD9Score'] . ')</th> <th>' . $rowGetGradingSystem['AD10Title'] . '(' . $rowGetGradingSystem['AD10Score'] . ')</th> <th>' . $rowGetGradingSystem['AD11Title'] . '(' . $rowGetGradingSystem['AD11Score'] . ')</th> <th>' . $rowGetGradingSystem['AD12Title'] . '(' . $rowGetGradingSystem['AD12Score'] . ')</th> <th>' . $rowGetGradingSystem['AD13Title'] . '(' . $rowGetGradingSystem['AD13core'] . ')</th> <th>' . $rowGetGradingSystem['AD14Title'] . '(' . $rowGetGradingSystem['AD14Score'] . ')</th> <th>' . $rowGetGradingSystem['AD15Title'] . '(' . $rowGetGradingSystem['AD15Score'] . ')</th>';
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

        $sqlGetstudent_session = "SELECT * FROM `students` INNER JOIN affective_domain_score ON students.id=affective_domain_score.studentid AND affective_domain_score.session='$session' AND affective_domain_score.classid = '$classid' AND affective_domain_score.sectionid = '$sectionnew' AND affective_domain_score.term = '$term'";
        $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
        $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
        $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

        if ($countGetstudent_session > 0) {
            do {
                echo '<tr id="' . $rowGetstudent_session["id"] . '" class="edit_tr">
                    			<td>' . $cnt++ . '</td>
                    			<td>' . $rowGetstudent_session['lastname'] . ' ' . $rowGetstudent_session['middlename'] . ' ' . $rowGetstudent_session['firstname'] . '</td>
                    			<td>' . $rowGetstudent_session['admission_no'] . '</td>';

                if ($rowGetGradingSystem['NumberofAD'] == '1') {
                    echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                              <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain6"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                               <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain7"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                           <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain8"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain9"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
                } elseif ($rowGetGradingSystem['NumberofAD'] == '2') {
                    echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                              <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain6"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                               <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain7"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                           <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain8"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain9"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
                } elseif ($rowGetGradingSystem['NumberofAD'] == '3') {
                    echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                              <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain6"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                               <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain7"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                           <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain8"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain9"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
                } elseif ($rowGetGradingSystem['NumberofAD'] == '4') {
                    echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                              <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain6"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                               <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain7"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                           <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain8"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain9"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
                } elseif ($rowGetGradingSystem['NumberofAD'] == '5') {
                    echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                              <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain6"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                               <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain7"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                           <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain8"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain9"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
                } elseif ($rowGetGradingSystem['NumberofAD'] == '6') {
                    echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                            <td style="display:none;">
                                               <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain7"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td style="display:none;">
                                           <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain8"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain9"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
                } elseif ($rowGetGradingSystem['NumberofAD'] == '7') {
                    echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain7"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                           <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain8"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain9"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
                } elseif ($rowGetGradingSystem['NumberofAD'] == '8') {
                    echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain7"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain8"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain9"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
                } elseif ($rowGetGradingSystem['NumberofAD'] == '9') {
                    echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain7"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain8"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain9"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
                } elseif ($rowGetGradingSystem['NumberofAD'] == '10') {
                    echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain7"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain8"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain9"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
                } elseif ($rowGetGradingSystem['NumberofAD'] == '11') {
                    echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain7"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain8"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain9"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
                } elseif ($rowGetGradingSystem['NumberofAD'] == '12') {
                    echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain7"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain8"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain9"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
                } elseif ($rowGetGradingSystem['NumberofAD'] == '13') {
                    echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain7"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain8"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain9"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
                } elseif ($rowGetGradingSystem['NumberofAD'] == '14') {
                    echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain7"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain8"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain9"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td style="display:none;">
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
                } elseif ($rowGetGradingSystem['NumberofAD'] == '15') {
                    echo '<td class="edit_td">
                                              <span id="ca1_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain1"] . '</span>
                                              <input type="text" value="' . $rowGetstudent_session["domain1"] . '" class="editbox" id="ca1_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                               <span id="ca2_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain2"] . '</span>
                                               <input type="text" value="' . $rowGetstudent_session["domain2"] . '" class="editbox" id="ca2_input_' . $rowGetstudent_session["id"] . '"/>
                                            </td>
                                            <td>
                                           <span id="ca3_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain3"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain3"] . '" class="editbox" id="ca3_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca4_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain4"] . '</span>
                                           <input type="text" value="' . $rowGetstudent_session["domain4"] . '" class="editbox" id="ca4_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca5_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain5"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain5"] . '" class="editbox" id="ca5_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca6_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain6"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain6"] . '" class="editbox" id="ca6_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca7_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain7"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain7"] . '" class="editbox" id="ca7_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca8_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain8"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain8"] . '" class="editbox" id="ca8_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca9_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain9"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain9"] . '" class="editbox" id="ca9_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca10_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain10"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain10"] . '" class="editbox" id="ca10_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca11_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain11"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain11"] . '" class="editbox" id="ca11_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca12_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain12"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain12"] . '" class="editbox" id="ca12_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca13_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain13"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain13"] . '" class="editbox" id="ca13_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca14_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain14"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain14"] . '" class="editbox" id="ca14_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>
                                        <td>
                                            <span id="ca15_' . $rowGetstudent_session["id"] . '" class="text">' . $rowGetstudent_session["domain15"] . '</span>
                                            <input type="text" value="' . $rowGetstudent_session["domain15"] . '" class="editbox" id="ca15_input_' . $rowGetstudent_session["id"] . '"/>
                                        </td>';
                } else {
                    echo '';
                }

                // if($rowGetGradingSystem['NumberofAD'] =='1')
                // {
                //     $ca1 = $rowGetstudent_session['domain1'];
                // }
                // elseif($rowGetGradingSystem['NumberofAD'] =='2')
                // {
                //     $ca1 = $rowGetstudent_session['domain1'] + $rowGetstudent_session['domain2'];
                // }
                // elseif($rowGetGradingSystem['NumberofAD'] =='3')
                // {
                //     $ca1 = $rowGetstudent_session['domain1'] + $rowGetstudent_session['domain2'] + $rowGetstudent_session['domain3'];
                // }
                // elseif($rowGetGradingSystem['NumberofAD'] =='4')
                // {
                //     $ca1 = $rowGetstudent_session['domain1'] + $rowGetstudent_session['domain2'] + $rowGetstudent_session['domain3'] + $rowGetstudent_session['domain4'];
                // }
                // elseif($rowGetGradingSystem['NumberofAD'] =='5')
                // {
                //     $ca1 = $rowGetstudent_session['domain1'] + $rowGetstudent_session['domain2'] + $rowGetstudent_session['domain3'] + $rowGetstudent_session['domain4'] + $rowGetstudent_session['domain5'];
                // }
                // elseif($rowGetGradingSystem['NumberofAD'] =='6')
                // {
                //     $ca1 = $rowGetstudent_session['domain1'] + $rowGetstudent_session['domain2'] + $rowGetstudent_session['domain3'] + $rowGetstudent_session['domain4'] + $rowGetstudent_session['domain5'] + $rowGetstudent_session['domain6'];
                // }
                // elseif($rowGetGradingSystem['NumberofAD'] =='7')
                // {
                //     $ca1 = $rowGetstudent_session['domain1'] + $rowGetstudent_session['domain2'] + $rowGetstudent_session['domain3'] + $rowGetstudent_session['domain4'] + $rowGetstudent_session['domain5'] + $rowGetstudent_session['domain6'] + $rowGetstudent_session['domain7'];
                // }
                // elseif($rowGetGradingSystem['NumberofAD'] =='8')
                // {
                //     $ca1 = $rowGetstudent_session['domain1'] + $rowGetstudent_session['domain2'] + $rowGetstudent_session['domain3'] + $rowGetstudent_session['domain4'] + $rowGetstudent_session['domain5'] + $rowGetstudent_session['domain6'] + $rowGetstudent_session['domain7'] + $rowGetstudent_session['domain8'];
                // }
                // elseif($rowGetGradingSystem['NumberofAD'] =='9')
                // {
                //     $ca1 = $rowGetstudent_session['domain1'] + $rowGetstudent_session['domain2'] + $rowGetstudent_session['domain3'] + $rowGetstudent_session['domain4'] + $rowGetstudent_session['domain5'] + $rowGetstudent_session['domain6'] + $rowGetstudent_session['domain7'] + $rowGetstudent_session['domain8'] + $rowGetstudent_session['domain9'];
                // }
                // elseif($rowGetGradingSystem['NumberofAD'] =='10')
                // {
                //     $ca1 = $rowGetstudent_session['domain1'] + $rowGetstudent_session['domain2'] + $rowGetstudent_session['domain3'] + $rowGetstudent_session['domain4'] + $rowGetstudent_session['domain5'] + $rowGetstudent_session['domain6'] + $rowGetstudent_session['domain7'] + $rowGetstudent_session['domain8'] + $rowGetstudent_session['domain9'] + $rowGetstudent_session['domain10'];
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
        echo 'No Affective Domain has been set for this class';
    }
} else {
    echo 'Class Section Not Found';
}
