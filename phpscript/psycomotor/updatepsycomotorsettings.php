<?php 
include ('../../database/config.php');

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
$ca11id = $_POST['ca11id'];
$ca11maxid = $_POST['ca11maxid'];
$ca12id = $_POST['ca12id'];
$ca12maxid = $_POST['ca12maxid'];
$ca13id = $_POST['ca13id'];
$ca13maxid = $_POST['ca13maxid'];
$ca14id = $_POST['ca14id'];
$ca14maxid = $_POST['ca14maxid'];
$ca15id = $_POST['ca15id'];
$ca15maxid = $_POST['ca15maxid'];

$examNumber = $_POST['examNumber'];

$catitle = $_POST['catitle'];

$MidTermCaToUse = $_POST['selectedcaformidterm'];

$sqltosel = mysqli_query($link,"SELECT * FROM `psycomotor_settings` WHERE PTitle = '$catitle'");
$rowfetch = mysqli_fetch_assoc($sqltosel);
$count = mysqli_num_rows($sqltosel);

if($count > 0){
    
    $ResultSettingID = $rowfetch['id'];
    
    $sql = "DELETE FROM `psycomotor_settings` WHERE PTitle = '$catitle'";
    
    if(mysqli_query($link,$sql))
    {
        $sqlInsert =("INSERT INTO `psycomotor_settings`(`id`,`PTitle`, `NumberofP`, `P1Title`, `P1Score`, `P2Title`, `P2Score`, `P3Title`, `P3Score`, `P4Title`, `P4Score`, `P5Title`, `P5Score`, `P6Title`, `P6Score`, `P7Title`, `P7Score`, `P8Title`, `P8Score`, `P9Title`, `P9Score`, `P10Title`, `P10Score`, `P11Title`, `P11Score`, `P12Title`, `P12Score`, `P13Title`, `P13Score`, `P14Title`, `P14Score`, `P15Title`, `P15Score`, `MidTermPToUse`)
    	VALUES ('$ResultSettingID','$catitle','$examNumber','$ca1id','$ca1maxid','$ca2id','$ca2maxid','$ca3id','$ca3maxid','$ca4id','$ca4maxid','$ca5id','$ca5maxid','$ca6id','$ca6maxid','$ca7id','$ca7maxid','$ca8id','$ca8maxid','$ca9id','$ca9maxid','$ca10id','$ca10maxid','$ca11id','$ca11maxid','$ca12id','$ca12maxid','$ca13id','$ca13maxid','$ca14id','$ca14maxid','$ca15id','$ca15maxid','$MidTermCaToUse')");
        
        if(mysqli_query($link,$sqlInsert))
        {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">Successfully updated<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span> </button></div>';
        }
        else
        {
            echo "Error: " . $sqlInsert . "<br>" . mysqli_error($link);
        }
    }
    else
    {
        echo "Error: " . $sql . "<br>" . mysqli_error($link);
    }
}
else
{
	$sqlInsert =("INSERT INTO `psycomotor_settings`(`PTitle`, `NumberofP`, `P1Title`, `P1Score`, `P2Title`, `P2Score`, `P3Title`, `P3Score`, `P4Title`, `P4Score`, `P5Title`, `P5Score`, `P6Title`, `P6Score`, `P7Title`, `P7Score`, `P8Title`, `P8Score`, `P9Title`, `P9Score`, `P10Title`, `P10Score`, `P11Title`, `P11Score`, `P12Title`, `P12Score`, `P13Title`, `P13Score`, `P14Title`, `P14Score`, `P15Title`, `P15Score`, `MidTermPToUse`)
	VALUES ('$catitle','$examNumber','$ca1id','$ca1maxid','$ca2id','$ca2maxid','$ca3id','$ca3maxid','$ca4id','$ca4maxid','$ca5id','$ca5maxid','$ca6id','$ca6maxid','$ca7id','$ca7maxid','$ca8id','$ca8maxid','$ca9id','$ca9maxid','$ca10id','$ca10maxid','$ca11id','$ca11maxid','$ca12id','$ca12maxid','$ca13id','$ca13maxid','$ca14id','$ca14maxid','$ca15id','$ca15maxid','$MidTermCaToUse')");
    
    if(mysqli_query($link,$sqlInsert))
    {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">Successfully updated<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span> </button></div>';
    }
    else
    {
        echo "Error: " . $sqlInsert . "<br>" . mysqli_error($link);
    }
}

?>