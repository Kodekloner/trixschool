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

    <!--The result stylesheet -->
    <link rel="stylesheet" href="../assets/css/resultStyleSheet.css">
    <title>Broad Sheet</title>

    <style>
        @media print {

            /* Reset any existing page margins */
            @page {
                size: A4 landscape;
                margin: 10mm;
            }

            /* Hide everything except the content we want to print */
            body * {
                visibility: hidden;
                background: rgb(236, 234, 234);
            }

            /* Show only the container and its contents */
            .container.rel,
            .container.rel * {
                visibility: visible;
            }

            /* Position the container at the top of the page */
            .container.rel {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 0;
                margin: 0;
            }

            /* Ensure table fits within page */
            .table-responsive {
                overflow: visible;
                width: 100%;
            }

            /* Adjust table for better print layout */
            .rotate-table-grid {
                width: 100%;
                border-collapse: collapse;
                page-break-inside: auto;
            }

            /* Handle row breaks */
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            /* Ensure headers appear on each page */
            thead {
                display: table-header-group;
            }

            /* Prevent orphaned table footers */
            tfoot {
                display: table-footer-group;
            }

            /* Hide print button when printing */
            .fa-print,
            a[onclick="window.print()"] {
                display: none;
            }

            /* Adjust cell padding for better print layout */
            td,
            th {
                padding: 4px;
                font-size: 11px;
            }

            .noprint {
                display: none;
            }
        }

        @page {
            margin: 0;
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

                <div class="noprint"><?php include('../layout/header.php'); ?></div>

                <div class="content-data">

                    <div class="row noprint" style="margin: 15px;">
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
                                                    $sqlclasses = "SELECT DISTINCT(class_teacher.class_id),class FROM `class_teacher` INNER JOIN class_sections ON class_teacher.class_id=class_sections.class_id INNER JOIN classes ON class_sections.class_id=classes.id INNER JOIN assigncatoclass ON classes.id=assigncatoclass.ClassID AND assigncatoclass.ResultType != 'british' AND class_teacher.staff_id = '$id' ORDER BY class";
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
                                                    $sqlclasses = "SELECT * FROM `classes` INNER JOIN assigncatoclass ON classes.id=assigncatoclass.ClassID AND assigncatoclass.ResultType != 'british' ORDER BY class";
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


                    <div class="row" style="margin: 15px;">
                        <div class="col-sm-12 cardBoxSty">
                            <div class="row noprint">
                                <div class="col-sm-10 col-md-10 col-xs-10 col-lg-10">
                                    <h3 class="card-title">View Broad Sheet</h3>
                                </div>
                                <div class="col-sm-2 col-md-2 col-xs-2 col-lg-2">
                                    <div>
                                        <a href="" style="color: #000000; font-weight: 600;" onclick="window.print()"><i class="fa fa-print"></i> Print</a>
                                    </div>
                                </div>
                            </div>


                            <div class="container rel">
                                <div class="row">
                                    <div class="col-sm-3 col-md-3 col-xs-3 col-lg-3">
                                        <div style="margin-top: 60px; margin-left: 10px;">
                                            <img src="https://schoollift.s3.us-east-2.amazonaws.com/<?php echo $rowsch_settings['app_logo']; ?>" class="img-fluid" alt="..." style="width: 80%;">
                                        </div>
                                    </div>
                                    <div class="col-sm-9 col-md-9 col-xs-9 col-lg-9">
                                        <p class="schname"><?php echo $rowsch_settings['name']; ?></p>
                                        <p class="schloc" style="color: rgb(185, 7, 7);"><?php echo $rowsch_settings['address']; ?>.</p>
                                        <p style="text-align: center; font-weight: bold; font-size: 30px; margin-top: -10px; color: #333636;">BROAD SHEET</p>

                                    </div>
                                </div>

                                <div class="result table-responsive" style="margin-bottom: 20px;" id="tbl_data">
                                    Please filter to view broad sheet
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

                    $('#classsection').html('<option value="0">Loading...</option>');

                    var staffid = "<?php echo $id; ?>";

                    var pagename = "broadsheet";

                    if (classid != '' && classid != '0') {

                        var dataString = 'classid=' + classid + '&staffid=' + staffid + '&pagename=' + pagename;

                        // alert(dataString);
                        $.ajax({
                            url: '../../../phpscript/get-class-section.php',
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

                    var dataString = 'classid=' + classid + '&session=' + session + '&term=' + term + '&classsectionactual=' + classsectionactual;

                    if (classid != '' && classid != '0' && classsectionactual != '' && classsectionactual != '0' && session != '' && session != '0' && term != '' && term != '0') {
                        // alert(dataString);
                        $.ajax({
                            url: '../phpscript/view-studentsbroadsheet.php',
                            method: 'POST',
                            data: dataString,

                            success: function(maindata2) {

                                $('#tbl_data').html(maindata2);

                            }
                        });
                    } else {
                        $('#tbl_data').html('Please filter to view student list');

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