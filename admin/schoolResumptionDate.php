<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<script src="../assets/js/jquery-3.5.1.min.js"></script>

	<!--DataTable Style-->
	<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
	<link rel="stylesheet" href="../assets/css/datatables.min.css">
    <!-- Bootstrap CSS -->
	<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	 <!--My New Stylesheet CSS -->
	 <link rel="stylesheet" href="../assets/css/myStyleSheet.css">
    <title>School Resumption Date</title>
  </head>
  
     <?php include ('../layout/style.php');?>
  
  <body style="background: rgb(236, 234, 234);">

	

    	<div class="menu-wrapper">
       	    <div class="sidebar-header">
			<?php include ('../layout/sidebar.php');?>

			<div class="backdrop"></div>

			<div class="content">

				<?php include ('../layout/header.php');?>

					<div class="content-data">
					    
		<div class="row" style="margin: 15px;">
        
            <div class="col-sm-12">
				
                <div class="cardBoxSty">

					<h2 align="center" style="margin: 50px;">Next Term Resumption Date</h2>
                    <?php
                        if(isset($_POST['submitExam']))
                        {
            
                           $session = $_POST['session'];
                           $term = $_POST['term'];
                           
                           $resumedate = $_POST['resumedate'];
            
                            if($resumedate == "" || $term == "" || $resumedate == "0" || $session == "" || $session == "0" || $term == "0")
                            {
                                echo"
                                <div align='left' class='alert alert-warning'>
                                <button type='button' class='close' data-dismiss='alert' aria-label='Close'> <span aria-hidden='true'>×</span> </button>Opps! Seem like you left some spaces blank/unseleceted. Check and resubmit</div>
                                ";
                            }
                            else
                            {
            
                                $sqlexamgroupName = "SELECT * FROM `resumptiondate` WHERE `Session`='$session' AND `Term`='$term'";
                                $resultexamgroupName = mysqli_query($link, $sqlexamgroupName);
                                $rowexamgroupName = mysqli_fetch_assoc($resultexamgroupName);
                                $row_cntexamgroupName = mysqli_num_rows($resultexamgroupName);
            
                                if($row_cntexamgroupName > 0)
                                {
                                    $sqlInsertexamgroup = ("UPDATE `resumptiondate` SET `Date`='$resumedate' WHERE `Session`='$session' AND `Term`='$term'");
                                    $Insertexamgroup = mysqli_query($link, $sqlInsertexamgroup) or die("".mysqli_error());
                                                
                                    if($Insertexamgroup)
                                    {
                                        echo"
                                            <div align='left' class='alert alert-success'>
                                            <button type='button' class='close' data-dismiss='alert' aria-label='Close'> <span aria-hidden='true'>×</span> </button>Updated Successfully</div>
                                        ";
                                    }
                                    else
                                    {
                                        echo "Opps! not done. Something went wrong";
                                    }
                                }
                                else
                                {
                                    $sqlInsertexamgroup = ("INSERT INTO `resumptiondate` (`Session`, `Term`, `Date`) VALUES ('$session', '$term', '$resumedate')");
                                    $Insertexamgroup = mysqli_query($link, $sqlInsertexamgroup) or die("".mysqli_error());
                                                
                                    if($Insertexamgroup)
                                    {
                                        echo"
                                        <div align='left' class='alert alert-success'>
                                        <button type='button' class='close' data-dismiss='alert' aria-label='Close'> <span aria-hidden='true'>×</span> </button>Added Successfully...</div>
                                        ";
                                    }
                                    else
                                    {
                                        echo "Opps! not done. Something went wrong";
                                    }
                                }	
                            }
                        }
                    ?>
                    <form style="margin: 50px;" method="POST">
                        <div class="form-row">
                            <div class="form-group col-sm-12">
                                <select class="form-control" name="session">
                                    <option value="0">Session</option>
                                    
                                    <?php
                                    
                                        $sqlsessions = "SELECT * FROM `sessions`";
                                        $resultsessions = mysqli_query($link, $sqlsessions);
                                        $rowsessions = mysqli_fetch_assoc($resultsessions);
                                        $row_cntsessions = mysqli_num_rows($resultsessions);
                    
                                        if($row_cntsessions > 0)
                                        {
                                            do{
                                                
                                                echo'<option value="'.$rowsessions['id'].'">'.$rowsessions['session'].'</option>';
                                                
                                            }while($rowsessions = mysqli_fetch_assoc($resultsessions));
                                        }
                                    ?>
                                </select>
                            </div>
    
                            <div class="form-group col-sm-12">
                                <select class="form-control" name="term">
                                  <option>Select Term</option>
                                  <option value="1st">1st Term</option>
                                  <option value="2nd">2nd Term</option>
                                  <option value="3rd">3rd Term</option>
                                </select>
    						</div>	
                            <div class="form-group col-sm-12">
                                <input type="date" class="form-control" name="resumedate">
                            </div>		                           
                        </div>
                        <div align="center">
                            <button type="submit" name="submitExam" class="btn btn-primary btn-lg" style="border-radius: 25px; font-weight: 500; width: 200px;">Submit</button>
                        </div>
                    </form>

                </div>
            </div>
		</div>
	

                        
					</div>

			</div>
		</div>
        </div>






	
	<!-- Option 1: jQuery and Bootstrap Bundle (includes Popper) -->
   	<script src="../assets/bootstrap/js/jquery.slim.min.js"></script>
	<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
	
	<script>
	    
	     $('#desktop').click(function(){
            
                $('li a').toggleClass('hideMenuList');
                $('.sidebar').toggleClass('changeWidth');
            })
            
            
            
            $('#mobile').click(function(){
            
                $('.sidebar').toggleClass('showMenu');
                $('.backdrop').toggleClass('showBackdrop');
            })
            
            
            $('.cross-icon').click(function(){
            
                $('.sidebar').toggleClass('showMenu');
                $('.backdrop').removeClass('showBackdrop');
            })
            
            $('.backdrop').click(function(){
            
                $('.sidebar').removeClass('showMenu');
                $('.backdrop').removeClass('showBackdrop');
            })
            
            $('li').click(function () {
                $('li').removeClass();
                $(this).addclass('selected');
                $('.sideBar').removeClass('showMenu');
            })
            
	    
	</script>



	<script src="../assets/js/jquery-3.5.1.js"></script>
	<script src="../assets/js/jquery.dataTables.min.js"></script>
   <script src="../assets/js/datatables.min.js"></script>
	<script src="../assets/js/pdfmake.min.js"></script>
	<script src="../assets/js/vfs_fonts.js"></script>

	

  </body>
</html>