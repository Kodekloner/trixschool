<?php
    include ('../database/config.php');
    
    $rolefirst = $_POST['rolefirst'];
    
    $staffid = $_POST['staffid'];
    
    $session = $_POST['session'];

    echo $rolefirst . '<br>';

    echo $staffid . '<br>';

    echo $session . '<br>';
    
    if($rolefirst == 'student')
    {
        
        $sqlclasses = "SELECT * FROM `student_session` INNER JOIN classes ON student_session.class_id=classes.id WHERE session_id = '$session' AND student_id = '$staffid' ORDER BY class";
        $resultclasses = mysqli_query($link, $sqlclasses);
        $rowclasses = mysqli_fetch_assoc($resultclasses);
        $row_cntclasses = mysqli_num_rows($resultclasses);
        
        echo '<option value="0">Select Class</option>';
        
        if($row_cntclasses > 0)
        {
            do{
                
                echo'<option value="'.$rowclasses['class_id'].'">'.$rowclasses['class'].'</option>';
                
            }while($rowclasses = mysqli_fetch_assoc($resultclasses));
        }
        else
        {
            echo'<option value="0">No Records Found</option>';
        }
    } else if($rolefirst == 'parent')
    {   
        
        $student_id_sql = "SELECT student_id FROM student_session WHERE id = '$staffid'";
        $student_id_sql_2 = mysqli_query($link, $student_id_sql);
        $student_id = mysqli_fetch_assoc($student_id_sql_2)['student_id'];

        $sqlclasses = "SELECT * FROM `student_session` INNER JOIN classes ON student_session.class_id=classes.id WHERE session_id = '$session' AND student_id = '$student_id' ORDER BY class";
        $resultclasses = mysqli_query($link, $sqlclasses);
        $rowclasses = mysqli_fetch_assoc($resultclasses);
        $row_cntclasses = mysqli_num_rows($resultclasses);
        
        echo '<option value="0">Select Class</option>';
        
        if($row_cntclasses > 0)
        {
            do{
                
                echo'<option value="'.$rowclasses['class_id'].'">'.$rowclasses['class'].'</option>';
                
            }while($rowclasses = mysqli_fetch_assoc($resultclasses));
        }
        else
        {
            echo'<option value="0">No Records Found</option>';
        }
    }
    else
    {
       
        $sqlstaffcheck = "SELECT * FROM `staff_roles` INNER JOIN roles ON staff_roles.role_id=roles.id WHERE staff_roles.staff_id='$staffid'";
        $resultstaffcheck = mysqli_query($link, $sqlstaffcheck);
        $rowstaffcheck = mysqli_fetch_assoc($resultstaffcheck);
        $row_cntstaffcheck = mysqli_num_rows($resultstaffcheck);

        if($row_cntstaffcheck > 0)
        {
            echo '<option value="0">Select Class</option>';
            if($rowstaffcheck['name'] == 'Teacher')
            {
                //$sqlclasses = "SELECT DISTINCT(subject_timetable.class_id),class FROM `subject_timetable` INNER JOIN class_sections ON subject_timetable.class_id=class_sections.class_id INNER JOIN classes ON class_sections.class_id=classes.id INNER JOIN assigncatoclass ON classes.id=assigncatoclass.ClassID AND subject_timetable.staff_id = '$staffid' ORDER BY class";
                $sqlclasses = "SELECT DISTINCT(subjecttables.class_id), class
                                FROM `subjecttables` 
                                INNER JOIN class_sections ON subjecttables.class_id = class_sections.class_id 
                                INNER JOIN classes ON class_sections.class_id = classes.id 
                                INNER JOIN assigncatoclass ON classes.id = assigncatoclass.ClassID 
                                WHERE subjecttables.staff_id = '$staffid' 
                                ORDER BY class";
                                
                // $sqlclasses = "SELECT DISTINCT(subjecttables.class_id),class FROM `subjecttables` INNER JOIN class_sections ON subjecttables.class_id=class_sections.class_id INNER JOIN classes ON class_sections.class_id=classes.id INNER JOIN assigncatoclass ON classes.id=assigncatoclass.ClassID AND assigncatoclass.ResultType != 'british' AND subjecttables.staff_id = '$staffid' ORDER BY class";
				
		        $resultclasses = mysqli_query($link, $sqlclasses);
                $rowclasses = mysqli_fetch_assoc($resultclasses);
                $row_cntclasses = mysqli_num_rows($resultclasses);
                echo "count" . $row_cntclasses;
                if($row_cntclasses > 0)
                {
                    do{
                        echo'<option value="'.$rowclasses['class_id'].'">'.$rowclasses['class'].'</option>';
                        
                    }while($rowclasses = mysqli_fetch_assoc($resultclasses));
                }
                else
                {
                    echo'<option value="0">No Records Found</option>';
                }
            }
            else
            {
                $sqlclasses = "SELECT * FROM `classes` INNER JOIN assigncatoclass ON classes.id=assigncatoclass.ClassID ORDER BY class";
                $resultclasses = mysqli_query($link, $sqlclasses);
                $rowclasses = mysqli_fetch_assoc($resultclasses);
                $row_cntclasses = mysqli_num_rows($resultclasses);

                if($row_cntclasses > 0)
                {
                    do{
                        
                        echo'<option value="'.$rowclasses['ClassID'].'">'.$rowclasses['class'].'</option>';
                        
                    }while($rowclasses = mysqli_fetch_assoc($resultclasses));
                }
            }
            
        }
        else
        {
            
            echo'<option value="0">No Records Found</option>';
                
        }   
    }
                                    
?>
