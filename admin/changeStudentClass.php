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
    <title>Change Student Class</title>
    <style>
        .inCheckBox{
            margin-top: -5px;
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
        
            <div class="col-sm-12">
				
                <div class="cardBoxSty">

				<form>
                    <div class="form-row">
                        <div class="form-group col-sm">
                            <select class="form-control">
                                <option>Select Session</option>
                                <option>2020/2021</option>
                                <option>2021/2022</option>
                                <option>2022/2023</option>
                            </select>
						</div>
						
						<div class="form-group col-sm">
                            <select class="form-control">
                                <option>Select Term</option>
                                <option>1st Term</option>
                                <option>2nd Term</option>
                                <option>3rd Term</option>
                            </select>
                        </div>
					

                        <div class="form-group col-sm">
                            <select class="form-control">
                                <option>Select Class</option>
                                <option>Nursery 1</option>
								<option>Nursery 2</option>
								<option>Primary 1</option>
                            </select>
						</div>

						<div class="form-group col-sm">
                            <select class="form-control">
                                <option>Select Section</option>
                                <option>Blue</option>
								<option>Red</option>
                            </select>
						</div>
						
                        <div class="form-group col-sm">
							<button type="submit" class="btn btn-primary" style="border-radius: 20px;">
								<i class="fa fa-search" aria-hidden="true"></i>
								<span style="margin-left: 5px;">Search</span>
							</button>
                        </div>
                    </div>
                </form>
					

                </div>
            </div>
		</div>

		<div class="row" style="margin: 15px;">
        
            <div class="col-sm-12">
				
                <div class="table-responsive data_table">

					<h3 style="margin-bottom: 50px;">Change Student Class</h3>

                    
                    <button type="submit" data-toggle="modal" data-target="#exampleModal" class="btn btn-primary" style="border-radius: 20px; float: right;">
                        Change All 
                    </button>
                 
					<table id="example" class="table table-striped" style="width:100%">
						<thead>
							<tr>
								<th>Reg No.</th>
								<th>Students</th>
								<th>Class</th>
                                <th>Section</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>1102</td>
								<td>Eva Monday</td>
								<td>Nursery One</td>
                                <td>Blue</td>
								<td>
                                    <button type="submit" data-toggle="modal" data-target="#changeModal" class="btn btn-primary" style="border-radius: 20px;">
                                        Change Class
                                    </button>
                                </td>
							</tr>
							<tr>
								<td>1103</td>
								<td>Chinoso Madu</td>
								<td>Nursery One</td>
                                <td>Blue</td>
								<td>
                                    <button type="submit" data-toggle="modal" data-target="#changeModal" class="btn btn-primary" style="border-radius: 20px;">
                                        Change Class
                                    </button>
                                </td>
							</tr>
							<tr>
								<td>1104</td>
								<td>Henry Peters</td>
								<td>Nursery One</td>
                                <td>Blue</td>
								<td>
                                    <button type="submit" data-toggle="modal" data-target="#changeModal" class="btn btn-primary" style="border-radius: 20px;">
                                        Change Class
                                    </button>
                                </td>
							</tr>
							<tr>
								<td>1105</td>
								<td>Habiba John</td>
								<td>Nursery One</td>
                                <td>Blue</td>
								<td>
                                    <button type="submit" data-toggle="modal" data-target="#changeModal" class="btn btn-primary" style="border-radius: 20px;">
                                        Change Class
                                    </button>
                                </td>
							</tr>
							
						
						</tbody>
					</table>

                </div>
            </div>
		</div>
		
        
        
        <!-- Change Single Student Classroom modal -->
        <!-- Change Single Student Classroom modal -->

        <div class="modal fade" id="changeModal" tabindex="-1" aria-labelledby="changeModalLabel" aria-hidden="true">

            <div class="modal-dialog">

                <div class="modal-content">

                    <div class="modal-header">
                        <h4 style="margin-left: 20%;" class="modal-title" id="exampleModalLabel">Change Class-Rooms</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        
                        <form>
                           
                            <div class="form-row"> 
                                <div class="col-sm-12">
                                    <label style="font-weight: 600;">Classrooms:</label>
                                    <select class="form-control">
                                        <option>Select Class</option>
                                    </select>
                                </div>
                                <div class="col-sm-12" style="margin-top: 20px;">
                                    <label style="font-weight: 600;">Section:</label>
                                    <select class="form-control">
                                        <option>Select Section</option>
                                    </select>
                                </div>
                            </div>

                        </form>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary">Save changes</button>
                    </div>
                    
                </div>

            </div>

        </div>

       <!-- Change Single Student Classroom modal -->
        <!-- Change Single Student Classroom modal -->


        
        <!-- Change all Student Classroom modal -->
        <!-- Change allStudent Classroom modal -->

        <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">

            <div class="modal-dialog">

                <div class="modal-content">

                    <div class="modal-header">
                        <h4 style="margin-left: 23%;" class="modal-title" id="exampleModalLabel">Change Class-Rooms</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <form>

                            <div class="form-row">
                                <div class="col-sm-9">
                                  
                                </div>

                               
                                <div class="col-sm-3">
                                    <button type="submit" class="btn btn-primary" style="border-radius: 30px;">
                                        <span>Load Students</span>
                                    </button>
                                </div>
                            </div>


                            <div class="form-row" style="margin-top: 30px;">
                                <div class="col-sm-2 col-xs-2">
                                    <b>Reg No.</b>
                                </div>

                                <div class="col-sm-2 col-xs-2">
                                    <b>Student</b>
                                </div>
                                <div class="col-sm-4 col-xs-4">
                                    <b >Classroom</b>
                                </div>

                                <div class="col-sm-4 col-xs-4">
                                    <b>Section</b>
                                </div>
                            </div>
                            <hr>

                            <div class="form-row" style="margin: 10px;">
                               
                                <div class="col-sm-2">
                                    <span>010</span>
                                </div>

                                <div class="col-sm-2">
                                    <span>Joe Mark</span>
                                </div>

                                <div class="col-sm-4">
                                    <select class="form-control">
                                        <option>Class</option>
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <select class="form-control">
                                        <option>Section</option>
                                    </select>
                                </div>
                            </div>
                            <hr>
                            <div class="form-row" style="margin: 10px;">
                               
                                <div class="col-sm-2">
                                    <span>010</span>
                                </div>

                                <div class="col-sm-2">
                                    <span>Joe Mark</span>
                                </div>

                                <div class="col-sm-4">
                                    <select class="form-control">
                                        <option>Class</option>
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <select class="form-control">
                                        <option>Section</option>
                                    </select>
                                </div>
                            </div>
                            <hr>
                            <div class="form-row" style="margin: 10px;">
                               
                                <div class="col-sm-2">
                                    <span>010</span>
                                </div>

                                <div class="col-sm-2">
                                    <span>Joe Mark</span>
                                </div>

                                <div class="col-sm-4">
                                    <select class="form-control">
                                        <option>Class</option>
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <select class="form-control">
                                        <option>Section</option>
                                    </select>
                                </div>
                            </div>
                            <hr>
                            <div class="form-row" style="margin: 10px;">
                               
                                <div class="col-sm-2">
                                    <span>010</span>
                                </div>

                                <div class="col-sm-2">
                                    <span>Joe Mark</span>
                                </div>

                                <div class="col-sm-4">
                                    <select class="form-control">
                                        <option>Class</option>
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <select class="form-control">
                                        <option>Section</option>
                                    </select>
                                </div>
                            </div>
                            <hr>
                            <div class="form-row" style="margin: 10px;">
                               
                                <div class="col-sm-2">
                                    <span>010</span>
                                </div>

                                <div class="col-sm-2">
                                    <span>Joe Mark</span>
                                </div>

                                <div class="col-sm-4">
                                    <select class="form-control">
                                        <option>Class</option>
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <select class="form-control">
                                        <option>Section</option>
                                    </select>
                                </div>
                            </div>
                            <hr>
                            <div class="form-row" style="margin: 10px;">
                               
                                <div class="col-sm-2">
                                    <span>010</span>
                                </div>

                                <div class="col-sm-2">
                                    <span>Joe Mark</span>
                                </div>

                                <div class="col-sm-4">
                                    <select class="form-control">
                                        <option>Class</option>
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <select class="form-control">
                                        <option>Section</option>
                                    </select>
                                </div>
                            </div>

                        </form>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary">Save changes</button>
                    </div>
                    
                </div>

            </div>

        </div>

    <!-- Change all Student Classroom modal -->
    <!-- Change all Student Classroom modal -->



                        
					</div>

			</div>
		</div>
        </div>
	







		
	<script>
		$(document).ready(function(){
			var table = $('#example').DataTable({
				scrollX: true,
				buttons:['copy', 'csv', 'excel', 'pdf', 'print']
			});

			table.buttons().container()
			.appendTo('#example_wrapper .col-md-6:eq(0)');
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
	
	<!-- Option 1: jQuery and Bootstrap Bundle (includes Popper) -->
   	<script src="../assets/bootstrap/js/jquery.slim.min.js"></script>
	<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>



	<script src="../assets/js/jquery-3.5.1.js"></script>
	<script src="../assets/js/jquery.dataTables.min.js"></script>
   <script src="../assets/js/datatables.min.js"></script>
	<script src="../assets/js/pdfmake.min.js"></script>
	<script src="../assets/js/vfs_fonts.js"></script>

	

  </body>
</html>