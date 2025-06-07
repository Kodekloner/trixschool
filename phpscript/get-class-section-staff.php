<?php
    include ('../database/config.php');
    
    $staffid = $_POST['staffid'];
    
    $session = $_POST['session'];
    
    $classid = $_POST['classid'];
    
    $sqlsection = "SELECT sections.id AS sectionid,sections.section AS section,class_sections.id AS class_sectionsid FROM `class_teacher` INNER JOIN sections ON class_teacher.section_id=sections.id INNER JOIN class_sections ON sections.id=class_sections.section_id WHERE class_teacher.staff_id='$staffid' AND class_teacher.session_id='$session' AND class_sections.class_id='$classid' ORDER BY section ASC";
    $resultsection = mysqli_query($link, $sqlsection);
    $rowsection = mysqli_fetch_assoc($resultsection);
    $row_cntsection = mysqli_num_rows($resultsection);

    if($row_cntsection > 0)
    {
        echo'<option value="0">Select Section</option>';
        do{
            
            echo'<option value="'.$rowsection['class_sectionsid'].'" data-id="'.$rowsection['sectionid'].'">'.$rowsection['section'].'</option>';
            
        }while($rowsection = mysqli_fetch_assoc($resultsection));
    }
    else
    {
        echo'<option value="0">No records found</option>';
    }
    
?>