<?php
include('../database/config.php');

$ca1id = $_POST['ca1id'];
$ca1maxid = $_POST['ca1maxid'];
$ca2id = $_POST['ca2id'];
$ca2maxid = $_POST['ca2maxid'];
$ca3id = $_POST['ca3id'];
$ca3maxid = $_POST['ca3maxid'];
$ca4id = $_POST['ca4id'];
$ca4maxid = $_POST['ca4maxid'];
$ca5id = $_POST['ca5id'];
$ca5maxid = $_POST['ca5maxid'];

$ca6id = $_POST['ca6id'];
$ca6maxid = $_POST['ca6maxid'];
$ca7id = $_POST['ca7id'];
$ca7maxid = $_POST['ca7maxid'];
$ca8id = $_POST['ca8id'];
$ca8maxid = $_POST['ca8maxid'];
$ca9id = $_POST['ca9id'];
$ca9maxid = $_POST['ca9maxid'];
$ca10id = $_POST['ca10id'];
$ca10maxid = $_POST['ca10maxid'];

$examNumber = $_POST['examNumber'];

$catitle = $_POST['catitle'];

$MidTermCaToUse = $_POST['selectedcaformidterm'];

$sqltosel = mysqli_query($link, "SELECT * FROM `resultsetting` WHERE CaTitle = '$catitle'");
$rowfetch = mysqli_fetch_assoc($sqltosel);
$count = mysqli_num_rows($sqltosel);

if ($count > 0) {

    $ResultSettingID = $rowfetch['ResultSettingID'];

    $sql = "DELETE FROM `resultsetting` WHERE CaTitle = '$catitle'";

    if (mysqli_query($link, $sql)) {
        $sqlInsert = ("INSERT INTO `resultsetting`(`ResultSettingID`,`CaTitle`, `NumberOfCA`, `CA1Title`, `CA1Score`, `CA2Title`, `CA2Score`, `CA3Title`, `CA3Score`, `CA4Title`, `CA4Score`, `CA5Title`, `CA5Score`, `CA6Title`, `CA6Score`, `CA7Title`, `CA7Score`, `CA8Title`, `CA8Score`, `CA9Title`, `CA9Score`, `CA10Title`, `CA10Score`, `MidTermCaToUse`)
    	VALUES ('$ResultSettingID','$catitle','$examNumber','$ca1id','$ca1maxid','$ca2id','$ca2maxid','$ca3id','$ca3maxid','$ca4id','$ca4maxid','$ca5id','$ca5maxid','$ca6id','$ca6maxid','$ca7id','$ca7maxid','$ca8id','$ca8maxid','$ca9id','$ca9maxid','$ca10id','$ca10maxid','$MidTermCaToUse')");

        if (mysqli_query($link, $sqlInsert)) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">Successfully updated<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span> </button></div>';
        } else {
            echo "Error: " . $sqlInsert . "<br>" . mysqli_error($link);
        }
    } else {
        echo "Error: " . $sql . "<br>" . mysqli_error($link);
    }
} else {
    $sqlInsert = ("INSERT INTO `resultsetting`(`CaTitle`, `NumberOfCA`, `CA1Title`, `CA1Score`, `CA2Title`, `CA2Score`, `CA3Title`, `CA3Score`, `CA4Title`, `CA4Score`, `CA5Title`, `CA5Score`, `CA6Title`, `CA6Score`, `CA7Title`, `CA7Score`, `CA8Title`, `CA8Score`, `CA9Title`, `CA9Score`, `CA10Title`, `CA10Score`, `MidTermCaToUse`)
	VALUES ('$catitle','$examNumber','$ca1id','$ca1maxid','$ca2id','$ca2maxid','$ca3id','$ca3maxid','$ca4id','$ca4maxid','$ca5id','$ca5maxid','$ca6id','$ca6maxid','$ca7id','$ca7maxid','$ca8id','$ca8maxid','$ca9id','$ca9maxid','$ca10id','$ca10maxid','$MidTermCaToUse')");

    if (mysqli_query($link, $sqlInsert)) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">Successfully updated<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span> </button></div>';
    } else {
        echo "Error: " . $sqlInsert . "<br>" . mysqli_error($link);
    }
}
