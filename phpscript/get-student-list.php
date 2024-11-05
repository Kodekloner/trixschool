<?php
    include ('../database/config.php');
    
    $classid = $_POST['classid'];
    
    $classsection = $_POST['classsection'];
    
    $sessionid = $_POST['sessionid'];
    
    $term = $_POST['term'];
    
    $examname = $_POST['examname'];
    
    $sqlstudent = "SELECT students.id AS StudentID,firstname,middlename,lastname,admission_no FROM `student_session` INNER JOIN students ON student_session.student_id=students.id AND student_session.session_id='$sessionid' AND student_session.class_id='$classid' AND student_session.section_id='$classsection' ORDER BY lastname ASC";
    $resultstudent = mysqli_query($link, $sqlstudent);
    $rowstudent = mysqli_fetch_assoc($resultstudent);
    $row_cntstudent = mysqli_num_rows($resultstudent);
   
   if($row_cntstudent > 0)
   {
       do{    
           
           $student = $rowstudent['StudentID'];
           
            $sqltosel = mysqli_query($link,"SELECT * FROM `studentexamlist` WHERE ExamGroupID = '$examname' AND ClassID='$classid' AND SectionID='$classsection' AND SessionID='$sessionid' AND Term='$term' AND StudentID = '$student'");
            $row = mysqli_fetch_assoc($sqltosel);
            $count = mysqli_num_rows($sqltosel);
            
                if($count > 0)
                {
                    $checked= 'checked';
                }
                else
                {
                    $checked= '';
                }
            
            echo '<div class="form-row" style="margin-top: 10px;">
                <div class="col-sm-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="chkBoc" value="'.$rowstudent['StudentID'].'" '.$checked.'>
                    </div>
                </div>
        
                <div class="col-sm-4">
                    <span>'.$rowstudent['admission_no'].'</span>
                </div>
        
                <div class="col-sm-4">
                    <span>'.$rowstudent['lastname'].' '.$rowstudent['middlename'].' '.$rowstudent['firstname'].'</span>
                </div>
            </div>';
    
        }while($rowstudent = mysqli_fetch_assoc($resultstudent));
   }
   else{
       echo '<div class="alert alert-warning" role="alert"> No records found!</div>';
   }
    
    
    
?>