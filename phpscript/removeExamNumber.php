<?php
    include ('../database/config.php');
    
    $id = $_POST['id'];
    
   
    $sql = "DELETE FROM `examsubjects` WHERE ExamSubjectID = '$id'";
    
    if(mysqli_query($link,$sql))
    {
        
    }
?>