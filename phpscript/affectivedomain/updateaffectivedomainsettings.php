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

$sqltosel = mysqli_query($link,"SELECT * FROM `affective_domain_settings` WHERE ADTitle = '$catitle'");
$rowfetch = mysqli_fetch_assoc($sqltosel);
$count = mysqli_num_rows($sqltosel);

if($count > 0){
    
    $ResultSettingID = $rowfetch['id'];
    
    $sql = "DELETE FROM `affective_domain_settings` WHERE ADTitle = '$catitle'";
    
    if(mysqli_query($link,$sql))
    {
        $sqlInsert =("INSERT INTO `affective_domain_settings`(`id`,`ADTitle`, `NumberofAD`, `AD1Title`, `AD1Score`, `AD2Title`, `AD2Score`, `AD3Title`, `AD3Score`, `AD4Title`, `AD4Score`, `AD5Title`, `AD5Score`, `AD6Title`, `AD6Score`, `AD7Title`, `AD7Score`, `AD8Title`, `AD8Score`, `AD9Title`, `AD9Score`, `AD10Title`, `AD10Score`, `AD11Title`, `AD11Score`, `AD12Title`, `AD12Score`, `AD13Title`, `AD13Score`, `AD14Title`, `AD14Score`, `AD15Title`, `AD15Score`, `MidTermADToUse`)
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
	$sqlInsert =("INSERT INTO `affective_domain_settings`(`ADTitle`, `NumberofAD`, `AD1Title`, `AD1Score`, `AD2Title`, `AD2Score`, `AD3Title`, `AD3Score`, `AD4Title`, `AD4Score`, `AD5Title`, `AD5Score`, `AD6Title`, `AD6Score`, `AD7Title`, `AD7Score`, `AD8Title`, `AD8Score`, `AD9Title`, `AD9Score`, `AD10Title`, `AD10Score`, `AD11Title`, `AD11Score`, `AD12Title`, `AD12Score`, `AD13Title`, `AD13Score`, `AD14Title`, `AD14Score`, `AD15Title`, `AD15Score`, `MidTermADToUse`)
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