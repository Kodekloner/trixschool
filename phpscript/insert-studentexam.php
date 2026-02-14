<?php
include('../database/config.php');

$classid = explode(',', $_POST['classid']);

$resultsettingid = $_POST['resultsettingid'];

$resulttype = $_POST['resulttype'];

// Start transaction to ensure data consistency
mysqli_begin_transaction($link);

try {
    // First, remove all existing assignments for the selected classes
    foreach ($classid as $class_id) {
        $class_id = intval($class_id);

        // Delete any traditional CA assigned to this class
        mysqli_query($link, "DELETE FROM `assigncatoclass` WHERE ClassID = '$class_id'");

        // Delete any kindergarten assessment assigned to this class
        mysqli_query($link, "DELETE FROM `kindergarten_assignment` WHERE class_id = '$class_id'");
    }

    // Now insert the new assignments for this resultsetting
    foreach ($classid as $class_id) {
        $class_id = intval($class_id);
        $sqlInsert = "INSERT INTO `assigncatoclass` (`ResultSettingID`, `ClassID`, `ResultType`)
                      VALUES ('$resultsettingid', '$class_id', '$resulttype')";
        if (!mysqli_query($link, $sqlInsert)) {
            throw new Exception(mysqli_error($link));
        }
    }

    mysqli_commit($link);
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            Successfully updated (any previous assignments for these classes were removed).
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
          </div>';
} catch (Exception $e) {
    mysqli_rollback($link);
    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
}
?>

<!-- $sqltosel = mysqli_query($link,"SELECT * FROM `assigncatoclass` WHERE ResultSettingID = '$resultsettingid'");
$count = mysqli_num_rows($sqltosel);

if($count > 0){
    
    $sql = "DELETE FROM `assigncatoclass` WHERE ResultSettingID = '$resultsettingid'";
    
    if(mysqli_query($link,$sql))
    {
        
        foreach($classid as $classidNew){
            $classidNew;
            
            $sqlInsert =("INSERT INTO `assigncatoclass`(`ResultSettingID`, `ClassID`, `ResultType`) VALUES ('$resultsettingid','$classidNew','$resulttype')");
            
            if(mysqli_query($link,$sqlInsert))
            {
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">Successfully updated<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span> </button></div>';
            }
            else
            {
                echo "Error: " . $sqlInsert . "<br>" . mysqli_error($link);
            }
        }
            
    }
    else
    {
        echo "Error: " . $sql . "<br>" . mysqli_error($link);
    }
}
else
{
	foreach($classid as $classidNew){
        $classidNew;
        
        $sqlInsert =("INSERT INTO `assigncatoclass`(`ResultSettingID`, `ClassID`, `ResultType`) VALUES ('$resultsettingid','$classidNew','$resulttype')");
        
        if(mysqli_query($link,$sqlInsert))
        {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">Successfully updated<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span> </button></div>';
        }
        else
        {
            echo "Error: " . $sqlInsert . "<br>" . mysqli_error($link);
        }
    }
}

?> -->