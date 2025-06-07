<?php
include('../database/config.php');

$rolefirst = $_POST['rolefirst'];

$staffid = $_POST['staffid'];

$session = $_POST['session'];

if ($rolefirst == 'student') {

    $sqlclasses = "SELECT * FROM `student_session` INNER JOIN classes ON student_session.class_id=classes.id WHERE session_id = '$session' AND student_id = '$staffid' ORDER BY class";
    $resultclasses = mysqli_query($link, $sqlclasses);
    $rowclasses = mysqli_fetch_assoc($resultclasses);
    $row_cntclasses = mysqli_num_rows($resultclasses);

    echo '<option value="0">Select Class</option>';

    if ($row_cntclasses > 0) {
        do {

            echo '<option value="' . $rowclasses['class_id'] . '">' . $rowclasses['class'] . '</option>';
        } while ($rowclasses = mysqli_fetch_assoc($resultclasses));
    } else {
        echo '<option value="0">No Records Found</option>';
    }
} else if ($rolefirst == 'parent') {

    $student_id_sql = "SELECT student_id FROM student_session WHERE id = '$staffid'";
    $student_id_sql_2 = mysqli_query($link, $student_id_sql);
    $student_id = mysqli_fetch_assoc($student_id_sql_2)['student_id'];

    $sqlclasses = "SELECT * FROM `student_session` INNER JOIN classes ON student_session.class_id=classes.id WHERE session_id = '$session' AND student_id = '$student_id' ORDER BY class";
    $resultclasses = mysqli_query($link, $sqlclasses);
    $rowclasses = mysqli_fetch_assoc($resultclasses);
    $row_cntclasses = mysqli_num_rows($resultclasses);

    echo '<option value="0">Select Class</option>';

    if ($row_cntclasses > 0) {
        do {

            echo '<option value="' . $rowclasses['class_id'] . '">' . $rowclasses['class'] . '</option>';
        } while ($rowclasses = mysqli_fetch_assoc($resultclasses));
    } else {
        echo '<option value="0">No Records Found</option>';
    }
} else {

    $sqlstaffcheck = "SELECT * FROM `staff_roles` INNER JOIN roles ON staff_roles.role_id=roles.id WHERE staff_roles.staff_id='$staffid'";
    $resultstaffcheck = mysqli_query($link, $sqlstaffcheck);
    $rowstaffcheck = mysqli_fetch_assoc($resultstaffcheck);
    $row_cntstaffcheck = mysqli_num_rows($resultstaffcheck);

    if ($row_cntstaffcheck > 0) {
        echo '<option value="0">Select Class</option>';
        if ($rowstaffcheck['name'] == 'Teacher') {
            // Escape the $staff_id to prevent SQL injection
            $staff_id = mysqli_real_escape_string($link, $staffid);

            // 1. Query to get a concatenated list of class IDs for the given staff member
            $sql1 = "SELECT GROUP_CONCAT(ct.class_id) AS c 
                        FROM class_teacher ct 
                        WHERE ct.staff_id = '$staff_id' 
                        GROUP BY ct.staff_id";

            // Execute the query
            $result1 = mysqli_query($link, $sql1);

            // Initialize variable to store the concatenated class IDs
            $class_ides = '';
            if ($result1) {
                $row1 = mysqli_fetch_assoc($result1);
                if ($row1 && !empty($row1['c'])) {
                    $class_ides = $row1['c'];
                }
            }

            // 2. Clean up the class IDs: split the string into an array, remove empty values,
            // and then join them back into a clean comma-separated string.
            $ides11 = array();
            $ides1 = '';
            if (!empty($class_ides)) {
                $ides = explode(',', $class_ides);
                foreach ($ides as $value) {
                    if (trim($value) !== '') {
                        $ides11[] = $value;
                    }
                }
                $ides1 = implode(',', $ides11);
            }

            // 3. If there are valid class IDs, query the 'classes' table to get the class details.
            $data = array();
            if (!empty($ides1)) {
                // Convert IDs to integers for safety and rebuild the string
                $ids = array_map('intval', explode(',', $ides1));
                $ids_string = implode(',', $ids);

                $sql2 = "SELECT * FROM classes WHERE id IN ($ids_string)";
                $result2 = mysqli_query($link, $sql2);

                if ($result2) {
                    while ($rowclasses = mysqli_fetch_assoc($result2)) {
                        echo '<option value="' . $rowclasses['id'] . '">' . $rowclasses['class'] . '</option>';
                    }
                } else {
                    echo '<option value="0">No Records Found</option>';
                }
            }
            // $sqlclasses = "SELECT DISTINCT(subjecttables.class_id),class FROM `subjecttables` INNER JOIN class_sections ON subjecttables.class_id=class_sections.class_id INNER JOIN classes ON class_sections.class_id=classes.id INNER JOIN assigncatoclass ON classes.id=assigncatoclass.ClassID AND subjecttables.staff_id = '$id' ORDER BY class";
            // $resultclasses = mysqli_query($link, $sqlclasses);
            // $rowclasses = mysqli_fetch_assoc($resultclasses);
            // $row_cntclasses = mysqli_num_rows($resultclasses);
        }

        //$sqlclasses = "SELECT DISTINCT(subject_timetable.class_id),class FROM `subject_timetable` INNER JOIN class_sections ON subject_timetable.class_id=class_sections.class_id INNER JOIN classes ON class_sections.class_id=classes.id INNER JOIN assigncatoclass ON classes.id=assigncatoclass.ClassID AND subject_timetable.staff_id = '$staffid' ORDER BY class";
        // $sqlclasses = "SELECT DISTINCT(subjecttables.class_id), class
        //                 FROM `subjecttables` 
        //                 INNER JOIN class_sections ON subjecttables.class_id = class_sections.class_id 
        //                 INNER JOIN classes ON class_sections.class_id = classes.id 
        //                 INNER JOIN assigncatoclass ON classes.id = assigncatoclass.ClassID 
        //                 WHERE subjecttables.staff_id = '$staffid' 
        //                 ORDER BY class";

        // $sqlclasses = "SELECT DISTINCT(subjecttables.class_id),class FROM `subjecttables` INNER JOIN class_sections ON subjecttables.class_id=class_sections.class_id INNER JOIN classes ON class_sections.class_id=classes.id INNER JOIN assigncatoclass ON classes.id=assigncatoclass.ClassID AND assigncatoclass.ResultType != 'british' AND subjecttables.staff_id = '$staffid' ORDER BY class";

        // $resultclasses = mysqli_query($link, $sqlclasses);
        // $rowclasses = mysqli_fetch_assoc($resultclasses);
        // $row_cntclasses = mysqli_num_rows($resultclasses);

        //     if($row_cntclasses > 0)
        //     {
        //         do{
        //             echo'<option value="'.$rowclasses['class_id'].'">'.$rowclasses['class'].'</option>';

        //         }while($rowclasses = mysqli_fetch_assoc($resultclasses));
        //     }
        //     else
        //     {
        //         echo'<option value="0">No Records Found</option>';
        //     }
        // }
        else {
            $sqlclasses = "SELECT * FROM `classes` INNER JOIN assigncatoclass ON classes.id=assigncatoclass.ClassID ORDER BY class";
            $resultclasses = mysqli_query($link, $sqlclasses);
            $rowclasses = mysqli_fetch_assoc($resultclasses);
            $row_cntclasses = mysqli_num_rows($resultclasses);

            if ($row_cntclasses > 0) {
                do {

                    echo '<option value="' . $rowclasses['ClassID'] . '">' . $rowclasses['class'] . '</option>';
                } while ($rowclasses = mysqli_fetch_assoc($resultclasses));
            }
        }
    } else {

        echo '<option value="0">No Records Found</option>';
    }
}
