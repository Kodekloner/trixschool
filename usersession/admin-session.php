<?php
    include ('../database/config.php');
    ob_start();
    session_start();

    if(!isset($_SESSION['username']) && empty($_SESSION['username'])) 
    {
        if(session_destroy())
        {
            header("Location: https://www.bjschool.org/site/login");
        }
       
    }
    
?>