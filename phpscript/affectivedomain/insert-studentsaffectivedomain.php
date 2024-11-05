<?php 
include ('../../database/config.php');
    // $classsection = $_POST['classsection'];
    
    $classsection = $_POST['classsectionactual'];
            
    $classid = $_POST['classid'];
    
    $session = $_POST['session'];
    
    $term = $_POST['term'];
    
    $sqlGetclass_sections = "SELECT * FROM `class_sections` WHERE `id`='$classsection'";
    $queryGetclass_sections = mysqli_query($link, $sqlGetclass_sections);
    $rowGetclass_sections = mysqli_fetch_assoc($queryGetclass_sections);
    $countGetclass_sections = mysqli_num_rows($queryGetclass_sections);
    
    if($countGetclass_sections > 0){
        
        $sectionnew = $rowGetclass_sections['section_id'];
    
        $sqlGetstudent_session = "SELECT * FROM `student_session` INNER JOIN students ON student_session.student_id=students.id WHERE session_id='$session' AND class_id = '$classid' AND section_id = '$sectionnew'";
        
        $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
        $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
        $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);
        if($countGetstudent_session > 0)
        {
            do{
                echo $studentid = $rowGetstudent_session['student_id'];
                
                $sqlGetscore = "SELECT * FROM `affective_domain_score` WHERE studentid='$studentid' AND classid = '$classid' AND sectionid = '$sectionnew' AND session = '$session' AND term = '$term'";
                $queryGetscore = mysqli_query($link, $sqlGetscore);
                $rowGetscore = mysqli_fetch_assoc($queryGetscore);
                $countGetscore = mysqli_num_rows($queryGetscore);
                
                if($countGetscore > 0)
                {
                   
                }
                else
                {
                    
                    $sqlInsert =("INSERT INTO `affective_domain_score` (`studentid`, `classid`, `sectionid`, `session`, `term`) 
                    VALUES ('$studentid','$classid','$sectionnew','$session','$term')");
                    
                    if(mysqli_query($link,$sqlInsert))
                    {
                        
                    }
                    else
                    {
                        
                    }
                    
                }
                
            }while($rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session));
            
        }
        else
        {
            echo "Error: " . $sqlGetstudent_session . "<br>" . mysqli_error($link);
        }
    }
?>