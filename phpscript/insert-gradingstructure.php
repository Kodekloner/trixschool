<?php
    
    include ('../database/config.php');
    
    $gradingtitle = $_POST['gradingtitle'];
    
    $GradingTitleidold = $_POST['gradingtitleold'];
    
    $grade = $_POST['grade'];
    
    $gradefrom = $_POST['gradefrom'];
    
    $gradeto = $_POST['gradeto'];
    
    $graderemark = $_POST['graderemark'];
    
    $sqltosel = mysqli_query($link,"SELECT * FROM `gradingstructure` WHERE GradingTitle = '$GradingTitleidold'");
    $count = mysqli_num_rows($sqltosel);

    if($count > 0){
        
        $sql = "DELETE FROM `gradingstructure` WHERE  GradingTitle = '$GradingTitleidold'";

        if(mysqli_query($link,$sql))
        {
            foreach($grade as $key => $gradenew)
            {
                $gradenew;
                
                $gradefromnew = $gradefrom[$key];
                
                $gradetonew = $gradeto[$key];
                
                $graderemarknew = $graderemark[$key];
                
                $sqlInsert =("INSERT INTO `gradingstructure`(`GradingTitle`, `Grade`, `Remark`, `RangeStart`, `RangeEnd`) 
                VALUES ('$gradingtitle','$gradenew','$graderemarknew','$gradefromnew','$gradetonew')");
                
                if(mysqli_query($link,$sqlInsert))
                {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">Successfully updated<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span> </button></div>';
                }
                else
                {
                    echo "Error: " . $sqlInsert . "<br>" . mysqli_error($link);
                }
                
                $sqlInsert1 =("UPDATE `assigngradingtclass` SET `GradingTitle`='$gradingtitle' WHERE `GradingTitle`='$GradingTitleidold'");
            
                if(mysqli_query($link,$sqlInsert1))
                {
                }
                else
                {
                }
            }
        }
        else
        {
        	
        }
        echo 1;
    }
    else
    {
    	foreach($grade as $key => $gradenew)
        {
            $gradenew;
            
            $gradefromnew = $gradefrom[$key];
            
            $gradetonew = $gradeto[$key];
            
            $graderemarknew = $graderemark[$key];
            
            $sqlInsert =("INSERT INTO `gradingstructure`(`GradingTitle`, `Grade`, `Remark`, `RangeStart`, `RangeEnd`) 
            VALUES ('$gradingtitle','$gradenew','$graderemarknew','$gradefromnew','$gradetonew')");
            
            if(mysqli_query($link,$sqlInsert))
            {
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">Successfully updated<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span> </button></div>';
            }
            else
            {
                echo "Error: " . $sqlInsert . "<br>" . mysqli_error($link);
            }
            echo 2;
        }
    }

?>