<?php
include ('../database/config.php');
require_once('../helper/defaultcomment_helper.php');
?>
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
    <title>Head Teacher Result Comment</title>
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
                        <div class="form-group col-sm-4">
                            <select class="form-control" id="schoolhead">
                                <option value="0">Select Head Teacher</option>
                                <?php
                                    $sqlstaffcheck = "SELECT * FROM `staff_roles` INNER JOIN roles ON staff_roles.role_id=roles.id WHERE staff_roles.staff_id='$id'";
                                    $resultstaffcheck = mysqli_query($link, $sqlstaffcheck);
                                    $rowstaffcheck = mysqli_fetch_assoc($resultstaffcheck);
                                    $row_cntstaffcheck = mysqli_num_rows($resultstaffcheck);

                                    if($row_cntstaffcheck > 0)
                                    {
                                        if($rowstaffcheck['name'] == 'Head Teacher')
                                        {
                                            $sqlstaff = "SELECT staff.id AS staff_id, staff.name AS staff_name, staff.surname AS staff_surname
                                                         FROM `staff`
                                                         WHERE staff.id='$id'
                                                         ORDER BY surname ASC";
                                        }
                                        else
                                        {
                                            $sqlstaff = "SELECT staff.id AS staff_id, staff.name AS staff_name, staff.surname AS staff_surname
                                                         FROM `staff`
                                                         INNER JOIN staff_roles ON staff.id = staff_roles.staff_id
                                                         INNER JOIN roles ON staff_roles.role_id = roles.id
                                                         WHERE roles.name='Head Teacher'
                                                         ORDER BY surname ASC";
                                        }

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
                                        echo'<option value="0">No Records Found</option>';
                                    }

                                ?>
                            </select>
						</div>
                        <div class="form-group col-sm-4">
                            <select class="form-control" id="classid">
                                <option value="0">Select Class</option>
                                <?php
                                    $sqlclasses = "SELECT * FROM `classes` ORDER BY class ASC";
                                    $resultclasses = mysqli_query($link, $sqlclasses);
                                    $rowclasses = mysqli_fetch_assoc($resultclasses);
                                    $row_cntclasses = mysqli_num_rows($resultclasses);

                                    if($row_cntclasses > 0)
                                    {
                                        do{
                                            echo '<option value="'.$rowclasses['id'].'">'.$rowclasses['class'].'</option>';
                                        }while($rowclasses = mysqli_fetch_assoc($resultclasses));
                                    }
                                ?>
                            </select>
                        </div>
                        <div class="form-group col-sm-4">
                            <select class="form-control" id="resultType">
                                <option value="termly">Termly</option>
                                <option value="midterm">Mid-Term</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-sm-12">
                            <div class="alert alert-info" id="defaultcomment-meta" style="margin-bottom: 0;">
                                Select a head teacher, class and result type to load the allowed score range.
                            </div>
                        </div>
                    </div>
                </form>

                </div>
            </div>
		</div>

        <?php
            include ('../helper/s3_helper.php');

            if(isset($_POST['submitbtn']))
            {
               $commentfrom = $_POST['commentfrom'] ?? '';
               $commentfromto = $_POST['commentfromto'] ?? '';
               $comment = $_POST['comment'] ?? '';
               $CommentType = 'SchoolHead';
               $principalID = (int) ($_POST['principalID'] ?? 0);
               $classID = (int) ($_POST['classID'] ?? 0);
               $resultType = $_POST['resultType'] ?? 'termly';

                $validation = validate_defaultcomment_payload(
                    $link,
                    $principalID,
                    $classID,
                    $CommentType,
                    $resultType,
                    $commentfrom,
                    $commentfromto,
                    $comment
                );

                if(!$validation['success'])
                {
                    echo defaultcomment_alert_markup('warning', $validation['message'], $principalID, $classID, $validation['resultSubType']);
                }
                else
                {
                    $commentSafe = mysqli_real_escape_string($link, $validation['comment']);
                    $resultType = $validation['resultSubType'];
                    $commentfrom = $validation['rangeStart'];
                    $commentfromto = $validation['rangeEnd'];

                    $sqldefaultcomment = "SELECT * FROM `defaultcomment`
                                          WHERE PrincipalOrDeanOrHeadTeacherUserID = '$principalID'
                                            AND ClassID = '$classID'
                                            AND CommentType = '$CommentType'
                                            AND ResultSubType = '$resultType'
                                            AND RangeStart = '$commentfrom'
                                            AND RangeEnd = '$commentfromto'
                                            AND DefaultComment = '$commentSafe'";
                    $resultdefaultcomment = mysqli_query($link, $sqldefaultcomment);
                    $row_cntdefaultcomment = $resultdefaultcomment ? mysqli_num_rows($resultdefaultcomment) : 0;

                    if($row_cntdefaultcomment > 0)
                    {
                        echo defaultcomment_alert_markup('warning', 'Already exists.', $principalID, $classID, $resultType);
                    }
                    else
                    {
                        $sqlInsertdefaultcomment = "INSERT INTO `defaultcomment`
                                                    (`PrincipalOrDeanOrHeadTeacherUserID`, `ClassID`, `CommentType`, `ResultSubType`, `RangeStart`, `RangeEnd`, `DefaultComment`)
                                                    VALUES
                                                    ('$principalID', '$classID', '$CommentType', '$resultType', '$commentfrom', '$commentfromto', '$commentSafe')";
                        $Insertdefaultcomment = mysqli_query($link, $sqlInsertdefaultcomment);

                        if($Insertdefaultcomment)
                        {
                            echo defaultcomment_alert_markup('success', 'Added successfully.', $principalID, $classID, $resultType);
                        }
                        else
                        {
                            echo defaultcomment_alert_markup('warning', 'Operation failed. Something went wrong.', $principalID, $classID, $resultType);
                        }
                    }
                }
            }
        ?>

        <?php
            if(isset($_POST['proceeddelete']))
            {

               $defaultcommentID = (int) ($_POST['comid'] ?? 0);

               $sqldefaultcomment = "SELECT * FROM `defaultcomment` WHERE defaultcommentID = '$defaultcommentID'";
                $resultdefaultcomment = mysqli_query($link, $sqldefaultcomment);
                $rowdefaultcomment = mysqli_fetch_assoc($resultdefaultcomment);
                $row_cntdefaultcomment = $resultdefaultcomment ? mysqli_num_rows($resultdefaultcomment) : 0;

                if($row_cntdefaultcomment > 0)
                {
                    $sqlDeleteexamgroup = "DELETE FROM `defaultcomment` WHERE defaultcommentID= '$defaultcommentID'";
                    $Deleteexamgroup = mysqli_query($link, $sqlDeleteexamgroup);

                    if($Deleteexamgroup)
                    {
                        echo defaultcomment_alert_markup(
                            'success',
                            'Deleted successfully.',
                            $rowdefaultcomment['PrincipalOrDeanOrHeadTeacherUserID'],
                            $rowdefaultcomment['ClassID'],
                            $rowdefaultcomment['ResultSubType']
                        );
                    }
                    else
                    {
                        echo defaultcomment_alert_markup(
                            'warning',
                            'Operation failed. Something went wrong.',
                            $rowdefaultcomment['PrincipalOrDeanOrHeadTeacherUserID'],
                            $rowdefaultcomment['ClassID'],
                            $rowdefaultcomment['ResultSubType']
                        );
                    }
                }
                else
                {
                    echo defaultcomment_alert_markup('warning', 'Record not found.', 0, 0, 'termly');
                }
            }
        ?>

        <?php
            if(isset($_POST['editgradebtn']))
            {

               $commentfrom = $_POST['commentfrom'] ?? '';
               $commentfromto = $_POST['commentfromto'] ?? '';
               $comment = $_POST['comment'] ?? '';
               $defaultcommentID = (int) ($_POST['defaultcommentID'] ?? 0);
               $CommentType = 'SchoolHead';

                $sqldefaultcomment = "SELECT * FROM `defaultcomment` WHERE defaultcommentID = '$defaultcommentID'";
                $resultdefaultcomment = mysqli_query($link, $sqldefaultcomment);
                $rowdefaultcomment = mysqli_fetch_assoc($resultdefaultcomment);
                $row_cntdefaultcomment = $resultdefaultcomment ? mysqli_num_rows($resultdefaultcomment) : 0;

                if($row_cntdefaultcomment <= 0)
                {
                    echo defaultcomment_alert_markup('warning', 'Record not found.', 0, 0, 'termly');
                }
                else
                {
                    $principalID = (int) $rowdefaultcomment['PrincipalOrDeanOrHeadTeacherUserID'];
                    $classID = (int) $rowdefaultcomment['ClassID'];
                    $resultType = $rowdefaultcomment['ResultSubType'];

                    $validation = validate_defaultcomment_payload(
                        $link,
                        $principalID,
                        $classID,
                        $CommentType,
                        $resultType,
                        $commentfrom,
                        $commentfromto,
                        $comment,
                        $defaultcommentID
                    );

                    if(!$validation['success'])
                    {
                        echo defaultcomment_alert_markup('warning', $validation['message'], $principalID, $classID, $validation['resultSubType']);
                    }
                    else
                    {
                        $commentSafe = mysqli_real_escape_string($link, $validation['comment']);
                        $commentfrom = $validation['rangeStart'];
                        $commentfromto = $validation['rangeEnd'];
                        $resultType = $validation['resultSubType'];

                        $sqldefaultcomment = "SELECT * FROM `defaultcomment`
                                              WHERE defaultcommentID = '$defaultcommentID'
                                                AND DefaultComment = '$commentSafe'
                                                AND RangeStart = '$commentfrom'
                                                AND RangeEnd = '$commentfromto'";
                        $resultdefaultcomment = mysqli_query($link, $sqldefaultcomment);
                        $row_cntdefaultcomment = $resultdefaultcomment ? mysqli_num_rows($resultdefaultcomment) : 0;

                        if($row_cntdefaultcomment > 0)
                        {
                            echo defaultcomment_alert_markup('warning', 'No changes detected.', $principalID, $classID, $resultType);
                        }
                        else
                        {
                            $sqlInsertdefaultcomment = "UPDATE `defaultcomment`
                                                        SET `RangeStart`='$commentfrom',
                                                            `RangeEnd`='$commentfromto',
                                                            `DefaultComment`='$commentSafe'
                                                        WHERE `defaultcommentID` = '$defaultcommentID'";
                            $Insertdefaultcomment = mysqli_query($link, $sqlInsertdefaultcomment);

                            if($Insertdefaultcomment)
                            {
                                echo defaultcomment_alert_markup('success', 'Updated successfully.', $principalID, $classID, $resultType);
                            }
                            else
                            {
                                echo defaultcomment_alert_markup('warning', 'Operation failed. Something went wrong.', $principalID, $classID, $resultType);
                            }
                        }
                    }
                }
            }
        ?>

        <?php
            if(isset($_POST['submitbtnsign'])){
                //Get current user ID from session
                $principalID = (int) ($_POST['principalID'] ?? 0);
                $classID = (int) ($_POST['classID'] ?? 0);
                $resultType = $_POST['resultType'] ?? 'termly';

                if(!empty($_FILES['staffsignature']['name']) || $principalID != '' && $principalID != '0')
                {
                    //File uplaod configuration
                    $result = 0;
                    $uploadDir = "../img/signature/";
                    $fileName = time().'_'.basename($_FILES['staffsignature']['name']);
                    $imageFileType = pathinfo($fileName,PATHINFO_EXTENSION);
                    $fileInfo = pathinfo($_FILES['staffsignature']['name']);
                    $targetPath = $uploadDir. $fileName;

                    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" )
                    {

                        echo '<div class="alert alert-warning  fade show" role="alert">
                                <div class="alert-icon"><i class="flaticon-warning"></i></div>
                                <div class="alert-text">Unsupported File Format. Only png,jpeg or gif is accepted</div>
                                <div class="alert-close">
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true"><i class="la la-close"></i></span>
                                    </button>
                                    '.defaultcomment_reload_state_markup($principalID, $classID, $resultType).'
                                </div>
                            </div>';
                    }
                    else
                    {
                        if ($_FILES["staffsignature"]["size"] > 10000000) {
                            echo '<div class="alert alert-warning  fade show" role="alert">
                                    <div class="alert-icon"><i class="flaticon-warning"></i></div>
                                    <div class="alert-text">Sorry, File Size is too much. Kindly get another file and re-upload</div>
                                    <div class="alert-close">
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <span aria-hidden="true"><i class="la la-close"></i></span>
                                        </button>
                                        '.defaultcomment_reload_state_markup($principalID, $classID, $resultType).'
                                    </div>
                                </div>';
                        }
                        else{

                            $sqlstaffsignature = "SELECT * FROM `staffsignature` WHERE staff_id= '$principalID'";
                            $resultstaffsignature = mysqli_query($link, $sqlstaffsignature);
                            $rowstaffsignature = mysqli_fetch_assoc($resultstaffsignature);
                            $row_cntstaffsignature = mysqli_num_rows($resultstaffsignature);

                            if($row_cntstaffsignature > 0)
                            {
                                //Upload file to server
                                $upload_result = upload_to_s3($_FILES['staffsignature']['tmp_name'], $fileInfo, $fileName, "uploads/img/signature/");

                                if ($upload_result['success']) {
                                // if(@move_uploaded_file($_FILES['staffsignature']['tmp_name'], $targetPath))
                                // {
                                    //Get current user ID from session

                                    //Update picture name in the database
                                    $newfileName = $upload_result['s3_key'];
                                    $sqlUploadUserImage = ("UPDATE `staffsignature` SET `Signature`='$newfileName' WHERE `staff_id` = '$principalID'");
                                        $resultUploadUserImage = mysqli_query($link, $sqlUploadUserImage);

                                        //Update status
                                            if($resultUploadUserImage)
                                                {
                                                echo '<div class="alert alert-success  fade show" role="alert">
                                                    <div class="alert-icon"><i class="flaticon2-check-mark"></i></div>
                                                    <div class="alert-text">Great! Signature Updated Successfuly</div>
                                                    <div class="alert-close">
                                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                            <span aria-hidden="true"><i class="la la-close"></i></span>
                                                        </button>
                                                        '.defaultcomment_reload_state_markup($principalID, $classID, $resultType).'
                                                    </div>
                                                </div>';
                                                    }

                                        }
                            }
                            else
                            {
                                //Upload file to server
                                $upload_result = upload_to_s3($_FILES['staffsignature']['tmp_name'], $fileInfo, $fileName, "uploads/img/signature/");
                                //
                                if ($upload_result['success']) {
                                    //Get current user ID from session
                                // if(@move_uploaded_file($_FILES['staffsignature']['tmp_name'], $targetPath))
                                // {

                                    $newfileName = $upload_result['s3_key'];
                                    //Update picture name in the database
                                    $sqlUploadUserImage = ("INSERT INTO `staffsignature`(`staff_id`, `Signature`) VALUES ('$principalID','$newfileName')");
                                        $resultUploadUserImage = mysqli_query($link, $sqlUploadUserImage);

                                        //Update status
                                            if($resultUploadUserImage)
                                                {
                                                echo '<div class="alert alert-success  fade show" role="alert">
                                                    <div class="alert-icon"><i class="flaticon2-check-mark"></i></div>
                                                    <div class="alert-text">Great! Signature Updated Successfuly</div>
                                                    <div class="alert-close">
                                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                            <span aria-hidden="true"><i class="la la-close"></i></span>
                                                        </button>
                                                        '.defaultcomment_reload_state_markup($principalID, $classID, $resultType).'
                                                    </div>
                                                </div>';
                                                    }

                                        }
                            }
                        }
                    }
                }
                else{
                    echo '<div class="alert alert-warning  fade show" role="alert">
                            <div class="alert-icon"><i class="flaticon-warning"></i></div>
                            <div class="alert-text">Opps! Please Select a File and a staff for upload</div>
                            <div class="alert-close">
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true"><i class="la la-close"></i></span>
                                </button>
                                '.defaultcomment_reload_state_markup($principalID, $classID, $resultType).'
                            </div>
                        </div>';
                }
            }
        ?>
		<div class="row" style="margin: 15px;">

            <div class="col-sm-12">

                <div class="table-responsive data_table">

					<h3 style="margin-bottom: 50px;">Head Teacher's Default Comment</h3>

                    <button type="button" class="btn btn-primary hideme" data-toggle="modal" data-target="#exampleModal" style="border-radius: 20px; float: right; margin-bottom: 30px;">
                       Set Default
                   </button>

                   <button type="button" class="btn btn-primary hideme" data-toggle="modal" data-target="#signatureModal" style="border-radius: 20px; float: right; margin-bottom: 30px;">
                       Set Signature
                   </button>

					<table id="example" class="table table-striped" style="width:100%">
						<thead>
							<tr>
								<th>Start</th>
								<th>End</th>
                                <th>Comment</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody id="commenttbl">

						</tbody>
					</table>

                </div>
            </div>
		</div>

        <!-- Create Comment Modal -->
			<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">

                        <h4 class="modal-title" id="exampleModalLabel" style="margin-left: 18%; text-align: center; color: #2c2c2c;">Create Default Message</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        </div>
                        <form method="post" enctype="multipart/form-data" accept-charset="UTF-8">
                            <div class="modal-body">
                                <div class="alert alert-info" id="defaultcomment-modal-meta">
                                    Select a head teacher, class and result type first to load the allowed score range.
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <label style="font-weight: 500;">Ranges:</label>
                                        <input type="number" step=".01" id="commentfrom" name="commentfrom" class="form-control" placeholder="example 80">
                                    </div>
                                    <div class="col">
                                        <label style="font-weight: 500;">&nbsp;&nbsp;&nbsp;</label>
                                      <input type="number" step=".01" id="commentfromto" name="commentfromto" class="form-control" placeholder="example 100">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="exampleFormControlTextarea1" style="font-weight: 500;">Comment:</label>
                                    <textarea class="form-control" name="comment" id="exampleFormControlTextarea1" rows="3" placeholder="exmple: excellent result"></textarea>
                                  </div>

                                  <input type="hidden" id="principalID" name="principalID">
                                  <input type="hidden" id="classID" name="classID">
                                  <input type="hidden" id="resultTypeInput" name="resultType">

                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="submit" name="submitbtn" class="btn btn-primary"><i class="fa fa-check"></i> Submit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <!-- Create Comment Modal -->


        <!-- Create signature Modal -->
			<div class="modal fade" id="signatureModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">

                            <h3 class="modal-title" id="exampleModalLabel" style="margin-left: 18%; color: #2c2c2c;">Create Signature</h3>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form method="post" enctype="multipart/form-data" accept-charset="UTF-8">
                            <div class="modal-body">
                                <div class="col">
                                    <input type="file" name="staffsignature" class="form-control" placeholder="80">
                                </div>

                                <div id="staffsigndiv">

                                </div>

                                <input type="hidden" id="principalIDsig" name="principalID">
                                <input type="hidden" id="classIDsig" name="classID">
                                <input type="hidden" id="resultTypeSig" name="resultType">

                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="submit" name="submitbtnsign" class="btn btn-primary"><i class="fa fa-check"></i> Submit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <!-- Create signature Modal -->


        <!-- Edit Comment Modal -->
			<div class="modal fade" id="exampleModalEdit" tabindex="-1" aria-labelledby="exampleModalEditLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">

                        <h3 class="modal-title" id="exampleModalLabel" style="margin-left: 18%; color: #2c2c2c;">Edit Default Message</h3>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        </div>
                        <form method="post" enctype="multipart/form-data" id="displayform" accept-charset="UTF-8">

                        </form>

                    </div>
                </div>
            </div>
        <!-- Edit Comment Modal -->


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
                        <span style="font-size: 18px; font-weight: 500;">Are you sure you want to Delete this? <br>
                        Please note that this action cannot be reversed!!!</span>

                    </div>
                    <div class="modal-footer">
                        <form method="post" enctype="multipart/form-data" id="displayform" accept-charset="UTF-8">

                            <input type="hidden" id="comid" name="comid">

                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" name="proceeddelete"><i class="fa fa-check"></i> Submit</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- Delete Modal -->


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
            
            function setActionVisibility(showButtons) {
                if (showButtons) {
                    $('.hideme').show('slow');
                } else {
                    $('.hideme').hide('slow');
                }
            }

            function syncHiddenInputs(staffId, classId, resultType) {
                $('#principalID').val(staffId);
                $('#principalIDsig').val(staffId);
                $('#classID').val(classId);
                $('#classIDsig').val(classId);
                $('#resultTypeInput').val(resultType);
                $('#resultTypeSig').val(resultType);
            }

            function setMetaState(success, message, maxScore) {
                var metaText = message;

                if (success && maxScore !== null) {
                    metaText = message + ' Maximum allowed score: ' + maxScore + '.';
                }

                $('#defaultcomment-meta')
                    .removeClass('alert-warning alert-info')
                    .addClass(success ? 'alert-info' : 'alert-warning')
                    .text(metaText);

                $('#defaultcomment-modal-meta')
                    .removeClass('alert-warning alert-info')
                    .addClass(success ? 'alert-info' : 'alert-warning')
                    .text(metaText);

                if (success && maxScore !== null) {
                    $('#commentfrom, #commentfromto').attr('max', maxScore);
                } else {
                    $('#commentfrom, #commentfromto').removeAttr('max');
                }
            }

            function loadCommentTable(staffId, classId, resultType) {
                $('#commenttbl').html('<tr><td colspan="4"><i class="fa fa-circle-o-notch fa-spin"></i> Processing...</td></tr>');

                $.ajax({
                    url: '../../../phpscript/get-commenttbl.php',
                    method:'POST',
                    data: {
                        id: staffId,
                        classid: classId,
                        resultType: resultType,
                        commentType: 'SchoolHead'
                    },
                    success: function(data) {
                        $('#commenttbl').html(data);
                    }
                });
            }

            function loadHeadSignature(staffId) {
                if (!staffId || staffId === '0') {
                    $('#staffsigndiv').html('');
                    return;
                }

                $.ajax({
                    url: '../../../phpscript/get-staff-signature.php',
                    method:'POST',
                    data: {
                        id: staffId
                    },
                    success: function(data) {
                        $('#staffsigndiv').html(data);
                    }
                });
            }

            function refreshDefaultCommentState() {
                var staffId = $('#schoolhead').val();
                var classId = $('#classid').val();
                var resultType = $('#resultType').val();

                syncHiddenInputs(staffId, classId, resultType);

                if (!staffId || staffId === '0' || !classId || classId === '0') {
                    setActionVisibility(false);
                    setMetaState(false, 'Select a head teacher, class and result type to load the allowed score range.', null);
                    $('#commenttbl').html('<tr><td colspan="4">No Records Found</td></tr>');
                    return;
                }

                $('#commenttbl').html('<tr><td colspan="4"><i class="fa fa-circle-o-notch fa-spin"></i> Processing...</td></tr>');

                $.ajax({
                    url: '../../../phpscript/get-defaultcomment-meta.php',
                    method:'POST',
                    dataType:'json',
                    data: {
                        classid: classId,
                        resultType: resultType
                    },
                    success: function(data) {
                        setMetaState(data.success, data.message, data.success ? data.maxScore : null);

                        if (data.success) {
                            setActionVisibility(true);
                            loadCommentTable(staffId, classId, resultType);
                        } else {
                            setActionVisibility(false);
                            $('#commenttbl').html('<tr><td colspan="4">' + data.message + '</td></tr>');
                        }
                    },
                    error: function() {
                        setActionVisibility(false);
                        setMetaState(false, 'Unable to load the class score settings right now.', null);
                        $('#commenttbl').html('<tr><td colspan="4">Unable to load records.</td></tr>');
                    }
                });
            }

            $('#schoolhead, #classid, #resultType').on('change', function(){
                loadHeadSignature($('#schoolhead').val());
                refreshDefaultCommentState();
            });

            setActionVisibility(false);
            $('#commenttbl').html('<tr><td colspan="4">No Records Found</td></tr>');

            var reloadStaffId = $('#reloadStaffID').val() || $('#schoolhead').val();
            var reloadClassId = $('#reloadClassID').val() || '0';
            var reloadResultType = $('#reloadResultType').val() || 'termly';

            if (reloadResultType) {
                $('#resultType').val(reloadResultType);
            }

            if (reloadStaffId && reloadStaffId !== '0') {
                $('#schoolhead').val(reloadStaffId);
                loadHeadSignature(reloadStaffId);
            }

            if (reloadClassId && reloadClassId !== '0') {
                $('#classid').val(reloadClassId);
            }

            if (reloadStaffId && reloadStaffId !== '0' && reloadClassId && reloadClassId !== '0') {
                refreshDefaultCommentState();
            } else {
                syncHiddenInputs(reloadStaffId || '0', reloadClassId, $('#resultType').val());
            }
		});

		$('body').on('click','#editbtn',function(){

		    $('#displayform').html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing');
			var id = $(this).data('id');

			$.ajax({
                url: '../../../phpscript/get-modalcont.php',
                method:'POST',
                data: 'id=' + id,
                success: function(data) {
                    $('#displayform').html(data);

                }
            });
		});

		$("body").on("click", "#delbtn", function(){

            var id = $(this).data('id');

            $('#comid').val(id);

        });
        if ( window.history.replaceState ) {
          window.history.replaceState( null, null, window.location.href );
        }





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
