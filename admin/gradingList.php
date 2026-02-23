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
    <title>Grading List</title>

    <?php include('../layout/style.php'); ?>

</head>



<body style="background: rgb(236, 234, 234);">



    <div class="menu-wrapper">
        <div class="sidebar-header">
            <?php include('../layout/sidebar.php'); ?>

            <div class="backdrop"></div>

            <div class="content">

                <?php include('../layout/header.php'); ?>

                <div class="content-data">

                    <div class="row" style="margin: 15px;">
                        <div class="col-sm-4">
                            <div class="form_table">
                                <h3 style="margin-bottom: 50px;">Add Grade List</h3>

                                <form>

                                    <div class="form-group">
                                        <label for="examType">Grading Title</label>
                                        <input class="form-control" id="gradingtitle">

                                        <input type="hidden" id="gradingtitleold">

                                    </div>

                                    <div class="form-group">
                                        <select class="form-control" id="gradeNumber">
                                            <option>Select Grade Number</option>
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
                                            <option value="16">16</option>
                                            <option value="17">17</option>
                                            <option value="18">18</option>
                                            <option value="19">19</option>
                                            <option value="20">20</option>
                                        </select>
                                    </div>

                                    <div id="errmsgnew">

                                    </div>
                                    <div id="newgradeline">

                                    </div>

                                    <div class="form-group form-check">
                                        <input type="checkbox" class="form-check-input" id="isMidterm">
                                        <label class="form-check-label" for="isMidterm">Mid Term Grading List</label>
                                    </div>

                                    <button type="button" class="btn btn-primary" style="margin-top: 30px;" id="submitsubbtn">Submit</button>
                                </form>
                            </div>

                        </div>

                        <div class="col-sm-8">

                            <div class="table-responsive data_table">
                                <h3 style="margin-bottom: 50px;">Grade List</h3>

                                <div class="table-responsive">
                                    <table class="table table-striped" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Grading Title</th>
                                                <th>Type</th>
                                                <th>Classes Assigned</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php

                                            $sqlgradingstructure = "SELECT DISTINCT GradingTitle FROM `gradingstructure`";
                                            $resultgradingstructure = mysqli_query($link, $sqlgradingstructure);
                                            $rowgradingstructure = mysqli_fetch_assoc($resultgradingstructure);
                                            $row_cntgradingstructure = mysqli_num_rows($resultgradingstructure);

                                            if ($row_cntgradingstructure > 0) {
                                                do {
                                                    $GradingTitle = $rowgradingstructure['GradingTitle'];

                                                    $sqlgradingcnt = "SELECT * FROM `gradingstructure` WHERE GradingTitle = '$GradingTitle'";
                                                    $resultgradingcnt = mysqli_query($link, $sqlgradingcnt);
                                                    $rowgradingcnt = mysqli_fetch_assoc($resultgradingcnt);
                                                    $row_cntgradingcnt = mysqli_num_rows($resultgradingcnt);

                                                    $sqlassigngradingtclass = "SELECT * FROM `assigngradingtclass` WHERE GradingTitle = '$GradingTitle'";
                                                    $resultassigngradingtclass = mysqli_query($link, $sqlassigngradingtclass);
                                                    $rowassigngradingtclass = mysqli_fetch_assoc($resultassigngradingtclass);
                                                    $row_cntassigngradingtclass = mysqli_num_rows($resultassigngradingtclass);

                                                    echo '<tr>
                                                                    <th rowspan="' . $row_cntcnt . '" style="vertical-align: middle; font-weight: 400;">' . $rowgradingstructure['GradingTitle'] . '</th>
                                                                    <td>' . $rowgradingstructure['Type'] . '</td>
                                                                    <td>' . $row_cntassigngradingtclass . '</td>
                                                                    <td>
                                    									<a href="#" style="color: blue;" data-toggle="modal" data-target="#exampleModal" data-toggle="tooltip" aria-hidden="true" data-id="' . $rowgradingstructure['GradingTitle'] . '" data-type="' . $rowgradingstructure['Type'] . '" id="addstudentbtn"><i class="fa fa-tag" title="Assign/View Class"></i></a>&nbsp;&nbsp;&nbsp;
                                                                        <a href="#" style="color: #000000;" data-id="' . $GradingTitle . '" data-canum="' . $row_cntgradingcnt . '" data-type="' . $rowgradingstructure['Type'] . '" id="editbtn"><i class="fa fa-pencil" aria-hidden="true"></i></a>&nbsp;&nbsp;&nbsp;
                                                                        <a href="#" style="color: #ff0000;" title="Delete" data-toggle="modal" data-target="#deleteModal" data-toggle="tooltip" aria-hidden="true" data-id="' . $rowgradingstructure['GradingTitle'] . '" id="deletebtn"><i class="fa fa-trash" aria-hidden="true"></i></a>
                                                                    </td>
                                                                </tr>';
                                                } while ($rowgradingstructure = mysqli_fetch_assoc($resultgradingstructure));
                                            } else {
                                                echo '<tr>
                                                            <td colspan="12">No Records Found</td>
                                                        </tr>';
                                            }
                                            ?>

                                        </tbody>

                                    </table>
                                </div>
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
                                    <div id="successmsg"></div>
                                    <span style="font-size: 18px; font-weight: 500;">Are you sure you want to Delete this? <br>
                                        Please note that this action cannot be reversed!!!</span>

                                    <input type="hidden" id="methIDinput">

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

                        <div class="modal-dialog modal-lg">

                            <div class="modal-content">

                                <div class="modal-header">
                                    <h3 style="margin-left: 35%;" class="modal-title" id="exampleModalLabel">Assign Grading to Class</h3>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>

                                <div class="modal-body">
                                    <form>
                                        <input type="hidden" id="GradingType" name="GradingType">

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
        $("body").on('change', '#gradeNumber', function() {

            $('#newgradeline').html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing');

            var gradeNumber = $('#gradeNumber').val();
            var gradingtitle = $('#gradingtitle').val();

            $.ajax({
                url: '../../../phpscript/get-grade-inputs.php',
                method: 'POST',
                data: 'gradeNumber=' + gradeNumber + '&gradingtitle=' + gradingtitle,
                success: function(data) {
                    $('#newgradeline').html(data);

                }
            });

        });

        $("body").on("click", "#submitsubbtn", function() {

            $(this).html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing')
            var grade = [];
            $.each($(".grade"), function() {
                grade.push($(this).val());
            });

            var gradefrom = [];
            $.each($(".gradefrom"), function() {
                gradefrom.push($(this).val());
            });

            var gradeto = [];
            $.each($(".gradeto"), function() {
                gradeto.push($(this).val());
            });

            var graderemark = [];
            $.each($(".graderemark"), function() {
                graderemark.push($(this).val());
            });

            var gradingtitle = $('#gradingtitle').val();

            var gradingtitleold = $('#gradingtitleold').val();

            var type = $('#isMidterm').is(':checked') ? 'midterm' : 'term';

            if (gradingtitle != '' && gradingtitle != '0' && grade.length > 0 && gradefrom.length == grade.length && gradeto.length == grade.length && graderemark.length == grade.length) {

                var dataString = 'gradingtitle=' + gradingtitle + '&gradingtitleold=' + gradingtitleold + '&grade=' + grade + '&gradefrom=' + gradefrom + '&gradeto=' + gradeto + '&graderemark=' + graderemark;

                // alert(dataString);
                $.ajax({
                    url: '../../../phpscript/insert-gradingstructure.php',
                    method: 'POST',
                    data: {
                        gradingtitle: gradingtitle,
                        gradingtitleold: gradingtitleold,
                        grade: grade,
                        gradefrom: gradefrom,
                        gradeto: gradeto,
                        graderemark: graderemark,
                        type: type
                    },
                    success: function(maindata2) {

                        $('#errmsgnew').html(maindata2);
                        window.scrollTo(0, 0);
                        $('#submitsubbtn').html('Submit');

                        setTimeout(function() {
                            location.reload();
                        }, 5000);

                    }
                });
            } else {
                $('#errmsgnew').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
                window.scrollTo(0, 0);
                $('#submitsubbtn').html('Save changes');
            }

        });

        $("body").on("click", "#editbtn", function() {

            var gradingtitle = $(this).data('id');

            var gradeNumber = $(this).data('canum');

            var type = $(this).data('type');

            $('#gradingtitle').val(gradingtitle);

            $('#gradingtitleold').val(gradingtitle);

            $('#gradeNumber').val(gradeNumber);

            if (type == 'midterm') {
                $('#isMidterm').prop('checked', true);
            } else {
                $('#isMidterm').prop('checked', false);
            }

            // alert(examID);

            $('#newgradeline').html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing');

            $.ajax({
                url: '../../../phpscript/get-grade-inputs.php',
                method: 'POST',
                data: 'gradeNumber=' + gradeNumber + '&gradingtitle=' + gradingtitle,
                success: function(data) {
                    $('#newgradeline').html(data);

                }
            });
        });

        $("body").on("click", "#deletebtn", function() {

            var methID = $(this).data('id');

            $('#methIDinput').val(methID);

        });

        $("body").on("click", "#proceeddelete", function() {

            $(this).html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing');

            var methID = $('#methIDinput').val();

            $.ajax({
                url: '../../../phpscript/deletegradingmeth.php',
                method: 'POST',
                data: 'methID=' + methID,
                success: function(data) {
                    $('#successmsg').html(data);
                    $("#proceeddelete").html('<i class="fa fa-check"></i> Submit');


                    setTimeout(function() {
                        location.reload();
                    }, 5000);

                }
            });

        });

        $("body").on("click", "#addstudentbtn", function() {

            $('#displayclasses').html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing');

            var GradingTitle = $(this).data('id');

            var GradingType = $(this).data('type');

            var dataString = 'GradingTitle=' + GradingTitle;

            $('#GradingType').val(GradingType);

            // alert(dataString);
            $.ajax({
                url: '../../../phpscript/get-class-grading.php',
                method: 'POST',
                data: dataString,

                success: function(maindata2) {

                    $('#displayclasses').html(maindata2);

                }
            });
        });

        $("body").on("click", "#submitstudentbtn", function() {

            $(this).html('<i class="fa fa-circle-o-notch fa-spin"></i> ...Processing');

            var classid = [];
            $.each($("input[name='chkBoc']:checked"), function() {
                classid.push($(this).val());
            });

            var GradingTitleid = $('#GradingTitleid').val();

            if (GradingTitleid != '' && GradingTitleid != '0' && classid.length > 0) {
                var dataString = 'GradingTitleid=' + GradingTitleid + '&classid=' + classid;

                // alert(dataString);
                $.ajax({
                    url: '../../../phpscript/insert-studentgrade.php',
                    method: 'POST',
                    data: dataString,

                    success: function(maindata2) {

                        $('#errmsgnewstudent').html(maindata2);
                        window.scrollTo(0, 0);
                        $('#submitstudentbtn').html('Save changes');

                        setTimeout(function() {
                            location.reload();
                        }, 5000);

                    }
                });
            } else {
                $('#errmsgnew').html('<div class="alert alert-primary" role="alert"> No Field Should Empty!</div>');
                window.scrollTo(0, 0);
                $('#submitstudentbtn').html('Save changes');
            }

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