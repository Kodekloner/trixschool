<?php
include('../database/config.php');

$classid = explode(',', $_POST['classid']);

$GradingTitleid = $_POST['GradingTitleid'];

$GradingType = $_POST['GradingType'];

if ($GradingType == 'midterm') {
    $conflictClasses = [];
    foreach ($classid as $cid) {
        $sqlCheck = "SELECT COUNT(*) as cnt FROM assigngradingtclass agc 
                     INNER JOIN gradingstructure gs ON agc.GradingTitle = gs.GradingTitle 
                     WHERE gs.Type = 'midterm' AND agc.ClassID = '$cid' AND agc.GradingTitle != '$GradingTitleid'";
        $resCheck = mysqli_query($link, $sqlCheck);
        $rowCheck = mysqli_fetch_assoc($resCheck);
        if ($rowCheck['cnt'] > 0) {
            $sqlClassName = "SELECT class FROM classes WHERE id = '$cid'";
            $resClassName = mysqli_query($link, $sqlClassName);
            $rowClassName = mysqli_fetch_assoc($resClassName);
            $conflictClasses[] = $rowClassName['class'];
        }
    }
    if (!empty($conflictClasses)) {
        echo '<div class="alert alert-danger">The following classes already have a mid-term grading list: ' . implode(', ', $conflictClasses) . '. Please remove the existing assignment first.</div>';
        exit;
    }
}


$sqltosel = mysqli_query($link, "SELECT * FROM `assigngradingtclass` WHERE GradingTitle = '$GradingTitleid'");
$count = mysqli_num_rows($sqltosel);

if ($count > 0) {

    $sql = "DELETE FROM `assigngradingtclass` WHERE GradingTitle = '$GradingTitleid'";

    if (mysqli_query($link, $sql)) {

        foreach ($classid as $classidNew) {
            $classidNew;

            $sqlInsert = ("INSERT INTO `assigngradingtclass`(`GradingTitle`, `ClassID`) VALUES ('$GradingTitleid','$classidNew')");

            if (mysqli_query($link, $sqlInsert)) {
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">Successfully updated<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span> </button></div>';
            } else {
                echo "Error: " . $sqlInsert . "<br>" . mysqli_error($link);
            }
        }
    } else {
        echo "Error: " . $sql . "<br>" . mysqli_error($link);
    }
} else {
    foreach ($classid as $classidNew) {
        $classidNew;

        $sqlInsert = ("INSERT INTO `assigngradingtclass`(`GradingTitle`, `ClassID`) VALUES ('$GradingTitleid','$classidNew')");

        if (mysqli_query($link, $sqlInsert)) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">Successfully updated<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span> </button></div>';
        } else {
            echo "Error: " . $sqlInsert . "<br>" . mysqli_error($link);
        }
    }
}
