<?php
    include ('../database/config.php');

        $rowID=$_POST['rowID'];
        $extcomment=$_POST['extcomment'];
        $remark=$_POST['remark'];

		$sqlUpdateScore ="UPDATE `britishresult` SET `Remark` = '$remark', `AdditionalComments` = '$extcomment' WHERE `ID` = '$rowID'";
		$queryUpdateScore = mysqli_query($link, $sqlUpdateScore);
    
?>
