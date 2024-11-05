<?php
    include ('../database/config.php');
    
    
    $classsection = $_POST['classsection'];
    
    $classsectionactual = $_POST['classsectionactual'];
            
    $classid = $_POST['classid'];
    
    $session = $_POST['session'];
    
    $term = $_POST['term'];
    
    $reltype = $_POST['reltype'];
    
    $rolefirst = $_POST['rolefirst'];
    
    if($rolefirst == 'student'){
        
    }
    else
    {
        if($reltype == 'cummulative')
        {
            $sqlGetstudent_session = "SELECT * FROM `publishresult` WHERE `Session`='$session' AND ResultType= '$reltype'";
        }
        else
        {
            $sqlGetstudent_session = "SELECT * FROM `publishresult` WHERE `Session`='$session' AND ResultType= '$reltype' AND Term = '$term'";
        }
        
        $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
        $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
        $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);
        
        
        if($countGetstudent_session > 0)
        {
            echo '<a href="#" data-toggle="modal" data-target="#exampleModal" data-toggle="tooltip" aria-hidden="true" class="btn btn-primary" style="border-radius: 20px;">
                    Publish Result
                </a><br>
                <span><small style="color:green;">Published</small></span>
                <input type="hidden" id="datee" value="'.$rowGetstudent_session['Date'].'">';
        }
        else
        {
            echo '<a href="#" data-toggle="modal" data-target="#exampleModal" data-toggle="tooltip" aria-hidden="true" class="btn btn-primary" style="border-radius: 20px;">
                    Publish Result
                </a><br>
                <span><small style="color:red;">Not Published</small></span>
                <input type="hidden" id="datee" value="'.$rowGetstudent_session['Date'].'">';
        }
    }    
?>