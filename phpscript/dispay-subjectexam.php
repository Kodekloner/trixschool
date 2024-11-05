<?php
    include ('../database/config.php');
    
    $examname = $_POST['examID'];
    
    $examca = $_POST['examca'];
    
    $subgroupid = $_POST['subgroupid'];
    
    $sqlexamsubjects = "SELECT * FROM `examsubjects` WHERE ExamGroupID='$examname' AND CA = '$examca'";
    $resultexamsubjects = mysqli_query($link, $sqlexamsubjects);
    $rowexamsubjects = mysqli_fetch_assoc($resultexamsubjects);
    $row_cntexamsubjects = mysqli_num_rows($resultexamsubjects);

    if($row_cntexamsubjects > 0)
    {
        do{
            $ExamSubjectID = $rowexamsubjects['ExamSubjectID'];
            
            $SubjectID = $rowexamsubjects['SubjectID'];
            
            $Date = $rowexamsubjects['Date'];
            
            $Time = $rowexamsubjects['Time'];
            
            $Duration = $rowexamsubjects['Duration'];
            
            echo'<div id="row'.$ExamSubjectID.'">
                    <div class="form-row"> 
                        <div class="col-sm">
                            <select class="form-control form-control-sm subjectid">
                                <option>Subjects</option>';
                                
                                    $sqlsubjects = "SELECT * FROM `subjects` INNER JOIN subject_group_subjects ON subjects.id=subject_group_subjects.subject_id WHERE subject_group_id='$subgroupid' ORDER BY name ASC";
                                    $resultsubjects = mysqli_query($link, $sqlsubjects);
                                    $rowsubjects = mysqli_fetch_assoc($resultsubjects);
                                    $row_cntsubjects = mysqli_num_rows($resultsubjects);
                
                                    if($row_cntsubjects > 0)
                                    {
                                        do{
                                            
                                            if($rowsubjects['id'] == $SubjectID)
                                            {
                                                $selected = 'selected';
                                            }
                                            else
                                            {
                                                $selected = '';
                                            }
                                            
                                            echo'<option value="'.$rowsubjects['id'].'" '.$selected.'>'.$rowsubjects['name'].'</option>';
                                            
                                        }while($rowsubjects = mysqli_fetch_assoc($resultsubjects));
                                    }
                                    
                            echo '</select>
                        </div>
                        <!-- <div class="col-sm">
                            <input type="date" class="form-control form-control-sm subjectexamdate" placeholder="Exam Date" value="'.$Date.'">
                        </div>
                        <div class="col-sm">
                            <input type="time" class="form-control form-control-sm subjectexamtime" placeholder="Exam Time" value="'.$Time.'">
                        </div>
                        <div class="col-sm">
                            <input type="text" class="form-control form-control-sm subjectexamduration" placeholder="Duration" value="'.$Duration.'">
                        </div> -->
                        <div class="col-sm">
                            <div align="center">
                                <button style="border:none;" class="removeExamNumberbtn" data-id="row'.$ExamSubjectID.'" data-myid="'.$ExamSubjectID.'">
                                    <i class="fa fa-close" style="color: #ff0000;border:none;" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
            
                    </div> <br>
                </div>';
            
        }while($rowexamsubjects = mysqli_fetch_assoc($resultexamsubjects));
    }
    else
    {
        echo '<div id="row1">
                <div class="form-row"> 
                    <div class="col-sm">
                        <select class="form-control form-control-sm subjectid">
                            <option>Subjects</option>';
                            
                                $sqlsubjects = "SELECT * FROM `subjects` INNER JOIN subject_group_subjects ON subjects.id=subject_group_subjects.subject_id WHERE subject_group_id='$subgroupid' ORDER BY name ASC";
                                $resultsubjects = mysqli_query($link, $sqlsubjects);
                                $rowsubjects = mysqli_fetch_assoc($resultsubjects);
                                $row_cntsubjects = mysqli_num_rows($resultsubjects);
            
                                if($row_cntsubjects > 0)
                                {
                                    do{
                                        
                                        echo'<option value="'.$rowsubjects['id'].'">'.$rowsubjects['name'].'</option>';
                                        
                                    }while($rowsubjects = mysqli_fetch_assoc($resultsubjects));
                                }
                                
                        echo '</select>
                    </div>
                    <div class="col-sm">
                        <input type="date" class="form-control form-control-sm subjectexamdate" placeholder="Exam Date">
                    </div>
                    <div class="col-sm">
                        <input type="time" class="form-control form-control-sm subjectexamtime" placeholder="Exam Time">
                    </div>
                    <div class="col-sm">
                        <input type="text" class="form-control form-control-sm subjectexamduration" placeholder="Duration">
                    </div>
                    <div class="col-sm">
                        <div align="center">
                            <button style="border:none;" class="removeExamNumberbtn" data-id="row1">
                                <i class="fa fa-close" style="color: #ff0000;border:none;" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
        
                </div> <br>
            </div>';
    }

?>