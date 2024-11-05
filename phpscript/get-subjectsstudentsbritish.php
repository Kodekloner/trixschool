<?php
    include ('../database/config.php');
    
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
    
    if($countGetclass_sections > 0){
        
        $sectionnew = $rowGetclass_sections['section_id'];
        
        $class_id = $rowGetclass_sections['class_id'];
        
        $sqlGetGradingSystem = "SELECT * FROM `assigncatoclass` INNER JOIN resultsetting ON assigncatoclass.ResultSettingID=resultsetting.ResultSettingID WHERE assigncatoclass.ClassID='$class_id'";
        $queryGetGradingSystem = mysqli_query($link, $sqlGetGradingSystem);
        $rowGetGradingSystem = mysqli_fetch_assoc($queryGetGradingSystem);
        $countGetGradingSystem = mysqli_num_rows($queryGetGradingSystem);
        
        if($countGetGradingSystem > 0)
        {
            
            echo'<table class="table table-striped table-bordered" id="editable-datatable" style="margin-top: 30px;">
                <thead>
                    <tr>
                        <th>S/N</th>
                        <th>Full Name</th>
                        <th>Admission No.</th>
                        <th>Remark</th>
                        <th>Additional Comments</th>
                    </tr>
                </thead>
                <tbody>';
                    $cnt=1;
                    
                    $sqlGetstudent_session = "SELECT * FROM `britishresult` INNER JOIN students ON britishresult.StudentID=students.id AND `Session`='$session' AND ClassID = '$classid' AND Term = '$term' AND SectionID = '$sectionnew' AND SubjectID = '$subjects'";
                    $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                    $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                    $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);
                    
                    if($countGetstudent_session > 0)
                    {
                        do{
                            $rowGetRemark = $rowGetstudent_session['Remark'];
                            
                            if($rowGetRemark == 'Expected')
                            {
                                $selectedExpected= 'selected';
                            }
                            else
                            {
                                $selectedExpected= '';
                            }
                            
                            if($rowGetRemark == 'Emerging')
                            {
                                $selectedEmerging= 'selected';
                            }
                            else
                            {
                                $selectedEmerging= '';
                            }
                            
                            if($rowGetRemark == 'Exceeding')
                            {
                                $selectedExceeding = 'selected';
                            }
                            else
                            {
                                $selectedExceeding= '';
                            }
                            echo '<tr>
                    			<td>'.$cnt++.'</td>
                    			<td>'.$rowGetstudent_session['lastname'].' '.$rowGetstudent_session['middlename'].' '.$rowGetstudent_session['firstname'].'</td>
                    			<td>'.$rowGetstudent_session['admission_no'].'</td>
                    			<td>
                    			    <select class="custom-select my-1 mr-sm-2 remarks" id="extcomment_'.$rowGetstudent_session["ID"].'" data-id="'.$rowGetstudent_session["ID"].'" data-extcom="'.$rowGetstudent_session['AdditionalComments'].'">
                                        <option value="0" selected>Select Remark</option>
                                        <option value="Expected" '.$selectedExpected.'>Expected</option>
                                        <option value="Emerging" '.$selectedEmerging.'>Emerging</option>
                                        <option value="Exceeding" '.$selectedExceeding.'>Exceeding</option>
                                    </select>
                                </td>
                                <td>
                                  <textarea type="text" rows="2" id="" class="form-control britishfield" data-id="extcomment_'.$rowGetstudent_session["ID"].'" data-rowid="'.$rowGetstudent_session["ID"].'" placeholder="input comment here">'.$rowGetstudent_session['AdditionalComments'].'</textarea>
                                </td>      
                    		</tr>';
                        }while($rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session));
                        
                    }
                    else
                    {
                        echo '<tr><td>No Records Found</td></tr>';
                    }
            
            	echo '</tbody>
            </table>';
        }
        else
        {
            echo 'No CA has been set for this class';
        }
    }
    else
    {
        echo 'Class Section Not Found';
    }
   
?>
