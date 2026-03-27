<?php
    include ('../database/config.php');
    require_once('../helper/publishresult_helper.php');
    
    
    $classsection = $_POST['classsection'] ?? 0;
    
    $classsectionactual = $_POST['classsectionactual'] ?? 0;
            
    $classid = $_POST['classid'] ?? 0;
    
    $session = $_POST['session'] ?? 0;
    
    $term = $_POST['term'] ?? '';
    
    $reltype = $_POST['reltype'] ?? '';
    
    $rolefirst = $_POST['rolefirst'] ?? '';
    
    if($rolefirst == 'student'){
        
    }
    else
    {
        $rowGetstudent_session = find_publishresult_record($link, $session, $term, $reltype, $classid, $classsectionactual);
        $countGetstudent_session = !empty($rowGetstudent_session) ? 1 : 0;
        $savedDate = $rowGetstudent_session['Date'] ?? '';
        
        
        if($countGetstudent_session > 0)
        {
            echo '<a href="#" data-toggle="modal" data-target="#exampleModal" data-toggle="tooltip" aria-hidden="true" class="btn btn-primary" style="border-radius: 20px;">
                    Publish Result
                </a><br>
                <span><small style="color:green;">Published</small></span>
                <input type="hidden" id="datee" value="'.$savedDate.'">';
        }
        else
        {
            echo '<a href="#" data-toggle="modal" data-target="#exampleModal" data-toggle="tooltip" aria-hidden="true" class="btn btn-primary" style="border-radius: 20px;">
                    Publish Result
                </a><br>
                <span><small style="color:red;">Not Published</small></span>
                <input type="hidden" id="datee" value="'.$savedDate.'">';
        }
    }    
?>
