<?php
    include ('../database/config.php');
    
    $examType = $_POST['examType'];
            
    $classid = $_POST['class'];
    
    $session = $_POST['session'];
    
    $term = $_POST['term'];
    
    $subjects = $_POST['subjects'];
    
    $classsection = $_POST['classsection'];
    
    $sqlsubjects = "SELECT * FROM `studentexamlist` WHERE ExamGroupID='$examType' AND ClassID='$classid' AND SessionID='$session' AND Term='$term' AND SectionID='$classsection'";
    $resultsubjects = mysqli_query($link, $sqlsubjects);
    $rowsubjects = mysqli_fetch_assoc($resultsubjects);
    $row_cntsubjects = mysqli_num_rows($resultsubjects);

    if($row_cntsubjects > 0)
    {
        
        do{
            $studentID = $rowsubjects['StudentID'];
            
            $sqlscore = "SELECT * FROM `score` WHERE ExamGroupID='$examType' AND ClassID='$classid' AND Session='$session' AND Term='$term' AND SectionID='$classsection' AND StudentID='$studentID' AND SubjectID='$subjects'";
            $resultscore = mysqli_query($link, $sqlscore);
            $rowscore = mysqli_fetch_assoc($resultscore);
            $row_cntscore = mysqli_num_rows($resultscore);
        
            if($row_cntscore > 0)
            {
                
            }
            else
            {
                $sqlscoreinsert = "INSERT INTO `score`(`ExamGroupID`, `StudentID`, `ClassID`, `SectionID`, `SubjectID`, `Session`, `Term`) VALUES ('$examType','$studentID','$classid','$classsection','$subjects','$session','$term')";
                $resultscoreinsert = mysqli_query($link, $sqlscoreinsert);
            }
        }while($rowsubjects = mysqli_fetch_assoc($resultsubjects));
    }
    else
    {
        
    }
                                    
?>