<?php
//Include database connection
include ('../database/config.php');

$selDeleteID = $_POST['selDeleteID'];

if($_POST['selDeleteID']) 
{
    $selDeleteID = $_POST['selDeleteID']; //escape string
	 
		$sqlDel1 = ("DELETE FROM `score` WHERE `score`.`ID` = '$selDeleteID'");
		$resultDel1 = mysqli_query($link, $sqlDel1)or die(mysqli_error($link));
		
							
				echo '<div class="alert alert-success alert-rounded"> <i class="ti-check"></i>'.'Score removed from scoresheet successfully'.
	                        '<button type="button" class="close" data-dismiss="alert" aria-label="Close"> <span aria-hidden="true">×</span> </button>
	                      </div>';
						
}
?>        