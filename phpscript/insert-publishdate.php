<?php
    include ('../database/config.php');
    require_once('../helper/publishresult_helper.php');
    
    $session = $_POST['session'] ?? 0;
    
    $term = $_POST['term'] ?? '';
    
    $reltype = $_POST['reltype'] ?? '';

    $classid = $_POST['classid'] ?? 0;

    $classsectionactual = $_POST['classsectionactual'] ?? 0;

    $rolefirst = $_POST['rolefirst'] ?? '';
    
    $displaydte = $_POST['displaydte'] ?? '';

    if (!can_staff_publish_result($link, $id ?? 0, $rolefirst)) {
        echo '<div class="alert alert-warning" role="alert">
                You do not have permission to publish results.
            </div>';
        exit;
    }

    $saved = save_publishresult_record($link, $session, $term, $reltype, $classid, $classsectionactual, $displaydte);

    if($saved)
    {
        echo '<div class="alert alert-success" role="alert">
                Updated Successfully.
            </div>';
    }
    else
    {
        echo '<div class="alert alert-warning" role="alert">
                Not Updated Successfully.
            </div>';
    }
         
?>
