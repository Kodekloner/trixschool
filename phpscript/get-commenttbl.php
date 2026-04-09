<?php
include('../database/config.php');
require_once('../helper/defaultcomment_helper.php');

$id = (int) ($_POST['id'] ?? 0);
$classid = (int) ($_POST['classid'] ?? 0);
$resultType = normalize_defaultcomment_result_subtype($_POST['resultType'] ?? 'termly');
$commentType = trim((string) ($_POST['commentType'] ?? 'Teacher'));
$commentType = strcasecmp($commentType, 'SchoolHead') === 0 ? 'SchoolHead' : 'Teacher';

if ($id <= 0 || $classid <= 0) {
    echo '<tr><td colspan="4">Select staff, class and result type first.</td></tr>';
    exit;
}

if ($commentType === 'SchoolHead' && class_disables_school_head_default_comments($link, $classid)) {
    echo '<tr><td colspan="4">Default head teacher comments are not available for British or kindergarten classes.</td></tr>';
    exit;
}

$sqlexamsubjects = "SELECT *
                    FROM `defaultcomment`
                    WHERE PrincipalOrDeanOrHeadTeacherUserID = '$id'
                      AND ClassID = '$classid'
                      AND CommentType = '$commentType'
                      AND ResultSubType = '$resultType'
                    ORDER BY RangeStart ASC, RangeEnd ASC";
$resultexamsubjects = mysqli_query($link, $sqlexamsubjects);
$rowexamsubjects = $resultexamsubjects ? mysqli_fetch_assoc($resultexamsubjects) : null;
$row_cntexamsubjects = $resultexamsubjects ? mysqli_num_rows($resultexamsubjects) : 0;

if ($row_cntexamsubjects > 0) {
    do {
        echo '<tr>
                <td>' . $rowexamsubjects['RangeStart'] . '</td>
                <td>' . $rowexamsubjects['RangeEnd'] . '</td>
                <td>' . htmlspecialchars($rowexamsubjects['DefaultComment'], ENT_QUOTES, 'UTF-8') . '</td>
                <td>
                    <a href="#" data-toggle="modal" data-target="#exampleModalEdit" data-id="' . $rowexamsubjects['defaultcommentID'] . '" id="editbtn" style="color: #000000;">
                        <i class="fa fa-pencil" title="Edit" data-toggle="tooltip" aria-hidden="true"></i>
                    </a>
                    <a href="#" style="color: #ff0000; margin-left: 10px;" data-id="' . $rowexamsubjects['defaultcommentID'] . '" id="delbtn" data-toggle="modal" data-target="#deleteModal" data-toggle="tooltip" aria-hidden="true">
                        <i class="fa fa-trash" title="Delete" data-toggle="tooltip" aria-hidden="true"></i>
                    </a>
                </td>
            </tr>';
    } while ($rowexamsubjects = mysqli_fetch_assoc($resultexamsubjects));
} else {
    echo '<tr><td colspan="4">No Records Found</td></tr>';
}

?>
