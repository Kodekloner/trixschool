<?php
include('../database/config.php');
require_once('../helper/defaultcomment_helper.php');

$id = (int) ($_POST['id'] ?? 0);

$sqlexamsubjects = "SELECT dc.*, classes.class
                    FROM `defaultcomment` dc
                    LEFT JOIN `classes` classes ON dc.ClassID = classes.id
                    WHERE dc.defaultcommentID = '$id'";
$resultexamsubjects = mysqli_query($link, $sqlexamsubjects);
$rowexamsubjects = $resultexamsubjects ? mysqli_fetch_assoc($resultexamsubjects) : null;
$row_cntexamsubjects = $resultexamsubjects ? mysqli_num_rows($resultexamsubjects) : 0;

if ($row_cntexamsubjects > 0) {
    do {
        $resultSubType = normalize_defaultcomment_result_subtype($rowexamsubjects['ResultSubType'] ?? 'termly');
        $maxScoreMeta = get_defaultcomment_max_score($link, $rowexamsubjects['ClassID'] ?? 0, $resultSubType);
        $maxAttribute = $maxScoreMeta['success'] ? ' max="' . $maxScoreMeta['maxScore'] . '"' : '';
        $maxScoreLabel = $maxScoreMeta['success'] ? $maxScoreMeta['maxScore'] : 'N/A';
        $classLabel = !empty($rowexamsubjects['class']) ? htmlspecialchars($rowexamsubjects['class'], ENT_QUOTES, 'UTF-8') : 'Legacy default';
        $resultTypeLabel = htmlspecialchars(ucwords(str_replace('-', ' ', $resultSubType)), ENT_QUOTES, 'UTF-8');

        echo '<div class="modal-body">
                <div class="alert alert-info" style="font-size: 14px;">
                    <strong>Class:</strong> ' . $classLabel . '<br>
                    <strong>Result Type:</strong> ' . $resultTypeLabel . '<br>
                    <strong>Allowed Max Score:</strong> ' . $maxScoreLabel . '
                </div>
                <div class="row">
                    <div class="col">
                        <label style="font-weight: 500;">Ranges:</label>
                        <input type="number" step=".01" name="commentfrom" class="form-control defaultcomment-range-input" placeholder="80" value="' . $rowexamsubjects['RangeStart'] . '"' . $maxAttribute . '>
                    </div>
                    <div class="col">
                        <label style="font-weight: 500;">&nbsp;&nbsp;&nbsp;</label>
                      <input type="number" step=".01" name="commentfromto" class="form-control defaultcomment-range-input" placeholder="100" value="' . $rowexamsubjects['RangeEnd'] . '"' . $maxAttribute . '>
                    </div>
                </div>
                <div class="form-group">
                    <label for="exampleFormControlTextarea1" style="font-weight: 500;">Comment:</label>
                    <textarea class="form-control" name="comment" id="exampleFormControlTextarea1" rows="3" placeholder="example: excellent result">' . htmlspecialchars($rowexamsubjects['DefaultComment'], ENT_QUOTES, 'UTF-8') . '</textarea>
                </div>
                <input type="hidden" name="defaultcommentID" value="' . $rowexamsubjects['defaultcommentID'] . '">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" name="editgradebtn" class="btn btn-primary"><i class="fa fa-check"></i> Submit</button>
            </div>';
    } while ($rowexamsubjects = mysqli_fetch_assoc($resultexamsubjects));
} else {
    echo '<div class="modal-body">No Records Found</div>';
}

?>
