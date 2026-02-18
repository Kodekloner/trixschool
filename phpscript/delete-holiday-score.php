<?php
include('../database/config.php');

$id = $_POST['id'];

$sql = "DELETE FROM holiday_assessment_scores WHERE id = '$id'";
if (mysqli_query($link, $sql)) {
	echo "success";
} else {
	echo "Error: " . mysqli_error($link);
}
