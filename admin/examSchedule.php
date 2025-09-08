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
    <title>Exam Schedule</title>
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
                                <option>Select Exam Group</option>
                                <option>Creche Examination</option>
                                <option>Nursery Examination</option>
                                <option>Primary Examination</option>
                                <option>Secondary Examination</option>
                            </select>
						</div>
						
						<div class="form-group col-sm">
                            <select class="form-control">
                                <option>Select Exam Names</option>
                                <option>1st CA</option>
                                <option>2nd CA</option>
                                <option>3rd CA</option>
                                <option>Class Exam</option>
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

					<h3 style="margin-bottom: 50px;">Exam Schedule</h3>

					<table id="example" class="table table-striped table-sm" style="width:100%">
						<thead>
							<tr>
								<th>Sujects</th>
								<th>Date From</th>
								<th>Start Time</th>
								<th>Duration</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>Mathematics</td>
								<td>10/11/2022</td>
								<td>08:00 Am</td>
								<td>40 Minutes</td>
							</tr>
							<tr>
								<td>English Language</td>
								<td>11/11/2022</td>
								<td>08:00 Am</td>
								<td>45 Minutes</td>
							</tr>
							<tr>
								<td>Computer Science</td>
								<td>12/11/2022</td>
								<td>08:00 Am</td>
								<td>30 Minutes</td>
							</tr>
							<tr>
								<td>Home Economics</td>
								<td>13/11/2022</td>
								<td>08:00 Am</td>
								<td>30 Minutes</td>
							</tr>
							
						
						</tbody>
					</table>

                </div>
            </div>
		</div>
		
                        
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