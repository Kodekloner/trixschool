<?php 
include ('../database/config.php');

    $methID = $_POST['methID'];
    
    $sql1 = "DELETE FROM `gradingstructure` WHERE GradingTitle = '$methID'";
    $linkquery1 = mysqli_query($link,$sql1);
    
    $sql2 = "DELETE FROM `assigngradingtclass` WHERE GradingTitle = '$methID'";
    $linkquery2 = mysqli_query($link,$sql2);
    
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">Successfully Deleted<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span> </button></div>';
            
?>