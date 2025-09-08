<?php include ('../database/config.php');?>

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
    <title>Examination Group</title>
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
					

                    	    
                            <?php
                                if(isset($_POST['submitExam']))
                                {
                    
                                   $ExamType = $_POST['ExamType'];
                                   $ResultType = $_POST['ResultType'];
                                   
                                   $subject_groups = $_POST['subject_groups'];
                    
                                    if($ExamType == "" || $ResultType == "" || $ExamType == "0" || $subject_groups == "" || $subject_groups == "0" || $ResultType == "0")
                                    {
                                        echo"
                                        <div align='left' class='alert alert-warning'>
                                        <button type='button' class='close' data-dismiss='alert' aria-label='Close'> <span aria-hidden='true'>×</span> </button>Opps! Seem like you left some spaces blank/unseleceted. Check and resubmit</div>
                                        ";
                                    }
                                    else
                                    {
                    
                                        $sqlexamgroupName = "SELECT * FROM `examgroup` WHERE ExamGroupName='$ExamType' AND `ResultType`='$ResultType' AND `subject_groups_id`='$subject_groups'";
                                        $resultexamgroupName = mysqli_query($link, $sqlexamgroupName);
                                        $rowexamgroupName = mysqli_fetch_assoc($resultexamgroupName);
                                        $row_cntexamgroupName = mysqli_num_rows($resultexamgroupName);
                    
                                        if($row_cntexamgroupName > 0)
                                        {
                                            echo"
                                                <div align='left' class='alert alert-warning'>
                                                <button type='button' class='close' data-dismiss='alert' aria-label='Close'> <span aria-hidden='true'>×</span> </button>Exam Group already exists</div>
                                                ";
                                        }
                                        else
                                        {
                                            $sqlInsertexamgroup = ("INSERT INTO `examgroup` (`subject_groups_id`, `ExamGroupName`, `ResultType`) VALUES ('$subject_groups', '$ExamType', '$ResultType')");
                                            $Insertexamgroup = mysqli_query($link, $sqlInsertexamgroup) or die("".mysqli_error());
                                                        
                                            if($Insertexamgroup)
                                            {
                                                echo"
                                                <div align='left' class='alert alert-success'>
                                                <button type='button' class='close' data-dismiss='alert' aria-label='Close'> <span aria-hidden='true'>×</span> </button>Exam Group Added Successfully...</div>
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
                            
                            <?php
                                if(isset($_POST['DeleteExamGroup']))
                                {
                    
                                   $ExamTypeID = $_POST['ExamGroupID'];
                    
                                    $sqlDeleteexamgroup = ("DELETE FROM `examgroup` WHERE ExamGroupID = '$ExamTypeID'");
                                    $Deleteexamgroup = mysqli_query($link, $sqlDeleteexamgroup) or die("".mysqli_error());
                                                
                                    if($Deleteexamgroup)
                                    {
                                        echo"
                                        <div align='left' class='alert alert-success'>
                                        <button type='button' class='close' data-dismiss='alert' aria-label='Close'> <span aria-hidden='true'>×</span> </button>Exam Group Deleted Successfully...</div>
                                        ";
                                    }
                                    else
                                    {
                                        echo "Opps! not done. Something went wrong";
                                    }
                                }
                            ?>
                            
                            <?php
                                if(isset($_POST['EditExamGroup']))
                                {
                    
                                    $ExamTypeIDedit = $_POST['ExamGroupIDedit'];
                                    
                                    $ExamTypeedit = $_POST['ExamTypeedit'];
                                    
                                    $ResultTypeedit = $_POST['ResultTypeedit'];
                    
                                    $sqleditexamgroup = ("UPDATE `examgroup` SET `ExamGroupName`='$ExamTypeedit',`ResultType`='$ResultTypeedit' WHERE ExamGroupID = '$ExamTypeIDedit'");
                                    $editexamgroup = mysqli_query($link, $sqleditexamgroup) or die("".mysqli_error());
                                                
                                    if($editexamgroup)
                                    {
                                        echo"
                                        <div align='left' class='alert alert-success'>
                                        <button type='button' class='close' data-dismiss='alert' aria-label='Close'> <span aria-hidden='true'>×</span> </button>Exam Group Edited Successfully...</div>
                                        ";
                                    }
                                    else
                                    {
                                        echo "Opps! not done. Something went wrong";
                                    }
                                }
                            ?>
                    
                    		<div class="row" style="margin: 15px;">
                    
                                <div class="col-sm-4 col-md-4" >
                    				<div>
                    
                    					<h3 style="margin-bottom: 50px;">Add Exam Group</h3>
                    
                    					<form method="POST">
                    						<div class="form-group">
                    							<label for="gradeType">Subject Group</label>
                    							<select class="form-control" id="subject_groups" name="subject_groups">
                    								<option value="0">Select Subject Group</option>
                    								<?php
                                                
                                                        $sqlsubject_groups = "SELECT * FROM `subject_groups`";
                                                        $resultsubject_groups = mysqli_query($link, $sqlsubject_groups);
                                                        $rowsubject_groups = mysqli_fetch_assoc($resultsubject_groups);
                                                        $row_cntsubject_groups = mysqli_num_rows($resultsubject_groups);
                                    
                                                        if($row_cntsubject_groups > 0)
                                                        {
                                                            do{
                                                                
                                                                echo'<option value="'.$rowsubject_groups['id'].'">'.$rowsubject_groups['name'].'</option>';
                                                                
                                                            }while($rowsubject_groups = mysqli_fetch_assoc($resultsubject_groups));
                                                        }
                                                    ?>
                                                    
                    							</select>
                    						</div>
                    						
                    					
                    						<button name="submitExam" type="submit" class="btn btn-primary" style="margin-top: 30px;">Submit</button>
                    					</form>
                    
                    				</div>
                            
                                </div>
                    		
                    			<p></p>
                    
                                <div class="col-sm-8 col-md-8">
                    				
                                    <div class="table-responsive data_table">
                    
                    					<h3 style="margin-bottom: 50px;">Examination Group</h3>
                    
                    					<table id="example" class="table table-striped" style="width:100%">
                    						<thead>
                    							<tr>
                    								<th>Exam Group Name</th>
                    								<th>Subject Group</th>
                    								<th>Result Type</th>
                    								<th>Action</th>
                    							</tr>
                    						</thead>
                    						<tbody>
                    						    <?php
                                                    
                                                    $sqlexamgroupist = "SELECT * FROM `examgroup` INNER JOIN subject_groups ON examgroup.subject_groups_id=subject_groups.id";
                                                    $resultexamgroupist = mysqli_query($link, $sqlexamgroupist);
                                                    $rowexamgroupist = mysqli_fetch_assoc($resultexamgroupist);
                                                    $row_cntexamgroupist = mysqli_num_rows($resultexamgroupist);
                                
                                                    if($row_cntexamgroupist > 0)
                                                    {
                                                        do{
                                                            
                                                            echo'
                    						                    <tr>
                                                                    <td>'.$rowexamgroupist['ExamGroupName'].'</td>
                                                                    <td>'.$rowexamgroupist['name'].'</td>
                                    								<td>'.$rowexamgroupist['ResultType'].' ResultSheet</td>
                                    								<td>
                                    									<a href="addExam.php?examname='.$rowexamgroupist['ExamGroupName'].'&resulttype='.$rowexamgroupist['ResultType'].'&id='.$rowexamgroupist['id'].'" style="color: #000000;">
                                    										<i class="fa fa-plus" title="Add Exam" data-toggle="tooltip" aria-hidden="true"></i>
                                    									</a>
                                    									<a href="#" style="color: #000000; margin-left: 5px;">
                                    										<i class="fa fa-pencil editicon" data-id="'.$rowexamgroupist['ExamGroupID'].'" data-name="'.$rowexamgroupist['ExamGroupName'].'" data-result="'.$rowexamgroupist['ResultType'].'" data-toggle="modal" data-target="#editModal" title="Edit" data-toggle="tooltip" aria-hidden="true"></i>
                                    									</a>
                                    									<a href="#" style="color: #ff0000; margin-left: 5px;">
                                    										<i class="fa fa-trash delicon" data-id="'.$rowexamgroupist['ExamGroupID'].'" data-toggle="modal" data-target="#deleteModal" title="Delete" data-toggle="tooltip" aria-hidden="true"></i>
                                    									</a>
                                    									
                                    								</td>
                    						                    </tr>
                                                            ';
                                                            
                                                        }while($rowexamgroupist = mysqli_fetch_assoc($resultexamgroupist));
                                                    }
                                                ?>
                    						</tbody>
                    					</table>
                    
                                    </div>
                                </div>
                    		</div>
                    
                    		 <!-- Delete Modal -->
                    		 <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            
                                        <h3 class="modal-title" id="exampleModalLabel" style="margin-left: 35%; color: #ff0000;">Warning <i class="fa fa-exclamation-triangle"></i></h3>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                        </div>
                                        <div class="modal-body" align="center">
                                            <span style="font-size: 18px; font-weight: 500;">Are you sure you want to Delete this Exam Group. <br>
                                            Please note that this action cannot be reversed!!!</span>
                                        </div>
                                        <div class="modal-footer">
                                            <form method="POST">
                        						<input type="hidden" id="myid" name="ExamGroupID">
                            						
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        						<button name="DeleteExamGroup" type="submit" class="btn btn-primary">Submit</button>
                        					</form>
                                            
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Delete Modal -->
                    
                    
                    		 <!-- Edit Modal -->
                    		 <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            
                                            <h3 class="modal-title" id="exampleModalLabel" style="margin-left: 35%; color: Black;">Edit Exam Group</h3>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        	
                    					<form method="POST">
                                            <div class="modal-body">
                                                <div class="form-group">
                        						  <label for="gradeName">Exam Type</label>
                        						  <input type="text" class="form-control" id="gradeNameedit" name="ExamTypeedit">
                        						</div>
                        						<div class="form-group">
                        							<label for="gradeType">Result Type</label>
                        							<select class="form-control" id="gradeTypeedit" name="ResultTypeedit">
                        								<option value="0">Select Result Type</option>
                        								<option value="British">British ResultSheet</option>
                        								<option value="AphaNumeric(Class Position and Subject Grade)">AlphaNumeric (Class Position and Subject Grade)</option>
                        								<option value="AphaNumeric(Class Grade and Subject Position)">AlphaNumeric (Class Grade and Subject Position)</option>
                        							  </select>
                        						</div>
                                            </div>
                            				<input type="hidden" id="myidedit" name="ExamGroupIDedit">
                                						
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        						<button name="EditExamGroup" type="submit" class="btn btn-primary">Submit</button>
                            					
                                            </div>
                                        </form>
                                            
                                    </div>
                                </div>
                            </div>
                            <!-- Edit Modal -->
                    	
					</div>

			</div>
		</div>
    </div>








		<!-- My own external JS file -->
		<script src="../assets/js/myScript.js"></script>
	<!-- Option 1: jQuery and Bootstrap Bundle (includes Popper) -->
   	<script src="../assets/bootstrap/js/jquery.slim.min.js"></script>
	<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>



	<script src="../assets/js/jquery-3.5.1.js"></script>
	<script src="../assets/js/jquery.dataTables.min.js"></script>
   <script src="../assets/js/datatables.min.js"></script>
	<script src="../assets/js/pdfmake.min.js"></script>
	<script src="../assets/js/vfs_fonts.js"></script>

	
        <script>
        if ( window.history.replaceState ) {
            window.history.replaceState( null, null, window.location.href );
        }
        </script>  
        
        <script type="text/javascript">
            $('body').on('click','.delicon',function () {
                
                var myid = $(this).data('id');
                
                $('#myid').val(myid);
                
            });
            
            $('body').on('click','.editicon',function () {
                
                var myid = $(this).data('id');
                
                var name = $(this).data('name');
                
                var result = $(this).data('result');
                
                $('#myidedit').val(myid);
                
                $('#gradeNameedit').val(name);
                
                $('#gradeTypeedit').val(result);
                
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
            





        </script>
  </body>
</html>