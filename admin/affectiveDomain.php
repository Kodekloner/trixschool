<?php include('../database/config.php'); ?>
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
    <title>Affective Domain Scores</title>

    <style type="text/css">
        .editbox {

            display: none
        }

        .editbox {
            font-size: 14px;
            width: 30px;
            background-color: #FFFFFF;
            border: solid 1px #FFFFFF;
            padding: 5px 5px;
        }

        .edit_tr:hover {
            background-color: #80C8E5;
            cursor: pointer;
        }
    </style>
</head>

<?php include('../layout/style.php'); ?>

<body style="background: rgb(236, 234, 234);">


    <div class="menu-wrapper">
        <div class="sidebar-header">
            <?php include('../layout/sidebar.php'); ?>

            <div class="backdrop"></div>

            <div class="content">

                <?php include('../layout/header.php'); ?>

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

                                            if ($row_cntsessions > 0) {
                                                do {

                                                    echo '<option value="' . $rowsessions['id'] . '">' . $rowsessions['session'] . '</option>';
                                                } while ($rowsessions = mysqli_fetch_assoc($resultsessions));
                                            }
                                            ?>
                                        </select>
                                        <!--They would need to select Session in-order to pick a term-->
                                    </div>

                                    <div class="form-group col-sm">
                                        <select class="form-control" id="term">
                                            <option value="0">Select Term</option>
                                            <option value="1st">1st Term</option>
                                            <option value="2nd">2nd Term</option>
                                            <option value="3rd">3rd Term</option>
                                        </select>
                                        <!--They would need to select Term in-order to display the Exam Group Created for that term-->
                                    </div>

                                    <div class="form-group col-sm">
                                        <select class="form-control" id="class">
                                            <option value="0">Class</option>
                                            <?php

                                            $sqlstaffcheck = "SELECT * FROM `staff_roles` INNER JOIN roles ON staff_roles.role_id=roles.id WHERE staff_roles.staff_id='$id'";
                                            $resultstaffcheck = mysqli_query($link, $sqlstaffcheck);
                                            $rowstaffcheck = mysqli_fetch_assoc($resultstaffcheck);
                                            $row_cntstaffcheck = mysqli_num_rows($resultstaffcheck);

                                            if ($row_cntstaffcheck > 0) {
                                                if ($rowstaffcheck['name'] == 'Teacher') {

                                                    // Escape the $staff_id to prevent SQL injection
                                                    $staff_id = mysqli_real_escape_string($link, $id);

                                                    // 1. Query to get a concatenated list of class IDs for the given staff member
                                                    $sql1 = "SELECT GROUP_CONCAT(ct.class_id) AS c 
                                                            FROM class_teacher ct 
                                                            WHERE ct.staff_id = '$staff_id' 
                                                            GROUP BY ct.staff_id";

                                                    // Execute the query
                                                    $result1 = mysqli_query($link, $sql1);

                                                    // Initialize variable to store the concatenated class IDs
                                                    $class_ides = '';
                                                    if ($result1) {
                                                        $row1 = mysqli_fetch_assoc($result1);
                                                        if ($row1 && !empty($row1['c'])) {
                                                            $class_ides = $row1['c'];
                                                        }
                                                    }

                                                    // 2. Clean up the class IDs: split the string into an array, remove empty values,
                                                    // and then join them back into a clean comma-separated string.
                                                    $ides11 = array();
                                                    $ides1 = '';
                                                    if (!empty($class_ides)) {
                                                        $ides = explode(',', $class_ides);
                                                        foreach ($ides as $value) {
                                                            if (trim($value) !== '') {
                                                                $ides11[] = $value;
                                                            }
                                                        }
                                                        $ides1 = implode(',', $ides11);
                                                    }

                                                    // 3. If there are valid class IDs, query the 'classes' table to get the class details.
                                                    $data = array();
                                                    if (!empty($ides1)) {
                                                        // Convert IDs to integers for safety and rebuild the string
                                                        $ids = array_map('intval', explode(',', $ides1));
                                                        $ids_string = implode(',', $ids);

                                                        $sql2 = "SELECT * FROM classes WHERE id IN ($ids_string)";
                                                        $result2 = mysqli_query($link, $sql2);

                                                        if ($result2) {
                                                            while ($rowclasses = mysqli_fetch_assoc($result2)) {
                                                                echo '<option value="' . $rowclasses['id'] . '">' . $rowclasses['class'] . '</option>';
                                                            }
                                                        } else {
                                                            echo '<option value="0">No Records Found</option>';
                                                        }
                                                    }
                                                    // $sqlclasses = "SELECT DISTINCT(subjecttables.class_id),class FROM `subjecttables` INNER JOIN class_sections ON subjecttables.class_id=class_sections.class_id INNER JOIN classes ON class_sections.class_id=classes.id INNER JOIN assigncatoclass ON classes.id=assigncatoclass.ClassID AND subjecttables.staff_id = '$id' ORDER BY class";
                                                    // $resultclasses = mysqli_query($link, $sqlclasses);
                                                    // $rowclasses = mysqli_fetch_assoc($resultclasses);
                                                    // $row_cntclasses = mysqli_num_rows($resultclasses);
                                                } else {
                                                    $sqlclasses = "SELECT * FROM `classes` INNER JOIN assigncatoclass ON classes.id=assigncatoclass.ClassID ORDER BY class";
                                                    $resultclasses = mysqli_query($link, $sqlclasses);
                                                    $rowclasses = mysqli_fetch_assoc($resultclasses);
                                                    $row_cntclasses = mysqli_num_rows($resultclasses);

                                                    if ($row_cntclasses > 0) {
                                                        do {

                                                            echo '<option value="' . $rowclasses['ClassID'] . '">' . $rowclasses['class'] . '</option>';
                                                        } while ($rowclasses = mysqli_fetch_assoc($resultclasses));
                                                    }
                                                }
                                            } else {

                                                echo '<option value="0">No Records Found</option>';
                                            }

                                            ?>
                                        </select>
                                        <!--They would need to select class and section in-order to display the Exam Names assigned to a particular class-->
                                    </div>

                                    <div class="form-group col-sm">
                                        <select class="form-control" id="classsection">
                                            <option value="0">Section</option>
                                        </select>
                                    </div>
                                    <!--<div class="form-group col-sm">-->
                                    <!--	<select class="form-control" id="affectivedomain">-->
                                    <!--		<option value="0">Affective Domain</option>-->
                                    <!--	</select>-->
                                    <!--</div>-->
                                    <div class="col-md-12" align="right">
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

                                <!--<div class="col-sm-3 col-md-3">-->

                                <!--	<div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">-->
                                <!--		<a class="nav-link " style="font-weight: 600;" id="v-pills-home-tab" data-toggle="pill" href="#v-pills-home" role="tab" aria-controls="v-pills-home" aria-selected="true">Download Class list (offline)</a>-->
                                <!--		<a class="nav-link"  style="font-weight: 600;" id="v-pills-messages-tab" data-toggle="pill" href="#v-pills-messages" role="tab" aria-controls="v-pills-messages" aria-selected="false">Bulk Upload (offline)</a>-->
                                <!--		<a class="nav-link active"  style="font-weight: 600;" id="v-pills-profile-tab" data-toggle="pill" href="#v-pills-profile" role="tab" aria-controls="v-pills-profile" aria-selected="true">Online Computation</a>-->
                                <!--	</div>-->

                                <!--</div>-->

                                <div class="col-sm-12 col-md-12 cardBoxSty">
                                    <!--<div class="tab-content" id="v-pills-tabContent">-->


                                    <!--================Download Class list=================-->
                                    <!--================Download Class list=================-->
                                    <!--<div class="tab-pane fade show" id="v-pills-home" role="tabpanel" aria-labelledby="v-pills-home-tab">-->

                                    <!--	<div class="row">-->
                                    <!--		<div class="col-12">-->
                                    <!--			<div class="card">-->
                                    <!--				<div class="card-body">-->

                                    <!--					<h3 style="color: black;">Download Class Exam List</h3>-->

                                    <!--					<div class="row" style="margin-top: 20px;">-->

                                    <!--						<div class="col-md-7">-->

                                    <!--						</div>-->

                                    <!--						<div class="col-md-3">-->
                                    <!--							<form>-->
                                    <!--								<div class="form-group col-sm">-->
                                    <!--									<select class="form-control">-->
                                    <!--										<option>Exam Names</option>-->
                                    <!--										<option>1st CA</option>-->
                                    <!--										<option>2nd CA</option>-->
                                    <!--										<option>3rd CA</option>-->
                                    <!--										<option>Class Exam</option>-->
                                    <!--									</select>-->
                                    <!--								</div>-->
                                    <!--							</form>-->
                                    <!--						</div>-->

                                    <!--						<div class="col-md-2">-->
                                    <!--							<button type="button" class="btn btn-primary" style="border-radius: 20px;">-->
                                    <!--								<i class="fa fa-search" aria-hidden="true"></i>-->
                                    <!--								<span style="font-weight: 500;">Load</span>-->
                                    <!--							</button>-->
                                    <!--						</div>-->
                                    <!--					</div>-->


                                    <!--						<div class="table-responsive" style="margin-top: 50px;">-->
                                    <!--							<table class="table display table-striped">-->
                                    <!--								<thead>-->
                                    <!--									<tr>-->

                                    <!--										<th>Subject</th>-->
                                    <!--										<th>Download Link</th>-->

                                    <!--									</tr>-->
                                    <!--								</thead>-->
                                    <!--								<tbody>-->
                                    <!--									<tr>-->
                                    <!--										<td>English Language</td>-->
                                    <!--										<td><a href="#">Download Link</a></td>-->

                                    <!--									</tr>-->
                                    <!--									<tr>-->
                                    <!--										<td>Maths</td>-->
                                    <!--										<td><a href="#">Download Link</a></td>-->

                                    <!--									</tr>-->
                                    <!--									<tr>-->
                                    <!--										<td>Computer</td>-->
                                    <!--										<td><a href="#">Download Link</a></td>-->

                                    <!--									</tr>-->
                                    <!--									<tr>-->
                                    <!--										<td>Civil Education</td>-->
                                    <!--										<td><a href="#">Download Link</a></td>-->

                                    <!--									</tr>-->
                                    <!--									<tr>-->
                                    <!--										<td>Agricultural</td>-->
                                    <!--										<td><a href="#">Download Link</a></td>-->

                                    <!--									</tr>-->

                                    <!--								</tbody>-->
                                    <!--							</table>-->
                                    <!--						</div>-->

                                    <!--				</div>-->
                                    <!--			</div>-->
                                    <!--		</div>-->
                                    <!--	</div>-->

                                    <!--</div>-->
                                    <!--================Download Class list=================-->
                                    <!--================Download Class list=================-->


                                    <!--=================Bulk Upload==============-->
                                    <!--==================================================-->
                                    <!--<div class="tab-pane fade" id="v-pills-messages" role="tabpanel" aria-labelledby="v-pills-messages-tab">-->
                                    <!--	<div class="row">-->
                                    <!--		<div class="col-12">-->
                                    <!--			<div class="card">-->
                                    <!--				<div class="card-body">-->

                                    <!--					<h3>Bulk Upload from CSV file</h3>-->

                                    <!--					<div class="row" style="margin-top: 20px;">-->

                                    <!--						<div class="col-md-7">-->

                                    <!--						</div>-->

                                    <!--						<div class="col-md-3">-->
                                    <!--							<form>-->
                                    <!--								<div class="form-group col-sm">-->
                                    <!--									<select class="form-control">-->
                                    <!--										<option>Exam Names</option>-->
                                    <!--										<option>1st CA</option>-->
                                    <!--										<option>2nd CA</option>-->
                                    <!--										<option>3rd CA</option>-->
                                    <!--										<option>Class Exam</option>-->
                                    <!--									</select>-->
                                    <!--								</div>-->
                                    <!--							</form>-->
                                    <!--						</div>-->

                                    <!--						<div class="col-md-2">-->
                                    <!--							<button type="button" class="btn btn-primary" style="border-radius: 20px;">-->
                                    <!--								<i class="fa fa-search" aria-hidden="true"></i>-->
                                    <!--								<span style="font-weight: 500;">Load</span>-->
                                    <!--							</button>-->
                                    <!--						</div>-->
                                    <!--					</div>-->



                                    <!--					<form style="margin-top: 50px; padding: 20px;">                                          -->

                                    <!--						<div class="form-group">-->
                                    <!--							<label>English Language:</label>-->
                                    <!--							<input type="file" class="form-control" id="exampleInputFile" aria-describedby="fileHelp">-->
                                    <!--						</div>-->

                                    <!--						<div class="form-group">-->
                                    <!--							<label>Maths:</label>-->
                                    <!--							<input type="file" class="form-control" id="exampleInputFile" aria-describedby="fileHelp">-->
                                    <!--						</div>-->

                                    <!--						<div class="form-group">-->
                                    <!--							<label>Civil Education:</label>-->
                                    <!--							<input type="file" class="form-control" id="exampleInputFile" aria-describedby="fileHelp">-->
                                    <!--						</div>-->

                                    <!--						<div class="form-group">-->
                                    <!--							<label>History:</label>-->
                                    <!--							<input type="file" class="form-control" id="exampleInputFile" aria-describedby="fileHelp">-->
                                    <!--						</div>-->

                                    <!--						<div class="form-group">-->
                                    <!--							<label>Computer:</label>-->
                                    <!--							<input type="file" class="form-control" id="exampleInputFile" aria-describedby="fileHelp">-->
                                    <!--						</div>-->

                                    <!--						<div class="form-group">-->
                                    <!--							<label>Economic:</label>-->
                                    <!--							<input type="file" class="form-control" id="exampleInputFile" aria-describedby="fileHelp">-->
                                    <!--						</div>-->

                                    <!--						<button type="submit" class="btn btn-primary" style="font-weight: 500; border-radius: 20px; margin-top: 20px;">Upload CSV Files</button>-->

                                    <!--					</form>-->

                                    <!--				</div>-->
                                    <!--			</div>-->
                                    <!--		</div>-->
                                    <!--	</div>-->

                                    <!--</div>-->
                                    <!--=================Bulk Upload==============-->
                                    <!--==================================================-->


                                    <!--==================================================-->
                                    <!--Online Computation-->
                                    <!--<div class="tab-pane  active" id="v-pills-profile" role="tabpanel" aria-labelledby="v-pills-profile-tab"> -->
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-body">

                                                    <h3 class="card-title">Online Result Affective Domain Computation</h3>

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
                                    <!--Online Computation-->
                                    <!--==================================================-->

                                    <!--</div>-->
                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- Modal -->
                    <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">

                                    <h3 class="modal-title" id="exampleModalLabel" style="margin-left: 35%; color: #ff0000;">Warning <i class="fa fa-exclamation-triangle"></i></h3>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body" align="center">
                                    <span style="font-size: 18px; font-weight: 500;">Are you sure you want to submit this Result now. <br>
                                        Please note this action cannot be reversed!!!</span>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-primary"><i class="fa fa-check"></i> Submit Result</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Modal -->

                    <!-- Delet Single-->
                    <div class="modal fade" id="delScore" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-md" role="document">
                            <div align="center" class="modal-content">

                                <form>
                                    <div class="modal-body">

                                        <span id="CompleteScoreDeleteOutput"></span>

                                        <div id="displayScoreDelMsg">
                                            <div align="center">Loading...</div>
                                        </div>
                                    </div>

                                    <div style="margin:auto; padding-bottom: 15px;">

                                        <button type="button" class="btn btn-info" data-dismiss="modal">Cancel</button>
                                        <button id="ProcToDelSelScore" type="button" class="btn btn-danger ProcToDelAllSelScore">Yes! Delete</button>
                                    </div>

                                </form>
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
        $("body").on("change", "#class", function() {

            $('#classsection').html('<option value="0">Loading...</option>');

            var classid = $(this).val();

            var staffid = "<?php echo $id; ?>";

            var rolefirst = "<?php echo $rolefirst; ?>";

            var session = $("#session").val();

            var term = $("#term").val();

            if (classid != '' && classid != '0') {

                var dataString = 'classid=' + classid + '&staffid=' + staffid + '&rolefirst=' + rolefirst;

                // alert(dataString);
                $.ajax({
                    url: '../../../phpscript/get-class-section-other.php',
                    method: 'POST',
                    data: dataString,

                    success: function(maindata2) {

                        $('#classsection').html(maindata2);

                    }
                });
            } else {
                $('#classsection').html('<option value="0">Please select class</option>');

            }

        });


        $("body").on("click", "#getstud", function() {

            $('#tbl_data').html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing');

            var classsectionactual = $("#classsection").val();

            var classid = $("#class").val();

            var session = $("#session").val();

            var term = $("#term").val();

            var dataString = 'classid=' + classid + '&classsectionactual=' + classsectionactual + '&session=' + session + '&term=' + term;

            //alert(dataString);
            if (classid != '' && classid != '0' && classsectionactual != '' && classsectionactual != '0' && session != '' && session != '0' && term != '' && term != '0') {

                $.ajax({
                    url: '../../../phpscript/affectivedomain/insert-studentsaffectivedomain.php',
                    method: 'POST',
                    data: dataString,

                    success: function(maindata2) {

                        // alert(maindata2);

                        $.ajax({
                            url: '../../../phpscript/affectivedomain/get-affectivedomainstudents.php',
                            method: 'POST',
                            data: dataString,

                            success: function(maindata3) {

                                $('#tbl_data').html(maindata3);

                            }
                        });

                    }
                });
            } else {
                $('#tbl_data').html('Please filter and select class section to view student list');

            }

        });

        $(document).on('click', '.edit_tr', function() {

            //get the unique ID of the row
            var ID = $(this).attr('id');

            //Hide all the label
            $("#ca1_" + ID).hide();
            $("#ca2_" + ID).hide();
            $("#ca3_" + ID).hide();
            $("#ca4_" + ID).hide();
            $("#ca5_" + ID).hide();
            $("#ca6_" + ID).hide();
            $("#ca7_" + ID).hide();
            $("#ca8_" + ID).hide();
            $("#ca9_" + ID).hide();
            $("#ca10_" + ID).hide();
            $("#ca11_" + ID).hide();
            $("#ca12_" + ID).hide();
            $("#ca13_" + ID).hide();
            $("#ca14_" + ID).hide();
            $("#ca15_" + ID).hide();

            //Show all the input box
            $("#ca1_input_" + ID).fadeIn(1000);
            $("#ca2_input_" + ID).fadeIn(1000);
            $("#ca3_input_" + ID).fadeIn(1000);
            $("#ca4_input_" + ID).fadeIn(1000);
            $("#ca5_input_" + ID).fadeIn(1000);
            $("#ca6_input_" + ID).fadeIn(1000);
            $("#ca7_input_" + ID).fadeIn(1000);
            $("#ca8_input_" + ID).fadeIn(1000);
            $("#ca9_input_" + ID).fadeIn(1000);
            $("#ca10_input_" + ID).fadeIn(1000);
            $("#ca11_input_" + ID).fadeIn(1000);
            $("#ca12_input_" + ID).fadeIn(1000);
            $("#ca13_input_" + ID).fadeIn(1000);
            $("#ca14_input_" + ID).fadeIn(1000);
            $("#ca15_input_" + ID).fadeIn(1000);


        });


        $(document).on('change', '.edit_tr', function() {
            var ID = $(this).attr('id');

            var ca1 = parseFloat($("#ca1_input_" + ID).val());
            var ca2 = parseFloat($("#ca2_input_" + ID).val());
            var ca3 = parseFloat($("#ca3_input_" + ID).val());
            var ca4 = parseFloat($("#ca4_input_" + ID).val());
            var ca5 = parseFloat($("#ca5_input_" + ID).val());
            var ca6 = parseFloat($("#ca6_input_" + ID).val());
            var ca7 = parseFloat($("#ca7_input_" + ID).val());
            var ca8 = parseFloat($("#ca8_input_" + ID).val());
            var ca9 = parseFloat($("#ca9_input_" + ID).val());
            var ca10 = parseFloat($("#ca10_input_" + ID).val());
            var ca11 = parseFloat($("#ca11_input_" + ID).val());
            var ca12 = parseFloat($("#ca12_input_" + ID).val());
            var ca13 = parseFloat($("#ca13_input_" + ID).val());
            var ca14 = parseFloat($("#ca14_input_" + ID).val());
            var ca15 = parseFloat($("#ca15_input_" + ID).val());

            var studname = $("#studname_" + ID).val();

            var term = $("#term").val();

            var session = $("#session").val();

            var dataString = 'ID=' + ID + '&ca1=' + ca1 + '&ca2=' + ca2 + '&ca3=' + ca3 + '&ca4=' + ca4 + '&ca5=' + ca5 + '&ca6=' + ca6 + '&ca7=' + ca7 + '&ca8=' + ca8 + '&ca9=' + ca9 + '&ca10=' + ca10 + '&ca11=' + ca11 + '&ca12=' + ca12 + '&ca13=' + ca13 + '&ca14=' + ca14 + '&ca15=' + ca15 + '&term=' + term + '&session=' + session;
            //$("#ca1_"+ID).html('>>>'); // Loading image

            // alert(dataString);
            $('#displaysmg').html('<div class="alert alert-primary" role="alert"> To input scores kindly click on the CA or Exam That you would like to input the score and input the score.</div>');

            $.ajax({
                type: "POST",
                url: "../../../phpscript/affectivedomain/updateStudentaffectivedomainTable.php",
                data: dataString,
                cache: false,
                success: function(result) {
                    $("#ca1_" + ID).html(ca1);
                    $("#ca2_" + ID).html(ca2);
                    $("#ca3_" + ID).html(ca3);
                    $("#ca4_" + ID).html(ca4);
                    $("#ca5_" + ID).html(ca5);
                    $("#ca6_" + ID).html(ca6);
                    $("#ca7_" + ID).html(ca7);
                    $("#ca8_" + ID).html(ca8);
                    $("#ca9_" + ID).html(ca9);
                    $("#ca10_" + ID).html(ca10);
                    $("#ca11_" + ID).html(ca11);
                    $("#ca12_" + ID).html(ca12);
                    $("#ca13_" + ID).html(ca13);
                    $("#ca14_" + ID).html(ca14);
                    $("#ca15_" + ID).html(ca15);

                }
            });

        });

        // Edit input box click action
        $(document).on('mouseup', '.editbox', function() {
            return false
        });


        // Outside click action
        $(document).mouseup(function() {
            $(".editbox").hide();
            $(".text").show();
        });

        $('body').on('click', '#delbtn', function() {
            var ScoreID = $(this).data('id');

            var studname = $(this).data('name');

            var dataString = '&ScoreID=' + ScoreID + '&studname=' + studname;
            $.ajax({
                type: "POST",
                url: "../../../phpscript/affectivedomain/loadSingleScoreDelPrompt.php",
                data: dataString,
                cache: false,

                success: function(result) {

                    $('#displayScoreDelMsg').html(result);
                }
            });

        });
        $('#desktop').click(function() {

            $('li a').toggleClass('hideMenuList');
            $('.sidebar').toggleClass('changeWidth');
        })



        $('#mobile').click(function() {

            $('.sidebar').toggleClass('showMenu');
            $('.backdrop').toggleClass('showBackdrop');
        })


        $('.cross-icon').click(function() {

            $('.sidebar').toggleClass('showMenu');
            $('.backdrop').removeClass('showBackdrop');
        })

        $('.backdrop').click(function() {

            $('.sidebar').removeClass('showMenu');
            $('.backdrop').removeClass('showBackdrop');
        })

        $('li').click(function() {
            $('li').removeClass();
            $(this).addclass('selected');
            $('.sideBar').removeClass('showMenu');
        })

        $(document).ready(function() {

            $("#ProcToDelSelScore").click(function() {

                $('#ProcToDelSelScore').html('Removing...<i class="fa fa-spinner fa-spin"></i>');

                var selDeleteID = $('#selDeleteID').val();

                var dataString = '&selDeleteID=' + selDeleteID;

                $.ajax({
                    type: "POST",
                    url: "../../../phpscript/affectivedomain/ProceedToDelSingleScore.php",
                    data: dataString,
                    cache: false,

                    success: function(result) {
                        $('#CompleteScoreDeleteOutput').html(result);

                        $('#tbl_data').html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing');

                        var classsection = $("#classsection").val();

                        var classsectionactual = $('#classsection :selected').data('id');

                        var classid = $("#class").val();

                        var session = $("#session").val();

                        var term = $("#term").val();

                        if (classid != '' && classid != '0' && classsection != '' && classsection != '0' && session != '' && session != '0' && term != '' && term != '0') {
                            var dataString = 'classid=' + classid + '&classsection=' + classsection + '&session=' + session + '&term=' + term + '&classsectionactual=' + classsectionactual;

                            // alert(dataString);
                            $.ajax({
                                url: '../../../phpscript/affectivedomain/insert-studentsaffectivedomain.php',
                                method: 'POST',
                                data: dataString,

                                success: function(maindata2) {

                                    // alert(maindata2);

                                    $.ajax({
                                        url: '../../../phpscript/affectivedomain/get-affectivedomainstudents.php',
                                        method: 'POST',
                                        data: dataString,

                                        success: function(maindata3) {

                                            $('#tbl_data').html(maindata3);

                                        }
                                    });

                                }
                            });
                        } else {
                            $('#tbl_data').html('Please filter and select subject to view student list');

                        }
                        //location.reload();   
                        $('#ProcToDelSelScore').html('Yes! Delete');
                        // $('#ProcToDelSelStaff').attr('disabled', true);                                    
                    }
                });
            });

        });
    </script>
</body>

</html>