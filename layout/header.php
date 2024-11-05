<?php
    include ('../database/config.php');
 
    echo'<header class="row" style="padding:10px;">

	    <div class="col-md-12 col-lg-2">
	        <img class="logoImg" src="https://schoollift.s3.us-east-2.amazonaws.com/'.$rowsch_settings['app_logo'].'" width="70%">
	    </div>
	    
	</header>
	<div class="row">

	    <div class="col-md-12 col-lg-10">
	        
	    </div>';
	    
	    if($rolefirst == 'staff')
	    {
	        echo '<div class="col-md-12 col-lg-2" align="right" style="padding-right:40px;">
    	        <b><i class="fa fa-angle-double-left"></i><a href="'.$defRUl.'admin/admin/dashboard" style="text-decoration:underline;font-size:15;color:black;">Go Back</a></b>
    	    </div>';
	    }
	    else
	    {
	        echo '<div class="col-md-12 col-lg-2" align="right" style="padding-right:40px;">
    	        <b><i class="fa fa-angle-double-left"></i><a href="'.$defRUl.'user/dashboard" style="text-decoration:underline;font-size:15;color:black;">Go Back</a></b>
    	    </div>';
		
	    }
	    
	echo '</div>';

?>
