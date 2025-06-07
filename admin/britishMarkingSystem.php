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
    <title>Mark Examination</title>

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
                                            <option>Select Term</option>
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

                                                    $sqlclasses = "SELECT DISTINCT(subjecttables.class_id),class FROM `subjecttables` INNER JOIN class_sections ON subjecttables.class_id=class_sections.class_id INNER JOIN classes ON class_sections.class_id=classes.id INNER JOIN assigncatoclass ON classes.id=assigncatoclass.ClassID AND assigncatoclass.ResultType = 'british' AND subjecttables.staff_id = '$id' ORDER BY class";

                                                    $resultclasses = mysqli_query($link, $sqlclasses);
                                                    $rowclasses = mysqli_fetch_assoc($resultclasses);
                                                    $row_cntclasses = mysqli_num_rows($resultclasses);

                                                    if ($row_cntclasses > 0) {
                                                        do {

                                                            echo '<option value="' . $rowclasses['class_id'] . '">' . $rowclasses['class'] . '</option>';
                                                        } while ($rowclasses = mysqli_fetch_assoc($resultclasses));
                                                    } else {
                                                        echo '<option value="0">No Records Found</option>';
                                                    }
                                                } else {
                                                    $sqlclasses = "SELECT * FROM `classes` INNER JOIN assigncatoclass ON classes.id=assigncatoclass.ClassID AND assigncatoclass.ResultType = 'british' ORDER BY class";
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
                                    <div class="form-group col-sm">
                                        <select class="form-control" id="subjects">
                                            <option value="0">Subject</option>
                                        </select>
                                    </div>
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

                                <div class="col-sm-12 col-md-12 cardBoxSty">

                                    <!--==================================================-->
                                    <!--Online Computation-->
                                    <!--<div class="tab-pane  active" id="v-pills-profile" role="tabpanel" aria-labelledby="v-pills-profile-tab"> -->
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-body">

                                                    <h3 class="card-title">British Result Computation</h3>

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

            var classid = $(this).val();

            var staffid = "<?php echo $id; ?>";

            var rolefirst = "<?php echo $rolefirst; ?>";

            $('#classsection').html('<option value="0">Loading...</option>');

            if (classid != '' && classid != '0') {

                var dataString = 'classid=' + classid + '&staffid=' + staffid + '&rolefirst=' + rolefirst;

                // alert(dataString);
                $.ajax({
                    url: '../../../phpscript/get-class-section-compute-result.php',
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

        $("body").on("change", "#classsection", function() {

            $('#subjects').html('<option value="0">Loading...</option>');

            var classid = $("#class").val();

            var actualclasssection = $(this).val();

            var session = $("#session").val();

            var term = $("#term").val();

            var staffid = "<?php echo $id; ?>";

            // alert(actualclasssection);

            if (classid != '' && classid != '0' && session != '' && session != '0' && term != '' && term != '0' && actualclasssection != '' && actualclasssection != '0') {

                var dataString = 'classid=' + classid + '&session=' + session + '&term=' + term + '&actualclasssection=' + actualclasssection + '&staffid=' + staffid;

                // alert(dataString);
                $.ajax({
                    url: '../../../phpscript/get-subjects.php',
                    method: 'POST',
                    data: dataString,

                    success: function(maindata2) {

                        $('#subjects').html(maindata2);

                    }
                });
            } else {
                $('#subjects').html('<option value="0">Please filter to view subjects</option>');

            }

        });

        $("body").on("click", "#getstud", function() {

            $('#tbl_data').html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing');

            var subjects = $("#subjects").val();

            var classsection = $('#subjects :selected').data('id');

            var classsectionactual = $("#classsection").val();

            var classid = $("#class").val();

            var session = $("#session").val();

            var term = $("#term").val();

            var dataString = 'classid=' + classid + '&classsection=' + classsection + '&session=' + session + '&term=' + term + '&subjects=' + subjects + '&classsectionactual=' + classsectionactual;

            if (classid != '' && classid != '0' && classsection != '' && classsection != '0' && session != '' && session != '0' && term != '' && term != '0' && subjects != '' && subjects != '0') {
                // alert(dataString);
                $.ajax({
                    url: '../../../phpscript/insert-studentsscoretblbritish.php',
                    method: 'POST',
                    data: dataString,

                    success: function(maindata2) {

                        // alert(maindata2);

                        $.ajax({
                            url: '../../../phpscript/get-subjectsstudentsbritish.php',
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

        });

        $(document).on('input', '.britishfield', function() {

            var remarkID = $(this).data('id');

            var rowID = $(this).data('rowid');

            var extcomment = $(this).val();

            var remark = $("#" + remarkID).val();

            var dataString = 'rowID=' + rowID + '&extcomment=' + extcomment + '&remark=' + remark;

            $.ajax({
                type: "POST",
                url: "../../../phpscript/updateStudentScoreLiveTablebritish.php",
                data: dataString,
                cache: false,
                success: function(result) {

                }
            });

        });

        $(document).on('change', '.remarks', function() {

            var remarkID = $(this).data('extcom');

            var rowID = $(this).data('id');

            //var extcomment = $("#"+remarkID).val();
            var extcomment = $(this).data('extcom')

            var remark = $(this).val();

            var dataString = 'rowID=' + rowID + '&extcomment=' + extcomment + '&remark=' + remark;

            $.ajax({
                type: "POST",
                url: "../../../phpscript/updateStudentScoreLiveTablebritish.php",
                data: dataString,
                cache: false,
                success: function(result) {

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
    </script>
</body>

</html>