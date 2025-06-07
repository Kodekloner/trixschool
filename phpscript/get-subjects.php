<?php
    include ('../database/config.php');
    
    $actualclasssection = $_POST['actualclasssection'];
    
    $classid = $_POST['classid'];
    
    $session = $_POST['session'];
    
    $term = $_POST['term'];
    
    $staffid = $_POST['staffid'];
    
    $sqlclasseschecker = "SELECT * FROM class_sections WHERE section_id = '$actualclasssection' AND class_id = '$classid'";
    $resultclasseschecker = mysqli_query($link, $sqlclasseschecker);
    $rowclasseschecker = mysqli_fetch_assoc($resultclasseschecker);
    $row_cntclasseschecker = mysqli_num_rows($resultclasseschecker);
    
    $classsection = $rowclasseschecker['id'];
 
    $sqlstaffcheck = "SELECT * FROM `staff_roles` INNER JOIN roles ON staff_roles.role_id=roles.id WHERE staff_roles.staff_id='$staffid'";
    $resultstaffcheck = mysqli_query($link, $sqlstaffcheck);
    $rowstaffcheck = mysqli_fetch_assoc($resultstaffcheck);
    $row_cntstaffcheck = mysqli_num_rows($resultstaffcheck);
    
    if($row_cntstaffcheck > 0)
    {
        echo'<option value="0">Subject</option>';
        
        if($rowstaffcheck['name'] == 'Teacher')
        {
            $sqlclasses = "SELECT subjects.name AS subjectsname,subjects.id AS subjectsid FROM `subjecttables` INNER JOIN subject_group_subjects ON subjecttables.subject_group_subject_id=subject_group_subjects.id INNER JOIN subjects ON subject_group_subjects.subject_id=subjects.id WHERE subjecttables.staff_id = '$staffid' AND subjecttables.class_id = '$classid' AND subjecttables.section_id = '$actualclasssection' AND subject_group_subjects.session_id = '$session'";
            $resultclasses = mysqli_query($link, $sqlclasses);
            $rowclasses = mysqli_fetch_assoc($resultclasses);
            $row_cntclasses = mysqli_num_rows($resultclasses);

            if($row_cntclasses > 0)
            {
                do{
                    
                    echo'<option value="'.$rowclasses['subjectsid'].'" data-id="'.$classsection.'">'.$rowclasses['subjectsname'].'</option>';
                    
                }while($rowclasses = mysqli_fetch_assoc($resultclasses));
            }
            else
            {
                echo'<option value="0">No Records Found</option>';
            }
        }
        else
        {
            $sqlsubjects = "SELECT subjects.name AS subjectsname,subjects.id AS subjectsid 
            FROM `subject_group_class_sections` 
            INNER JOIN subject_group_subjects ON subject_group_class_sections.subject_group_id=subject_group_subjects.subject_group_id AND subject_group_subjects.session_id = '$session'
            INNER JOIN subjects ON subject_group_subjects.subject_id=subjects.id 
            WHERE subject_group_class_sections.class_section_id='$classsection' ORDER BY subjects.name ASC";
            $resultsubjects = mysqli_query($link, $sqlsubjects);
            $rowsubjects = mysqli_fetch_assoc($resultsubjects);
            $row_cntsubjects = mysqli_num_rows($resultsubjects);
        
            if($row_cntsubjects > 0)
            {
                
                do{
                    
                    echo'<option value="'.$rowsubjects['subjectsid'].'" data-id="'.$classsection.'">'.$rowsubjects['subjectsname'].'</option>';
                    
                }while($rowsubjects = mysqli_fetch_assoc($resultsubjects));
            }
            else
            {
                echo'<option value="0">No Records Found</option>';
            }
        }
        
    }
    else
    {
        
       echo'<option value="0">No Records Found</option>';
            
    }
    
    
                                    
?>