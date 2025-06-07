<?php
    include ('../database/config.php');
    
    $session = $_POST['session'];
    
    $term = $_POST['term'];
    
    $reltype = $_POST['reltype'];
    
    $reltype = $_POST['reltype'];
    
    $displaydte = $_POST['displaydte'];
    

    if($reltype == 'cummulative')
    {
        $sqlGetstudent_session = "SELECT * FROM `publishresult` WHERE `Session`='$session' AND ResultType= '$reltype'";
        $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
        $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
        $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);
        
        if($countGetstudent_session > 0)
        {
            $sqlGetinsert = "UPDATE `publishresult` SET `Date`='$displaydte' WHERE `Session`='$session' AND ResultType= '$reltype'";
            
            if(mysqli_query($link, $sqlGetinsert))
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
        }
        else
        {
            $sqlGetinsert = "INSERT INTO `publishresult`(`Session`, `ResultType`, `Date`) VALUES ('$session','$reltype','$displaydte')";
            
            if(mysqli_query($link, $sqlGetinsert))
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
        }
    }
    else
    {
        $sqlGetstudent_session = "SELECT * FROM `publishresult` WHERE `Session`='$session' AND ResultType= '$reltype' AND Term = '$term'";
        $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
        $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
        $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);
        
        if($countGetstudent_session > 0)
        {
            $sqlGetinsert = "UPDATE `publishresult` SET `Date`='$displaydte' WHERE `Session`='$session' AND ResultType= '$reltype' AND Term = '$term'";
            
            if(mysqli_query($link, $sqlGetinsert))
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
        }
        else
        {
            $sqlGetinsert = "INSERT INTO `publishresult`(`Session`, `Term`, `ResultType`, `Date`) VALUES ('$session','$term','$reltype','$displaydte')";
            
            if(mysqli_query($link, $sqlGetinsert))
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
        }
    }
         
?>