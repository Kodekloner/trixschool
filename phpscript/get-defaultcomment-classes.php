<?php
include('../database/config.php');

$staffid = (int) ($_POST['staffid'] ?? 0);
$commentType = strtolower(trim($_POST['commentType'] ?? 'teacher'));

if ($commentType === 'schoolhead') {
    $sql = "SELECT id, class FROM `classes` ORDER BY class ASC";
} else {
    if ($staffid <= 0) {
        echo '<option value="0">Select Class Teacher first</option>';
        exit;
    }

    $sql = "SELECT DISTINCT classes.id, classes.class
            FROM `class_teacher`
            INNER JOIN classes ON class_teacher.class_id = classes.id
            WHERE class_teacher.staff_id = '$staffid'
            ORDER BY classes.class ASC";
}

$result = mysqli_query($link, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    echo '<option value="0">Select Class</option>';
    while ($row = mysqli_fetch_assoc($result)) {
        echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['class']) . '</option>';
    }
} else {
    echo '<option value="0">No Records Found</option>';
}
