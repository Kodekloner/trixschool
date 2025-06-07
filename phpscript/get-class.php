<?php
    include ('../database/config.php');
    
    echo $examType = $_POST['examType'];
    
    echo $session = $_POST['session'];
    
    $sqlexamsubjects = "SELECT DISTINCT classes.id AS ClassID, classes.class AS ClassName FROM `studentexamlist` 
    INNER JOIN classes ON studentexamlist.ClassID=classes.id
    WHERE ExamGroupID='$examType' AND studentexamlist.SessionID='$session'";
    $resultexamsubjects = mysqli_query($link, $sqlexamsubjects);
    $rowexamsubjects = mysqli_fetch_assoc($resultexamsubjects);
    $row_cntexamsubjects = mysqli_num_rows($resultexamsubjects);

    if($row_cntexamsubjects > 0)
    {
        
        echo '<option value="0">Class</option>';
        do{
            
            echo '<option value="'.$rowexamsubjects['ClassID'].'">'.$rowexamsubjects['ClassName'].'</option>';
        }while($rowexamsubjects = mysqli_fetch_assoc($resultexamsubjects));
    }
    else
    {
        echo '<option value="0">No Records Found</option>';
    }

?>