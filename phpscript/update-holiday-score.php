<?php
include('../database/config.php');

$id = $_POST['id'];
$score = $_POST['score'];
$session = $_POST['session'];
$term = $_POST['term'];

$sql = "UPDATE holiday_assessment_scores SET score = '$score' WHERE id = '$id' AND session_id = '$session' AND term = '$term'";
if (mysqli_query($link, $sql)) {
        echo "success";
} else {
        echo "Error: " . mysqli_error($link);
}
