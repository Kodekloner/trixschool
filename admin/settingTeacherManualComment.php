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
	 <!--My New Stylesheet CSS --><?php include ('../database/config.php');?>
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
    <title>Class Teacher Result Manual Comment</title>
    
    <style type="text/css">
        .editbox
        {
            
         display:none
         
        }
        .editbox
        {
        font-size:14px;
        width:30px;
        background-color: #FFFFFF;
        border:solid 1px #FFFFFF;
        padding:5px 5px;
        }
        .edit_tr:hover
        {
        background-color: #80C8E5;
        cursor:pointer;
        }
    </style>
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
        
            <div class="col-sm-12 cardBoxSty">
				

				<form style="margin: 0px;">
                    <div class="form-row">
                        
                        <div class="form-group col-sm">
                            <select class="form-control" id="session">
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
							<!--They would need to select Session in-order to pick a term-->
						</div>
						
						<div class="form-group col-sm">
                            <select class="form-control" id="term">
                              <option>Select Term</option>
                              <option value="1st">1st Term</option>
                              <option value="2nd">2nd Term</option>
                              <option value="3rd">3rd Term</option>
                            </select>
							<!--They would need to select Term in-order to display the Exam Group Created for that term-->
                        </div>
                        <div class="form-group col-sm">
			<select class="form-control" id="staffid">
                                <option value="0">Select Class Teacher</option>
                                <?php
                                    $sqlstaffcheck = "SELECT * FROM `staff_roles` INNER JOIN roles ON staff_roles.role_id=roles.id WHERE staff_roles.staff_id='$id'";
                                    $resultstaffcheck = mysqli_query($link, $sqlstaffcheck);
                                    $rowstaffcheck = mysqli_fetch_assoc($resultstaffcheck);
                                    $row_cntstaffcheck = mysqli_num_rows($resultstaffcheck);
                                    
                                    if($row_cntstaffcheck > 0)
                                    {
                                        if($rowstaffcheck['name'] == 'Teacher')
                                        {
                                            $sqlstaff = "SELECT DISTINCT staff.id AS staff_id,staff.name AS staff_name,staff.surname AS staff_surname FROM `staff` INNER JOIN class_teacher ON staff.id=class_teacher.staff_id WHERE staff.id='$id' ORDER BY surname ASC";
                                            $resultstaff = mysqli_query($link, $sqlstaff);
                                            $rowstaff = mysqli_fetch_assoc($resultstaff);
                                            $row_cntstaff = mysqli_num_rows($resultstaff);
                        
                                            if($row_cntstaff > 0)
                                            {
                                                do{
                                                    
                                                    echo'<option value="'.$rowstaff['staff_id'].'">'.$rowstaff['staff_surname'].' '.$rowstaff['staff_name'].'</option>';
                                                    
                                                }while($rowstaff = mysqli_fetch_assoc($resultstaff));
                                            }
                                        }
                                        else
                                        {
                                            $sqlstaff = "SELECT DISTINCT staff.id AS staff_id,staff.name AS staff_name,staff.surname AS staff_surname FROM `staff` INNER JOIN class_teacher ON staff.id=class_teacher.staff_id ORDER BY surname ASC";
                                            $resultstaff = mysqli_query($link, $sqlstaff);
                                            $rowstaff = mysqli_fetch_assoc($resultstaff);
                                            $row_cntstaff = mysqli_num_rows($resultstaff);
                        
                                            if($row_cntstaff > 0)
                                            {
                                                do{
                                                    
                                                    echo'<option value="'.$rowstaff['staff_id'].'">'.$rowstaff['staff_surname'].' '.$rowstaff['staff_name'].'</option>';
                                                    
                                                }while($rowstaff = mysqli_fetch_assoc($resultstaff));
                                            }
                                        }
                                        
                                    }
                                    else
                                    {
                                        echo'<option value="0">No Records Found</option>';
                                    }
                                ?>
                            </select>
						</div>	
						<div class="form-group col-sm">
                            <select class="form-control" id="class">
                                <option value="0">Class</option>
                                
                            </select>
							<!--They would need to select class and section in-order to display the Exam Names assigned to a particular class-->
						</div>

						<div class="form-group col-sm">
                            <select class="form-control" id="classsection">
                                <option value="0">Section</option>
                            </select>
						</div>
						<div class="col-sm" align="right">
							<button type="button" class="btn btn-primary" style="border-radius: 20px;" id="getstud">
								<i class="fa fa-search" aria-hidden="true"></i>
								<span style="font-weight: 500;">Load</span>
							</button>
						</div>
                    </div>
                </form>
					

            </div>
		</div>

		<div class="card cardBoxSty" style="margin: 15px;">

			<div class="card-body">

				<div class="row">

					<div class="col-sm-12 col-md-12 cardBoxSty">
						
						<div class="row">
							<div class="col-12">
								<div class="card">
									<div class="card-body">

										<h3 class="card-title">Class Teacher Manual Comment</h3>

										<div class="table-responsive m-t-40" id="tbl_data">
											<div class="alert alert-primary" role="alert">
                                                Please Filter to proceed.
                                            </div>
										</div>                               
									</div>
								</div>
							</div>
						<!--</div>-->
						</div>
							
					</div>

				</div>
				
			</div>

		</div>
 
					</div>

			</div>
		</div>
    </div>
	
	<script src="../assets/plugins/jquery-datatables-editable/jquery.dataTables.js"></script>
	<script src="../assets/plugins/datatables/dataTables.bootstrap.js"></script>
	<script src="../assets/plugins/tiny-editable/mindmup-editabletable.js"></script>
	<script src="../assets/plugins/tiny-editable/numeric-input-example.js"></script>

	<!-- Option 1: jQuery and Bootstrap Bundle (includes Popper) -->
   	<script src="../assets/bootstrap/js/jquery.slim.min.js"></script>
	<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>



	<script src="../assets/js/jquery-3.5.1.js"></script>
	<script src="../assets/js/jquery.dataTables.min.js"></script>
   <script src="../assets/js/datatables.min.js"></script>
	<script src="../assets/js/pdfmake.min.js"></script>
	<script src="../assets/js/vfs_fonts.js"></script>

    <script>
        
        $("body").on("change", "#staffid", function(){
            
            var staffid = $(this).val();
            var session = $("#session").val();
            
            $('#class').html('<option value="0">Loading...</option>');
            
            if(staffid != '' && staffid != '0' && session != '' && session != '0')
            {

                var dataString = 'staffid='+ staffid + '&session='+ session;
                
                // alert(dataString);
                $.ajax({
                    url: '../../../phpscript/get-class-for-staff.php',
                    method:'POST',
                    data:dataString,
                    
                    success: function(maindata2) {
                    
                        $('#class').html(maindata2);
                        
                    }
                });
            }
            else
            {
                $('#class').html('<option value="0">Please select Staff and Session first</option>');
                
            }
             
        });
        
        $("body").on("change", "#class", function(){
            
            var staffid = $("#staffid").val();
            var classid = $(this).val();
            var session = $("#session").val();
            
            $('#classsection').html('<option value="0">Loading...</option>');
            
            if(classid != '' && classid != '0')
            {

                var dataString = 'classid=' + classid + '&staffid='+ staffid + '&session='+ session;
                
                // alert(dataString);
                $.ajax({
                    url: '../../../phpscript/get-class-section-staff.php',
                    method:'POST',
                    data:dataString,
                    
                    success: function(maindata2) {
                    
                        $('#classsection').html(maindata2);
                        
                    }
                });
            }
            else
            {
                $('#classsection').html('<option value="0">Please select class</option>');
                
            }
             
        });
        
        $("body").on("click", "#getstud", function(){
            
            $('#tbl_data').html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing');
            
            var classsection = $("#classsection").val();
            
            var classsectionactual = $('#classsection :selected').data('id');
            
            var classid = $("#class").val();
            
            var session = $("#session").val();
            
            var term = $("#term").val();
            
            var RemarkType = 'teacher';
            
            if(classid != '' && classid != '0' && classsection != '' && classsection != '0' && session != '' && session != '0' && term != '' && term != '0')
            {
                var dataString = 'classid=' + classid + '&classsection=' + classsection + '&session=' + session + '&term=' + term + '&classsectionactual=' + classsectionactual + '&RemarkType=' + RemarkType;
                
                // alert(dataString);
                $.ajax({
                    url: '../../../phpscript/view-studentmanualcomment.php',
                    method:'POST',
                    data:dataString,
                    
                    success: function(maindata2) {
                    
                        $('#tbl_data').html(maindata2);
                              
                    }
                });
            }
            else
            {
                $('#tbl_data').html('Please filter to view student list');
                
            }
             
        });
        
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
        
        $(document).on('input', '.britishfield', function () {
            
            var studentid = $(this).data('id');
            
            var extcomment = $(this).val();
        
            var session = $("#session").val();
            
            var term = $("#term").val();
            
            var RemarkType = 'teacher';
            
            var staffid = $("#staffid").val();
            
            var dataString = 'studentid='+ studentid + '&extcomment='+ extcomment + '&session='+ session + '&term='+ term + '&RemarkType='+ RemarkType + '&staffid='+ staffid;
            
            // alert(dataString);
            
            $.ajax({
                type: "POST",
                url: "../../../phpscript/updateStudentmancomment.php",
                data: dataString,
                cache: false,
                success: function(result)
                {
                    
                }
            });
       
        });
    </script>
  </body>
</html>
