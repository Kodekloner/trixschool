<?php
    include ('../database/config.php');
    
    $examNumber = $_POST['examNumber'];
    
    
    echo '<div id="row'.$examNumber.'"> <div class="form-row"> 
            <div class="col-sm">
                <select class="form-control form-control-sm subjectid">
                    <option>Subjects</option>';
        
                    $sqlsubjects = "SELECT * FROM `subjects` ORDER BY name ASC";
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
            <!-- <div class="col-sm">
                <input type="date" class="form-control form-control-sm subjectexamdate" placeholder="Exam Date">
            </div>
            <div class="col-sm">
                <input type="time" class="form-control form-control-sm subjectexamtime" placeholder="Exam Time">
            </div>
            <div class="col-sm">
                <input type="text" class="form-control form-control-sm subjectexamduration" placeholder="Duration">
            </div> -->
            <div class="col-sm">
                <div align="center">
                    <button style="border:none;" class="removeExamNumberbtn" data-id="row'.$examNumber.'">
                        <i class="fa fa-close" style="color: #ff0000;border:none;" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
    
        </div> <br> </div>';
?>