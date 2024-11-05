<?php
    session_start();
    
    $username = trim($_POST['username']);

    $_SESSION['username'] = $username;
    
    echo $_SESSION['username'];

?>