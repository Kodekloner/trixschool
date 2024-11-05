<?php
    include ('../database/config.php');
    
    $examname = $_POST['examname'];
    
    $session = $_POST['session'];
    
    $cainput = $_POST['cainput'];
    
    $term = $_POST['term'];

    $subjectid = explode(',',$_POST['subjectid']);
    
    // $subjectexamdate = explode(',',$_POST['subjectexamdate']);
    
    // $subjectexamtime = explode(',',$_POST['subjectexamtime']);
    
    // $subjectexamduration = explode(',',$_POST['subjectexamduration']);
    
    $sqltosel = mysqli_query($link,"SELECT * FROM `examsubjects` WHERE ExamGroupID = '$examname' AND SessionID = '$session' AND Term = '$term'");
    $count = mysqli_num_rows($sqltosel);

    if($count > 0){
        
        $sql = "DELETE FROM `examsubjects` WHERE  ExamGroupID = '$examname' AND SessionID = '$session' AND Term = '$term'";

        if(mysqli_query($link,$sql))
        {
            foreach($subjectid as $key => $subjectidnew)
            {
                $subjectidnew;
                
                // $subjectexamdatenew = $subjectexamdate[$key];
                
                // $subjectexamtimenew = $subjectexamtime[$key];
                
                // $subjectexamdurationnew = $subjectexamduration[$key];
                
                $sqlInsert =("INSERT INTO `examsubjects`(`ExamGroupID`, `SessionID`, `Term`, `CA`, `SubjectID`)
            	VALUES ('$examname','$session','$term','$cainput','$subjectidnew')");
                
                if(mysqli_query($link,$sqlInsert))
                {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">successfully updated<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span> </button></div>';
                }
                else
                {
                    echo "Error: " . $sqlInsert . "<br>" . mysqli_error($link);
                }
            }
        }
        else
        {
        	
        }
    }
    else
    {
    	foreach($subjectid as $key => $subjectidnew)
        {
            $subjectidnew;
            
            // $subjectexamdatenew = $subjectexamdate[$key];
            
            // $subjectexamtimenew = $subjectexamtime[$key];
            
            // $subjectexamdurationnew = $subjectexamduration[$key];
            
            $sqlInsert =("INSERT INTO `examsubjects`(`ExamGroupID`, `SessionID`, `Term`, `CA`, `SubjectID`)
            VALUES ('$examname','$session','$term','$cainput','$subjectidnew')");
            
            if(mysqli_query($link,$sqlInsert))
            {
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">successfully updated<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span> </button></div>';
            }
            else
            {
                echo "Error: " . $sqlInsert . "<br>" . mysqli_error($link);
            }
        }
    }

?>