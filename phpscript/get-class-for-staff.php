<?php
include('../database/config.php');

$staffid = $_POST['staffid'];

$session = $_POST['session'];

$sqlsection = "SELECT classes.id AS classid, classes.class AS classname 
               FROM `class_teacher` 
               INNER JOIN classes ON class_teacher.class_id = classes.id 
               WHERE class_teacher.staff_id = '$staffid' 
                 AND class_teacher.session_id = '$session' 
               GROUP BY classes.id 
               ORDER BY classname ASC";
$resultsection = mysqli_query($link, $sqlsection);
$rowsection = mysqli_fetch_assoc($resultsection);
$row_cntsection = mysqli_num_rows($resultsection);

if ($row_cntsection > 0) {
    echo '<option value="0">Select Class</option>';
    do {

        echo '<option value="' . $rowsection['classid'] . '">' . $rowsection['classname'] . '</option>';
    } while ($rowsection = mysqli_fetch_assoc($resultsection));
} else {
    echo '<option value="0">No records found</option>';
}
