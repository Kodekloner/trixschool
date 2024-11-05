<?php 
include ('../database/config.php');

    $session = $_POST['session'];
    
    $term = $_POST['term'];
    
    $examID = $_POST['examID'];

    $sql1 = "DELETE FROM `resultsetting` WHERE Session = '$session' AND ExamGroupID = '$examID' AND Term = '$term'";
    $linkquery1 = mysqli_query($link,$sql1);
    
    $sql2 = "DELETE FROM `examsubjects` WHERE SessionID = '$session' AND ExamGroupID = '$examID' AND Term = '$term'";
    $linkquery2 = mysqli_query($link,$sql2);

    $sql3 = "DELETE FROM `studentexamlist` WHERE SessionID = '$session' AND ExamGroupID = '$examID' AND Term = '$term'";
    $linkquery3 = mysqli_query($link,$sql3);

    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">Successfully Deleted<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span> </button></div>';
            
?>