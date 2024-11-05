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
    <title>Affective Domain Setting</title>
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
					
                     <div class="messagetoo"></div>
                    
                    		<div class="row" style="margin: 15px;">
                                <div class="col-sm-4 col-md-4">
                    				<div>
                    
                    					<h3 style="margin-bottom: 50px;">Create Affective Domains</h3>
                    
                    					<form>
                    						<div class="form-group">
                    						  <label for="CaTitle">Affective Domain Title</label>
                    						  <input type="text" class="form-control" id="CaTitle" name="ExamType" placeholder="Primary CA">
                    						</div>
                    						
                    						<div class="form-group">
                                                <select class="form-control" id="examNumber">
                                                    <option>Select Number of Affective Domains</option>
                                                    <option value="1">1</option>
                                                    <option value="2">2</option>
                                                    <option value="3">3</option>
                                                    <option value="4">4</option>
                                                    <option value="5">5</option>
                                                    <option value="6">6</option>
                                                    <option value="7">7</option>
                                                    <option value="8">8</option>
                                                    <option value="9">9</option>
                                                    <option value="10">10</option>
                                                    <option value="11">11</option>
                                                    <option value="12">12</option>
                                                    <option value="13">13</option>
                                                    <option value="14">14</option>
                                                    <option value="15">15</option>
                                                </select>
                    
                                            </div>
                    
                    						<div id="addNumber">
                                                
                                            </div>
                    
                    						<button class="btn btn-primary submitbtn" style="margin-top: 30px;">Submit</button>
                    					</form>
                    
                    				</div>
                            
                                </div>
                            
                                <div class="col-sm-8 col-md-8">
                    				
                                    <div class="table-responsive data_table">
                    
                                        <h3>Affective Domain List</h3>
                                        <hr>
                                        
                                        <div class="row" style="margin-bottom: 50px;">
                                            <div class="col-sm-12">
                                            
                                            </div>
                                        </div>
                    
                    					<table id="example" class="table table-striped table-sm"  style="width:100%;">
                    						<thead>
                    							<tr>
                    								<th>AD Title</th>
                                                    <th>Classes Assigned</th>
                    								<th>Action</th>
                    							</tr>
                    						</thead>
                    						<tbody>
                    						    <?php
                                                    
                                                    $sqlresultsetting = "SELECT * FROM `affective_domain_settings`";
                                                    $resultresultsetting = mysqli_query($link, $sqlresultsetting);
                                                    $rowresultsetting = mysqli_fetch_assoc($resultresultsetting);
                                                    $row_cntresultsetting = mysqli_num_rows($resultresultsetting);
                                
                                                    if($row_cntresultsetting > 0)
                                                    {
                                                        do{
                                                            
                                                            $resultsettingid = $rowresultsetting['id'];
                                                            
                                                            echo'
                    						                    <tr>
                                                                    <td>'.$rowresultsetting['ADTitle'].'</td>';
                                                                    
                                    								$sqlassignsaftoclass = "SELECT * FROM `assignsaftoclass` WHERE AffectiveDomainSettingsId='$resultsettingid'";
                                                                    $resultassignsaftoclass = mysqli_query($link, $sqlassignsaftoclass);
                                                                    $rowassignsaftoclass = mysqli_fetch_assoc($resultassignsaftoclass);
                                                                    $row_cntassignsaftoclass = mysqli_num_rows($resultassignsaftoclass);
                                                
                                    								echo'<td>'.$row_cntassignsaftoclass.'</td>
                                    								<td>
                                                                        <button style="color: #000000; margin-left: 10px;border:none;" data-id="'.$rowresultsetting['id'].'" data-resulttype="'.$rowresultsetting['id'].'" id="addstudentbtn">
                                    										<i class="fa fa-tag" title="Assign/View Class" data-toggle="modal" data-target="#exampleModal" data-toggle="tooltip" aria-hidden="true"></i>
                                    									</button>
                                                                        <button style="color: #000000; margin-left: 10px;border:none;" data-numca="'.$rowresultsetting['NumberofAD'].'" data-id="'.$rowresultsetting['ADTitle'].'" id="editsubjectbtn">
                                                                            <i class="fa fa-pencil"  title="Edit" data-toggle="tooltip" aria-hidden="true"></i>
                                                                            <!--Onclick on the edit icon it should bring them back to the add exam section-->
                                                                        </button>
                                                                        
                                                                     '; ?>
                                                                     <a data-placement="left" href="feenext/affective_domain_delete/<?php echo $resultsettingid; ?>"class="btn btn-default btn-xs"  data-toggle="tooltip" title="" onclick="return confirm('Proceed and Delete')">
                                                                             <i class="fa fa-remove"></i>
                                                                        </a>
                                                                     <?php echo '
                                    								</td>
                    						                    </tr>
                                                            ';
                                                        }while($rowresultsetting = mysqli_fetch_assoc($resultresultsetting));
                                                            
                                                    }
                                                ?>
                    						</tbody>
                    					</table>
                    
                                    </div>
                                </div>
                    		</div>
                    		<input type="hidden" id="cainput">
                    		
                            <!--===== Add Subject to Exam Modal =======-->
                            <!--===== Add Subject to Exam Modal =======-->
                    
                            <div class="modal fade" id="addSubjectModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    
                                <div class="modal-dialog modal-lg">
                    
                                    <div class="modal-content">
                    
                                        <div class="modal-header">
                                            <h4 style="margin-left: 40%;" class="modal-title" id="exampleModalLabel">Add Subjects</h4>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                            
                                        </div>
                    
                                        <div class="modal-body">
                                            
                                            <div class="row" style="margin-bottom: 50px;">
                                                <div class="col-sm-3">
                                                    <b>Exam Group Name:</b>
                                                    <p><?php echo $_GET['examname'];?></p>
                                                    
                                                </div>
                        
                                                <div class="col-sm-3">
                                                    <b>Exam Name:</b>
                                                    <p id="displayexamname"></p>
                                                </div>
                        
                                                <div class="col-sm-3">
                        
                                                </div>
                        
                                                <div class="col-sm-3">
                                                  
                                                </div>
                                            </div>
                                            
                                            <div class="form-group">
                                                <button id="addExamNumberbtn" class="btn btn-primary" style="border-radius: 20px; float: right; margin-top: -40px;">Add Exam Subject</button>
                                            </div>
                                            <div id="errmsgnew"></div>
                                            
                                            <div class="form-row"> 
                                                    <div class="col-sm">
                                                        <label style="font-weight: 600;">Exam Subjects:</label>
                                                        
                                                    </div>
                                                    <!--<div class="col-sm">-->
                                                    <!--    <label style="font-weight: 600;">Date:</label>-->
                                                       
                                                    <!--</div>-->
                                                    <!--<div class="col-sm">-->
                                                    <!--    <label style="font-weight: 600;">Time:</label>-->
                                                       
                                                    <!--</div>-->
                                                    <!--<div class="col-sm">-->
                                                    <!--    <label style="font-weight: 600;">Duration:</label>-->
                                                        
                                                    <!--</div>-->
                    
                                                    <!--<div class="col-sm">-->
                                                    <!--    &nbsp;&nbsp;-->
                                                    <!--</div>-->
                                            </div>
                                            <div id="addExamNumber">
                                                <i class="fa fa-circle-o-notch fa-spin"></i>
                                            </div>
                                            <input type="hidden" id="totadded" value="1">
                                            <input type="hidden" id="sessionnewinput">
                                            <input type="hidden" id="termnewinput">
                                        </div>
                    
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                            <button type="button" class="btn btn-primary" id="submitsubbtn">Save changes</button>
                                        </div>
                                        
                                    </div>
                    
                                </div>
                    
                            </div>
                    
                            <!--===== Add Subject to Exam Modal =======-->
                            <!--===== Add Subject to Exam Modal =======-->
                    
                    
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
                                            <div id="successmsg"></div>
                                            <span style="font-size: 18px; font-weight: 500;">Are you sure you want to Delete this Exam. <br>
                                            Please note that this action cannot be reversed!!!</span>
                                            
                                            <input type="hidden" id="examNumberinput">
                                            <input type="hidden" id="sessioninput">
                                            <input type="hidden" id="terminput">
                                            
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                            <button type="button" class="btn btn-primary" id="proceeddelete"><i class="fa fa-check"></i> Submit</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Delete Modal -->
                    
                    
                            <!-- Modal Assign Student to Examination Modal -->
                            <!-- Modal Assign Student to Examination Modal -->
                    
                                <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    
                                    <div class="modal-dialog">
                    
                                        <div class="modal-content">
                    
                                            <div class="modal-header">
                                                <h4 style="margin-left: 25%;" class="modal-title" id="exampleModalLabel">Assign Affective Domains to Class</h4>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                    
                                            <div class="modal-body">
                                                <form>
                                                    <div id="errmsgnewstudent"></div>
                                                    
                                                    <div class="form-row" id="displayclasses">
                                                        
                                                        
                    
                                                    </div>
                    
                                                </form>
                    
                                            </div>
                    
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                <button type="button" class="btn btn-primary" id="submitstudentbtn">Save changes</button>
                                            </div>
                                            
                                        </div>
                    
                                    </div>
                    
                                </div>
                    
                            <!-- Modal Assign Student to Examination Modal -->
                            <!-- Modal Assign Student to Examination Modal -->
					</div>

			</div>
		    </div>
        </div>
	    
	    
	    

        
    <script>
        
        ///this is Exam list page
        // $("body").on('click','#addExamNumberbtn',function(){
            
        //     $(this).html('<i class="fa fa-circle-o-notch fa-spin"></i>')
            
        //     var totadded = $('#totadded').val();
            
        //     var examNumber = parseInt(totadded) + 1;
            
        //     $('#totadded').val(examNumber);
            
        //     event.preventDefault()
        //     $.ajax({
        //         url: '../../../phpscript/addExamNumber.php',
        //         method:'POST',
        //         data: 'examNumber=' + examNumber,
        //         success: function(data) {
        //             $('#addExamNumber').append(data);
        //             $('#addExamNumberbtn').html('Add Exam Subject')
        //         }
        //     });
        // });
        
        // $("body").on('click','.removeExamNumberbtn',function(){
            
        //     $(this).html('<i class="fa fa-circle-o-notch fa-spin"></i>');
            
        //     event.preventDefault()
            
        //     var myid = $(this).data('id');
            
        //     var id = $(this).data('myid');
            
        //     $('#totadded').val(parseInt($('#totadded').val()) - 1);
            
        //     $.ajax({
        //         url: '../../../phpscript/removeExamNumber.php',
        //         method:'POST',
        //         data: 'id=' + id,
        //         success: function(data) {
                    
        //             $('#'+myid).remove();
            
        //         }
        //     });
        // });

    </script>

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
        
        // $("body").on("click", "#submitsubbtn", function(){
            
        //     $(this).html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing')
        //     var subjectid = [];
        //     $.each($(".subjectid"), function(){            
        //         subjectid.push($(this).val());
        //     });
            
        //     var subjectexamdate = [];
        //     $.each($(".subjectexamdate"), function(){            
        //         subjectexamdate.push($(this).val());
        //     });
            
        //     var subjectexamtime = [];
        //     $.each($(".subjectexamtime"), function(){            
        //         subjectexamtime.push($(this).val());
        //     });
            
        //     var subjectexamduration = [];
        //     $.each($(".subjectexamduration"), function(){            
        //         subjectexamduration.push($(this).val());
        //     });
            

        //     var session = $('#sessionnewinput').val();
            
        //     var term = $('#termnewinput').val();
            
        //     var cainput = $('#cainput').val();
            
        //     var examname = "<?php echo $examID;?>";
            
        //     if(examname != '' && examname != '0' && subjectid.length > 0)
        //     {

        //         var dataString = 'examname=' + examname + '&session=' + session + '&term=' + term + '&cainput=' + cainput + '&subjectid=' + subjectid;
                
        //         // alert(dataString);
        //         $.ajax({
        //             url: '../../../phpscript/insert-subjectexam.php',
        //             method:'POST',
        //             data:dataString,
                    
        //             success: function(maindata2) {
                    
        //                 $('#errmsgnew').html(maindata2);
        //                 window.scrollTo(0, 0);
        //                 $('#submitsubbtn').html('Save changes');
                        
        //                 setTimeout(function() {
        //                     location.reload();
        //                 }, 5000);
                        
        //             }
        //         });
        //     }
        //     else
        //     {
        //         $('#errmsgnew').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
        //         window.scrollTo(0, 0); 
        //         $('#submitsubbtn').html('Save changes');
        //     }
             
        // });
        
        // $("body").on("change", "#class", function(){
  
        //     var classid = $(this).val();
            
        //     $('#classsection').html('<option value="0">Loading...</option>');
            
        //     if(classid != '' && classid != '0')
        //     {

        //         var dataString = 'classid=' + classid;
                
        //         // alert(dataString);
        //         $.ajax({
        //             url: '../../../phpscript/get-class-section.php',
        //             method:'POST',
        //             data:dataString,
                    
        //             success: function(maindata2) {
                    
        //                 $('#classsection').html(maindata2);
                        
        //             }
        //         });
        //     }
        //     else
        //     {
        //         $('#classsection').html('<option value="0">Please select class</option>');
                
        //     }
             
        // });
        
        $("body").on("click", "#addstudentbtn", function(){
            
            $('#displayclasses').html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing');
            
            var resultsettingid = $(this).data('id');
            
            var dataString = 'resultsettingid=' + resultsettingid;
                
                // alert(dataString);
            $.ajax({
                url: '../../../phpscript/affectivedomain/get-class-assignedtoAF.php',
                method:'POST',
                data:dataString,
                
                success: function(maindata2) {
                
                    $('#displayclasses').html(maindata2);
                    
                }
            });
        });
        
        $("body").on("click", ".submitbtn", function(){
            
            event.preventDefault()
            $(this).html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing')
            
            var examNumber = $('#examNumber').val();
            
            var catitle = $('#CaTitle').val();
            
            if(catitle != '' && catitle != '0')
            {
                if(examNumber == '1')
                {
                    $('#ca2id').val('');
                    $('#ca2maxid').val('0');
                    $('#ca3id').val('');
                    $('#ca3maxid').val('0');
                    $('#ca4id').val('');
                    $('#ca4maxid').val('0');
                    $('#ca5id').val('');
                    $('#ca5maxid').val('0');
        
                    $('#ca6id').val('');
                    $('#ca6maxid').val('0');
                    $('#ca7id').val('');
                    $('#ca7maxid').val('0');
                    $('#ca8id').val('');
                    $('#ca8maxid').val('0');
                    $('#ca9id').val('');
                    $('#ca9maxid').val('0');
                    $('#ca10id').val('');
                    $('#ca10maxid').val('0');
                    $('#ca11id').val('');
                    $('#ca11maxid').val('0');
                    $('#ca12id').val('');
                    $('#ca12maxid').val('0');
                    $('#ca13id').val('');
                    $('#ca13maxid').val('0');
                    $('#ca14id').val('');
                    $('#ca14maxid').val('0');
                    $('#ca15id').val('');
                    $('#ca15maxid').val('0');
                    
                    
                    var ca1id = $('#ca1id').val();
                    var ca1maxid = $('#ca1maxid').val();
                    var ca2id = $('#ca2id').val();
                    var ca2maxid = $('#ca2maxid').val();
                    var ca3id = $('#ca3id').val();
                    var ca3maxid = $('#ca3maxid').val();
                    var ca4id = $('#ca4id').val();
                    var ca4maxid = $('#ca4maxid').val();
                    var ca5id = $('#ca5id').val();
                    var ca5maxid = $('#ca5maxid').val();
        
                    var ca6id = $('#ca6id').val();
                    var ca6maxid = $('#ca6maxid').val();
                    var ca7id = $('#ca7id').val();
                    var ca7maxid = $('#ca7maxid').val();
                    var ca8id = $('#ca8id').val();
                    var ca8maxid = $('#ca8maxid').val();
                    var ca9id = $('#ca9id').val();
                    var ca9maxid = $('#ca9maxid').val();
                    var ca10id = $('#ca10id').val();
                    var ca10maxid = $('#ca10maxid').val();
                    var ca11id = $('#ca11id').val();
                    var ca11maxid = $('#ca11maxid').val();
                    var ca12id = $('#ca12id').val();
                    var ca12maxid = $('#ca12maxid').val();
                    var ca13id = $('#ca13id').val();
                    var ca13maxid = $('#ca13maxid').val();
                    var ca14id = $('#ca14id').val();
                    var ca14maxid = $('#ca14maxid').val();
                    var ca15id = $('#ca15id').val();
                    var ca15maxid = $('#ca15maxid').val();
                    
                    if(ca1id == '' || ca1maxid == '0')
                    {
                        $('.messagetoo').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
                        window.scrollTo(0, 0); 
                        $('.submitbtn').html('Submit');
                    }
                    else
                    {
                        
                        var selectedcaformidterm = [];
                        $.each($("input[name='selForMidTerm']:checked"), function(){
                            selectedcaformidterm.push($(this).data('id'));
                        });
            
                            var dataString = 'catitle=' + catitle +'&ca1id=' + ca1id + '&ca1maxid=' + ca1maxid +'&ca2id=' + ca2id+'&ca2maxid=' +ca2maxid +'&ca3id=' +ca3id+ '&ca3maxid=' +  ca3maxid   + '&ca4id='+  ca4id +'&ca4maxid='+ca4maxid + '&ca5id='+   ca5id +'&ca5maxid='+ca5maxid +'&examNumber=' + examNumber+'&ca6id=' + ca6id + '&ca6maxid=' + ca6maxid +'&ca7id=' + ca7id+'&ca7maxid=' +ca7maxid +'&ca8id=' +ca8id+ '&ca8maxid=' +  ca8maxid   + '&ca9id='+  ca9id +'&ca9maxid='+ca9maxid + '&ca10id='+   ca10id +'&ca10maxid='+ca10maxid + '&ca11id='+   ca11id +'&ca11maxid='+ca11maxid + '&ca12id='+   ca12id +'&ca12maxid='+ca12maxid + '&ca13id='+   ca13id +'&ca13maxid='+ca13maxid + '&ca14id='+   ca14id +'&ca14maxid='+ca14maxid + '&ca15id='+   ca15id +'&ca15maxid='+ca15maxid + '&selectedcaformidterm=' + selectedcaformidterm;
                
                            $.ajax({
                                url: '../../../phpscript/affectivedomain/updateaffectivedomainsettings.php',
                                method:'POST',
                                data:dataString,
                                
                                success: function(maindata2) {
                                
                                    $('.messagetoo').html(maindata2);
                                    window.scrollTo(0, 0);
                                    $('.submitbtn').html('Submit');
                                    
                                    setTimeout(function() {
                                        location.reload();
                                    }, 5000);
                                    
                                }
                            });
                    }
                    
                }
                else if(examNumber == '2')
                {
                    $('#ca3id').val('');
                    $('#ca3maxid').val('0');
                    $('#ca4id').val('');
                    $('#ca4maxid').val('0');
                    $('#ca5id').val('');
                    $('#ca5maxid').val('0');
        
                    $('#ca6id').val('');
                    $('#ca6maxid').val('0');
                    $('#ca7id').val('');
                    $('#ca7maxid').val('0');
                    $('#ca8id').val('');
                    $('#ca8maxid').val('0');
                    $('#ca9id').val('');
                    $('#ca9maxid').val('0');
                    $('#ca10id').val('');
                    $('#ca10maxid').val('0');
                    $('#ca11id').val('');
                    $('#ca11maxid').val('0');
                    $('#ca12id').val('');
                    $('#ca12maxid').val('0');
                    $('#ca13id').val('');
                    $('#ca13maxid').val('0');
                    $('#ca14id').val('');
                    $('#ca14maxid').val('0');
                    $('#ca15id').val('');
                    $('#ca15maxid').val('0');
                    
                    
                    var ca1id = $('#ca1id').val();
                    var ca1maxid = $('#ca1maxid').val();
                    var ca2id = $('#ca2id').val();
                    var ca2maxid = $('#ca2maxid').val();
                    var ca3id = $('#ca3id').val();
                    var ca3maxid = $('#ca3maxid').val();
                    var ca4id = $('#ca4id').val();
                    var ca4maxid = $('#ca4maxid').val();
                    var ca5id = $('#ca5id').val();
                    var ca5maxid = $('#ca5maxid').val();
        
                    var ca6id = $('#ca6id').val();
                    var ca6maxid = $('#ca6maxid').val();
                    var ca7id = $('#ca7id').val();
                    var ca7maxid = $('#ca7maxid').val();
                    var ca8id = $('#ca8id').val();
                    var ca8maxid = $('#ca8maxid').val();
                    var ca9id = $('#ca9id').val();
                    var ca9maxid = $('#ca9maxid').val();
                    var ca10id = $('#ca10id').val();
                    var ca10maxid = $('#ca10maxid').val();
                    var ca11id = $('#ca11id').val();
                    var ca11maxid = $('#ca11maxid').val();
                    var ca12id = $('#ca12id').val();
                    var ca12maxid = $('#ca12maxid').val();
                    var ca13id = $('#ca13id').val();
                    var ca13maxid = $('#ca13maxid').val();
                    var ca14id = $('#ca14id').val();
                    var ca14maxid = $('#ca14maxid').val();
                    var ca15id = $('#ca15id').val();
                    var ca15maxid = $('#ca15maxid').val();
                    
                    if(ca1id == '' || ca1maxid == '0' || ca2id == '' || ca2maxid == '0')
                    {
                        $('.messagetoo').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
                        window.scrollTo(0, 0); 
                        $('.submitbtn').html('Submit');
                    }
                    else
                    {
                        
                        var selectedcaformidterm = [];
                        $.each($("input[name='selForMidTerm']:checked"), function(){
                            selectedcaformidterm.push($(this).data('id'));
                        });
            
                        // alert(selectedcaformidterm);
                        
                        var dataString = 'catitle=' + catitle +'&ca1id=' + ca1id + '&ca1maxid=' + ca1maxid +'&ca2id=' + ca2id+'&ca2maxid=' +ca2maxid +'&ca3id=' +ca3id+ '&ca3maxid=' +  ca3maxid   + '&ca4id='+  ca4id +'&ca4maxid='+ca4maxid + '&ca5id='+   ca5id +'&ca5maxid='+ca5maxid +'&examNumber=' + examNumber+'&ca6id=' + ca6id + '&ca6maxid=' + ca6maxid +'&ca7id=' + ca7id+'&ca7maxid=' +ca7maxid +'&ca8id=' +ca8id+ '&ca8maxid=' +  ca8maxid   + '&ca9id='+  ca9id +'&ca9maxid='+ca9maxid + '&ca10id='+   ca10id +'&ca10maxid='+ca10maxid + '&ca11id='+   ca11id +'&ca11maxid='+ca11maxid + '&ca12id='+   ca12id +'&ca12maxid='+ca12maxid + '&ca13id='+   ca13id +'&ca13maxid='+ca13maxid + '&ca14id='+   ca14id +'&ca14maxid='+ca14maxid + '&ca15id='+   ca15id +'&ca15maxid='+ca15maxid + '&selectedcaformidterm=' + selectedcaformidterm;
            
                        $.ajax({
                            url: '../../../phpscript/affectivedomain/updateaffectivedomainsettings.php',
                            method:'POST',
                            data:dataString,
                            
                            success: function(anasmaindata2) {
                            
                                $('.messagetoo').html(anasmaindata2);
                                window.scrollTo(0, 0);
                                $('.submitbtn').html('Submit');
                                
                                setTimeout(function() {
                                    location.reload();
                                }, 5000);
                                
                            }
                        });
                    }
                }
                else if(examNumber == '3')
                {
                    $('#ca4id').val('');
                    $('#ca4maxid').val('0');
                    $('#ca5id').val('');
                    $('#ca5maxid').val('0');
        
                    $('#ca6id').val('');
                    $('#ca6maxid').val('0');
                    $('#ca7id').val('');
                    $('#ca7maxid').val('0');
                    $('#ca8id').val('');
                    $('#ca8maxid').val('0');
                    $('#ca9id').val('');
                    $('#ca9maxid').val('0');
                    $('#ca10id').val('');
                    $('#ca10maxid').val('0');
                    $('#ca11id').val('');
                    $('#ca11maxid').val('0');
                    $('#ca12id').val('');
                    $('#ca12maxid').val('0');
                    $('#ca13id').val('');
                    $('#ca13maxid').val('0');
                    $('#ca14id').val('');
                    $('#ca14maxid').val('0');
                    $('#ca15id').val('');
                    $('#ca15maxid').val('0');
                    
                    
                    var ca1id = $('#ca1id').val();
                    var ca1maxid = $('#ca1maxid').val();
                    var ca2id = $('#ca2id').val();
                    var ca2maxid = $('#ca2maxid').val();
                    var ca3id = $('#ca3id').val();
                    var ca3maxid = $('#ca3maxid').val();
                    var ca4id = $('#ca4id').val();
                    var ca4maxid = $('#ca4maxid').val();
                    var ca5id = $('#ca5id').val();
                    var ca5maxid = $('#ca5maxid').val();
        
                    var ca6id = $('#ca6id').val();
                    var ca6maxid = $('#ca6maxid').val();
                    var ca7id = $('#ca7id').val();
                    var ca7maxid = $('#ca7maxid').val();
                    var ca8id = $('#ca8id').val();
                    var ca8maxid = $('#ca8maxid').val();
                    var ca9id = $('#ca9id').val();
                    var ca9maxid = $('#ca9maxid').val();
                    var ca10id = $('#ca10id').val();
                    var ca10maxid = $('#ca10maxid').val();
                    var ca11id = $('#ca11id').val();
                    var ca11maxid = $('#ca11maxid').val();
                    var ca12id = $('#ca12id').val();
                    var ca12maxid = $('#ca12maxid').val();
                    var ca13id = $('#ca13id').val();
                    var ca13maxid = $('#ca13maxid').val();
                    var ca14id = $('#ca14id').val();
                    var ca14maxid = $('#ca14maxid').val();
                    var ca15id = $('#ca15id').val();
                    var ca15maxid = $('#ca15maxid').val();
                    
                    if(ca1id == '' || ca1maxid == '0' || ca2id == '' || ca2maxid == '0' || ca3id == '' || ca3maxid == '0')
                    {
                        $('.messagetoo').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
                        window.scrollTo(0, 0); 
                        $('.submitbtn').html('Submit');
                    }
                    else
                    {
                        
                        var selectedcaformidterm = [];
                        $.each($("input[name='selForMidTerm']:checked"), function(){
                            selectedcaformidterm.push($(this).data('id'));
                        });
            
                        // alert(selectedcaformidterm);
            
                            var dataString = 'catitle=' + catitle +'&ca1id=' + ca1id + '&ca1maxid=' + ca1maxid +'&ca2id=' + ca2id+'&ca2maxid=' +ca2maxid +'&ca3id=' +ca3id+ '&ca3maxid=' +  ca3maxid   + '&ca4id='+  ca4id +'&ca4maxid='+ca4maxid + '&ca5id='+   ca5id +'&ca5maxid='+ca5maxid +'&examNumber=' + examNumber+'&ca6id=' + ca6id + '&ca6maxid=' + ca6maxid +'&ca7id=' + ca7id+'&ca7maxid=' +ca7maxid +'&ca8id=' +ca8id+ '&ca8maxid=' +  ca8maxid   + '&ca9id='+  ca9id +'&ca9maxid='+ca9maxid + '&ca10id='+   ca10id +'&ca10maxid='+ca10maxid + '&ca11id='+   ca11id +'&ca11maxid='+ca11maxid + '&ca12id='+   ca12id +'&ca12maxid='+ca12maxid + '&ca13id='+   ca13id +'&ca13maxid='+ca13maxid + '&ca14id='+   ca14id +'&ca14maxid='+ca14maxid + '&ca15id='+   ca15id +'&ca15maxid='+ca15maxid + '&selectedcaformidterm=' + selectedcaformidterm;
                
                            $.ajax({
                                url: '../../../phpscript/affectivedomain/updateaffectivedomainsettings.php',
                                method:'POST',
                                data:dataString,
                                
                                success: function(anasmaindata2) {
                                
                                    $('.messagetoo').html(anasmaindata2);
                                    window.scrollTo(0, 0);
                                    $('.submitbtn').html('Submit');
                                    
                                    setTimeout(function() {
                                        location.reload();
                                    }, 5000);
                                    
                                }
                            });
                    }
                }
                else if(examNumber == '4')
                {
                    $('#ca5id').val('');
                    $('#ca5maxid').val('0');
        
                    $('#ca6id').val('');
                    $('#ca6maxid').val('0');
                    $('#ca7id').val('');
                    $('#ca7maxid').val('0');
                    $('#ca8id').val('');
                    $('#ca8maxid').val('0');
                    $('#ca9id').val('');
                    $('#ca9maxid').val('0');
                    $('#ca10id').val('');
                    $('#ca10maxid').val('0');
                    $('#ca11id').val('');
                    $('#ca11maxid').val('0');
                    $('#ca12id').val('');
                    $('#ca12maxid').val('0');
                    $('#ca13id').val('');
                    $('#ca13maxid').val('0');
                    $('#ca14id').val('');
                    $('#ca14maxid').val('0');
                    $('#ca15id').val('');
                    $('#ca15maxid').val('0');
                    
                    
                    var ca1id = $('#ca1id').val();
                    var ca1maxid = $('#ca1maxid').val();
                    var ca2id = $('#ca2id').val();
                    var ca2maxid = $('#ca2maxid').val();
                    var ca3id = $('#ca3id').val();
                    var ca3maxid = $('#ca3maxid').val();
                    var ca4id = $('#ca4id').val();
                    var ca4maxid = $('#ca4maxid').val();
                    var ca5id = $('#ca5id').val();
                    var ca5maxid = $('#ca5maxid').val();
        
                    var ca6id = $('#ca6id').val();
                    var ca6maxid = $('#ca6maxid').val();
                    var ca7id = $('#ca7id').val();
                    var ca7maxid = $('#ca7maxid').val();
                    var ca8id = $('#ca8id').val();
                    var ca8maxid = $('#ca8maxid').val();
                    var ca9id = $('#ca9id').val();
                    var ca9maxid = $('#ca9maxid').val();
                    var ca10id = $('#ca10id').val();
                    var ca10maxid = $('#ca10maxid').val();
                    var ca11id = $('#ca11id').val();
                    var ca11maxid = $('#ca11maxid').val();
                    var ca12id = $('#ca12id').val();
                    var ca12maxid = $('#ca12maxid').val();
                    var ca13id = $('#ca13id').val();
                    var ca13maxid = $('#ca13maxid').val();
                    var ca14id = $('#ca14id').val();
                    var ca14maxid = $('#ca14maxid').val();
                    var ca15id = $('#ca15id').val();
                    var ca15maxid = $('#ca15maxid').val();
                    
                    if(ca1id == '' || ca1maxid == '0' || ca2id == '' || ca2maxid == '0' || ca3id == '' || ca3maxid == '0' || ca4id == '' || ca4maxid == '0')
                    {
                        $('.messagetoo').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
                        window.scrollTo(0, 0); 
                        $('.submitbtn').html('Submit');
                    }
                    else
                    {
                        
                        var selectedcaformidterm = [];
                        $.each($("input[name='selForMidTerm']:checked"), function(){
                            selectedcaformidterm.push($(this).data('id'));
                        });
            
                        // alert(selectedcaformidterm);
            
                            var dataString = 'catitle=' + catitle +'&ca1id=' + ca1id + '&ca1maxid=' + ca1maxid +'&ca2id=' + ca2id+'&ca2maxid=' +ca2maxid +'&ca3id=' +ca3id+ '&ca3maxid=' +  ca3maxid   + '&ca4id='+  ca4id +'&ca4maxid='+ca4maxid + '&ca5id='+   ca5id +'&ca5maxid='+ca5maxid +'&examNumber=' + examNumber+'&ca6id=' + ca6id + '&ca6maxid=' + ca6maxid +'&ca7id=' + ca7id+'&ca7maxid=' +ca7maxid +'&ca8id=' +ca8id+ '&ca8maxid=' +  ca8maxid   + '&ca9id='+  ca9id +'&ca9maxid='+ca9maxid + '&ca10id='+   ca10id +'&ca10maxid='+ca10maxid + '&ca11id='+   ca11id +'&ca11maxid='+ca11maxid + '&ca12id='+   ca12id +'&ca12maxid='+ca12maxid + '&ca13id='+   ca13id +'&ca13maxid='+ca13maxid + '&ca14id='+   ca14id +'&ca14maxid='+ca14maxid + '&ca15id='+   ca15id +'&ca15maxid='+ca15maxid + '&selectedcaformidterm=' + selectedcaformidterm;
                
                            $.ajax({
                                url: '../../../phpscript/affectivedomain/updateaffectivedomainsettings.php',
                                method:'POST',
                                data:dataString,
                                
                                success: function(anasmaindata2) {
                                
                                    $('.messagetoo').html(anasmaindata2);
                                    window.scrollTo(0, 0);
                                    $('.submitbtn').html('Submit');
                                    
                                    setTimeout(function() {
                                        location.reload();
                                    }, 5000);
                                    
                                }
                            });
                    }
                }
                else if(examNumber == '5')
                {
                    $('#ca6id').val('');
                    $('#ca6maxid').val('0');
                    $('#ca7id').val('');
                    $('#ca7maxid').val('0');
                    $('#ca8id').val('');
                    $('#ca8maxid').val('0');
                    $('#ca9id').val('');
                    $('#ca9maxid').val('0');
                    $('#ca10id').val('');
                    $('#ca10maxid').val('0');
                    $('#ca11id').val('');
                    $('#ca11maxid').val('0');
                    $('#ca12id').val('');
                    $('#ca12maxid').val('0');
                    $('#ca13id').val('');
                    $('#ca13maxid').val('0');
                    $('#ca14id').val('');
                    $('#ca14maxid').val('0');
                    $('#ca15id').val('');
                    $('#ca15maxid').val('0');
                    
                    
                    var ca1id = $('#ca1id').val();
                    var ca1maxid = $('#ca1maxid').val();
                    var ca2id = $('#ca2id').val();
                    var ca2maxid = $('#ca2maxid').val();
                    var ca3id = $('#ca3id').val();
                    var ca3maxid = $('#ca3maxid').val();
                    var ca4id = $('#ca4id').val();
                    var ca4maxid = $('#ca4maxid').val();
                    var ca5id = $('#ca5id').val();
                    var ca5maxid = $('#ca5maxid').val();
        
                    var ca6id = $('#ca6id').val();
                    var ca6maxid = $('#ca6maxid').val();
                    var ca7id = $('#ca7id').val();
                    var ca7maxid = $('#ca7maxid').val();
                    var ca8id = $('#ca8id').val();
                    var ca8maxid = $('#ca8maxid').val();
                    var ca9id = $('#ca9id').val();
                    var ca9maxid = $('#ca9maxid').val();
                    var ca10id = $('#ca10id').val();
                    var ca10maxid = $('#ca10maxid').val();
                    var ca11id = $('#ca11id').val();
                    var ca11maxid = $('#ca11maxid').val();
                    var ca12id = $('#ca12id').val();
                    var ca12maxid = $('#ca12maxid').val();
                    var ca13id = $('#ca13id').val();
                    var ca13maxid = $('#ca13maxid').val();
                    var ca14id = $('#ca14id').val();
                    var ca14maxid = $('#ca14maxid').val();
                    var ca15id = $('#ca15id').val();
                    var ca15maxid = $('#ca15maxid').val();
                    
                    if(ca1id == '' || ca1maxid == '0' || ca2id == '' || ca2maxid == '0' || ca3id == '' || ca3maxid == '0' || ca4id == '' || ca4maxid == '0' || ca5id == '' || ca5maxid == '0')
                    {
                        $('.messagetoo').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
                        window.scrollTo(0, 0); 
                        $('.submitbtn').html('Submit');
                    }
                    else
                    {
                        
                        var selectedcaformidterm = [];
                        $.each($("input[name='selForMidTerm']:checked"), function(){
                            selectedcaformidterm.push($(this).data('id'));
                        });
            
                        // alert(selectedcaformidterm);
            
                            var dataString = 'catitle=' + catitle +'&ca1id=' + ca1id + '&ca1maxid=' + ca1maxid +'&ca2id=' + ca2id+'&ca2maxid=' +ca2maxid +'&ca3id=' +ca3id+ '&ca3maxid=' +  ca3maxid   + '&ca4id='+  ca4id +'&ca4maxid='+ca4maxid + '&ca5id='+   ca5id +'&ca5maxid='+ca5maxid +'&examNumber=' + examNumber+'&ca6id=' + ca6id + '&ca6maxid=' + ca6maxid +'&ca7id=' + ca7id+'&ca7maxid=' +ca7maxid +'&ca8id=' +ca8id+ '&ca8maxid=' +  ca8maxid   + '&ca9id='+  ca9id +'&ca9maxid='+ca9maxid + '&ca10id='+   ca10id +'&ca10maxid='+ca10maxid + '&ca11id='+   ca11id +'&ca11maxid='+ca11maxid + '&ca12id='+   ca12id +'&ca12maxid='+ca12maxid + '&ca13id='+   ca13id +'&ca13maxid='+ca13maxid + '&ca14id='+   ca14id +'&ca14maxid='+ca14maxid + '&ca15id='+   ca15id +'&ca15maxid='+ca15maxid + '&selectedcaformidterm=' + selectedcaformidterm;
                
                            $.ajax({
                                url: '../../../phpscript/affectivedomain/updateaffectivedomainsettings.php',
                                method:'POST',
                                data:dataString,
                                
                                success: function(anasmaindata2) {
                                
                                    $('.messagetoo').html(anasmaindata2);
                                    window.scrollTo(0, 0);
                                    $('.submitbtn').html('Submit');
                                    
                                    setTimeout(function() {
                                        location.reload();
                                    }, 5000);
                                    
                                }
                            });
                    }
                }
                else if(examNumber == '6')
                {
                    $('#ca7id').val('');
                    $('#ca7maxid').val('0');
                    $('#ca8id').val('');
                    $('#ca8maxid').val('0');
                    $('#ca9id').val('');
                    $('#ca9maxid').val('0');
                    $('#ca10id').val('');
                    $('#ca10maxid').val('0');
                    $('#ca11id').val('');
                    $('#ca11maxid').val('0');
                    $('#ca12id').val('');
                    $('#ca12maxid').val('0');
                    $('#ca13id').val('');
                    $('#ca13maxid').val('0');
                    $('#ca14id').val('');
                    $('#ca14maxid').val('0');
                    $('#ca15id').val('');
                    $('#ca15maxid').val('0');
                    
                    
                    var ca1id = $('#ca1id').val();
                    var ca1maxid = $('#ca1maxid').val();
                    var ca2id = $('#ca2id').val();
                    var ca2maxid = $('#ca2maxid').val();
                    var ca3id = $('#ca3id').val();
                    var ca3maxid = $('#ca3maxid').val();
                    var ca4id = $('#ca4id').val();
                    var ca4maxid = $('#ca4maxid').val();
                    var ca5id = $('#ca5id').val();
                    var ca5maxid = $('#ca5maxid').val();
        
                    var ca6id = $('#ca6id').val();
                    var ca6maxid = $('#ca6maxid').val();
                    var ca7id = $('#ca7id').val();
                    var ca7maxid = $('#ca7maxid').val();
                    var ca8id = $('#ca8id').val();
                    var ca8maxid = $('#ca8maxid').val();
                    var ca9id = $('#ca9id').val();
                    var ca9maxid = $('#ca9maxid').val();
                    var ca10id = $('#ca10id').val();
                    var ca10maxid = $('#ca10maxid').val();
                    var ca11id = $('#ca11id').val();
                    var ca11maxid = $('#ca11maxid').val();
                    var ca12id = $('#ca12id').val();
                    var ca12maxid = $('#ca12maxid').val();
                    var ca13id = $('#ca13id').val();
                    var ca13maxid = $('#ca13maxid').val();
                    var ca14id = $('#ca14id').val();
                    var ca14maxid = $('#ca14maxid').val();
                    var ca15id = $('#ca15id').val();
                    var ca15maxid = $('#ca15maxid').val();
                    
                    if(ca1id == '' || ca1maxid == '0' || ca2id == '' || ca2maxid == '0' || ca3id == '' || ca3maxid == '0' || ca4id == '' || ca4maxid == '0' || ca5id == '' || ca5maxid == '0' || ca6id == '' || ca6maxid == '0')
                    {
                        $('.messagetoo').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
                        window.scrollTo(0, 0); 
                        $('.submitbtn').html('Submit');
                    }
                    else
                    {
                        
                        var selectedcaformidterm = [];
                        $.each($("input[name='selForMidTerm']:checked"), function(){
                            selectedcaformidterm.push($(this).data('id'));
                        });
            
                        // alert(selectedcaformidterm);
            
                            var dataString = 'catitle=' + catitle +'&ca1id=' + ca1id + '&ca1maxid=' + ca1maxid +'&ca2id=' + ca2id+'&ca2maxid=' +ca2maxid +'&ca3id=' +ca3id+ '&ca3maxid=' +  ca3maxid   + '&ca4id='+  ca4id +'&ca4maxid='+ca4maxid + '&ca5id='+   ca5id +'&ca5maxid='+ca5maxid +'&examNumber=' + examNumber+'&ca6id=' + ca6id + '&ca6maxid=' + ca6maxid +'&ca7id=' + ca7id+'&ca7maxid=' +ca7maxid +'&ca8id=' +ca8id+ '&ca8maxid=' +  ca8maxid   + '&ca9id='+  ca9id +'&ca9maxid='+ca9maxid + '&ca10id='+   ca10id +'&ca10maxid='+ca10maxid + '&ca11id='+   ca11id +'&ca11maxid='+ca11maxid + '&ca12id='+   ca12id +'&ca12maxid='+ca12maxid + '&ca13id='+   ca13id +'&ca13maxid='+ca13maxid + '&ca14id='+   ca14id +'&ca14maxid='+ca14maxid + '&ca15id='+   ca15id +'&ca15maxid='+ca15maxid + '&selectedcaformidterm=' + selectedcaformidterm;
                
                            $.ajax({
                                url: '../../../phpscript/affectivedomain/updateaffectivedomainsettings.php',
                                method:'POST',
                                data:dataString,
                                
                                success: function(anasmaindata2) {
                                
                                    $('.messagetoo').html(anasmaindata2);
                                    window.scrollTo(0, 0);
                                    $('.submitbtn').html('Submit');
                                    
                                    setTimeout(function() {
                                        location.reload();
                                    }, 5000);
                                    
                                }
                            });
                    }
                }
                else if(examNumber == '7')
                {
                    $('#ca8id').val('');
                    $('#ca8maxid').val('0');
                    $('#ca9id').val('');
                    $('#ca9maxid').val('0');
                    $('#ca10id').val('');
                    $('#ca10maxid').val('0');
                    $('#ca11id').val('');
                    $('#ca11maxid').val('0');
                    $('#ca12id').val('');
                    $('#ca12maxid').val('0');
                    $('#ca13id').val('');
                    $('#ca13maxid').val('0');
                    $('#ca14id').val('');
                    $('#ca14maxid').val('0');
                    $('#ca15id').val('');
                    $('#ca15maxid').val('0');
                    
                    
                    var ca1id = $('#ca1id').val();
                    var ca1maxid = $('#ca1maxid').val();
                    var ca2id = $('#ca2id').val();
                    var ca2maxid = $('#ca2maxid').val();
                    var ca3id = $('#ca3id').val();
                    var ca3maxid = $('#ca3maxid').val();
                    var ca4id = $('#ca4id').val();
                    var ca4maxid = $('#ca4maxid').val();
                    var ca5id = $('#ca5id').val();
                    var ca5maxid = $('#ca5maxid').val();
        
                    var ca6id = $('#ca6id').val();
                    var ca6maxid = $('#ca6maxid').val();
                    var ca7id = $('#ca7id').val();
                    var ca7maxid = $('#ca7maxid').val();
                    var ca8id = $('#ca8id').val();
                    var ca8maxid = $('#ca8maxid').val();
                    var ca9id = $('#ca9id').val();
                    var ca9maxid = $('#ca9maxid').val();
                    var ca10id = $('#ca10id').val();
                    var ca10maxid = $('#ca10maxid').val();
                    var ca11id = $('#ca11id').val();
                    var ca11maxid = $('#ca11maxid').val();
                    var ca12id = $('#ca12id').val();
                    var ca12maxid = $('#ca12maxid').val();
                    var ca13id = $('#ca13id').val();
                    var ca13maxid = $('#ca13maxid').val();
                    var ca14id = $('#ca14id').val();
                    var ca14maxid = $('#ca14maxid').val();
                    var ca15id = $('#ca15id').val();
                    var ca15maxid = $('#ca15maxid').val();
                    
                    if(ca1id == '' || ca1maxid == '0' || ca2id == '' || ca2maxid == '0' || ca3id == '' || ca3maxid == '0' || ca4id == '' || ca4maxid == '0' || ca5id == '' || ca5maxid == '0' || ca6id == '' || ca6maxid == '0' || ca7id == '' || ca7maxid == '0')
                    {
                        $('.messagetoo').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
                        window.scrollTo(0, 0); 
                        $('.submitbtn').html('Submit');
                    }
                    else
                    {
                        
                        var selectedcaformidterm = [];
                        $.each($("input[name='selForMidTerm']:checked"), function(){
                            selectedcaformidterm.push($(this).data('id'));
                        });
            
                        // alert(selectedcaformidterm);
            
                            var dataString = 'catitle=' + catitle +'&ca1id=' + ca1id + '&ca1maxid=' + ca1maxid +'&ca2id=' + ca2id+'&ca2maxid=' +ca2maxid +'&ca3id=' +ca3id+ '&ca3maxid=' +  ca3maxid   + '&ca4id='+  ca4id +'&ca4maxid='+ca4maxid + '&ca5id='+   ca5id +'&ca5maxid='+ca5maxid +'&examNumber=' + examNumber+'&ca6id=' + ca6id + '&ca6maxid=' + ca6maxid +'&ca7id=' + ca7id+'&ca7maxid=' +ca7maxid +'&ca8id=' +ca8id+ '&ca8maxid=' +  ca8maxid   + '&ca9id='+  ca9id +'&ca9maxid='+ca9maxid + '&ca10id='+   ca10id +'&ca10maxid='+ca10maxid + '&ca11id='+   ca11id +'&ca11maxid='+ca11maxid + '&ca12id='+   ca12id +'&ca12maxid='+ca12maxid + '&ca13id='+   ca13id +'&ca13maxid='+ca13maxid + '&ca14id='+   ca14id +'&ca14maxid='+ca14maxid + '&ca15id='+   ca15id +'&ca15maxid='+ca15maxid + '&selectedcaformidterm=' + selectedcaformidterm;
                
                            $.ajax({
                                url: '../../../phpscript/affectivedomain/updateaffectivedomainsettings.php',
                                method:'POST',
                                data:dataString,
                                
                                success: function(anasmaindata2) {
                                
                                    $('.messagetoo').html(anasmaindata2);
                                    window.scrollTo(0, 0);
                                    $('.submitbtn').html('Submit');
                                    
                                    setTimeout(function() {
                                        location.reload();
                                    }, 5000);
                                    
                                }
                            });
                    }
                }
                else if(examNumber == '8')
                {
                    $('#ca9id').val('');
                    $('#ca9maxid').val('0');
                    $('#ca10id').val('');
                    $('#ca10maxid').val('0');
                    $('#ca11id').val('');
                    $('#ca11maxid').val('0');
                    $('#ca12id').val('');
                    $('#ca12maxid').val('0');
                    $('#ca13id').val('');
                    $('#ca13maxid').val('0');
                    $('#ca14id').val('');
                    $('#ca14maxid').val('0');
                    $('#ca15id').val('');
                    $('#ca15maxid').val('0');
                    
                    
                    var ca1id = $('#ca1id').val();
                    var ca1maxid = $('#ca1maxid').val();
                    var ca2id = $('#ca2id').val();
                    var ca2maxid = $('#ca2maxid').val();
                    var ca3id = $('#ca3id').val();
                    var ca3maxid = $('#ca3maxid').val();
                    var ca4id = $('#ca4id').val();
                    var ca4maxid = $('#ca4maxid').val();
                    var ca5id = $('#ca5id').val();
                    var ca5maxid = $('#ca5maxid').val();
        
                    var ca6id = $('#ca6id').val();
                    var ca6maxid = $('#ca6maxid').val();
                    var ca7id = $('#ca7id').val();
                    var ca7maxid = $('#ca7maxid').val();
                    var ca8id = $('#ca8id').val();
                    var ca8maxid = $('#ca8maxid').val();
                    var ca9id = $('#ca9id').val();
                    var ca9maxid = $('#ca9maxid').val();
                    var ca10id = $('#ca10id').val();
                    var ca10maxid = $('#ca10maxid').val();
                    var ca11id = $('#ca11id').val();
                    var ca11maxid = $('#ca11maxid').val();
                    var ca12id = $('#ca12id').val();
                    var ca12maxid = $('#ca12maxid').val();
                    var ca13id = $('#ca13id').val();
                    var ca13maxid = $('#ca13maxid').val();
                    var ca14id = $('#ca14id').val();
                    var ca14maxid = $('#ca14maxid').val();
                    var ca15id = $('#ca15id').val();
                    var ca15maxid = $('#ca15maxid').val();
                    
                    if(ca1id == '' || ca1maxid == '0' || ca2id == '' || ca2maxid == '0' || ca3id == '' || ca3maxid == '0' || ca4id == '' || ca4maxid == '0' || ca5id == '' || ca5maxid == '0' || ca6id == '' || ca6maxid == '0' || ca7id == '' || ca7maxid == '0' || ca8id == '' || ca8maxid == '0')
                    {
                        $('.messagetoo').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
                        window.scrollTo(0, 0); 
                        $('.submitbtn').html('Submit');
                    }
                    else
                    {
                        
                        var selectedcaformidterm = [];
                        $.each($("input[name='selForMidTerm']:checked"), function(){
                            selectedcaformidterm.push($(this).data('id'));
                        });
            
                        // alert(selectedcaformidterm);
            
                            var dataString = 'catitle=' + catitle +'&ca1id=' + ca1id + '&ca1maxid=' + ca1maxid +'&ca2id=' + ca2id+'&ca2maxid=' +ca2maxid +'&ca3id=' +ca3id+ '&ca3maxid=' +  ca3maxid   + '&ca4id='+  ca4id +'&ca4maxid='+ca4maxid + '&ca5id='+   ca5id +'&ca5maxid='+ca5maxid +'&examNumber=' + examNumber+'&ca6id=' + ca6id + '&ca6maxid=' + ca6maxid +'&ca7id=' + ca7id+'&ca7maxid=' +ca7maxid +'&ca8id=' +ca8id+ '&ca8maxid=' +  ca8maxid   + '&ca9id='+  ca9id +'&ca9maxid='+ca9maxid + '&ca10id='+   ca10id +'&ca10maxid='+ca10maxid + '&ca11id='+   ca11id +'&ca11maxid='+ca11maxid + '&ca12id='+   ca12id +'&ca12maxid='+ca12maxid + '&ca13id='+   ca13id +'&ca13maxid='+ca13maxid + '&ca14id='+   ca14id +'&ca14maxid='+ca14maxid + '&ca15id='+   ca15id +'&ca15maxid='+ca15maxid + '&selectedcaformidterm=' + selectedcaformidterm;
                
                            $.ajax({
                                url: '../../../phpscript/affectivedomain/updateaffectivedomainsettings.php',
                                method:'POST',
                                data:dataString,
                                
                                success: function(anasmaindata2) {
                                
                                    $('.messagetoo').html(anasmaindata2);
                                    window.scrollTo(0, 0);
                                    $('.submitbtn').html('Submit');
                                    
                                    setTimeout(function() {
                                        location.reload();
                                    }, 5000);
                                    
                                }
                            });
                    }
                }
                else if(examNumber == '9')
                {
                    $('#ca10id').val('');
                    $('#ca10maxid').val('0');
                    $('#ca11id').val('');
                    $('#ca11maxid').val('0');
                    $('#ca12id').val('');
                    $('#ca12maxid').val('0');
                    $('#ca13id').val('');
                    $('#ca13maxid').val('0');
                    $('#ca14id').val('');
                    $('#ca14maxid').val('0');
                    $('#ca15id').val('');
                    $('#ca15maxid').val('0');
                    
                    
                    var ca1id = $('#ca1id').val();
                    var ca1maxid = $('#ca1maxid').val();
                    var ca2id = $('#ca2id').val();
                    var ca2maxid = $('#ca2maxid').val();
                    var ca3id = $('#ca3id').val();
                    var ca3maxid = $('#ca3maxid').val();
                    var ca4id = $('#ca4id').val();
                    var ca4maxid = $('#ca4maxid').val();
                    var ca5id = $('#ca5id').val();
                    var ca5maxid = $('#ca5maxid').val();
        
                    var ca6id = $('#ca6id').val();
                    var ca6maxid = $('#ca6maxid').val();
                    var ca7id = $('#ca7id').val();
                    var ca7maxid = $('#ca7maxid').val();
                    var ca8id = $('#ca8id').val();
                    var ca8maxid = $('#ca8maxid').val();
                    var ca9id = $('#ca9id').val();
                    var ca9maxid = $('#ca9maxid').val();
                    var ca10id = $('#ca10id').val();
                    var ca10maxid = $('#ca10maxid').val();
                    var ca11id = $('#ca11id').val();
                    var ca11maxid = $('#ca11maxid').val();
                    var ca12id = $('#ca12id').val();
                    var ca12maxid = $('#ca12maxid').val();
                    var ca13id = $('#ca13id').val();
                    var ca13maxid = $('#ca13maxid').val();
                    var ca14id = $('#ca14id').val();
                    var ca14maxid = $('#ca14maxid').val();
                    var ca15id = $('#ca15id').val();
                    var ca15maxid = $('#ca15maxid').val();
                    
                    if(ca1id == '' || ca1maxid == '0' || ca2id == '' || ca2maxid == '0' || ca3id == '' || ca3maxid == '0' || ca4id == '' || ca4maxid == '0' || ca5id == '' || ca5maxid == '0' || ca6id == '' || ca6maxid == '0' || ca7id == '' || ca7maxid == '0' || ca8id == '' || ca8maxid == '0' || ca9id == '' || ca9maxid == '0')
                    {
                        $('.messagetoo').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
                        window.scrollTo(0, 0); 
                        $('.submitbtn').html('Submit');
                    }
                    else
                    {
                        
                        var selectedcaformidterm = [];
                        $.each($("input[name='selForMidTerm']:checked"), function(){
                            selectedcaformidterm.push($(this).data('id'));
                        });
            
                        // alert(selectedcaformidterm);
            
                            var dataString = 'catitle=' + catitle +'&ca1id=' + ca1id + '&ca1maxid=' + ca1maxid +'&ca2id=' + ca2id+'&ca2maxid=' +ca2maxid +'&ca3id=' +ca3id+ '&ca3maxid=' +  ca3maxid   + '&ca4id='+  ca4id +'&ca4maxid='+ca4maxid + '&ca5id='+   ca5id +'&ca5maxid='+ca5maxid +'&examNumber=' + examNumber+'&ca6id=' + ca6id + '&ca6maxid=' + ca6maxid +'&ca7id=' + ca7id+'&ca7maxid=' +ca7maxid +'&ca8id=' +ca8id+ '&ca8maxid=' +  ca8maxid   + '&ca9id='+  ca9id +'&ca9maxid='+ca9maxid + '&ca10id='+   ca10id +'&ca10maxid='+ca10maxid + '&ca11id='+   ca11id +'&ca11maxid='+ca11maxid + '&ca12id='+   ca12id +'&ca12maxid='+ca12maxid + '&ca13id='+   ca13id +'&ca13maxid='+ca13maxid + '&ca14id='+   ca14id +'&ca14maxid='+ca14maxid + '&ca15id='+   ca15id +'&ca15maxid='+ca15maxid + '&selectedcaformidterm=' + selectedcaformidterm;
                
                            $.ajax({
                                url: '../../../phpscript/affectivedomain/updateaffectivedomainsettings.php',
                                method:'POST',
                                data:dataString,
                                
                                success: function(anasmaindata2) {
                                
                                    $('.messagetoo').html(anasmaindata2);
                                    window.scrollTo(0, 0);
                                    $('.submitbtn').html('Submit');
                                    
                                    setTimeout(function() {
                                        location.reload();
                                    }, 5000);
                                    
                                }
                            });
                    }
                }
                else if(examNumber == '10')
                {
                    $('#ca11id').val('');
                    $('#ca11maxid').val('0');
                    $('#ca12id').val('');
                    $('#ca12maxid').val('0');
                    $('#ca13id').val('');
                    $('#ca13maxid').val('0');
                    $('#ca14id').val('');
                    $('#ca14maxid').val('0');
                    $('#ca15id').val('');
                    $('#ca15maxid').val('0');
                    
                    
                    var ca1id = $('#ca1id').val();
                    var ca1maxid = $('#ca1maxid').val();
                    var ca2id = $('#ca2id').val();
                    var ca2maxid = $('#ca2maxid').val();
                    var ca3id = $('#ca3id').val();
                    var ca3maxid = $('#ca3maxid').val();
                    var ca4id = $('#ca4id').val();
                    var ca4maxid = $('#ca4maxid').val();
                    var ca5id = $('#ca5id').val();
                    var ca5maxid = $('#ca5maxid').val();
        
                    var ca6id = $('#ca6id').val();
                    var ca6maxid = $('#ca6maxid').val();
                    var ca7id = $('#ca7id').val();
                    var ca7maxid = $('#ca7maxid').val();
                    var ca8id = $('#ca8id').val();
                    var ca8maxid = $('#ca8maxid').val();
                    var ca9id = $('#ca9id').val();
                    var ca9maxid = $('#ca9maxid').val();
                    var ca10id = $('#ca10id').val();
                    var ca10maxid = $('#ca10maxid').val();
                    var ca11id = $('#ca11id').val();
                    var ca11maxid = $('#ca11maxid').val();
                    var ca12id = $('#ca12id').val();
                    var ca12maxid = $('#ca12maxid').val();
                    var ca13id = $('#ca13id').val();
                    var ca13maxid = $('#ca13maxid').val();
                    var ca14id = $('#ca14id').val();
                    var ca14maxid = $('#ca14maxid').val();
                    var ca15id = $('#ca15id').val();
                    var ca15maxid = $('#ca15maxid').val();
                    
                    if(ca1id == '' || ca1maxid == '0' || ca2id == '' || ca2maxid == '0' || ca3id == '' || ca3maxid == '0' || ca4id == '' || ca4maxid == '0' || ca5id == '' || ca5maxid == '0' || ca6id == '' || ca6maxid == '0' || ca7id == '' || ca7maxid == '0' || ca8id == '' || ca8maxid == '0' || ca9id == '' || ca9maxid == '0' || ca10id == '' || ca10maxid == '0')
                    {
                        $('.messagetoo').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
                        window.scrollTo(0, 0); 
                        $('.submitbtn').html('Submit');
                    }
                    else
                    {
                        
                        var selectedcaformidterm = [];
                        $.each($("input[name='selForMidTerm']:checked"), function(){
                            selectedcaformidterm.push($(this).data('id'));
                        });
            
                        // alert(selectedcaformidterm);
            
                            var dataString = 'catitle=' + catitle +'&ca1id=' + ca1id + '&ca1maxid=' + ca1maxid +'&ca2id=' + ca2id+'&ca2maxid=' +ca2maxid +'&ca3id=' +ca3id+ '&ca3maxid=' +  ca3maxid   + '&ca4id='+  ca4id +'&ca4maxid='+ca4maxid + '&ca5id='+   ca5id +'&ca5maxid='+ca5maxid +'&examNumber=' + examNumber+'&ca6id=' + ca6id + '&ca6maxid=' + ca6maxid +'&ca7id=' + ca7id+'&ca7maxid=' +ca7maxid +'&ca8id=' +ca8id+ '&ca8maxid=' +  ca8maxid   + '&ca9id='+  ca9id +'&ca9maxid='+ca9maxid + '&ca10id='+   ca10id +'&ca10maxid='+ca10maxid + '&ca11id='+   ca11id +'&ca11maxid='+ca11maxid + '&ca12id='+   ca12id +'&ca12maxid='+ca12maxid + '&ca13id='+   ca13id +'&ca13maxid='+ca13maxid + '&ca14id='+   ca14id +'&ca14maxid='+ca14maxid + '&ca15id='+   ca15id +'&ca15maxid='+ca15maxid + '&selectedcaformidterm=' + selectedcaformidterm;
                            $.ajax({
                                url: '../../../phpscript/affectivedomain/updateaffectivedomainsettings.php',
                                method:'POST',
                                data:dataString,
                                
                                success: function(anasmaindata2) {
                                
                                    $('.messagetoo').html(anasmaindata2);
                                    window.scrollTo(0, 0);
                                    $('.submitbtn').html('Submit');
                                    
                                    setTimeout(function() {
                                        location.reload();
                                    }, 5000);
                                    
                                }
                            });
                    }
                }
                else if(examNumber == '11')
                {
                    $('#ca12id').val('');
                    $('#ca12maxid').val('0');
                    $('#ca13id').val('');
                    $('#ca13maxid').val('0');
                    $('#ca14id').val('');
                    $('#ca14maxid').val('0');
                    $('#ca15id').val('');
                    $('#ca15maxid').val('0');
                    
                    
                    var ca1id = $('#ca1id').val();
                    var ca1maxid = $('#ca1maxid').val();
                    var ca2id = $('#ca2id').val();
                    var ca2maxid = $('#ca2maxid').val();
                    var ca3id = $('#ca3id').val();
                    var ca3maxid = $('#ca3maxid').val();
                    var ca4id = $('#ca4id').val();
                    var ca4maxid = $('#ca4maxid').val();
                    var ca5id = $('#ca5id').val();
                    var ca5maxid = $('#ca5maxid').val();
        
                    var ca6id = $('#ca6id').val();
                    var ca6maxid = $('#ca6maxid').val();
                    var ca7id = $('#ca7id').val();
                    var ca7maxid = $('#ca7maxid').val();
                    var ca8id = $('#ca8id').val();
                    var ca8maxid = $('#ca8maxid').val();
                    var ca9id = $('#ca9id').val();
                    var ca9maxid = $('#ca9maxid').val();
                    var ca10id = $('#ca10id').val();
                    var ca10maxid = $('#ca10maxid').val();
                    var ca11id = $('#ca11id').val();
                    var ca11maxid = $('#ca11maxid').val();
                    var ca12id = $('#ca12id').val();
                    var ca12maxid = $('#ca12maxid').val();
                    var ca13id = $('#ca13id').val();
                    var ca13maxid = $('#ca13maxid').val();
                    var ca14id = $('#ca14id').val();
                    var ca14maxid = $('#ca14maxid').val();
                    var ca15id = $('#ca15id').val();
                    var ca15maxid = $('#ca15maxid').val();
                    
                    if(ca1id == '' || ca1maxid == '0' || ca2id == '' || ca2maxid == '0' || ca3id == '' || ca3maxid == '0' || ca4id == '' || ca4maxid == '0' || ca5id == '' || ca5maxid == '0' || ca6id == '' || ca6maxid == '0' || ca7id == '' || ca7maxid == '0' || ca8id == '' || ca8maxid == '0' || ca9id == '' || ca9maxid == '0' || ca10id == '' || ca10maxid == '0' || ca11id == '' || ca11maxid == '0')
                    {
                        $('.messagetoo').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
                        window.scrollTo(0, 0); 
                        $('.submitbtn').html('Submit');
                    }
                    else
                    {
                        
                        var selectedcaformidterm = [];
                        $.each($("input[name='selForMidTerm']:checked"), function(){
                            selectedcaformidterm.push($(this).data('id'));
                        });
            
                        // alert(selectedcaformidterm);
            
                            var dataString = 'catitle=' + catitle +'&ca1id=' + ca1id + '&ca1maxid=' + ca1maxid +'&ca2id=' + ca2id+'&ca2maxid=' +ca2maxid +'&ca3id=' +ca3id+ '&ca3maxid=' +  ca3maxid   + '&ca4id='+  ca4id +'&ca4maxid='+ca4maxid + '&ca5id='+   ca5id +'&ca5maxid='+ca5maxid +'&examNumber=' + examNumber+'&ca6id=' + ca6id + '&ca6maxid=' + ca6maxid +'&ca7id=' + ca7id+'&ca7maxid=' +ca7maxid +'&ca8id=' +ca8id+ '&ca8maxid=' +  ca8maxid   + '&ca9id='+  ca9id +'&ca9maxid='+ca9maxid + '&ca10id='+   ca10id +'&ca10maxid='+ca10maxid + '&ca11id='+   ca11id +'&ca11maxid='+ca11maxid + '&ca12id='+   ca12id +'&ca12maxid='+ca12maxid + '&ca13id='+   ca13id +'&ca13maxid='+ca13maxid + '&ca14id='+   ca14id +'&ca14maxid='+ca14maxid + '&ca15id='+   ca15id +'&ca15maxid='+ca15maxid + '&selectedcaformidterm=' + selectedcaformidterm;
                
                            $.ajax({
                                url: '../../../phpscript/affectivedomain/updateaffectivedomainsettings.php',
                                method:'POST',
                                data:dataString,
                                
                                success: function(anasmaindata2) {
                                
                                    $('.messagetoo').html(anasmaindata2);
                                    window.scrollTo(0, 0);
                                    $('.submitbtn').html('Submit');
                                    
                                    setTimeout(function() {
                                        location.reload();
                                    }, 5000);
                                    
                                }
                            });
                    }
                }
                else if(examNumber == '12')
                {
                    $('#ca13id').val('');
                    $('#ca13maxid').val('0');
                    $('#ca14id').val('');
                    $('#ca14maxid').val('0');
                    $('#ca15id').val('');
                    $('#ca15maxid').val('0');
                    
                    
                    var ca1id = $('#ca1id').val();
                    var ca1maxid = $('#ca1maxid').val();
                    var ca2id = $('#ca2id').val();
                    var ca2maxid = $('#ca2maxid').val();
                    var ca3id = $('#ca3id').val();
                    var ca3maxid = $('#ca3maxid').val();
                    var ca4id = $('#ca4id').val();
                    var ca4maxid = $('#ca4maxid').val();
                    var ca5id = $('#ca5id').val();
                    var ca5maxid = $('#ca5maxid').val();
        
                    var ca6id = $('#ca6id').val();
                    var ca6maxid = $('#ca6maxid').val();
                    var ca7id = $('#ca7id').val();
                    var ca7maxid = $('#ca7maxid').val();
                    var ca8id = $('#ca8id').val();
                    var ca8maxid = $('#ca8maxid').val();
                    var ca9id = $('#ca9id').val();
                    var ca9maxid = $('#ca9maxid').val();
                    var ca10id = $('#ca10id').val();
                    var ca10maxid = $('#ca10maxid').val();
                    var ca11id = $('#ca11id').val();
                    var ca11maxid = $('#ca11maxid').val();
                    var ca12id = $('#ca12id').val();
                    var ca12maxid = $('#ca12maxid').val();
                    var ca13id = $('#ca13id').val();
                    var ca13maxid = $('#ca13maxid').val();
                    var ca14id = $('#ca14id').val();
                    var ca14maxid = $('#ca14maxid').val();
                    var ca15id = $('#ca15id').val();
                    var ca15maxid = $('#ca15maxid').val();
                    
                    if(ca1id == '' || ca1maxid == '0' || ca2id == '' || ca2maxid == '0' || ca3id == '' || ca3maxid == '0' || ca4id == '' || ca4maxid == '0' || ca5id == '' || ca5maxid == '0' || ca6id == '' || ca6maxid == '0' || ca7id == '' || ca7maxid == '0' || ca8id == '' || ca8maxid == '0' || ca9id == '' || ca9maxid == '0' || ca10id == '' || ca10maxid == '0' || ca11id == '' || ca11maxid == '0' || ca12id == '' || ca12maxid == '0')
                    {
                        $('.messagetoo').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
                        window.scrollTo(0, 0); 
                        $('.submitbtn').html('Submit');
                    }
                    else
                    {
                        
                        var selectedcaformidterm = [];
                        $.each($("input[name='selForMidTerm']:checked"), function(){
                            selectedcaformidterm.push($(this).data('id'));
                        });
            
                        // alert(selectedcaformidterm);
            
                            var dataString = 'catitle=' + catitle +'&ca1id=' + ca1id + '&ca1maxid=' + ca1maxid +'&ca2id=' + ca2id+'&ca2maxid=' +ca2maxid +'&ca3id=' +ca3id+ '&ca3maxid=' +  ca3maxid   + '&ca4id='+  ca4id +'&ca4maxid='+ca4maxid + '&ca5id='+   ca5id +'&ca5maxid='+ca5maxid +'&examNumber=' + examNumber+'&ca6id=' + ca6id + '&ca6maxid=' + ca6maxid +'&ca7id=' + ca7id+'&ca7maxid=' +ca7maxid +'&ca8id=' +ca8id+ '&ca8maxid=' +  ca8maxid   + '&ca9id='+  ca9id +'&ca9maxid='+ca9maxid + '&ca10id='+   ca10id +'&ca10maxid='+ca10maxid + '&ca11id='+   ca11id +'&ca11maxid='+ca11maxid + '&ca12id='+   ca12id +'&ca12maxid='+ca12maxid + '&ca13id='+   ca13id +'&ca13maxid='+ca13maxid + '&ca14id='+   ca14id +'&ca14maxid='+ca14maxid + '&ca15id='+   ca15id +'&ca15maxid='+ca15maxid + '&selectedcaformidterm=' + selectedcaformidterm;
                
                            $.ajax({
                                url: '../../../phpscript/affectivedomain/updateaffectivedomainsettings.php',
                                method:'POST',
                                data:dataString,
                                
                                success: function(anasmaindata2) {
                                
                                    $('.messagetoo').html(anasmaindata2);
                                    window.scrollTo(0, 0);
                                    $('.submitbtn').html('Submit');
                                    
                                    setTimeout(function() {
                                        location.reload();
                                    }, 5000);
                                    
                                }
                            });
                    }
                }
                else if(examNumber == '13')
                {
                    $('#ca14id').val('');
                    $('#ca14maxid').val('0');
                    $('#ca15id').val('');
                    $('#ca15maxid').val('0');
                    
                    
                    var ca1id = $('#ca1id').val();
                    var ca1maxid = $('#ca1maxid').val();
                    var ca2id = $('#ca2id').val();
                    var ca2maxid = $('#ca2maxid').val();
                    var ca3id = $('#ca3id').val();
                    var ca3maxid = $('#ca3maxid').val();
                    var ca4id = $('#ca4id').val();
                    var ca4maxid = $('#ca4maxid').val();
                    var ca5id = $('#ca5id').val();
                    var ca5maxid = $('#ca5maxid').val();
        
                    var ca6id = $('#ca6id').val();
                    var ca6maxid = $('#ca6maxid').val();
                    var ca7id = $('#ca7id').val();
                    var ca7maxid = $('#ca7maxid').val();
                    var ca8id = $('#ca8id').val();
                    var ca8maxid = $('#ca8maxid').val();
                    var ca9id = $('#ca9id').val();
                    var ca9maxid = $('#ca9maxid').val();
                    var ca10id = $('#ca10id').val();
                    var ca10maxid = $('#ca10maxid').val();
                    var ca11id = $('#ca11id').val();
                    var ca11maxid = $('#ca11maxid').val();
                    var ca12id = $('#ca12id').val();
                    var ca12maxid = $('#ca12maxid').val();
                    var ca13id = $('#ca13id').val();
                    var ca13maxid = $('#ca13maxid').val();
                    var ca14id = $('#ca14id').val();
                    var ca14maxid = $('#ca14maxid').val();
                    var ca15id = $('#ca15id').val();
                    var ca15maxid = $('#ca15maxid').val();
                    
                    if(ca1id == '' || ca1maxid == '0' || ca2id == '' || ca2maxid == '0' || ca3id == '' || ca3maxid == '0' || ca4id == '' || ca4maxid == '0' || ca5id == '' || ca5maxid == '0' || ca6id == '' || ca6maxid == '0' || ca7id == '' || ca7maxid == '0' || ca8id == '' || ca8maxid == '0' || ca9id == '' || ca9maxid == '0' || ca10id == '' || ca10maxid == '0' || ca11id == '' || ca11maxid == '0' || ca12id == '' || ca12maxid == '0' || ca13id == '' || ca13maxid == '0')
                    {
                        $('.messagetoo').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
                        window.scrollTo(0, 0); 
                        $('.submitbtn').html('Submit');
                    }
                    else
                    {
                        
                        var selectedcaformidterm = [];
                        $.each($("input[name='selForMidTerm']:checked"), function(){
                            selectedcaformidterm.push($(this).data('id'));
                        });
            
                        // alert(selectedcaformidterm);
            
                            var dataString = 'catitle=' + catitle +'&ca1id=' + ca1id + '&ca1maxid=' + ca1maxid +'&ca2id=' + ca2id+'&ca2maxid=' +ca2maxid +'&ca3id=' +ca3id+ '&ca3maxid=' +  ca3maxid   + '&ca4id='+  ca4id +'&ca4maxid='+ca4maxid + '&ca5id='+   ca5id +'&ca5maxid='+ca5maxid +'&examNumber=' + examNumber+'&ca6id=' + ca6id + '&ca6maxid=' + ca6maxid +'&ca7id=' + ca7id+'&ca7maxid=' +ca7maxid +'&ca8id=' +ca8id+ '&ca8maxid=' +  ca8maxid   + '&ca9id='+  ca9id +'&ca9maxid='+ca9maxid + '&ca10id='+   ca10id +'&ca10maxid='+ca10maxid + '&ca11id='+   ca11id +'&ca11maxid='+ca11maxid + '&ca12id='+   ca12id +'&ca12maxid='+ca12maxid + '&ca13id='+   ca13id +'&ca13maxid='+ca13maxid + '&ca14id='+   ca14id +'&ca14maxid='+ca14maxid + '&ca15id='+   ca15id +'&ca15maxid='+ca15maxid + '&selectedcaformidterm=' + selectedcaformidterm;
                
                            $.ajax({
                                url: '../../../phpscript/affectivedomain/updateaffectivedomainsettings.php',
                                method:'POST',
                                data:dataString,
                                
                                success: function(anasmaindata2) {
                                
                                    $('.messagetoo').html(anasmaindata2);
                                    window.scrollTo(0, 0);
                                    $('.submitbtn').html('Submit');
                                    
                                    setTimeout(function() {
                                        location.reload();
                                    }, 5000);
                                    
                                }
                            });
                    }
                }
                else if(examNumber == '14')
                {
                    $('#ca15id').val('');
                    $('#ca15maxid').val('0');
                    
                    
                    var ca1id = $('#ca1id').val();
                    var ca1maxid = $('#ca1maxid').val();
                    var ca2id = $('#ca2id').val();
                    var ca2maxid = $('#ca2maxid').val();
                    var ca3id = $('#ca3id').val();
                    var ca3maxid = $('#ca3maxid').val();
                    var ca4id = $('#ca4id').val();
                    var ca4maxid = $('#ca4maxid').val();
                    var ca5id = $('#ca5id').val();
                    var ca5maxid = $('#ca5maxid').val();
        
                    var ca6id = $('#ca6id').val();
                    var ca6maxid = $('#ca6maxid').val();
                    var ca7id = $('#ca7id').val();
                    var ca7maxid = $('#ca7maxid').val();
                    var ca8id = $('#ca8id').val();
                    var ca8maxid = $('#ca8maxid').val();
                    var ca9id = $('#ca9id').val();
                    var ca9maxid = $('#ca9maxid').val();
                    var ca10id = $('#ca10id').val();
                    var ca10maxid = $('#ca10maxid').val();
                    var ca11id = $('#ca11id').val();
                    var ca11maxid = $('#ca11maxid').val();
                    var ca12id = $('#ca12id').val();
                    var ca12maxid = $('#ca12maxid').val();
                    var ca13id = $('#ca13id').val();
                    var ca13maxid = $('#ca13maxid').val();
                    var ca14id = $('#ca14id').val();
                    var ca14maxid = $('#ca14maxid').val();
                    var ca15id = $('#ca15id').val();
                    var ca15maxid = $('#ca15maxid').val();
                    
                    if(ca1id == '' || ca1maxid == '0' || ca2id == '' || ca2maxid == '0' || ca3id == '' || ca3maxid == '0' || ca4id == '' || ca4maxid == '0' || ca5id == '' || ca5maxid == '0' || ca6id == '' || ca6maxid == '0' || ca7id == '' || ca7maxid == '0' || ca8id == '' || ca8maxid == '0' || ca9id == '' || ca9maxid == '0' || ca10id == '' || ca10maxid == '0' || ca11id == '' || ca11maxid == '0' || ca12id == '' || ca12maxid == '0' || ca13id == '' || ca13maxid == '0' || ca14id == '' || ca14maxid == '0')
                    {
                        $('.messagetoo').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
                        window.scrollTo(0, 0); 
                        $('.submitbtn').html('Submit');
                    }
                    else
                    {
                        
                        var selectedcaformidterm = [];
                        $.each($("input[name='selForMidTerm']:checked"), function(){
                            selectedcaformidterm.push($(this).data('id'));
                        });
            
                        // alert(selectedcaformidterm);
            
                            var dataString = 'catitle=' + catitle +'&ca1id=' + ca1id + '&ca1maxid=' + ca1maxid +'&ca2id=' + ca2id+'&ca2maxid=' +ca2maxid +'&ca3id=' +ca3id+ '&ca3maxid=' +  ca3maxid   + '&ca4id='+  ca4id +'&ca4maxid='+ca4maxid + '&ca5id='+   ca5id +'&ca5maxid='+ca5maxid +'&examNumber=' + examNumber+'&ca6id=' + ca6id + '&ca6maxid=' + ca6maxid +'&ca7id=' + ca7id+'&ca7maxid=' +ca7maxid +'&ca8id=' +ca8id+ '&ca8maxid=' +  ca8maxid   + '&ca9id='+  ca9id +'&ca9maxid='+ca9maxid + '&ca10id='+   ca10id +'&ca10maxid='+ca10maxid + '&ca11id='+   ca11id +'&ca11maxid='+ca11maxid + '&ca12id='+   ca12id +'&ca12maxid='+ca12maxid + '&ca13id='+   ca13id +'&ca13maxid='+ca13maxid + '&ca14id='+   ca14id +'&ca14maxid='+ca14maxid + '&ca15id='+   ca15id +'&ca15maxid='+ca15maxid + '&selectedcaformidterm=' + selectedcaformidterm;
                
                            $.ajax({
                                url: '../../../phpscript/affectivedomain/updateaffectivedomainsettings.php',
                                method:'POST',
                                data:dataString,
                                
                                success: function(anasmaindata2) {
                                
                                    $('.messagetoo').html(anasmaindata2);
                                    window.scrollTo(0, 0);
                                    $('.submitbtn').html('Submit');
                                    
                                    setTimeout(function() {
                                        location.reload();
                                    }, 5000);
                                    
                                }
                            });
                    }
                }
                else if(examNumber == '15')
                {
                    var ca1id = $('#ca1id').val();
                    var ca1maxid = $('#ca1maxid').val();
                    var ca2id = $('#ca2id').val();
                    var ca2maxid = $('#ca2maxid').val();
                    var ca3id = $('#ca3id').val();
                    var ca3maxid = $('#ca3maxid').val();
                    var ca4id = $('#ca4id').val();
                    var ca4maxid = $('#ca4maxid').val();
                    var ca5id = $('#ca5id').val();
                    var ca5maxid = $('#ca5maxid').val();
        
                    var ca6id = $('#ca6id').val();
                    var ca6maxid = $('#ca6maxid').val();
                    var ca7id = $('#ca7id').val();
                    var ca7maxid = $('#ca7maxid').val();
                    var ca8id = $('#ca8id').val();
                    var ca8maxid = $('#ca8maxid').val();
                    var ca9id = $('#ca9id').val();
                    var ca9maxid = $('#ca9maxid').val();
                    var ca10id = $('#ca10id').val();
                    var ca10maxid = $('#ca10maxid').val();
                    var ca11id = $('#ca11id').val();
                    var ca11maxid = $('#ca11maxid').val();
                    var ca12id = $('#ca12id').val();
                    var ca12maxid = $('#ca12maxid').val();
                    var ca13id = $('#ca13id').val();
                    var ca13maxid = $('#ca13maxid').val();
                    var ca14id = $('#ca14id').val();
                    var ca14maxid = $('#ca14maxid').val();
                    var ca15id = $('#ca15id').val();
                    var ca15maxid = $('#ca15maxid').val();
                    
                    if(ca1id == '' || ca1maxid == '0' || ca2id == '' || ca2maxid == '0' || ca3id == '' || ca3maxid == '0' || ca4id == '' || ca4maxid == '0' || ca5id == '' || ca5maxid == '0' || ca6id == '' || ca6maxid == '0' || ca7id == '' || ca7maxid == '0' || ca8id == '' || ca8maxid == '0' || ca9id == '' || ca9maxid == '0' || ca10id == '' || ca10maxid == '0' || ca11id == '' || ca11maxid == '0' || ca12id == '' || ca12maxid == '0' || ca13id == '' || ca13maxid == '0' || ca14id == '' || ca14maxid == '0' || ca15id == '' || ca15maxid == '0')
                    {
                        $('.messagetoo').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
                        window.scrollTo(0, 0); 
                        $('.submitbtn').html('Submit');
                    }
                    else
                    {
                        
                        var selectedcaformidterm = [];
                        $.each($("input[name='selForMidTerm']:checked"), function(){
                            selectedcaformidterm.push($(this).data('id'));
                        });
            
                        // alert(selectedcaformidterm);
            
                            var dataString = 'catitle=' + catitle +'&ca1id=' + ca1id + '&ca1maxid=' + ca1maxid +'&ca2id=' + ca2id+'&ca2maxid=' +ca2maxid +'&ca3id=' +ca3id+ '&ca3maxid=' +  ca3maxid   + '&ca4id='+  ca4id +'&ca4maxid='+ca4maxid + '&ca5id='+   ca5id +'&ca5maxid='+ca5maxid +'&examNumber=' + examNumber+'&ca6id=' + ca6id + '&ca6maxid=' + ca6maxid +'&ca7id=' + ca7id+'&ca7maxid=' +ca7maxid +'&ca8id=' +ca8id+ '&ca8maxid=' +  ca8maxid   + '&ca9id='+  ca9id +'&ca9maxid='+ca9maxid + '&ca10id='+   ca10id +'&ca10maxid='+ca10maxid + '&ca11id='+   ca11id +'&ca11maxid='+ca11maxid + '&ca12id='+   ca12id +'&ca12maxid='+ca12maxid + '&ca13id='+   ca13id +'&ca13maxid='+ca13maxid + '&ca14id='+   ca14id +'&ca14maxid='+ca14maxid + '&ca15id='+   ca15id +'&ca15maxid='+ca15maxid + '&selectedcaformidterm=' + selectedcaformidterm;
                
                            $.ajax({
                                url: '../../../phpscript/updateaffectivedomainsettings.php',
                                method:'POST',
                                data:dataString,
                                
                                success: function(anasmaindata2) {
                                
                                    $('.messagetoo').html(anasmaindata2);
                                    window.scrollTo(0, 0);
                                    $('.submitbtn').html('Submit');
                                    
                                    setTimeout(function() {
                                        location.reload();
                                    }, 5000);
                                    
                                }
                            });
                    }
                }
                else
                {
                    
                }
            }
            else
            {
                $('.messagetoo').html('<div class="alert alert-primary" role="alert"> No Field Should be Empty!</div>');
                window.scrollTo(0, 0); 
                $('.submitbtn').html('Submit');
            }
            
        });
        
        $("body").on('change','#examNumber',function(){
            
            $('#addNumber').html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing');

            var examNumber = $('#examNumber').val();
            
            var catitle = $('#CaTitle').val();
            
            $.ajax({
                    url: '../../../phpscript/affectivedomain/affectivedomainsettingsload.php',
                    method:'POST',
                    data: 'examNumber=' + examNumber + '&catitle=' + catitle,
                    success: function(data) {
                        $('#addNumber').html(data);
                        
                        if(examNumber == '1')
                        {
                            $('.fifteen').hide(); 
                            $('.one').show('slow');
                        }
                        else if(examNumber == '2')
                        {
                            $('.fifteen').hide();
                            $('.two').show('slow');
                        }
                        else if(examNumber == '3')
                        {
                            $('.fifteen').hide();
                            $('.three').show('slow');
                        }
                        else if(examNumber == '4')
                        {
                            $('.fifteen').hide();
                            $('.four').show('slow');
                        }
                        else if(examNumber == '5')
                        {
                            $('.fifteen').hide();
                            $('.five').show('slow');
                        }
                        else if(examNumber == '6')
                        {
                            $('.fifteen').hide();
                            $('.six').show('slow');
                        }
                        else if(examNumber == '7')
                        {
                            $('.fifteen').hide();
                            $('.seven').show('slow');
                        }
                        else if(examNumber == '8')
                        { 
                            $('.fifteen').hide();
                            $('.eight').show('slow');
                        }
                        else if(examNumber == '9')
                        {
                            $('.fifteen').hide();
                            $('.nine').show('slow');
                        }
                        else if(examNumber == '10')
                        {
                            $('.fifteen').hide();
                            $('.ten').show('slow');
                        }
                        else if(examNumber == '11')
                        {
                            $('.fifteen').hide();
                            $('.eleven').show('slow');
                        }
                        else if(examNumber == '12')
                        {
                            $('.fifteen').hide();
                            $('.twelve').show('slow');
                        }
                        else if(examNumber == '13')
                        {
                            $('.fifteen').hide();
                            $('.thirteen').show('slow');
                        }
                        else if(examNumber == '14')
                        {
                            $('.fifteen').hide();
                            $('.fourteen').show('slow');
                        }
                        else if(examNumber == '15')
                        {
                            $('.fifteen').hide();
                            $('.fifteen').show('slow');
                        }
                        else
                        {
                            $('.fifteen').hide();
                        }

                    }
            });

        });
        
        // $("body").on('click','#addsubjectbtn',function(){
            
        //     $('#addExamNumber').html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing');
            
        //     var examname = $(this).data('id');
            
        //     var examca = $(this).data('examca');
            
        //     var examID = "<?php echo $examID;?>";
            
        //     var sessionnew = $(this).data('session');
            
        //     var termnew = $(this).data('term');
            
        //     var subgroupid = "<?php echo $subgroupid; ?>";
            
        //     $('#displayexamname').html(examname);
            
        //     $('#cainput').val(examca);
            
        //     $('#sessionnewinput').val(sessionnew);
            
        //     $('#termnewinput').val(termnew);
            
        //     $('#errmsgnew').html('');
            
        //     var dataString = 'examID=' + examID + '&examca=' + examca + '&subgroupid=' + subgroupid;
            
        //     // alert(dataString);
        //     $.ajax({
        //         url: '../../../phpscript/dispay-subjectexam.php',
        //         method:'POST',
        //         data:dataString,
                
        //         success: function(maindata2) {
                
        //             $('#addExamNumber').html(maindata2);
                    
        //         }
        //     });
                
        // });
        
        $("body").on("click", "#submitstudentbtn", function(){
            
            $(this).html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing');
            
            var classid = [];
            $.each($("input[name='chkBoc']:checked"), function(){            
                classid.push($(this).val());
            });
            
            var resultsettingid = $('#resultsettingid').val();
            
            var resulttype = $('#resulttype').val();
            
            if(resultsettingid != '' && resultsettingid != '0' && classid.length > 0)
            {
                var dataString = 'resultsettingid=' + resultsettingid + '&classid=' + classid + '&resulttype=' + resulttype;
                
                // alert(dataString);
                $.ajax({
                    url: '../../../phpscript/affectivedomain/insert-affectivedomain.php',
                    method:'POST',
                    data:dataString,
                    
                    success: function(maindata2) {
                    
                        $('#errmsgnewstudent').html(maindata2);
                        window.scrollTo(0, 0);
                        $('#submitstudentbtn').html('Save changes');
                        
                        setTimeout(function() {
                            location.reload();
                        }, 5000);
                        
                    }
                });
            }
            else
            {
                $('#errmsgnew').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
                window.scrollTo(0, 0); 
                $('#submitsubbtn').html('Save changes');
            }
             
        });
        
        $("body").on("click", "#editsubjectbtn", function(){
            
            var examNumbernew = $(this).data('numca');
            
            var catitle = $(this).data('id');
            
            $('#examNumber').val(examNumbernew);
            $('#CaTitle').val(catitle);
            
            var examNumber = $('#examNumber').val();
            
            var catitle = $('#CaTitle').val();
            
            $('#addNumber').html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing');
            
            $.ajax({
                url: '../../../phpscript/affectivedomain/affectivedomainsettingsload.php',
                method:'POST',
                data: 'examNumber=' + examNumber + '&catitle=' + catitle,
                success: function(data) {
                    $('#addNumber').html(data);
                    
                    if(examNumber == '1')
                    {
                        $('.fifteen').hide();
                        $('.one').show('slow');
                    }
                    else if(examNumber == '2')
                    {
                        $('.fifteen').hide();
                        $('.two').show('slow');
                    }
                    else if(examNumber == '3')
                    {
                        $('.fifteen').hide();
                        $('.three').show('slow');
                    }
                    else if(examNumber == '4')
                    {
                        $('.fifteen').hide();
                        $('.four').show('slow');
                    }
                    else if(examNumber == '5')
                    {
                        $('.fifteen').hide();
                        $('.five').show('slow');
                    }
                    else if(examNumber == '6')
                    {
                        $('.fifteen').hide();
                        $('.six').show('slow');
                    }
                    else if(examNumber == '7')
                    {
                        $('.fifteen').hide();
                        $('.seven').show('slow');
                    }
                    else if(examNumber == '8')
                    { 
                        $('.fifteen').hide();
                        $('.eight').show('slow');
                    }
                    else if(examNumber == '9')
                    {
                        $('.fifteen').hide();
                        $('.nine').show('slow');
                    }
                    else if(examNumber == '10')
                    {
                        $('.fifteen').hide();
                        $('.ten').show('slow');
                    }
                    else if(examNumber == '11')
                    {
                        $('.fifteen').hide();
                        $('.eleven').show('slow');
                    }
                    else if(examNumber == '12')
                    {
                        $('.fifteen').hide();
                        $('.twelve').show('slow');
                    }
                    else if(examNumber == '13')
                    {
                        $('.fifteen').hide();
                        $('.thirteen').show('slow');
                    }
                    else if(examNumber == '14')
                    {
                        $('.fifteen').hide();
                        $('.fourteen').show('slow');
                    }
                    else if(examNumber == '15')
                    {
                        $('.fifteen').hide();
                        $('.fifteen').show('slow');
                    }
                    else
                    {
                        $('.fifteen').hide();
                    }

                }
            });
            
        });
        
        // $("body").on("click", "#deletegradesetting", function(){
            
        //     var sessionnew = $(this).data('id');
            
        //     var termnew = $(this).data('term');
            
        //     $('#sessioninput').val(sessionnew);
        //     $('#terminput').val(termnew);
            
        // });
        
        // $("body").on("click", "#proceeddelete", function(){
            
        //     $(this).html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing');
            
        //     var session = $('#sessioninput').val();
            
        //     var term = $('#terminput').val();
            
        //     var examID = "<?php echo $examID;?>";
            
        //     // alert('session=' + session + '&term=' + term + '&examID=' + examID);
            
        //     $.ajax({
        //         url: '../../../phpscript/deleteresultsettings.php',
        //         method:'POST',
        //         data: 'session=' + session + '&term=' + term + '&examID=' + examID,
        //         success: function(data) {
        //             $('#successmsg').html(data);
        //             $("#proceeddelete").html('<i class="fa fa-check"></i> Submit');
                    
        //         }
        //     });
            
        // });
        
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