<?php
include('../database/config.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <script src="../assets/js/jquery-3.5.1.min.js"></script>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <!--My New Stylesheet CSS -->
    <link rel="stylesheet" href="../assets/css/myStyleSheet.css">

    <!--The result stylesheet -->
    <link rel="stylesheet" href="../assets/css/resultStyleSheet.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.min.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

    <title>Result Page</title>
</head>
<style>
    @media only screen and (max-width: 767px) {
        /* CSS rules for smaller screens */

        /* Adjust the width of the result sheet to fit the screen */
        #result-body {
            width: 1300px;
            overflow: auto;
        }

        /* Apply scaling transformation to the result sheet */
        #printable {
            transform-origin: top left;
            transform: scale(0.25);
            width: 1200px;

        }
    }

    @page {
        margin: 0;
    }

    @media print {

        /* Define the page size and margins for printing */
        /* @page {
            size: A4 portrait; /* Change to landscape if needed */
            margin: 2mm;      /* Adjust as necessary
        } */
        
        /* Scale the printable container down to ensure it fits on one page */
        /* #printable {
            /* Adjust the scale value as needed based on your content size */
            /* transform: scale(0.80);
            transform-origin: top left;
            width: 1080px;
        } */
        
        /* Prevent page breaks inside key containers */
        /* .card, .table-responsive {
            page-break-inside: avoid;
        } */
        
        /* Optionally, reduce the font size if needed */
        /* body, .card {
            font-size: 13px;
        } */

        canvas.sunygraph {
            min-height: 200px;
            max-width: 98%;
            max-height: 100%;
            height: auto !important;
            width: auto !important;
        }

    }

    .tab {
        table-layout: auto;
        /* Keeps column widths uniform */
        word-wrap: break-word;
        /* Breaks long text properly */
        white-space: normal;
        /* Allows wrapping at spaces */
    }

    .tab td,
    .tab th {
        padding: 4px 6px;
        /* Reduces padding for a compact design */
        text-align: center;
        /* Centers content for better alignment */
        vertical-align: middle;
        /* Ensures content stays vertically aligned */
        /* font-size: 14px; */
        /* Adjusts font size for numbers */
    }

    .tab th {
        text-align: left;
        /* Aligns headers to the left for better readability */
        font-weight: bold;
        /* Keeps headers distinct */
    }
</style>
<?php

$classsection = $_GET['classsection'];

$classsectionactual = $_GET['classsectionactual'];

$classid = $_GET['classid'];

$term = $_GET['term'];
if ($term == '1st') {
    $term2 = 'first term';
}

if ($term == '2nd') {
    $term2 = 'second ter';
}

if ($term == '3rd') {
    $term2 = 'third term';
}
$session = $_GET['session'];
// Build the SQL query
$sql = "SELECT * FROM feenext WHERE class_id = $classid AND session = '$session' AND term = '$term' LIMIT 1";

// Execute the query
$next_fee_result = mysqli_query($link, $sql);

// Check if a row was found and display the data
if (mysqli_num_rows($next_fee_result) > 0) {
    $next_fee = mysqli_fetch_assoc($next_fee_result)["amount"];
} else {
    $next_fee = 0;
}

$sql = "SELECT * FROM sessions WHERE id = '$session' LIMIT 1";
$session_name = mysqli_query($link, $sql);
if (mysqli_num_rows($session_name) > 0) {
    $session_name = mysqli_fetch_assoc($session_name)["session"];
} else {
    $session_name = 0;
}




$session = $_GET['session'];

$sessionnew = $session + 1;

$id = $_GET['id'];

// $_GET['reltype'];

$reltypemain = $_GET['reltype'];

$sqlstudents = "SELECT * FROM `students` WHERE id='$id'";
$resultstudents = mysqli_query($link, $sqlstudents);
$rowstudents = mysqli_fetch_assoc($resultstudents);
$row_cntstudents = mysqli_num_rows($resultstudents);

$studimage = $rowstudents['image'];

$studname = $rowstudents['lastname'] . ' ' . $rowstudents['middlename'] . ' ' . $rowstudents['firstname'];

$studgender = $rowstudents['gender'];

$date1 = $rowstudents['dob'];
$date2 = date("Y-m-d");

$diff = abs(strtotime($date2) - strtotime($date1));

$years = floor($diff / (365 * 60 * 60 * 24));

$sqlGetclasses = "SELECT * FROM `classes` WHERE `id`='$classid'";
$queryGetclasses = mysqli_query($link, $sqlGetclasses);
$rowGetclasses = mysqli_fetch_assoc($queryGetclasses);
$countGetclasses = mysqli_num_rows($queryGetclasses);

$studclass = $rowGetclasses['class'];

$sqlGetassigncatoclass = "SELECT * FROM `assigncatoclass` WHERE `ClassID`='$classid'";
$queryGetassigncatoclass = mysqli_query($link, $sqlGetassigncatoclass);
$rowGetassigncatoclass = mysqli_fetch_assoc($queryGetassigncatoclass);
$countGetassigncatoclass = mysqli_num_rows($queryGetassigncatoclass);

// $rowGetassigncatoclass['ResultType'];'numeric';

$reltype = $rowGetassigncatoclass['ResultType'];
$sqlGetsessions = "SELECT * FROM `sessions` WHERE `id`='$session'";
$queryGetsessions = mysqli_query($link, $sqlGetsessions);
$rowGetsessions = mysqli_fetch_assoc($queryGetsessions);
$countGetsessions = mysqli_num_rows($queryGetsessions);

$studsession = $rowGetsessions['session'];

$sqlGetclass_sections = "SELECT * FROM `class_sections` WHERE `id`='$classsection'";
$queryGetclass_sections = mysqli_query($link, $sqlGetclass_sections);
$rowGetclass_sections = mysqli_fetch_assoc($queryGetclass_sections);
$countGetclass_sections = mysqli_num_rows($queryGetclass_sections);

$studsecid = $rowGetclass_sections['id'];

$sqlGetsections = "SELECT * FROM `sections` WHERE `id`='$classsectionactual'";
$queryGetsections = mysqli_query($link, $sqlGetsections);
$rowGetsections = mysqli_fetch_assoc($queryGetsections);
$countGetsections = mysqli_num_rows($queryGetsections);

$studsection = $rowGetsections['section'];

?>

<body style="background: rgb(236, 234, 234);">
    <div class="container-fluid" style="">

        <div class="row" id="non-printable" style="margin-top: 20px;">
            <div class="col-md-10 ">
                <a style="color: black; font-size: 20px;" href="<?php echo $defRUladmin; ?>/admin/examResult.php"><i class="fa fa-angle-double-left"></i> Back
                </a>
            </div>

            <div class="col-md-2">
                <a href="" style="color: #000000; font-weight: 600;" onclick="window.print()"><i class="fa fa-print"></i> Print</a>
            </div>

        </div>


        <div class="card" id="printable">

            <div class="card-body" style="color: black;">

                <div class="rel">


                    <div class="row">
                        <div class="col">
                            <div align="center">
                                <img src="https://schoollift.s3.us-east-2.amazonaws.com/<?php echo $rowsch_settings['app_logo']; ?>" align="center" class="img-fluid" style="margin: 10px; width: 50%;">
                            </div>
                        </div>

                        <div class="col-6">

                            <p class="schname" style="font-size:25px"><?php echo $rowsch_settings['name']; ?></p>
                            <p class="schloc" style="color: rgb(185, 7, 7);font-size:16px;margin-top:-20px;"><?php echo $rowsch_settings['address']; ?>.</p>
                            <div style="margin-top:-10px;text-align:center">
                                <span>Email: <?php echo $rowsch_settings['email']; ?>
                                </span><br />
                                <span>
                                    Website: <?php echo $defRUlsec; ?>
                                </span>
                            </div>
                        </div>

                        <div class="col">
                            <img src="https://schoollift.s3.us-east-2.amazonaws.com/<?php echo $studimage; ?>" align="center" class="img-fluid" style="margin: 10px; width: 45%;height:120px">
                        </div>
                    </div><br>

                    <div align="center">
                        <h5 style="font-size: 17px; font-weight: 500;margin-top:-40px">SUMMARY OF ACADEMIC PERFORMANCE FOR <span><?php echo $term; ?> TERM</span> <?php echo $session_name; ?> SESSION <span><?php $studsectionid; ?></span></h5>
                    </div>

                    <?php

                    if ($reltypemain == 'midterm') {

                    ?>
                        <div class="container-motto">
                            <?php

                            $sqlgetsubscore = ("SELECT * FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND SubjectID != '0' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'");
                            $resultgetsubscore = mysqli_query($link, $sqlgetsubscore);
                            $rowgetsubscore = mysqli_fetch_assoc($resultgetsubscore);
                            $row_cntgetsubscore = mysqli_num_rows($resultgetsubscore);

                            if ($row_cntgetsubscore > 0) {

                                $sqlgettotalgrade = "SELECT SUM(ca1) AS ca1,SUM(ca2) AS ca2,SUM(ca3) AS ca3,SUM(ca4) AS ca4,SUM(ca5) AS ca5,SUM(ca6) AS ca6,SUM(ca7) AS ca7,SUM(ca8) AS ca8,SUM(ca9) AS ca9,SUM(ca10) AS ca10 FROM `score` WHERE StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'";

                                $resultgettotalgrade = mysqli_query($link, $sqlgettotalgrade);
                                $rowgettotalgrade = mysqli_fetch_assoc($resultgettotalgrade);
                                $row_cntgettotalgrade = mysqli_num_rows($resultgettotalgrade);

                                $sqltogetresultsettings = mysqli_query($link, "SELECT * FROM `resultsetting` INNER JOIN assigncatoclass ON resultsetting.ResultSettingID=assigncatoclass.ResultSettingID WHERE ClassID = '$classid'");
                                $countresultsettings = mysqli_num_rows($sqltogetresultsettings);
                                $rowresultsettings = mysqli_fetch_array($sqltogetresultsettings);

                                if ($rowresultsettings > 0) {
                                    $MidTermCaToUse = $rowresultsettings['MidTermCaToUse'];

                                    $MidTermCaToUseArr = explode(',', $MidTermCaToUse);

                                    if (in_array("1", $MidTermCaToUseArr)) {
                                        $CA1MidTerm = $rowgettotalgrade['ca1'];
                                        $CA1MidTermHighestScore = $rowresultsettings['CA1Score'];
                                    } else {
                                        $CA1MidTerm = '0';
                                        $CA1MidTermHighestScore = '0';
                                    }

                                    if (in_array("2", $MidTermCaToUseArr)) {
                                        $CA2MidTerm = $rowgettotalgrade['ca2'];
                                        $CA2MidTermHighestScore = $rowresultsettings['CA2Score'];
                                    } else {
                                        $CA2MidTerm = '0';
                                        $CA2MidTermHighestScore = '0';
                                    }
                                    if (in_array("3", $MidTermCaToUseArr)) {
                                        $CA3MidTerm = $rowgettotalgrade['ca3'];
                                        $CA3MidTermHighestScore = $rowresultsettings['CA3Score'];
                                    } else {
                                        $CA3MidTerm = '0';
                                        $CA3MidTermHighestScore = '0';
                                    }
                                    if (in_array("4", $MidTermCaToUseArr)) {
                                        $CA4MidTerm = $rowgettotalgrade['ca4'];
                                        $CA4MidTermHighestScore = $rowresultsettings['CA4Score'];
                                    } else {
                                        $CA4MidTerm = '0';
                                        $CA4MidTermHighestScore = '0';
                                    }
                                    if (in_array("5", $MidTermCaToUseArr)) {
                                        $CA5MidTerm = $rowgettotalgrade['ca5'];
                                        $CA5MidTermHighestScore = $rowresultsettings['CA5Score'];
                                    } else {
                                        $CA5MidTerm = '0';
                                        $CA5MidTermHighestScore = '0';
                                    }
                                    if (in_array("6", $MidTermCaToUseArr)) {
                                        $CA6MidTerm = $rowgettotalgrade['ca6'];
                                        $CA6MidTermHighestScore = $rowresultsettings['CA6Score'];
                                    } else {
                                        $CA6MidTerm = '0';
                                        $CA6MidTermHighestScore = '0';
                                    }
                                    if (in_array("7", $MidTermCaToUseArr)) {
                                        $CA7MidTerm = $rowgettotalgrade['ca7'];
                                        $CA7MidTermHighestScore = $rowresultsettings['CA7Score'];
                                    } else {
                                        $CA7MidTerm = '0';
                                        $CA7MidTermHighestScore = '0';
                                    }
                                    if (in_array("8", $MidTermCaToUseArr)) {
                                        $CA8MidTerm = $rowgettotalgrade['ca8'];
                                        $CA8MidTermHighestScore = $rowresultsettings['CA8Score'];
                                    } else {
                                        $CA8MidTerm = '0';
                                        $CA8MidTermHighestScore = '0';
                                    }
                                    if (in_array("9", $MidTermCaToUseArr)) {
                                        $CA9MidTerm = $rowgettotalgrade['ca9'];
                                        $CA9MidTermHighestScore = $rowresultsettings['CA9Score'];
                                    } else {
                                        $CA9MidTerm = '0';
                                        $CA9MidTermHighestScore = '0';
                                    }
                                    if (in_array("10", $MidTermCaToUseArr)) {
                                        $CA10MidTerm = $rowgettotalgrade['ca10'];
                                        $CA10MidTermHighestScore = $rowresultsettings['CA10Score'];
                                    } else {
                                        $CA10MidTerm = '0';
                                        $CA10MidTermHighestScore = '0';
                                    }
                                } else {
                                }

                                $getMidTermAVG = $CA1MidTerm + $CA2MidTerm + $CA3MidTerm + $CA4MidTerm + $CA5MidTerm + $CA6MidTerm + $CA7MidTerm + $CA8MidTerm + $CA9MidTerm + $CA10MidTerm;

                                $getMidTermHighestScore = ($CA1MidTermHighestScore + $CA2MidTermHighestScore + $CA3MidTermHighestScore + $CA4MidTermHighestScore + $CA5MidTermHighestScore + $CA6MidTermHighestScore + $CA7MidTermHighestScore + $CA8MidTermHighestScore + $CA9MidTermHighestScore + $CA10MidTermHighestScore) * $row_cntgetsubscore;

                                // $gettotgrade = round($getMidTermAVG/$row_cntgetsubscore, 2);

                                $gettotgradeold = round(($getMidTermAVG / $getMidTermHighestScore) * 100, 2);

                                if ($gettotgradeold == '0' || $gettotgradeold == '' || $gettotgradeold == NULL) {
                                    $gettotgrade = 0;
                                } else {
                                    $gettotgrade = $gettotgradeold;
                                }

                                $sqlgettotgradstuc = ("SELECT * FROM `gradingstructure` INNER JOIN assigngradingtclass ON gradingstructure.GradingTitle = assigngradingtclass.GradingTitle WHERE $gettotgrade >= RangeStart AND $gettotgrade <= RangeEnd AND ClassID = '$classid'");
                                $resultgettotgradstuc = mysqli_query($link, $sqlgettotgradstuc);
                                $rowgettotgradstuc = mysqli_fetch_assoc($resultgettotgradstuc);
                                $row_cntgettotgradstuc = mysqli_num_rows($resultgettotgradstuc);

                                if ($row_cntgettotgradstuc > 0) {

                                    $totscorgrade = $rowgettotgradstuc['Grade'];
                                } else {

                                    $totscorgrade = 'NA';
                                }
                            } else {
                                $gradeid = 'NA';

                                $gettotgrade = 0;
                            }
                            ?>
                            <div class="row" style="margin: 10px;">
                                <div class="col-4">
                                    <h5 style="color: #000000;"> <b>NAME:</b> <?php echo $studname; ?></h5>
                                </div>
                                <div class="col-4">
                                    <h5 style="color: #000000;"> <b>CLASS:</b> <?php echo $studclass . ' ' . $studsection; ?></h5>
                                </div>
                                <div class="col-4">
                                    <h5 style="color: #000000;"> <b>GENDER:</b> <?php echo $studgender; ?></h5>
                                </div>
                            </div>

                            <div class="row" style="margin: 10px;">

                                <div class="col-4">
                                    <h5 style="color: #000000;"> <b>TOTAL SCORE:</b> <?php echo $gettotgrade; ?></h5>
                                </div>

                                <div class="col-4">
                                    <h5 style="color: #000000;"> <b>GRADE:</b> <?php echo $totscorgrade; ?></h5>
                                </div>
                            </div>

                        </div>

                        <div align="center">
                            <h5 style="font-size: 18px; font-weight: 800; color: #000000; margin-bottom: 0px;">ACADEMIC PERFORMANCE</h5>
                        </div>

                        <div class="result table-responsive" style="margin: 10px; margin-top: 5px;">
                            <?php

                            $sqlrelset = ("SELECT * FROM `resultsetting` INNER JOIN assigncatoclass ON resultsetting.ResultSettingID=assigncatoclass.ResultSettingID WHERE ClassID = '$classid'");
                            $resultrelset = mysqli_query($link, $sqlrelset);
                            $rowGetrelset = mysqli_fetch_assoc($resultrelset);
                            $row_cntrelset = mysqli_num_rows($resultrelset);

                            if ($row_cntrelset > 0) {
                                $MidTermCaToUsegetheader = $rowGetrelset['MidTermCaToUse'];

                                $MidTermCaToUseArrgetheader = explode(',', $MidTermCaToUsegetheader);

                                if (in_array("1", $MidTermCaToUseArrgetheader)) {
                                    $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th>';
                                } else {
                                    $ca1test = '';
                                }

                                if (in_array("2", $MidTermCaToUseArrgetheader)) {
                                    $ca2test = '<th>' . $rowGetrelset['CA2Title'] . '</th>';
                                } else {
                                    $ca2test = '';
                                }
                                if (in_array("3", $MidTermCaToUseArrgetheader)) {
                                    $ca3test = '<th>' . $rowGetrelset['CA3Title'] . '</th>';
                                } else {
                                    $ca3test = '';
                                }
                                if (in_array("4", $MidTermCaToUseArrgetheader)) {
                                    $ca4test = '<th>' . $rowGetrelset['CA4Title'] . '</th>';
                                } else {
                                    $ca4test = '';
                                }
                                if (in_array("5", $MidTermCaToUseArrgetheader)) {
                                    $ca5test = '<th>' . $rowGetrelset['CA5Title'] . '</th>';
                                } else {
                                    $ca5test = '';
                                }
                                if (in_array("6", $MidTermCaToUseArrgetheader)) {
                                    $ca6test = '<th>' . $rowGetrelset['CA6Title'] . '</th>';
                                } else {
                                    $ca6test = '';
                                }
                                if (in_array("7", $MidTermCaToUseArrgetheader)) {
                                    $ca7test = '<th>' . $rowGetrelset['CA7Title'] . '</th>';
                                } else {
                                    $ca7test = '';
                                }
                                if (in_array("8", $MidTermCaToUseArrgetheader)) {
                                    $ca8test = '<th>' . $rowGetrelset['CA8Title'] . '</th>';
                                } else {
                                    $ca8test = '';
                                }
                                if (in_array("9", $MidTermCaToUseArrgetheader)) {
                                    $ca9test = '<th>' . $rowGetrelset['CA9Title'] . '</th>';
                                } else {
                                    $ca9test = '';
                                }
                                if (in_array("10", $MidTermCaToUseArrgetheader)) {
                                    $ca10test = '<th>' . $rowGetrelset['CA10Title'] . '</th>';
                                } else {
                                    $ca10test = '';
                                }
                            } else {
                                $ca1test = '';
                            }

                            ?>
                            <table class="table-bordered tab table-sm tb-result-border">

                                <tr>
                                    <th>SUBJECT(s)</th>
                                    <?php echo $ca1test; ?>
                                    <?php echo $ca2test; ?>
                                    <?php echo $ca3test; ?>
                                    <?php echo $ca4test; ?>
                                    <?php echo $ca5test; ?>
                                    <?php echo $ca6test; ?>
                                    <?php echo $ca7test; ?>
                                    <?php echo $ca8test; ?>
                                    <?php echo $ca9test; ?>
                                    <?php echo $ca10test; ?>
                                    <th>TOTAL</th>
                                    <th>AVERAGE</th>
                                    <th>GRADE</th>
                                    <th>REMARK</th>
                                </tr>

                                <tbody>
                                    <?php

                                    $sqlsub = ("SELECT subjects.name AS name, subjects.id as id FROM `subject_group_class_sections` INNER JOIN subject_group_subjects ON subject_group_class_sections.subject_group_id=subject_group_subjects.subject_group_id INNER JOIN subjects ON subject_group_subjects.subject_id=subjects.id WHERE subject_group_class_sections.class_section_id = '$classsection' AND subject_group_class_sections.session_id='$session' AND subject_group_subjects.session_id='$session'");
                                    $resultsub = mysqli_query($link, $sqlsub);
                                    $rowGetsub = mysqli_fetch_assoc($resultsub);
                                    $row_cntsub = mysqli_num_rows($resultsub);

                                    $sqlgetscorecheck = ("SELECT * FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND SubjectID != '0' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'");
                                    $resultgetscorecheck = mysqli_query($link, $sqlgetscorecheck);
                                    $rowgetscorecheck = mysqli_fetch_assoc($resultgetscorecheck);
                                    $row_cntgetscorecheck = mysqli_num_rows($resultgetscorecheck);

                                    if ($row_cntgetscorecheck > 0) {

                                        do {

                                            $subname = $rowGetsub['name'];
                                            $subid = $rowGetsub['id'];

                                            $sqlgetscore = ("SELECT * FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND  StudentID = '$id' AND SubjectID != '0' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                                            $resultgetscore = mysqli_query($link, $sqlgetscore);
                                            $rowgetscore = mysqli_fetch_assoc($resultgetscore);
                                            $row_cntgetscore = mysqli_num_rows($resultgetscore);

                                            if ($row_cntgetscore > 0) {
                                                if ($row_cntrelset > 0) {
                                                    $MidTermCaToUseshowscores = $rowGetrelset['MidTermCaToUse'];

                                                    $MidTermCaToUseArrshowscores = explode(',', $MidTermCaToUseshowscores);

                                                    if (in_array("1", $MidTermCaToUseArrshowscores)) {
                                                        $ca1 = $rowgetscore['ca1'];
                                                        $ca1table = '<td>' . $rowgetscore['ca1'] . '</td>';
                                                        $CA1MidTermHighestScoresubscore = $rowGetrelset['CA1Score'];
                                                    } else {
                                                        $ca1 = '0';
                                                        $ca1table = '';
                                                        $CA1MidTermHighestScoresubscore = '0';
                                                    }

                                                    if (in_array("2", $MidTermCaToUseArrshowscores)) {
                                                        $ca2 = $rowgetscore['ca2'];
                                                        $ca2table = '<td>' . $rowgetscore['ca2'] . '</td>';
                                                        $CA2MidTermHighestScoresubscore = $rowGetrelset['CA2Score'];
                                                    } else {
                                                        $ca2 = '0';
                                                        $ca2table = '';
                                                        $CA2MidTermHighestScoresubscore = '0';
                                                    }
                                                    if (in_array("3", $MidTermCaToUseArrshowscores)) {
                                                        $ca3 = $rowgetscore['ca3'];
                                                        $ca3table = '<td>' . $rowgetscore['ca3'] . '</td>';
                                                        $CA3MidTermHighestScoresubscore = $rowGetrelset['CA3Score'];
                                                    } else {
                                                        $ca3 = '0';
                                                        $ca3table = '';
                                                        $CA3MidTermHighestScoresubscore = '0';
                                                    }
                                                    if (in_array("4", $MidTermCaToUseArrshowscores)) {
                                                        $ca4 = $rowgetscore['ca4'];
                                                        $ca4table = '<td>' . $rowgetscore['ca4'] . '</td>';
                                                        $CA4MidTermHighestScoresubscore = $rowGetrelset['CA4Score'];
                                                    } else {
                                                        $ca4 = '0';
                                                        $ca4table = '';
                                                        $CA4MidTermHighestScoresubscore = '0';
                                                    }
                                                    if (in_array("5", $MidTermCaToUseArrshowscores)) {
                                                        $ca5 = $rowgetscore['ca5'];
                                                        $ca5table = '<td>' . $rowgetscore['ca5'] . '</td>';
                                                        $CA5MidTermHighestScoresubscore = $rowGetrelset['CA5Score'];
                                                    } else {
                                                        $ca5 = '0';
                                                        $ca5table = '';
                                                        $CA5MidTermHighestScoresubscore = '0';
                                                    }
                                                    if (in_array("6", $MidTermCaToUseArrshowscores)) {
                                                        $ca6 = $rowgetscore['ca6'];
                                                        $ca6table = '<td>' . $rowgetscore['ca6'] . '</td>';
                                                        $CA6MidTermHighestScoresubscore = $rowGetrelset['CA6Score'];
                                                    } else {
                                                        $ca6 = '0';
                                                        $ca6table = '';
                                                        $CA6MidTermHighestScoresubscore = '0';
                                                    }
                                                    if (in_array("7", $MidTermCaToUseArrshowscores)) {
                                                        $ca7 = $rowgetscore['ca7'];
                                                        $ca7table = '<td>' . $rowgetscore['ca7'] . '</td>';
                                                        $CA7MidTermHighestScoresubscore = $rowGetrelset['CA7Score'];
                                                    } else {
                                                        $ca7 = '0';
                                                        $ca7table = '';
                                                        $CA7MidTermHighestScoresubscore = '0';
                                                    }
                                                    if (in_array("8", $MidTermCaToUseArrshowscores)) {
                                                        $ca8 = $rowgetscore['ca8'];
                                                        $ca8table = '<td>' . $rowgetscore['ca8'] . '</td>';
                                                        $CA8MidTermHighestScoresubscore = $rowGetrelset['CA8Score'];
                                                    } else {
                                                        $ca8 = '0';
                                                        $ca8table = '';
                                                        $CA8MidTermHighestScoresubscore = '0';
                                                    }
                                                    if (in_array("9", $MidTermCaToUseArrshowscores)) {
                                                        $ca9 = $rowgetscore['ca9'];
                                                        $ca9table = '<td>' . $rowgetscore['ca9'] . '</td>';
                                                        $CA9MidTermHighestScoresubscore = $rowGetrelset['CA9Score'];
                                                    } else {
                                                        $ca9 = '0';
                                                        $ca9table = '';
                                                        $CA9MidTermHighestScoresubscore = '0';
                                                    }
                                                    if (in_array("10", $MidTermCaToUseArrshowscores)) {
                                                        $ca10 = $rowgetscore['ca10'];
                                                        $ca10table = '<td>' . $rowgetscore['ca10'] . '</td>';
                                                        $CA10MidTermHighestScoresubscore = $rowGetrelset['CA10Score'];
                                                    } else {
                                                        $ca10 = '0';
                                                        $ca10table = '';
                                                        $CA10MidTermHighestScoresubscore = '0';
                                                    }
                                                } else {
                                                }

                                                $total = $ca1 + $ca2 + $ca3 + $ca4 + $ca5 + $ca6 + $ca7 + $ca8 + $ca9 + $ca10;

                                                $getMidTermHighestScoresubscore = $CA1MidTermHighestScoresubscore + $CA2MidTermHighestScoresubscore + $CA3MidTermHighestScoresubscore + $CA4MidTermHighestScoresubscore + $CA5MidTermHighestScoresubscore + $CA6MidTermHighestScoresubscore + $CA7MidTermHighestScoresubscore + $CA8MidTermHighestScoresubscore + $CA9MidTermHighestScoresubscore + $CA10MidTermHighestScoresubscore;

                                                $gettotgradetots = round(($total / $getMidTermHighestScoresubscore) * 100, 2);

                                                $sqlgetgradstuc = ("SELECT * FROM `gradingstructure` INNER JOIN assigngradingtclass ON gradingstructure.GradingTitle = assigngradingtclass.GradingTitle WHERE $gettotgradetots >= RangeStart AND $gettotgradetots <= RangeEnd AND ClassID = '$classid'");
                                                $resultgetgradstuc = mysqli_query($link, $sqlgetgradstuc);
                                                $rowgetgradstuc = mysqli_fetch_assoc($resultgetgradstuc);
                                                $row_cntgetgradstuc = mysqli_num_rows($resultgetgradstuc);

                                                if ($row_cntgetgradstuc > 0) {
                                                    $grade = $rowgetgradstuc['Grade'];
                                                    $remark = $rowgetgradstuc['Remark'];
                                                } else {
                                                }

                                                echo '<tr>
                                                                            <th>' . $subname . '</th>';
                                                echo $ca1table . $ca2table . $ca3table . $ca4table . $ca5table . $ca6table . $ca7table . $ca8table . $ca9table . $ca10table;
                                                echo '

                                                                            <td>' . $total . '/' . $getMidTermHighestScoresubscore . '</td>
                                                                            <td>' . $gettotgradetots . '</td>
                                                                            <td>' . $grade . '</td>

                                                                            <td>' . $remark . '</td>
                                                                        </tr>';
                                            }
                                        } while ($rowGetsub = mysqli_fetch_assoc($resultsub));
                                    } else {
                                        echo '<tr><td align="center" colspan="15" style="font-size: calc(12px + (18 - 12) * ((100vw - 300px) / (1600 - 300)))"><div class="alert alert-info alert-dismissible mb-2" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>No Result Yet</div></tr></td>';
                                    }

                                    ?>
                                </tbody>
                            </table>
                        </div>

                        <?php
                        $sqlresumdateOld = ("SELECT * FROM `resumptiondate` WHERE `Session`='$session' AND `Term`='$term'");

                        $resultresumdateOld = mysqli_query($link, $sqlresumdateOld);
                        $getresumdateOld = mysqli_fetch_assoc($resultresumdateOld);
                        $sqlrow_cntresumdateOld = mysqli_num_rows($resultresumdateOld);

                        if ($sqlrow_cntresumdateOld > 0) {
                            $resumdateOld = $getresumdateOld['Date'];
                        } else {
                            $resumdateOld = 'N/A';
                        }

                        $sqlgetsubscore = ("SELECT * FROM `score` WHERE `ca1` != '0' AND `ca1` != '' AND `ca1` IS NOT NULL AND StudentID = '$id' AND ClassID = '$classid' AND SubjectID = '0' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'");
                        $resultgetsubscore = mysqli_query($link, $sqlgetsubscore);
                        $rowgetsubscore = mysqli_fetch_assoc($resultgetsubscore);
                        $row_cntgetsubscore = mysqli_num_rows($resultgetsubscore);

                        if ($row_cntgetsubscore > 0) {

                            $rowcountfixedgennew = $rowgetsubscore['ca1'];
                            $rowcountfixedpresent = $rowgetsubscore['ca2'];
                            $rowcountfixedlate = $rowgetsubscore['ca3'];
                            $rowcountfixedabsent = $rowgetsubscore['ca4'];
                        } else {
                            $sqlgettechgen = mysqli_query($link, "SELECT DISTINCT(student_attendences.`date`) FROM `student_attendences` WHERE student_attendences.`session` = '$session' AND student_attendences.`term` = '$term2'");
                            $fetchfixedgen = mysqli_fetch_assoc($sqlgettechgen);
                            $rowcountfixedgen = mysqli_num_rows($sqlgettechgen);

                            if ($rowcountfixedgen == NULL || $rowcountfixedgen == '') {
                                $rowcountfixedgennew = 0;
                            } else {
                                $rowcountfixedgennew = $rowcountfixedgen;
                            }

                            $sqlgettechpresent = mysqli_query($link, "SELECT * FROM `student_attendences` INNER JOIN student_session ON student_attendences.student_session_id=student_session.id WHERE student_attendences.`session` = '$session' AND student_attendences.`term` = '$term2' AND student_session.`student_id`='$id' AND student_session.`session_id`='$session' AND student_session.`class_id`='$classid' AND `attendence_type_id`='1'");
                            $fetchfixedpresent = mysqli_fetch_assoc($sqlgettechpresent);
                            $rowcountfixedpresent = mysqli_num_rows($sqlgettechpresent);

                            if ($rowcountfixedpresent == NULL || $rowcountfixedpresent == '') {
                                $rowcountfixedpresent = 0;
                            } else {
                                $rowcountfixedpresent = $rowcountfixedpresent;
                            }

                            $sqlgettechabsent = mysqli_query($link, "SELECT * FROM `student_attendences` INNER JOIN student_session ON student_attendences.student_session_id=student_session.id WHERE student_attendences.`session` = '$session' AND student_attendences.`term` = '$term2' AND student_session.`student_id`='$id' AND student_session.`session_id`='$session' AND student_session.`class_id`='$classid' AND `attendence_type_id`='4'");
                            $fetchfixedabsent = mysqli_fetch_assoc($sqlgettechabsent);
                            $rowcountfixedabsent = mysqli_num_rows($sqlgettechabsent);

                            if ($rowcountfixedabsent == NULL || $rowcountfixedabsent == '') {
                                $rowcountfixedabsent = 0;
                            } else {
                                $rowcountfixedabsent = $rowcountfixedabsent;
                            }
                        }
                        ?>
                        <div align="center" class="summDD">
                            <p>Total Score: <?php echo $gettotscore; ?> </p>
                            <p>Average Score: <?php echo $gettotgrade; ?> </p>
                            <p>Class Average: <?php echo $decStubsubavg; ?> </p>
                            <p>No. of Subjects: <?php echo $row_cntgetscorecheck; ?></p>
                        </div>

                        <div class="performance">
                            <div class="row">
                                <div class="col-4">
                                    <div class="containerForChart">

                                        <canvas class="newgraph" id="mysunChart" style="width:100%;"></canvas>

                                    </div>
                                </div>
                                <div class="col-4" style="padding-right: 0px">
                                    <div class="container-motto" style="margin-right: 2px;">
                                        <div class="result table-responsive" style="margin: 10px; margin-top: 5px;">
                                            <table class="tab table-sm" style="width:98%;border:0px solid black;">
                                                <tr>
                                                    <th colspan="4" style="text-align: center;">AFFECTIVE DOMAIN</th>
                                                </tr>
                                                <tbody>
                                                    <?php

                                                    $sqlrelset = ("SELECT * FROM `affective_domain_settings` INNER JOIN assignsaftoclass ON affective_domain_settings.id=assignsaftoclass.AffectiveDomainSettingsId WHERE ClassID = '$classid'");
                                                    $resultrelset = mysqli_query($link, $sqlrelset);
                                                    $rowGetrelset = mysqli_fetch_assoc($resultrelset);
                                                    $row_cntrelset = mysqli_num_rows($resultrelset);

                                                    if ($row_cntrelset > 0) {
                                                        $sqlgetscore = ("SELECT * FROM `affective_domain_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND term = '$term' AND sectionid = '$classsectionactual'");
                                                        $resultgetscore = mysqli_query($link, $sqlgetscore);
                                                        $rowgetscore = mysqli_fetch_assoc($resultgetscore);
                                                        $row_cntgetscore = mysqli_num_rows($resultgetscore);

                                                        if ($row_cntgetscore > 0) {
                                                            if ($rowGetrelset['NumberofAD'] == '1') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofAD'] == '2') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofAD'] == '3') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofAD'] == '4') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofAD'] == '5') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofAD'] == '6') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofAD'] == '7') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofAD'] == '8') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofAD'] == '9') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofAD'] == '10') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofAD'] == '11') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofAD'] == '12') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain12"] . '</td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofAD'] == '13') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain12"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD13Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain13"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofAD'] == '14') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain12"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD13Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain13"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD14Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain14"] . '</td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofAD'] == '15') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain12"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD13Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain13"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD14Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain14"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD15Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain15"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } else {
                                                            }
                                                        } else {
                                                            echo '<tr><td align="center" colspan="15" style="font-size: calc(12px + (18 - 12) * ((100vw - 300px) / (1600 - 300)))"><div class="alert alert-info alert-dismissible mb-2" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>No Result Yet</div></tr></td>';
                                                        }
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4" style="padding-left: 0px">
                                    <div class="container-motto" style="margin-left: 2px;">
                                        <div class="result table-responsive" style="margin: 10px; margin-top: 5px;">
                                            <table class="tab table-sm" style="width:98%;">
                                                <tr>
                                                    <th colspan="4" style="text-align: center;">PSYCOMOTOR</th>
                                                </tr>
                                                <tbody>
                                                    <?php

                                                    $sqlrelset = ("SELECT * FROM `psycomotor_settings` INNER JOIN assignspsycomotortoclass ON psycomotor_settings.id=assignspsycomotortoclass.PsycomotorSettingsId WHERE ClassID = '$classid'");
                                                    $resultrelset = mysqli_query($link, $sqlrelset);
                                                    $rowGetrelset = mysqli_fetch_assoc($resultrelset);
                                                    $row_cntrelset = mysqli_num_rows($resultrelset);

                                                    if ($row_cntrelset > 0) {
                                                        $sqlgetscore = ("SELECT * FROM `psycomotor_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND term = '$term' AND sectionid = '$classsectionactual'");
                                                        $resultgetscore = mysqli_query($link, $sqlgetscore);
                                                        $rowgetscore = mysqli_fetch_assoc($resultgetscore);
                                                        $row_cntgetscore = mysqli_num_rows($resultgetscore);

                                                        if ($row_cntgetscore > 0) {
                                                            if ($rowGetrelset['NumberofP'] == '1') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofP'] == '2') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofP'] == '3') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofP'] == '4') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofP'] == '5') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofP'] == '6') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofP'] == '7') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofP'] == '8') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofP'] == '9') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofP'] == '10') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <th>' . $rowGetrelset['P10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofP'] == '11') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <th>' . $rowGetrelset['P10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <th>' . $rowGetrelset['P11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofP'] == '12') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <th>' . $rowGetrelset['P10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <th>' . $rowGetrelset['P11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <th>' . $rowGetrelset['P12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor12"] . '</td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofP'] == '13') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <th>' . $rowGetrelset['P11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <th>' . $rowGetrelset['P12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor12"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <th>' . $rowGetrelset['P13Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor13"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofP'] == '14') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <th>' . $rowGetrelset['P11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <th>' . $rowGetrelset['P12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor12"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <th>' . $rowGetrelset['P13Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor13"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                        <th>' . $rowGetrelset['P14Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor14"] . '</td>
                                                                                    </tr>';
                                                            } elseif ($rowGetrelset['NumberofP'] == '15') {
                                                                echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <th>' . $rowGetrelset['P12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor12"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <th>' . $rowGetrelset['P13Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor13"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <th>' . $rowGetrelset['P14Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor14"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                        <th>' . $rowGetrelset['P15Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor15"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                            } else {
                                                            }
                                                        } else {
                                                            echo '<tr><td align="center" colspan="15" style="font-size: calc(12px + (18 - 12) * ((100vw - 300px) / (1600 - 300)))"><div class="alert alert-info alert-dismissible mb-2" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>No Result Yet</div></tr></td>';
                                                        }
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <?php

                                $sqlgettechremark = mysqli_query($link, "SELECT * FROM `remark` WHERE `RemarkType` = 'teacher' AND `StudentID`='$id' AND `Session`='$session' AND `Term`='$term' AND `remark`!=''");
                                $rowcountfixedremark = mysqli_num_rows($sqlgettechremark);
                                $fetchfixedremark = mysqli_fetch_assoc($sqlgettechremark);


                                if ($rowcountfixedremark > 0) {
                                    $teacherRemark = $fetchfixedremark['remark'];

                                    $teacherid = $fetchfixedremark['StaffID'];

                                    $sqlgetheadteachsign = ("SELECT * FROM `staffsignature` WHERE staff_id = '$hedteachid'");
                                    $resultgetheadteachsign = mysqli_query($link, $sqlgetheadteachsign);
                                    $rowgetheadteachsign = mysqli_fetch_assoc($resultgetheadteachsign);
                                    $row_cntgetheadteachsign = mysqli_num_rows($resultgetheadteachsign);


                                    if ($row_cntgetheadteachsign > 0) {
                                        $hedteachsign = '<img src="../img/signature/' . $rowgetheadteachsign['Signature'] . '" align="center" class="img-fluid" style="width: 70%;">';
                                    } else {
                                        $hedteachsign = '';
                                    }
                                } else {

                                    $sqlgetteachremark = ("SELECT * FROM `defaultcomment` INNER JOIN class_teacher ON defaultcomment.PrincipalOrDeanOrHeadTeacherUserID=class_teacher.staff_id WHERE $gettotgrade BETWEEN RangeStart AND RangeEnd AND CommentType = 'teacher' AND class_id = '$classid'");
                                    $resultgetteachremark = mysqli_query($link, $sqlgetteachremark);
                                    $rowgetteachremark = mysqli_fetch_assoc($resultgetteachremark);
                                    $row_cntgetteachremark = mysqli_num_rows($resultgetteachremark);

                                    if ($row_cntgetteachremark > 0) {
                                        $teacherRemark = $rowgetteachremark['DefaultComment'];

                                        $teacherid = $rowgetteachremark['PrincipalOrDeanOrHeadTeacherUserID'];

                                        $sqlgetheadteachsign = ("SELECT * FROM `staffsignature` WHERE staff_id = '$teacherid'");
                                        $resultgetheadteachsign = mysqli_query($link, $sqlgetheadteachsign);
                                        $rowgetheadteachsign = mysqli_fetch_assoc($resultgetheadteachsign);
                                        $row_cntgetheadteachsign = mysqli_num_rows($resultgetheadteachsign);


                                        if ($row_cntgetheadteachsign > 0) {
                                            $hedteachsign = '<img src="../img/signature/' . $rowgetheadteachsign['Signature'] . '" align="center" class="img-fluid" style="width: 70%;">';
                                        } else {
                                            $hedteachsign = '';
                                        }
                                    } else {
                                        $teacherRemark = 'N/A';

                                        $hedteachsign = '';
                                    }
                                }

                                $sqlgetprincremark = mysqli_query($link, "SELECT * FROM `remark` WHERE `RemarkType` = 'SchoolHead' AND `StudentID`='$id' AND `Session`='$session' AND `Term`='$term' AND `remark`!=''");
                                $rowcountprinfixedremark = mysqli_num_rows($sqlgetprincremark);
                                $fetchfixedprinremark = mysqli_fetch_assoc($sqlgetprincremark);

                                if ($rowcountprinfixedremark > 0) {
                                    $principalRemark = $fetchfixedprinremark['remark'];

                                    $headteacherid = $fetchfixedprinremark['staff_id'];

                                    $sqlgetheadteachhead = ("SELECT * FROM `staffsignature` WHERE staff_id = 5");
                                    $resultgetheadteachsignhead = mysqli_query($link, $sqlgetheadteachhead);
                                    $rowgetheadteachsignhead = mysqli_fetch_assoc($resultgetheadteachsignhead);
                                    $row_cntgetheadteachsignhead = mysqli_num_rows($resultgetheadteachsignhead);

                                    if ($row_cntgetheadteachsignhead > 0) {
                                        $hedteachsignhead = '<img src="' . $defRUl . 'img/signature/' . $rowgetheadteachsignhead['Signature'] . '" align="center" class="img-fluid" style="width: 70%;">';
                                    } else {
                                        $hedteachsignhead = '';
                                    }
                                } else {

                                    $sqlgetprincremark = ("SELECT * FROM `defaultcomment` WHERE $gettotgrade BETWEEN RangeStart AND RangeEnd AND CommentType = 'SchoolHead'");
                                    $resultgetprincremark = mysqli_query($link, $sqlgetprincremark);
                                    $rowgetteachremark = mysqli_fetch_assoc($resultgetprincremark);
                                    $row_cntgetprincremark = mysqli_num_rows($resultgetprincremark);

                                    if ($row_cntgetprincremark > 0) {
                                        $principalRemark = $rowgetteachremark['DefaultComment'];

                                        $headteacherid = $rowgetteachremark['PrincipalOrDeanOrHeadTeacherUserID'];

                                        $sqlgetheadteachhead = ("SELECT * FROM `staffsignature` WHERE staff_id = '$headteacherid'");
                                        $resultgetheadteachsignhead = mysqli_query($link, $sqlgetheadteachhead);
                                        $rowgetheadteachsignhead = mysqli_fetch_assoc($resultgetheadteachsignhead);
                                        $row_cntgetheadteachsignhead = mysqli_num_rows($resultgetheadteachsignhead);

                                        if ($row_cntgetheadteachsignhead > 0) {
                                            // $hedteachsignhead = '<img src="' . $defRUl . 'img/signature/' . $rowgetheadteachsignhead['Signature'] . '" align="center" class="img-fluid" style="width: 80%;">';
                                            $hedteachsignhead = '<img src=" https://schoollift.s3.us-east-2.amazonaws.com/' . $rowgetheadteachsignhead['Signature'] . '" align="center" class="img-fluid" style="width: 80%;">';
                                        } else {
                                            $hedteachsignhead = '';
                                        }
                                    } else {
                                        $principalRemark = 'N/A';

                                        $hedteachsignhead = '';
                                    }
                                }

                                $sqlresumdate = ("SELECT * FROM `resumptiondate` WHERE `Session`='$sessionnew' AND `Term`='$term' AND `Type`='$reltypemain'");

                                $resultresumdate = mysqli_query($link, $sqlresumdate);
                                $getresumdate = mysqli_fetch_assoc($resultresumdate);
                                $sqlrow_cntresumdate = mysqli_num_rows($resultresumdate);

                                if ($sqlrow_cntresumdate > 0) {
                                    $resumdate = date("l jS \of F Y", strtotime($getresumdate['Date']));
                                } else {
                                    $resumdate = 'N/A';
                                }
                                ?>
                                <div class="col-12">

                                    <div class="container-motto">
                                        <div style="margin: 20px;">
                                            <div class="row">
                                                <div class="col-sm-10 col-md-10">
                                                    <div align="center">
                                                        <p style="text-align: justify;"><b style="font-weight:600;">CLASS TEACHER'S COMMENT:</b>&nbsp;<?php echo $teacherRemark; ?></p>
                                                    </div>
                                                </div>

                                                <div class="col-sm-2 col-md-2">
                                                    <div align="center">
                                                        <?php echo $hedteachsign; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-sm-10 col-md-10">
                                                    <div>
                                                        <p style="text-align: justify;"><b style="font-weight:600;">PRINCIPAL/HEAD TEACHER'S COMMENT:</b>&nbsp;&nbsp;&nbsp;<?php echo $principalRemark; ?></p>
                                                    </div>
                                                </div>

                                                <div class="col-sm-2 col-md-2">
                                                    <div>
                                                        <?php echo $hedteachsignhead; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-sm-12 col-md-12">
                                                    <div>
                                                        <p style="text-align: justify;"><b style="font-weight:600;">NEXT TERM BEGINS:</b>&nbsp;<?php echo $resumdate; ?></b></p>
                                                        <?php
                                                        if ($next_fee > 0) {
                                                            echo '<p style="text-align: center;"><b style="font-weight:600;">NEXT TERM FEE: N' . $next_fee . '</b></p>';
                                                        }
                                                        ?>

                                                    </div>
                                                </div>

                                            </div>


                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <?php
                        /*
                                            if($term == '3rd')
                                            {

                                                $sessionnew = $session + 1;

                                                $sqlstudent_session = ("SELECT * FROM `student_session` WHERE `session_id`='$sessionnew' AND student_id = '$id'");

                                                $resultstudent_session = mysqli_query($link, $sqlstudent_session);
                                                $getstudent_session = mysqli_fetch_assoc($resultstudent_session);
                                                $sqlrow_cntstudent_session = mysqli_num_rows($resultstudent_session);

                                                if($sqlrow_cntstudent_session > 0)
                                                {
                                                    echo '<center>
                                                        <p class="promoted">PROMOTED TO THE NEXT CLASS</p>
                                                    </center>';
                                                }
                                                else
                                                {
                                                    echo '<center>
                                                        <p class="promoted">NOT PROMOTED TO THE NEXT CLASS</p>
                                                    </center>';
                                                }

                                            }
                                            else
                                            {

                                            }
                                            */
                        ?>

                        <?php

                    } elseif ($reltypemain == 'termly') {

                        if ($reltype == 'alphabetic') {
                        ?>
                            <div class="container-motto">
                                <?php

                                $sqlgetsubscore = ("SELECT * FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND SubjectID != '0' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'");
                                $resultgetsubscore = mysqli_query($link, $sqlgetsubscore);
                                $rowgetsubscore = mysqli_fetch_assoc($resultgetsubscore);
                                $row_cntgetsubscore = mysqli_num_rows($resultgetsubscore);

                                $sqlgettotalgrade = "SELECT SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS average FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND SubjectID != '0' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'";
                                $resultgettotalgrade = mysqli_query($link, $sqlgettotalgrade);
                                $rowgettotalgrade = mysqli_fetch_assoc($resultgettotalgrade);
                                $row_cntgettotalgrade = mysqli_num_rows($resultgettotalgrade);

                                $gettotgrade = floatval(round($rowgettotalgrade['average'] / $row_cntgetsubscore, 2));

                                $gettotscore = $rowgettotalgrade['average'];

                                $sqlgetClasscount = ("SELECT DISTINCT(StudentID) FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND SubjectID != '0' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'");
                                $resultgetClasscount = mysqli_query($link, $sqlgetClasscount);
                                $rowgetClasscount = mysqli_fetch_assoc($resultgetClasscount);
                                $row_cntClasscount = mysqli_num_rows($resultgetClasscount);

                                $sqlgetsubscoreALL = ("SELECT DISTINCT(SubjectID) FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND SubjectID != '0' AND Term = '$term' AND SectionID = '$classsectionactual'");
                                $resultgetsubscoreALL = mysqli_query($link, $sqlgetsubscoreALL);
                                $rowgetsubscoreALL = mysqli_fetch_assoc($resultgetsubscoreALL);
                                $row_cntgetsubscoreALL = mysqli_num_rows($resultgetsubscoreALL);

                                $sqlgettotclassscor = "SELECT SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS totalScore FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND SubjectID != '0' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SubjectID != 0 AND SectionID = '$classsectionactual' ORDER BY exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10";
                                $resultgettotclassscor = mysqli_query($link, $sqlgettotclassscor);
                                $rowgettotclassscor = mysqli_fetch_assoc($resultgettotclassscor);
                                $row_cntgettotclassscor = mysqli_num_rows($resultgettotclassscor);

                                $totsubjects = $row_cntClasscount * $row_cntgetsubscore;
                                $totsubjectsALL = $row_cntClasscount * $row_cntgetsubscoreALL;

                                $decStubsubavg = round($rowgettotclassscor['totalScore'] / $totsubjectsALL, 2);

                                $sqlsunnyhihhscoreuname = "SELECT DISTINCT(StudentID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10),COUNT(ID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) / COUNT(ID) AS total FROM score WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SubjectID != 0 AND SectionID = '$classsectionactual' GROUP BY StudentID order by total DESC LIMIT 1";
                                $resultsunnyhihhscoreuname = mysqli_query($link, $sqlsunnyhihhscoreuname);
                                $rowsunnyhihhscoreuname = mysqli_fetch_assoc($resultsunnyhihhscoreuname);
                                $row_cntsunnyhihhscoreuname = mysqli_num_rows($resultsunnyhihhscoreuname);

                                $sunhihscrun = round($rowsunnyhihhscoreuname['total'], 2);

                                $sqlsunnylowwscoreuname = "SELECT DISTINCT(StudentID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10),COUNT(ID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) / COUNT(ID) AS total FROM score WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND SubjectID != 0 AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' GROUP BY StudentID order by total ASC LIMIT 1";
                                $resultsunnylowwscoreuname = mysqli_query($link, $sqlsunnylowwscoreuname);
                                $rowsunnylowwscoreuname = mysqli_fetch_assoc($resultsunnylowwscoreuname);
                                $row_cntsunnylowwscoreuname = mysqli_num_rows($resultsunnylowwscoreuname);

                                $sunlowscrun = round($rowsunnylowwscoreuname['total'], 2);

                                if ($row_cntgettotalgrade > 0) {
                                    $sqlgettotgradstuc = ("SELECT * FROM `gradingstructure` INNER JOIN assigngradingtclass ON gradingstructure.GradingTitle = assigngradingtclass.GradingTitle WHERE $gettotgrade >= RangeStart AND $gettotgrade <= RangeEnd AND ClassID = '$classid'");
                                    $resultgettotgradstuc = mysqli_query($link, $sqlgettotgradstuc);
                                    $rowgettotgradstuc = mysqli_fetch_assoc($resultgettotgradstuc);
                                    $row_cntgettotgradstuc = mysqli_num_rows($resultgettotgradstuc);

                                    if ($row_cntgettotgradstuc > 0) {

                                        $totscorgrade = $rowgettotgradstuc['Grade'];
                                    } else {

                                        $totscorgrade = 'NA';
                                    }
                                } else {
                                    $gettotgrade = 'NA';
                                }

                                ?>
                                <div class="row" style="margin: 5px;">
                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>NAME:</b> <?php echo $studname; ?></h5>
                                    </div>

                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>CLASS:</b> <?php echo $studclass . ' ' . $studsection; ?></h5>
                                    </div>

                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>SEX:</b> <?php echo $studgender; ?></h5>
                                    </div>

                                </div>

                                <div class="row" style="margin: 10px;">
                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>OVERALL GRADE:</b> <?php echo $totscorgrade; ?></h5>
                                    </div>

                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>HIGHEST IN CLASS AVG:</b> <?php echo $sunhihscrun; ?></h5>
                                    </div>

                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>LOWEST IN CLASS AVE:</b> <?php echo $sunlowscrun; ?></h5>
                                    </div>

                                </div>


                            </div>

                            <div align="center">
                                <h5 style="font-size: 18px; font-weight: 800; color: #000000; margin-bottom: 0px;">ACADEMIC PERFORMANCE</h5>
                            </div>

                            <div class="result table-responsive" style="margin: 10px; margin-top: 5px;">
                                <?php

                                $sqlrelset = ("SELECT * FROM `resultsetting` INNER JOIN assigncatoclass ON resultsetting.ResultSettingID=assigncatoclass.ResultSettingID WHERE ClassID = '$classid'");
                                $resultrelset = mysqli_query($link, $sqlrelset);
                                $rowGetrelset = mysqli_fetch_assoc($resultrelset);
                                $row_cntrelset = mysqli_num_rows($resultrelset);

                                if ($row_cntrelset > 0) {
                                    if ($rowGetrelset['NumberOfCA'] == '1') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th>';
                                    } elseif ($rowGetrelset['NumberOfCA'] == '2') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th> <th>' . $rowGetrelset['CA2Title'] . '</th>';
                                    } elseif ($rowGetrelset['NumberOfCA'] == '3') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th> <th>' . $rowGetrelset['CA2Title'] . '</th> <th>' . $rowGetrelset['CA3Title'] . '</th>';
                                    } elseif ($rowGetrelset['NumberOfCA'] == '4') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th> <th>' . $rowGetrelset['CA2Title'] . '</th> <th>' . $rowGetrelset['CA3Title'] . '</th> <th>' . $rowGetrelset['CA4Title'] . '</th>';
                                    } elseif ($rowGetrelset['NumberOfCA'] == '5') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th> <th>' . $rowGetrelset['CA2Title'] . '</th> <th>' . $rowGetrelset['CA3Title'] . '</th> <th>' . $rowGetrelset['CA4Title'] . '</th> <th>' . $rowGetrelset['CA5Title'] . '</th>';
                                    } elseif ($rowGetrelset['NumberOfCA'] == '6') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th> <th>' . $rowGetrelset['CA2Title'] . '</th> <th>' . $rowGetrelset['CA3Title'] . '</th> <th>' . $rowGetrelset['CA4Title'] . '</th> <th>' . $rowGetrelset['CA5Title'] . '</th> <th>' . $rowGetrelset['CA6Title'] . '</th>';
                                    } elseif ($rowGetrelset['NumberOfCA'] == '7') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th> <th>' . $rowGetrelset['CA2Title'] . '</th> <th>' . $rowGetrelset['CA3Title'] . '</th> <th>' . $rowGetrelset['CA4Title'] . '</th> <th>' . $rowGetrelset['CA5Title'] . '</th> <th>' . $rowGetrelset['CA6Title'] . '</th> <th>' . $rowGetrelset['CA7Title'] . '</th>';
                                    } elseif ($rowGetrelset['NumberOfCA'] == '8') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th> <th>' . $rowGetrelset['CA2Title'] . '</th> <th>' . $rowGetrelset['CA3Title'] . '</th> <th>' . $rowGetrelset['CA4Title'] . '</th> <th>' . $rowGetrelset['CA5Title'] . '</th> <th>' . $rowGetrelset['CA6Title'] . '</th> <th>' . $rowGetrelset['CA7Title'] . '</th> <th>' . $rowGetrelset['CA8Title'] . '</th>';
                                    } elseif ($rowGetrelset['NumberOfCA'] == '9') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th> <th>' . $rowGetrelset['CA2Title'] . '</th> <th>' . $rowGetrelset['CA3Title'] . '</th> <th>' . $rowGetrelset['CA4Title'] . '</th> <th>' . $rowGetrelset['CA5Title'] . '</th> <th>' . $rowGetrelset['CA6Title'] . '</th> <th>' . $rowGetrelset['CA7Title'] . '</th> <th>' . $rowGetrelset['CA8Title'] . '</th> <th>' . $rowGetrelset['CA9Title'] . '</th>';
                                    } elseif ($rowGetrelset['NumberOfCA'] == '10') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th> <th>' . $rowGetrelset['CA2Title'] . '</th> <th>' . $rowGetrelset['CA3Title'] . '</th> <th>' . $rowGetrelset['CA4Title'] . '</th> <th>' . $rowGetrelset['CA5Title'] . '</th> <th>' . $rowGetrelset['CA6Title'] . '</th> <th>' . $rowGetrelset['CA7Title'] . '</th> <th>' . $rowGetrelset['CA8Title'] . '</th> <th>' . $rowGetrelset['CA9Title'] . '</th> <th>' . $rowGetrelset['CA10Title'] . '</th>';
                                    } else {
                                        $ca1test = '';
                                    }
                                } else {
                                    $ca1test = '';
                                }

                                ?>
                                <table class="table-bordered tab table-sm tb-result-border" style="width:98%;">

                                    <tr>
                                        <th>SUBJECT(s)</th>
                                        <?php echo $ca1test; ?>
                                        <?php echo $ca2test; ?>
                                        <?php echo $ca3test; ?>
                                        <?php echo $ca4test; ?>
                                        <?php echo $ca5test; ?>
                                        <?php echo $ca6test; ?>
                                        <?php echo $ca7test; ?>
                                        <?php echo $ca8test; ?>
                                        <?php echo $ca9test; ?>
                                        <?php echo $ca10test; ?>
                                        <th style="text-align:center">Exam</th>
                                        <th style="text-align:center">Total</th>
                                        <th style="text-align:center">Grade</th>
                                        <th style="width:90px;text-align:center">Lowest in Class</th>
                                        <th style="width:90px;text-align:center">Highest in Class</th>
                                        <th style="width:120px;text-align:center">Remark</th>
                                    </tr>

                                    <tbody>
                                        <?php

                                        $sqlsub = ("SELECT subjects.name AS name, subjects.id as id FROM `subject_group_class_sections` INNER JOIN subject_group_subjects ON subject_group_class_sections.subject_group_id=subject_group_subjects.subject_group_id INNER JOIN subjects ON subject_group_subjects.subject_id=subjects.id WHERE subject_group_class_sections.class_section_id = '$classsection' AND subject_group_class_sections.session_id='$session' AND subject_group_subjects.session_id='$session'");
                                        $resultsub = mysqli_query($link, $sqlsub);
                                        $rowGetsub = mysqli_fetch_assoc($resultsub);
                                        $row_cntsub = mysqli_num_rows($resultsub);

                                        $sqlgetscorecheck = ("SELECT * FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND SubjectID != '0' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'");
                                        $resultgetscorecheck = mysqli_query($link, $sqlgetscorecheck);
                                        $rowgetscorecheck = mysqli_fetch_assoc($resultgetscorecheck);
                                        $row_cntgetscorecheck = mysqli_num_rows($resultgetscorecheck);

                                        if ($row_cntgetscorecheck > 0) {

                                            // $sqlgetgadingmeth = ("SELECT * FROM `classordepartment` WHERE InstitutionID = '$institution' AND FacultyOrSchoolID='$facultyID' AND ClassOrDepartmentID = '$classid'");
                                            // $resultgetgadingmeth = mysqli_query($link, $sqlgetgadingmeth);
                                            // $rowgetgadingmeth = mysqli_fetch_assoc($resultgetgadingmeth);
                                            // $row_cntgetgadingmeth = mysqli_num_rows($resultgetgadingmeth);

                                            // $gradeid = $rowgetgadingmeth['GradingMethodID'] . '</br>';

                                            do {

                                                $subname = $rowGetsub['name'];
                                                $subid = $rowGetsub['id'];

                                                $sqlgetscore = ("SELECT * FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                                                $resultgetscore = mysqli_query($link, $sqlgetscore);
                                                $rowgetscore = mysqli_fetch_assoc($resultgetscore);
                                                $row_cntgetscore = mysqli_num_rows($resultgetscore);


                                                $sqlgetscore = ("SELECT * FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                                                $resultgetscore = mysqli_query($link, $sqlgetscore);
                                                $rowgetscore = mysqli_fetch_assoc($resultgetscore);
                                                $row_cntgetscore = mysqli_num_rows($resultgetscore);


                                                if ($row_cntgetscore > 0) {

                                                    if ($rowGetrelset['NumberOfCA'] == '1') {
                                                        $ca1 = $rowgetscore['ca1'];
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '2') {
                                                        $ca1 = $rowgetscore['ca1'] + $rowgetscore['ca2'];
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '3') {
                                                        $ca1 = $rowgetscore['ca1'] + $rowgetscore['ca2'] + $rowgetscore['ca3'];
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '4') {
                                                        $ca1 = $rowgetscore['ca1'] + $rowgetscore['ca2'] + $rowgetscore['ca3'] + $rowgetscore['ca4'];
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '5') {
                                                        $ca1 = $rowgetscore['ca1'] + $rowgetscore['ca2'] + $rowgetscore['ca3'] + $rowgetscore['ca4'] + $rowgetscore['ca5'];
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '6') {
                                                        $ca1 = $rowgetscore['ca1'] + $rowgetscore['ca2'] + $rowgetscore['ca3'] + $rowgetscore['ca4'] + $rowgetscore['ca5'] + $rowgetscore['ca6'];
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '7') {
                                                        $ca1 = $rowgetscore['ca1'] + $rowgetscore['ca2'] + $rowgetscore['ca3'] + $rowgetscore['ca4'] + $rowgetscore['ca5'] + $rowgetscore['ca6'] + $rowgetscore['ca7'];
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '8') {
                                                        $ca1 = $rowgetscore['ca1'] + $rowgetscore['ca2'] + $rowgetscore['ca3'] + $rowgetscore['ca4'] + $rowgetscore['ca5'] + $rowgetscore['ca6'] + $rowgetscore['ca7'] + $rowgetscore['ca8'];
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '9') {
                                                        $ca1 = $rowgetscore['ca1'] + $rowgetscore['ca2'] + $rowgetscore['ca3'] + $rowgetscore['ca4'] + $rowgetscore['ca5'] + $rowgetscore['ca6'] + $rowgetscore['ca7'] + $rowgetscore['ca8'] + $rowgetscore['ca9'];
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '10') {
                                                        $ca1 = $rowgetscore['ca1'] + $rowgetscore['ca2'] + $rowgetscore['ca3'] + $rowgetscore['ca4'] + $rowgetscore['ca5'] + $rowgetscore['ca6'] + $rowgetscore['ca7'] + $rowgetscore['ca8'] + $rowgetscore['ca9'] + $rowgetscore['ca10'];
                                                    } else {
                                                        $ca1 = 0;
                                                    }

                                                    $exam = $rowgetscore['exam'];

                                                    $total = $ca1 + $exam;

                                                    $subavg = $total;

                                                    $sqlgetgradstuc = ("SELECT * FROM `gradingstructure` INNER JOIN assigngradingtclass ON gradingstructure.GradingTitle = assigngradingtclass.GradingTitle WHERE $total >= RangeStart AND $total <= RangeEnd AND ClassID = '$classid'");
                                                    $resultgetgradstuc = mysqli_query($link, $sqlgetgradstuc);
                                                    $rowgetgradstuc = mysqli_fetch_assoc($resultgetgradstuc);
                                                    $row_cntgetgradstuc = mysqli_num_rows($resultgetgradstuc);

                                                    if ($row_cntgetgradstuc > 0) {
                                                        $grade = $rowgetgradstuc['Grade'];
                                                        $remark = $rowgetgradstuc['Remark'];

                                                        $sqlsunnyhihhscoreunamepersub = "SELECT DISTINCT(StudentID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS total FROM score WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' AND SubjectID = '$subid' GROUP BY StudentID order by total DESC LIMIT 1";
                                                        $resultsunnyhihhscoreunamepersub = mysqli_query($link, $sqlsunnyhihhscoreunamepersub);
                                                        $rowsunnyhihhscoreunamepersub = mysqli_fetch_assoc($resultsunnyhihhscoreunamepersub);
                                                        $row_cntsunnyhihhscoreunamepersub = mysqli_num_rows($resultsunnyhihhscoreunamepersub);

                                                        $sunhihscrunpersub = round($rowsunnyhihhscoreunamepersub['total']);

                                                        $sqlsunnylowwscoreunamepersub = "SELECT DISTINCT(StudentID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS total FROM score WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' AND SubjectID = '$subid' GROUP BY StudentID order by total ASC LIMIT 1";
                                                        $resultsunnylowwscoreunamepersub = mysqli_query($link, $sqlsunnylowwscoreunamepersub);
                                                        $rowsunnylowwscoreunamepersub = mysqli_fetch_assoc($resultsunnylowwscoreunamepersub);
                                                        $row_cntsunnylowwscoreunamepersub = mysqli_num_rows($resultsunnylowwscoreunamepersub);

                                                        $sunlowscrunpersub = round($rowsunnylowwscoreunamepersub['total']);

                                                        $sqlgetscorepos = "SELECT * FROM (SELECT *, @n := @n + 1 n FROM (SELECT SUM(exam+ca1+ca2+ca3+ca4+ca5) AS total, UserRegNumberOrUsername FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND InstitutionID = '$institution' AND ClassOrDepartmentID = '$classid' AND Session = '$sunnyresultsession' AND CourseOrSubjectID= '$subid' GROUP BY UserRegNumberOrUsername ORDER BY total DESC) as sunny, (SELECT @n := 0) as m) as sunito WHERE sunito.UserRegNumberOrUsername='$regno'";
                                                        $resultgetscorepos = mysqli_query($link, $sqlgetscorepos);
                                                        $rowgetscorepos = mysqli_fetch_assoc($resultgetscorepos);
                                                        $row_cntgetscorepos = mysqli_num_rows($resultgetscorepos);

                                                        $sqlgetsubper = "SELECT SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS average FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND InstitutionID = '$institution' AND ClassOrDepartmentID = '$classid' AND CourseOrSubjectID = '$subid' AND Session = '$sunnyresultsession' AND TermOrSemester = '$sunnyrelterm' ORDER BY exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10";
                                                        $resultgetsubper = mysqli_query($link, $sqlgetsubper);
                                                        $rowgetsubper = mysqli_fetch_assoc($resultgetsubper);
                                                        $row_cntgetsubper = mysqli_num_rows($resultgetsubper);

                                                        $getsubper = round($rowgetsubper['average'] / $row_cntClasscount, 2);

                                                        $getsco = round($rowgetscorepos['total'], 2);

                                                        $getscorpos = $rowgetscorepos['n'];
                                                    } else {
                                                    }

                                                    echo '<tr>
                                                                    <th>' . $subname . '</th>';
                                                    if ($rowGetrelset['NumberOfCA'] == '1') {
                                                        echo '<td>
                                                                                ' . $rowgetscore["ca1"] . '

                                                                                </td>';
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '2') {
                                                        echo '<td>
                                                                                ' . $rowgetscore["ca1"] . '

                                                                                </td>
                                                                                <td>
                                                                                ' . $rowgetscore["ca2"] . '

                                                                                </td>';
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '3') {
                                                        echo '<td>
                                                                            ' . $rowgetscore["ca1"] . '

                                                                            </td>
                                                                            <td>
                                                                            ' . $rowgetscore["ca2"] . '

                                                                            </td>
                                                                            <td>
                                                                            ' . $rowgetscore["ca3"] . '

                                                                            </td>';
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '4') {
                                                        echo '<td>
                                                                                ' . $rowgetscore["ca1"] . '

                                                                                </td>
                                                                                <td>
                                                                                ' . $rowgetscore["ca2"] . '

                                                                                </td>
                                                                                <td>
                                                                            ' . $rowgetscore["ca3"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca4"] . '

                                                                            </td>';
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '5') {
                                                        echo '<td>
                                                                                ' . $rowgetscore["ca1"] . '

                                                                                </td>
                                                                                <td>
                                                                                ' . $rowgetscore["ca2"] . '

                                                                                </td>
                                                                                <td>
                                                                            ' . $rowgetscore["ca3"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca4"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca5"] . '
                                                                            </td>';
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '6') {
                                                        echo '<td>
                                                                                ' . $rowgetscore["ca1"] . '

                                                                                </td>
                                                                                <td>
                                                                                ' . $rowgetscore["ca2"] . '

                                                                                </td>
                                                                                <td>
                                                                            ' . $rowgetscore["ca3"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca4"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca5"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca6"] . '

                                                                            </td>';
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '7') {
                                                        echo '<td>
                                                                                ' . $rowgetscore["ca1"] . '

                                                                                </td>
                                                                                <td>
                                                                                ' . $rowgetscore["ca2"] . '

                                                                                </td>
                                                                                <td>
                                                                            ' . $rowgetscore["ca3"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca4"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca5"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca6"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca7"] . '

                                                                            </td>';
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '8') {
                                                        echo '<td>
                                                                                ' . $rowgetscore["ca1"] . '

                                                                                </td>
                                                                                <td>
                                                                                ' . $rowgetscore["ca2"] . '

                                                                                </td>
                                                                                <td>
                                                                            ' . $rowgetscore["ca3"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca4"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca5"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca6"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca7"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca8"] . '

                                                                            </td>';
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '9') {
                                                        echo '<td>
                                                                                ' . $rowgetscore["ca1"] . '

                                                                                </td>
                                                                                <td>
                                                                                ' . $rowgetscore["ca2"] . '

                                                                                </td>
                                                                                <td>
                                                                            ' . $rowgetscore["ca3"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca4"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca5"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca6"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca7"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca8"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca9"] . '

                                                                            </td>';
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '10') {
                                                        echo '<td>
                                                                                ' . $rowgetscore["ca1"] . '

                                                                                </td>
                                                                                <td>
                                                                                ' . $rowgetscore["ca2"] . '

                                                                                </td>
                                                                                <td>
                                                                            ' . $rowgetscore["ca3"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca4"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca5"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca6"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca7"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca8"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca9"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca10"] . '

                                                                            </td>';
                                                    } else {
                                                    }
                                                    echo '
                                                                    <td>' . $exam . '</td>
                                                                    <td>' . $total . '</td>
                                                                    <td>' . $grade . '</td>
                                                                    <td>' . $sunlowscrunpersub . '</td>
                                                                    <td>' . $sunhihscrunpersub . '</td>
                                                                    <td>' . $remark . '</td>
                                                                </tr>';
                                                } else {
                                                }
                                            } while ($rowGetsub = mysqli_fetch_assoc($resultsub));
                                        } else {
                                            echo '<tr><td align="center" colspan="15" style="font-size: calc(12px + (18 - 12) * ((100vw - 300px) / (1600 - 300)))"><div class="alert alert-info alert-dismissible mb-2" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>No Result Yet</div></tr></td>';
                                        }

                                        ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php
                            $sqlresumdateOld = ("SELECT * FROM `resumptiondate` WHERE `Session`='$session' AND `Term`='$term'");

                            $resultresumdateOld = mysqli_query($link, $sqlresumdateOld);
                            $getresumdateOld = mysqli_fetch_assoc($resultresumdateOld);
                            $sqlrow_cntresumdateOld = mysqli_num_rows($resultresumdateOld);

                            if ($sqlrow_cntresumdateOld > 0) {
                                $resumdateOld = $getresumdateOld['Date'];
                            } else {
                                $resumdateOld = 'N/A';
                            }

                            $sqlgetsubscore = ("SELECT * FROM `score` WHERE (`ca1` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND SubjectID = '0' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'");
                            $resultgetsubscore = mysqli_query($link, $sqlgetsubscore);
                            $rowgetsubscore = mysqli_fetch_assoc($resultgetsubscore);
                            $row_cntgetsubscore = mysqli_num_rows($resultgetsubscore);
                            if ($row_cntgetsubscore > 0) {

                                $rowcountfixedgennew = $rowgetsubscore['ca1'];
                                $rowcountfixedpresent = $rowgetsubscore['ca2'];
                                $rowcountfixedlate = $rowgetsubscore['ca3'];
                                $rowcountfixedabsent = $rowgetsubscore['ca4'];
                            } else {
                                $sqlgettechgen = mysqli_query($link, "SELECT DISTINCT(student_attendences.`date`) FROM `student_attendences` WHERE student_attendences.`session` = '$session' AND student_attendences.`term` = '$term2'");
                                $fetchfixedgen = mysqli_fetch_assoc($sqlgettechgen);
                                $rowcountfixedgen = mysqli_num_rows($sqlgettechgen);

                                if ($rowcountfixedgen == NULL || $rowcountfixedgen == '') {
                                    $rowcountfixedgennew = 0;
                                } else {
                                    $rowcountfixedgennew = $rowcountfixedgen;
                                }

                                $sqlgettechpresent = mysqli_query($link, "SELECT * FROM `student_attendences` INNER JOIN student_session ON student_attendences.student_session_id=student_session.id WHERE student_attendences.`session` = '$session' AND student_attendences.`term` = '$term2' AND student_session.`student_id`='$id' AND student_session.`session_id`='$session' AND student_session.`class_id`='$classid' AND `attendence_type_id`='1'");
                                $fetchfixedpresent = mysqli_fetch_assoc($sqlgettechpresent);
                                $rowcountfixedpresent = mysqli_num_rows($sqlgettechpresent);

                                if ($rowcountfixedpresent == NULL || $rowcountfixedpresent == '') {
                                    $rowcountfixedpresent = 0;
                                } else {
                                    $rowcountfixedpresent = $rowcountfixedpresent;
                                }

                                $sqlgettechabsent = mysqli_query($link, "SELECT * FROM `student_attendences` INNER JOIN student_session ON student_attendences.student_session_id=student_session.id WHERE student_attendences.`session` = '$session' AND student_attendences.`term` = '$term2' AND student_session.`student_id`='$id' AND student_session.`session_id`='$session' AND student_session.`class_id`='$classid' AND `attendence_type_id`='4'");
                                $fetchfixedabsent = mysqli_fetch_assoc($sqlgettechabsent);
                                $rowcountfixedabsent = mysqli_num_rows($sqlgettechabsent);

                                if ($rowcountfixedabsent == NULL || $rowcountfixedabsent == '') {
                                    $rowcountfixedabsent = 0;
                                } else {
                                    $rowcountfixedabsent = $rowcountfixedabsent;
                                }
                            }
                            ?>
                            <div align="center" class="summDD">
                                <p>Total Score: <?php echo $gettotscore; ?> </p>
                                <p>Average Score: <?php echo $gettotgrade; ?> </p>
                                <p>Class Average: <?php echo $decStubsubavg; ?> </p>
                                <p>No. of Subjects: <?php echo $row_cntgetscorecheck; ?></p>
                            </div>


                            <div class="performance">
                                <div class="row">
                                    <div class="col-4">
                                        <div class="containerForChart" style="border:0px solid black">

                                            <canvas class="newgraph" id="mysunChart" style="width:100%;"></canvas>

                                        </div>
                                    </div>
                                    <div class="col-8" style="padding-right: 0px">
                                        <div class="container-motto" style="margin-right: 20px;border:0px solid red;">
                                            <div class="result" style="margin: 10px; display: flex; align-items: flex-start; gap: 20px; border: 0px solid red;">
                                                <table class="tab table-sm" style="width: 37%; table-layout: auto; border:0px solid red;">
                                                    <tr>
                                                        <th colspan="4" style="text-align: center;">AFFECTIVE DOMAIN </th>
                                                    </tr>
                                                    <tbody>
                                                        <?php

                                                        $sqlrelset = ("SELECT * FROM `affective_domain_settings` INNER JOIN assignsaftoclass ON affective_domain_settings.id=assignsaftoclass.AffectiveDomainSettingsId WHERE ClassID = '$classid'");
                                                        $resultrelset = mysqli_query($link, $sqlrelset);
                                                        $rowGetrelset = mysqli_fetch_assoc($resultrelset);
                                                        $row_cntrelset = mysqli_num_rows($resultrelset);

                                                        if ($row_cntrelset > 0) {
                                                            $sqlgetscore = ("SELECT * FROM `affective_domain_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND term = '$term' AND sectionid = '$classsectionactual'");
                                                            $resultgetscore = mysqli_query($link, $sqlgetscore);
                                                            $rowgetscore = mysqli_fetch_assoc($resultgetscore);
                                                            $row_cntgetscore = mysqli_num_rows($resultgetscore);

                                                            if ($row_cntgetscore > 0) {
                                                                if ($rowGetrelset['NumberofAD'] == '1') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '2') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '3') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '4') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '5') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '6') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '7') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '8') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '9') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '10') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '11') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '12') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain12"] . '</td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '13') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain12"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD13Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain13"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '14') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain12"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD13Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain13"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD14Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain14"] . '</td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '15') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain12"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD13Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain13"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD14Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain14"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD15Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain15"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } else {
                                                                }
                                                            } else {
                                                                echo '<tr><td align="center" colspan="15" style="font-size: calc(12px + (18 - 12) * ((100vw - 300px) / (1600 - 300)))"><div class="alert alert-info alert-dismissible mb-2" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>No Result Yet</div></tr></td>';
                                                            }
                                                        }
                                                        ?>
                                                    </tbody>
                                                </table>
                                                <table class="tab table-sm" style="width: 18%; table-layout: auto; border:0px solid red;">
                                                    <tr>
                                                        <th colspan="2" style="text-align: center;">ATTENDANCE</th>
                                                    </tr>
                                                    <tbody>
                                                        <tr>
                                                            <th>TOTAL DAYS</th>
                                                            <td> <?php echo $rowcountfixedgennew; ?></td>
                                                        </tr>

                                                        <tr>
                                                            <th>PRESENT</th>
                                                            <td><?php echo $rowcountfixedpresent; ?></td>
                                                        </tr>

                                                        <tr>
                                                            <th>ABSENT</th>
                                                            <td><?php echo $rowcountfixedabsent; ?></td>
                                                        </tr>

                                                        <tr>
                                                            <th>LATE</th>
                                                            <td><?php echo $rowcountfixedlate; ?></td>
                                                        </tr>

                                                    </tbody>
                                                </table>
                                                <table class="tab table-sm" style="width: 37%; table-layout: auto; border:0px solid red;">
                                                    <tr>
                                                        <th colspan="4" style="text-align: center;">PSYCOMOTOR</th>
                                                    </tr>
                                                    <tbody>
                                                        <?php

                                                        $sqlrelset = ("SELECT * FROM `psycomotor_settings` INNER JOIN assignspsycomotortoclass ON psycomotor_settings.id=assignspsycomotortoclass.PsycomotorSettingsId WHERE ClassID = '$classid'");
                                                        $resultrelset = mysqli_query($link, $sqlrelset);
                                                        $rowGetrelset = mysqli_fetch_assoc($resultrelset);
                                                        $row_cntrelset = mysqli_num_rows($resultrelset);

                                                        if ($row_cntrelset > 0) {
                                                            $sqlgetscore = ("SELECT * FROM `psycomotor_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND term = '$term' AND sectionid = '$classsectionactual'");
                                                            $resultgetscore = mysqli_query($link, $sqlgetscore);
                                                            $rowgetscore = mysqli_fetch_assoc($resultgetscore);
                                                            $row_cntgetscore = mysqli_num_rows($resultgetscore);

                                                            if ($row_cntgetscore > 0) {
                                                                if ($rowGetrelset['NumberofP'] == '1') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '2') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '3') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '4') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '5') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '6') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '7') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '8') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '9') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '10') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <th>' . $rowGetrelset['P10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '11') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <th>' . $rowGetrelset['P10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <th>' . $rowGetrelset['P11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '12') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <th>' . $rowGetrelset['P10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <th>' . $rowGetrelset['P11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <th>' . $rowGetrelset['P12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor12"] . '</td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '13') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <th>' . $rowGetrelset['P11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <th>' . $rowGetrelset['P12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor12"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <th>' . $rowGetrelset['P13Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor13"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '14') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <th>' . $rowGetrelset['P11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <th>' . $rowGetrelset['P12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor12"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <th>' . $rowGetrelset['P13Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor13"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                        <th>' . $rowGetrelset['P14Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor14"] . '</td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '15') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <th>' . $rowGetrelset['P12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor12"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <th>' . $rowGetrelset['P13Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor13"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <th>' . $rowGetrelset['P14Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor14"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                        <th>' . $rowGetrelset['P15Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor15"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } else {
                                                                }
                                                            } else {
                                                                echo '<tr><td align="center" colspan="15" style="font-size: calc(12px + (18 - 12) * ((100vw - 300px) / (1600 - 300)))"><div class="alert alert-info alert-dismissible mb-2" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>No Result Yet</div></tr></td>';
                                                            }
                                                        }
                                                        ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>


                                    <?php

                                    $sqlgettechremark = mysqli_query($link, "SELECT * FROM `remark` WHERE `RemarkType` = 'teacher' AND `StudentID` = '$id' AND `Session` = '$session' AND `Term` = '$term' AND `remark` != ''");
                                    $rowcountfixedremark = mysqli_num_rows($sqlgettechremark);
                                    $fetchfixedremark = mysqli_fetch_assoc($sqlgettechremark);


                                    if ($rowcountfixedremark > 0) {
                                        $teacherRemark = $fetchfixedremark['remark'];

                                        $teacherid = $fetchfixedremark['StaffID'];

                                        $sqlgetheadteachsign = ("SELECT * FROM `staffsignature` WHERE staff_id = $teacherid");
                                        $resultgetheadteachsign = mysqli_query($link, $sqlgetheadteachsign);
                                        $rowgetheadteachsign = mysqli_fetch_assoc($resultgetheadteachsign);
                                        $row_cntgetheadteachsign = mysqli_num_rows($resultgetheadteachsign);


                                        if ($row_cntgetheadteachsign > 0) {
                                            $hedteachsign = '<img src="../img/signature/' . $rowgetheadteachsign['Signature'] . '" align="center" class="img-fluid" style="width: 70%;">';
                                        } else {
                                            $hedteachsign = '';
                                        }
                                    } else {

                                        $sqlgetteachremark = ("SELECT * FROM `defaultcomment` INNER JOIN class_teacher ON defaultcomment.PrincipalOrDeanOrHeadTeacherUserID=class_teacher.staff_id WHERE $gettotgrade BETWEEN RangeStart AND RangeEnd AND CommentType = 'teacher' AND class_id = '$classid'");
                                        $resultgetteachremark = mysqli_query($link, $sqlgetteachremark);
                                        $rowgetteachremark = mysqli_fetch_assoc($resultgetteachremark);
                                        $row_cntgetteachremark = mysqli_num_rows($resultgetteachremark);

                                        if ($row_cntgetteachremark > 0) {
                                            $teacherRemark = $rowgetteachremark['DefaultComment'];

                                            $teacherid = $rowgetteachremark['PrincipalOrDeanOrHeadTeacherUserID'];


                                            $sqlgetheadteachsign = ("SELECT * FROM `staffsignature` WHERE staff_id = '$teacherid'");
                                            $resultgetheadteachsign = mysqli_query($link, $sqlgetheadteachsign);
                                            $rowgetheadteachsign = mysqli_fetch_assoc($resultgetheadteachsign);
                                            $row_cntgetheadteachsign = mysqli_num_rows($resultgetheadteachsign);


                                            if ($row_cntgetheadteachsign > 0) {
                                                $hedteachsign = '<img src="../img/signature/' . $rowgetheadteachsign['Signature'] . '" align="center" class="img-fluid" style="width: 70%;">';
                                            } else {
                                                $hedteachsign = '';
                                            }
                                        } else {
                                            $teacherRemark = 'N/A';

                                            $hedteachsign = '';
                                        }
                                    }

                                    $sqlgetprincremark = mysqli_query($link, "SELECT * FROM `remark` WHERE `RemarkType` = 'SchoolHead' AND `StudentID`='$id' AND `Session`='$session' AND `Term`='$term' AND `remark`!=''");
                                    $rowcountprinfixedremark = mysqli_num_rows($sqlgetprincremark);
                                    $fetchfixedprinremark = mysqli_fetch_assoc($sqlgetprincremark);

                                    if ($rowcountprinfixedremark > 0) {
                                        $principalRemark = $fetchfixedprinremark['remark'];

                                        $headteacherid = $fetchfixedprinremark['staff_id'];

                                        $sqlgetheadteachhead = ("SELECT * FROM `staffsignature` WHERE staff_id = 5");
                                        $resultgetheadteachsignhead = mysqli_query($link, $sqlgetheadteachhead);
                                        $rowgetheadteachsignhead = mysqli_fetch_assoc($resultgetheadteachsignhead);
                                        $row_cntgetheadteachsignhead = mysqli_num_rows($resultgetheadteachsignhead);

                                        if ($row_cntgetheadteachsignhead > 0) {
                                            $hedteachsignhead = '<img src="' . $defRUl . 'img/signature/' . $rowgetheadteachsignhead['Signature'] . '" align="center" class="img-fluid" style="width: 70%;">';
                                        } else {
                                            $hedteachsignhead = '';
                                        }
                                    } else {

                                        $sqlgetprincremark = ("SELECT * FROM `defaultcomment` WHERE $gettotgrade BETWEEN RangeStart AND RangeEnd AND CommentType = 'SchoolHead'");
                                        $resultgetprincremark = mysqli_query($link, $sqlgetprincremark);
                                        $rowgetteachremark = mysqli_fetch_assoc($resultgetprincremark);
                                        $row_cntgetprincremark = mysqli_num_rows($resultgetprincremark);

                                        if ($row_cntgetprincremark > 0) {
                                            $principalRemark = $rowgetteachremark['DefaultComment'];

                                            $headteacherid = $rowgetteachremark['PrincipalOrDeanOrHeadTeacherUserID'];

                                            $sqlgetheadteachhead = ("SELECT * FROM `staffsignature` WHERE staff_id = '$headteacherid'");
                                            $resultgetheadteachsignhead = mysqli_query($link, $sqlgetheadteachhead);
                                            $rowgetheadteachsignhead = mysqli_fetch_assoc($resultgetheadteachsignhead);
                                            $row_cntgetheadteachsignhead = mysqli_num_rows($resultgetheadteachsignhead);

                                            if ($row_cntgetheadteachsignhead > 0) {
                                                $hedteachsignhead = '<img src=" https://schoollift.s3.us-east-2.amazonaws.com/' . $rowgetheadteachsignhead['Signature'] . '" align="center" class="img-fluid" style="width: 80%;">';
                                            } else {
                                                $hedteachsignhead = '';
                                            }
                                        } else {
                                            $principalRemark = 'N/A';

                                            $hedteachsignhead = '';
                                        }
                                    }

                                    if ($term == '1st') {
                                        $termnew = '2nd';

                                        $sessionnew = $session;
                                    } elseif ($term == '2nd') {
                                        $termnew = '3rd';
                                        $sessionnew = $session;
                                    } else {
                                        $termnew = '1st';
                                        $sessionnew = $session + 1;
                                    }

                                    $sqlresumdate = ("SELECT * FROM `resumptiondate` WHERE `Session`='$sessionnew' AND `Term`='$termnew'");

                                    $resultresumdate = mysqli_query($link, $sqlresumdate);
                                    $getresumdate = mysqli_fetch_assoc($resultresumdate);
                                    $sqlrow_cntresumdate = mysqli_num_rows($resultresumdate);

                                    if ($sqlrow_cntresumdate > 0) {
                                        $resumdate = date("l jS \of F Y", strtotime($getresumdate['Date']));
                                    } else {
                                        $resumdate = 'N/A';
                                    }
                                    ?>

                                </div>
                                <p style="text-align:center;margin-top:-10px"><b>SCALE: Excellent 05, Good 04, Fair 03, Poor 02, None 01</b></p>
                            </div>

                            <div class="performance">
                                <div class="row">
                                    <div class="col-12">

                                        <div class="container-motto" style="border:0px solid black">
                                            <div style="margin: 20px;">
                                                <div class="row">
                                                    <div class="col-sm-10 col-md-10">
                                                        <div align="center">
                                                            <p style="text-align: justify;"><b style="font-weight:600;">CLASS TEACHER'S COMMENT:</b>&nbsp;<?php echo $teacherRemark; ?></p>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-2 col-md-2">
                                                        <div align="center">
                                                            <?php echo $hedteachsign; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-sm-10 col-md-10">
                                                        <div>
                                                            <p style="text-align: justify;"><b style="font-weight:600;">PRINCIPAL/HEAD TEACHER'S COMMENT:</b>&nbsp;&nbsp;&nbsp;<?php echo $principalRemark; ?></p>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-2 col-md-2">
                                                        <div>
                                                            <?php echo $hedteachsignhead; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-sm-12 col-md-12">
                                                        <div>
                                                            <p style="text-align: justify;"><b style="font-weight:600;">NEXT TERM BEGINS:</b>&nbsp;<?php echo $resumdate; ?></b></p>
                                                            <?php
                                                            if ($next_fee > 0) {
                                                                echo '<p style="text-align: center;"><b style="font-weight:600;">NEXT TERM FEEs: N' . $next_fee . '</b></p>';
                                                            }
                                                            ?>

                                                        </div>
                                                    </div>

                                                </div>

                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            <?php
                            /*
                                            if($term == '3rd')
                                            {

                                                $sessionnew = $session + 1;

                                                $sqlstudent_session = ("SELECT * FROM `student_session` WHERE `session_id`='$sessionnew' AND student_id = '$id'");

                                                $resultstudent_session = mysqli_query($link, $sqlstudent_session);
                                                $getstudent_session = mysqli_fetch_assoc($resultstudent_session);
                                                $sqlrow_cntstudent_session = mysqli_num_rows($resultstudent_session);

                                                if($sqlrow_cntstudent_session > 0)
                                                {
                                                    echo '<center>
                                                        <p class="promoted">PROMOTED TO THE NEXT CLASS</p>
                                                    </center>';
                                                }
                                                else
                                                {
                                                    echo '<center>
                                                        <p class="promoted">NOT PROMOTED TO THE NEXT CLASS</p>
                                                    </center>';
                                                }

                                            }
                                            else
                                            {

                                            }
                                            */
                            ?>

                        <?php
                        } elseif ($reltype == 'numeric') {
                        ?>
                            <div class="container-motto">
                                <?php
                                $sqlgetsubscore = ("SELECT * FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND SubjectID != '0' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'");
                                $resultgetsubscore = mysqli_query($link, $sqlgetsubscore);
                                $rowgetsubscore = mysqli_fetch_assoc($resultgetsubscore);
                                $row_cntgetsubscore = mysqli_num_rows($resultgetsubscore);

                                $sqlgettotalgrade = "SELECT SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS average FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND SubjectID != '0' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'";
                                $resultgettotalgrade = mysqli_query($link, $sqlgettotalgrade);
                                $rowgettotalgrade = mysqli_fetch_assoc($resultgettotalgrade);
                                $row_cntgettotalgrade = mysqli_num_rows($resultgettotalgrade);

                                $gettotgrade = floatval(round($rowgettotalgrade['average'] / $row_cntgetsubscore, 2));

                                $gettotscore = $rowgettotalgrade['average'];

                                $sqlgetClasscount = ("SELECT DISTINCT(StudentID) FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'");
                                $resultgetClasscount = mysqli_query($link, $sqlgetClasscount);
                                $rowgetClasscount = mysqli_fetch_assoc($resultgetClasscount);
                                $row_cntClasscount = mysqli_num_rows($resultgetClasscount);

                                $sqlgetsubscoreALL = ("SELECT DISTINCT(SubjectID) FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'");
                                $resultgetsubscoreALL = mysqli_query($link, $sqlgetsubscoreALL);
                                $rowgetsubscoreALL = mysqli_fetch_assoc($resultgetsubscoreALL);
                                $row_cntgetsubscoreALL = mysqli_num_rows($resultgetsubscoreALL);

                                $sqlgettotclassscor = "SELECT SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS totalScore FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' ORDER BY exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10";
                                $resultgettotclassscor = mysqli_query($link, $sqlgettotclassscor);
                                $rowgettotclassscor = mysqli_fetch_assoc($resultgettotclassscor);
                                $row_cntgettotclassscor = mysqli_num_rows($resultgettotclassscor);

                                $totsubjects = $row_cntClasscount * $row_cntgetsubscore;
                                $totsubjectsALL = $row_cntClasscount * $row_cntgetsubscoreALL;

                                $decStubsubavg = round($rowgettotclassscor['totalScore'] / $totsubjectsALL, 2);

                                $sqlsunnyhihhscoreuname = "SELECT DISTINCT(StudentID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10),COUNT(ID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) / COUNT(ID) AS total FROM score WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' GROUP BY StudentID order by total DESC LIMIT 1";
                                $resultsunnyhihhscoreuname = mysqli_query($link, $sqlsunnyhihhscoreuname);
                                $rowsunnyhihhscoreuname = mysqli_fetch_assoc($resultsunnyhihhscoreuname);
                                $row_cntsunnyhihhscoreuname = mysqli_num_rows($resultsunnyhihhscoreuname);

                                $sunhihscrun = round($rowsunnyhihhscoreuname['total'], 2);

                                $sqlsunnylowwscoreuname = "SELECT DISTINCT(StudentID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10),COUNT(ID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) / COUNT(ID) AS total FROM score WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' GROUP BY StudentID order by total ASC LIMIT 1";
                                $resultsunnylowwscoreuname = mysqli_query($link, $sqlsunnylowwscoreuname);
                                $rowsunnylowwscoreuname = mysqli_fetch_assoc($resultsunnylowwscoreuname);
                                $row_cntsunnylowwscoreuname = mysqli_num_rows($resultsunnylowwscoreuname);

                                $sunlowscrun = round($rowsunnylowwscoreuname['total'], 2);

                                $sqlgetscoretotalscorpositon = "SELECT * FROM (SELECT *, @n := @n + 1 n FROM (SELECT SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS total, StudentID FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' GROUP BY StudentID ORDER BY total DESC) as sunny, (SELECT @n := 0) as m) as sunito WHERE sunito.StudentID='$id'";
                                $resultgetscoretotalscorpositon = mysqli_query($link, $sqlgetscoretotalscorpositon);
                                $rowgetscoretotalscorpositon = mysqli_fetch_assoc($resultgetscoretotalscorpositon);
                                $row_cntgetscoretotalscorpositon = mysqli_num_rows($resultgetscoretotalscorpositon);

                                $gettotalscorpositon = $rowgetscoretotalscorpositon['n'];

                                function addOrdinalNumberSuffix($num)
                                {
                                    if (!in_array(($num % 100), array(11, 12, 13))) {
                                        switch ($num % 10) {
                                                // Handle 1st, 2nd, 3rd
                                            case 1:
                                                return $num . 'st';
                                            case 2:
                                                return $num . 'nd';
                                            case 3:
                                                return $num . 'rd';
                                        }
                                    }
                                    return $num . 'th';
                                }

                                if ($row_cntgettotalgrade > 0) {
                                    $sqlgettotgradstuc = ("SELECT * FROM `gradingstructure` INNER JOIN assigngradingtclass ON gradingstructure.GradingTitle = assigngradingtclass.GradingTitle WHERE $gettotgrade >= RangeStart AND $gettotgrade <= RangeEnd AND ClassID = '$classid'");
                                    $resultgettotgradstuc = mysqli_query($link, $sqlgettotgradstuc);
                                    $rowgettotgradstuc = mysqli_fetch_assoc($resultgettotgradstuc);
                                    $row_cntgettotgradstuc = mysqli_num_rows($resultgettotgradstuc);

                                    if ($row_cntgettotgradstuc > 0) {

                                        $totscorgrade = $rowgettotgradstuc['Grade'];
                                    } else {

                                        $totscorgrade = 'NA';
                                    }
                                } else {
                                    $gettotgrade = 'NA';
                                }

                                ?>
                                <div class="row" style="margin: 10px;">
                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>NAME:</b> <?php echo $studname; ?></h5>
                                    </div>

                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>CLASS:</b> <?php echo $studclass . ' ' . $studsection; ?></h5>
                                    </div>

                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>SEX:</b> <?php echo $studgender; ?></h5>
                                    </div>
                                </div>

                                <div class="row" style="margin: 10px;">
                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>CLASS POSITION:</b> <?php
                                                                                            echo addOrdinalNumberSuffix($gettotalscorpositon) . "\t";

                                                                                            if ($gettotalscorpositon % 10 == 0) {
                                                                                                echo "\n";
                                                                                            }
                                                                                            ?> </h5>
                                    </div>

                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>HIGHEST IN CLASS AVE:</b> <?php echo $sunhihscrun; ?></h5>
                                    </div>

                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>LOWEST IN CLASS AVE:</b> <?php echo $sunlowscrun; ?></h5>
                                    </div>

                                </div>


                            </div>

                            <div align="center">
                                <h5 style="font-size: 18px; font-weight: 800; color: #000000; margin-bottom: 0px;">ACADEMIC PERFORMANCE </h5>
                            </div>

                            <div class="result table-responsive" style="margin: 10px; margin-top: 5px;">
                                <?php

                                $sqlrelset = ("SELECT * FROM `resultsetting` INNER JOIN assigncatoclass ON resultsetting.ResultSettingID=assigncatoclass.ResultSettingID WHERE ClassID = '$classid'");
                                $resultrelset = mysqli_query($link, $sqlrelset);
                                $rowGetrelset = mysqli_fetch_assoc($resultrelset);
                                $row_cntrelset = mysqli_num_rows($resultrelset);

                                if ($row_cntrelset > 0) {
                                    if ($rowGetrelset['NumberOfCA'] == '1') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th>';
                                    } elseif ($rowGetrelset['NumberOfCA'] == '2') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th> <th>' . $rowGetrelset['CA2Title'] . '</th>';
                                    } elseif ($rowGetrelset['NumberOfCA'] == '3') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th> <th>' . $rowGetrelset['CA2Title'] . '</th> <th>' . $rowGetrelset['CA3Title'] . '</th>';
                                    } elseif ($rowGetrelset['NumberOfCA'] == '4') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th> <th>' . $rowGetrelset['CA2Title'] . '</th> <th>' . $rowGetrelset['CA3Title'] . '</th> <th>' . $rowGetrelset['CA4Title'] . '</th>';
                                    } elseif ($rowGetrelset['NumberOfCA'] == '5') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th> <th>' . $rowGetrelset['CA2Title'] . '</th> <th>' . $rowGetrelset['CA3Title'] . '</th> <th>' . $rowGetrelset['CA4Title'] . '</th> <th>' . $rowGetrelset['CA5Title'] . '</th>';
                                    } elseif ($rowGetrelset['NumberOfCA'] == '6') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th> <th>' . $rowGetrelset['CA2Title'] . '</th> <th>' . $rowGetrelset['CA3Title'] . '</th> <th>' . $rowGetrelset['CA4Title'] . '</th> <th>' . $rowGetrelset['CA5Title'] . '</th> <th>' . $rowGetrelset['CA6Title'] . '</th>';
                                    } elseif ($rowGetrelset['NumberOfCA'] == '7') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th> <th>' . $rowGetrelset['CA2Title'] . '</th> <th>' . $rowGetrelset['CA3Title'] . '</th> <th>' . $rowGetrelset['CA4Title'] . '</th> <th>' . $rowGetrelset['CA5Title'] . '</th> <th>' . $rowGetrelset['CA6Title'] . '</th> <th>' . $rowGetrelset['CA7Title'] . '</th>';
                                    } elseif ($rowGetrelset['NumberOfCA'] == '8') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th> <th>' . $rowGetrelset['CA2Title'] . '</th> <th>' . $rowGetrelset['CA3Title'] . '</th> <th>' . $rowGetrelset['CA4Title'] . '</th> <th>' . $rowGetrelset['CA5Title'] . '</th> <th>' . $rowGetrelset['CA6Title'] . '</th> <th>' . $rowGetrelset['CA7Title'] . '</th> <th>' . $rowGetrelset['CA8Title'] . '</th>';
                                    } elseif ($rowGetrelset['NumberOfCA'] == '9') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th> <th>' . $rowGetrelset['CA2Title'] . '</th> <th>' . $rowGetrelset['CA3Title'] . '</th> <th>' . $rowGetrelset['CA4Title'] . '</th> <th>' . $rowGetrelset['CA5Title'] . '</th> <th>' . $rowGetrelset['CA6Title'] . '</th> <th>' . $rowGetrelset['CA7Title'] . '</th> <th>' . $rowGetrelset['CA8Title'] . '</th> <th>' . $rowGetrelset['CA9Title'] . '</th>';
                                    } elseif ($rowGetrelset['NumberOfCA'] == '10') {
                                        $ca1test = '<th>' . $rowGetrelset['CA1Title'] . '</th> <th>' . $rowGetrelset['CA2Title'] . '</th> <th>' . $rowGetrelset['CA3Title'] . '</th> <th>' . $rowGetrelset['CA4Title'] . '</th> <th>' . $rowGetrelset['CA5Title'] . '</th> <th>' . $rowGetrelset['CA6Title'] . '</th> <th>' . $rowGetrelset['CA7Title'] . '</th> <th>' . $rowGetrelset['CA8Title'] . '</th> <th>' . $rowGetrelset['CA9Title'] . '</th> <th>' . $rowGetrelset['CA10Title'] . '</th>';
                                    } else {
                                        $ca1test = '';
                                    }
                                } else {
                                    $ca1test = '';
                                }

                                ?>
                                <table class="table-bordered tab table-sm tb-result-border" style="width:98%;">

                                    <tr>
                                        <th>SUBJECT(s)</th>
                                        <?php echo $ca1test; ?>
                                        <?php echo $ca2test; ?>
                                        <?php echo $ca3test; ?>
                                        <?php echo $ca4test; ?>
                                        <?php echo $ca5test; ?>
                                        <?php echo $ca6test; ?>
                                        <?php echo $ca7test; ?>
                                        <?php echo $ca8test; ?>
                                        <?php echo $ca9test; ?>
                                        <?php echo $ca10test; ?>
                                        <th>Exam</th>
                                        <th>Total</th>
                                        <th>Grade</th>
                                        <th>Lowest in Class</th>
                                        <th>Highest in Class</th>
                                        <th>Remark</th>
                                    </tr>

                                    <tbody>
                                        <?php

                                        $sqlsub = ("SELECT subjects.name AS name, subjects.id as id FROM `subject_group_class_sections` INNER JOIN subject_group_subjects ON subject_group_class_sections.subject_group_id=subject_group_subjects.subject_group_id INNER JOIN subjects ON subject_group_subjects.subject_id=subjects.id WHERE subject_group_class_sections.class_section_id = '$classsection' AND subject_group_class_sections.session_id='$session' AND subject_group_subjects.session_id='$session'");
                                        $resultsub = mysqli_query($link, $sqlsub);
                                        $rowGetsub = mysqli_fetch_assoc($resultsub);
                                        $row_cntsub = mysqli_num_rows($resultsub);

                                        $sqlgetscorecheck = ("SELECT * FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND SubjectID != '0' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'");
                                        $resultgetscorecheck = mysqli_query($link, $sqlgetscorecheck);
                                        $rowgetscorecheck = mysqli_fetch_assoc($resultgetscorecheck);
                                        $row_cntgetscorecheck = mysqli_num_rows($resultgetscorecheck);

                                        if ($row_cntgetscorecheck > 0) {

                                            // $sqlgetgadingmeth = ("SELECT * FROM `classordepartment` WHERE InstitutionID = '$institution' AND FacultyOrSchoolID='$facultyID' AND ClassOrDepartmentID = '$classid'");
                                            // $resultgetgadingmeth = mysqli_query($link, $sqlgetgadingmeth);
                                            // $rowgetgadingmeth = mysqli_fetch_assoc($resultgetgadingmeth);
                                            // $row_cntgetgadingmeth = mysqli_num_rows($resultgetgadingmeth);

                                            // $gradeid = $rowgetgadingmeth['GradingMethodID'] . '</br>';

                                            do {

                                                $subname = $rowGetsub['name'];
                                                $subid = $rowGetsub['id'];

                                                $sqlgetscore = ("SELECT * FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                                                $resultgetscore = mysqli_query($link, $sqlgetscore);
                                                $rowgetscore = mysqli_fetch_assoc($resultgetscore);
                                                $row_cntgetscore = mysqli_num_rows($resultgetscore);


                                                $sqlgetscore = ("SELECT * FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                                                $resultgetscore = mysqli_query($link, $sqlgetscore);
                                                $rowgetscore = mysqli_fetch_assoc($resultgetscore);
                                                $row_cntgetscore = mysqli_num_rows($resultgetscore);


                                                if ($row_cntgetscore > 0) {

                                                    if ($rowGetrelset['NumberOfCA'] == '1') {
                                                        $ca1 = $rowgetscore['ca1'];
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '2') {
                                                        $ca1 = $rowgetscore['ca1'] + $rowgetscore['ca2'];
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '3') {
                                                        $ca1 = $rowgetscore['ca1'] + $rowgetscore['ca2'] + $rowgetscore['ca3'];
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '4') {
                                                        $ca1 = $rowgetscore['ca1'] + $rowgetscore['ca2'] + $rowgetscore['ca3'] + $rowgetscore['ca4'];
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '5') {
                                                        $ca1 = $rowgetscore['ca1'] + $rowgetscore['ca2'] + $rowgetscore['ca3'] + $rowgetscore['ca4'] + $rowgetscore['ca5'];
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '6') {
                                                        $ca1 = $rowgetscore['ca1'] + $rowgetscore['ca2'] + $rowgetscore['ca3'] + $rowgetscore['ca4'] + $rowgetscore['ca5'] + $rowgetscore['ca6'];
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '7') {
                                                        $ca1 = $rowgetscore['ca1'] + $rowgetscore['ca2'] + $rowgetscore['ca3'] + $rowgetscore['ca4'] + $rowgetscore['ca5'] + $rowgetscore['ca6'] + $rowgetscore['ca7'];
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '8') {
                                                        $ca1 = $rowgetscore['ca1'] + $rowgetscore['ca2'] + $rowgetscore['ca3'] + $rowgetscore['ca4'] + $rowgetscore['ca5'] + $rowgetscore['ca6'] + $rowgetscore['ca7'] + $rowgetscore['ca8'];
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '9') {
                                                        $ca1 = $rowgetscore['ca1'] + $rowgetscore['ca2'] + $rowgetscore['ca3'] + $rowgetscore['ca4'] + $rowgetscore['ca5'] + $rowgetscore['ca6'] + $rowgetscore['ca7'] + $rowgetscore['ca8'] + $rowgetscore['ca9'];
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '10') {
                                                        $ca1 = $rowgetscore['ca1'] + $rowgetscore['ca2'] + $rowgetscore['ca3'] + $rowgetscore['ca4'] + $rowgetscore['ca5'] + $rowgetscore['ca6'] + $rowgetscore['ca7'] + $rowgetscore['ca8'] + $rowgetscore['ca9'] + $rowgetscore['ca10'];
                                                    } else {
                                                        $ca1 = 0;
                                                    }

                                                    $exam = $rowgetscore['exam'];

                                                    $total = $ca1 + $exam;

                                                    $subavg = $total;

                                                    $sqlgetgradstuc = ("SELECT * FROM `gradingstructure` INNER JOIN assigngradingtclass ON gradingstructure.GradingTitle = assigngradingtclass.GradingTitle WHERE $total >= RangeStart AND $total <= RangeEnd AND ClassID = '$classid'");
                                                    $resultgetgradstuc = mysqli_query($link, $sqlgetgradstuc);
                                                    $rowgetgradstuc = mysqli_fetch_assoc($resultgetgradstuc);
                                                    $row_cntgetgradstuc = mysqli_num_rows($resultgetgradstuc);

                                                    if ($row_cntgetgradstuc > 0) {
                                                        $grade = $rowgetgradstuc['Grade'];
                                                        $remark = $rowgetgradstuc['Remark'];

                                                        $sqlsunnyhihhscoreunamepersub = "SELECT DISTINCT(StudentID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS total FROM score WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' AND SubjectID = '$subid' GROUP BY StudentID order by total DESC LIMIT 1";
                                                        $resultsunnyhihhscoreunamepersub = mysqli_query($link, $sqlsunnyhihhscoreunamepersub);
                                                        $rowsunnyhihhscoreunamepersub = mysqli_fetch_assoc($resultsunnyhihhscoreunamepersub);
                                                        $row_cntsunnyhihhscoreunamepersub = mysqli_num_rows($resultsunnyhihhscoreunamepersub);

                                                        $sunhihscrunpersub = round($rowsunnyhihhscoreunamepersub['total']);

                                                        $sqlsunnylowwscoreunamepersub = "SELECT DISTINCT(StudentID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS total FROM score WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' AND SubjectID = '$subid' GROUP BY StudentID order by total ASC LIMIT 1";
                                                        $resultsunnylowwscoreunamepersub = mysqli_query($link, $sqlsunnylowwscoreunamepersub);
                                                        $rowsunnylowwscoreunamepersub = mysqli_fetch_assoc($resultsunnylowwscoreunamepersub);
                                                        $row_cntsunnylowwscoreunamepersub = mysqli_num_rows($resultsunnylowwscoreunamepersub);

                                                        $sunlowscrunpersub = round($rowsunnylowwscoreunamepersub['total']);

                                                        $sqlgetscorepos = "SELECT * FROM (SELECT *, @n := @n + 1 n FROM (SELECT SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS total, StudentID FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' GROUP BY StudentID ORDER BY total DESC) as sunny, (SELECT @n := 0) as m) as sunito WHERE sunito.StudentID='$id'";
                                                        $resultgetscorepos = mysqli_query($link, $sqlgetscorepos);
                                                        $rowgetscorepos = mysqli_fetch_assoc($resultgetscorepos);
                                                        $row_cntgetscorepos = mysqli_num_rows($resultgetscorepos);

                                                        $sqlgetsubper = "SELECT SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS average FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND InstitutionID = '$institution' AND ClassOrDepartmentID = '$classid' AND CourseOrSubjectID = '$subid' AND Session = '$sunnyresultsession' AND TermOrSemester = '$sunnyrelterm' ORDER BY exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10";
                                                        $resultgetsubper = mysqli_query($link, $sqlgetsubper);
                                                        $rowgetsubper = mysqli_fetch_assoc($resultgetsubper);
                                                        $row_cntgetsubper = mysqli_num_rows($resultgetsubper);

                                                        $getsubper = round($rowgetsubper['average'] / $row_cntClasscount, 2);

                                                        $getsco = round($rowgetscorepos['total'], 2);

                                                        $getscorpos = $rowgetscorepos['n'];
                                                    } else {
                                                    }

                                                    echo '<tr>
                                                                    <th>' . $subname . '</th>';
                                                    if ($rowGetrelset['NumberOfCA'] == '1') {
                                                        echo '<td>
                                                                                ' . $rowgetscore["ca1"] . '

                                                                                </td>';
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '2') {
                                                        echo '<td>
                                                                                ' . $rowgetscore["ca1"] . '

                                                                                </td>
                                                                                <td>
                                                                                ' . $rowgetscore["ca2"] . '

                                                                                </td>';
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '3') {
                                                        echo '<td>
                                                                            ' . $rowgetscore["ca1"] . '

                                                                            </td>
                                                                            <td>
                                                                            ' . $rowgetscore["ca2"] . '

                                                                            </td>
                                                                            <td>
                                                                            ' . $rowgetscore["ca3"] . '

                                                                            </td>';
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '4') {
                                                        echo '<td>
                                                                                ' . $rowgetscore["ca1"] . '

                                                                                </td>
                                                                                <td>
                                                                                ' . $rowgetscore["ca2"] . '

                                                                                </td>
                                                                                <td>
                                                                            ' . $rowgetscore["ca3"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca4"] . '

                                                                            </td>';
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '5') {
                                                        echo '<td>
                                                                                ' . $rowgetscore["ca1"] . '

                                                                                </td>
                                                                                <td>
                                                                                ' . $rowgetscore["ca2"] . '

                                                                                </td>
                                                                                <td>
                                                                            ' . $rowgetscore["ca3"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca4"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca5"] . '
                                                                            </td>';
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '6') {
                                                        echo '<td>
                                                                                ' . $rowgetscore["ca1"] . '

                                                                                </td>
                                                                                <td>
                                                                                ' . $rowgetscore["ca2"] . '

                                                                                </td>
                                                                                <td>
                                                                            ' . $rowgetscore["ca3"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca4"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca5"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca6"] . '

                                                                            </td>';
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '7') {
                                                        echo '<td>
                                                                                ' . $rowgetscore["ca1"] . '

                                                                                </td>
                                                                                <td>
                                                                                ' . $rowgetscore["ca2"] . '

                                                                                </td>
                                                                                <td>
                                                                            ' . $rowgetscore["ca3"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca4"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca5"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca6"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca7"] . '

                                                                            </td>';
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '8') {
                                                        echo '<td>
                                                                                ' . $rowgetscore["ca1"] . '

                                                                                </td>
                                                                                <td>
                                                                                ' . $rowgetscore["ca2"] . '

                                                                                </td>
                                                                                <td>
                                                                            ' . $rowgetscore["ca3"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca4"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca5"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca6"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca7"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca8"] . '

                                                                            </td>';
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '9') {
                                                        echo '<td>
                                                                                ' . $rowgetscore["ca1"] . '

                                                                                </td>
                                                                                <td>
                                                                                ' . $rowgetscore["ca2"] . '

                                                                                </td>
                                                                                <td>
                                                                            ' . $rowgetscore["ca3"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca4"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca5"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca6"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca7"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca8"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca9"] . '

                                                                            </td>';
                                                    } elseif ($rowGetrelset['NumberOfCA'] == '10') {
                                                        echo '<td>
                                                                                ' . $rowgetscore["ca1"] . '

                                                                                </td>
                                                                                <td>
                                                                                ' . $rowgetscore["ca2"] . '

                                                                                </td>
                                                                                <td>
                                                                            ' . $rowgetscore["ca3"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca4"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca5"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca6"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca7"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca8"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca9"] . '

                                                                            </td>
                                                                            <td>
                                                                                ' . $rowgetscore["ca10"] . '

                                                                            </td>';
                                                    } else {
                                                    }
                                                    echo '
                                                                    <td>' . $exam . '</td>
                                                                    <td>' . $total . '</td>
                                                                    <td>';
                                                    echo addOrdinalNumberSuffix($getscorpos) . "\t";

                                                    if ($getscorpos % 10 == 0) {
                                                        echo "\n";
                                                    }
                                                    echo '</td>
                                                                    <td>' . $sunlowscrunpersub . '</td>
                                                                    <td>' . $sunhihscrunpersub . '</td>
                                                                    <td>' . $remark . '</td>
                                                                </tr>';
                                                } else {
                                                }
                                            } while ($rowGetsub = mysqli_fetch_assoc($resultsub));
                                        } else {
                                            echo '<tr><td align="center" colspan="15" style="font-size: calc(12px + (18 - 12) * ((100vw - 300px) / (1600 - 300)))"><div class="alert alert-info alert-dismissible mb-2" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>No Result Yet</div></tr></td>';
                                        }

                                        ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php
                            $sqlresumdateOld = ("SELECT * FROM `resumptiondate` WHERE `Session`='$session' AND `Term`='$term'");

                            $resultresumdateOld = mysqli_query($link, $sqlresumdateOld);
                            $getresumdateOld = mysqli_fetch_assoc($resultresumdateOld);
                            $sqlrow_cntresumdateOld = mysqli_num_rows($resultresumdateOld);

                            if ($sqlrow_cntresumdateOld > 0) {
                                $resumdateOld = $getresumdateOld['Date'];
                            } else {
                                $resumdateOld = 'N/A';
                            }

                            $sqlgetsubscore = ("SELECT * FROM `score` WHERE `ca1` != '0' AND `ca1` != '' AND `ca1` IS NOT NULL AND StudentID = '$id' AND ClassID = '$classid' AND SubjectID = '0' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'");
                            $resultgetsubscore = mysqli_query($link, $sqlgetsubscore);
                            $rowgetsubscore = mysqli_fetch_assoc($resultgetsubscore);
                            $row_cntgetsubscore = mysqli_num_rows($resultgetsubscore);

                            if ($row_cntgetsubscore > 0) {

                                $rowcountfixedgennew = $rowgetsubscore['ca1'];
                                $rowcountfixedpresent = $rowgetsubscore['ca2'];
                                $rowcountfixedlate = $rowgetsubscore['ca3'];
                                $rowcountfixedabsent = $rowgetsubscore['ca4'];
                            } else {
                                $sqlgettechgen = mysqli_query($link, "SELECT DISTINCT(student_attendences.`date`) FROM `student_attendences` WHERE student_attendences.`session` = '$session' AND student_attendences.`term` = '$term2'");
                                $fetchfixedgen = mysqli_fetch_assoc($sqlgettechgen);
                                $rowcountfixedgen = mysqli_num_rows($sqlgettechgen);

                                if ($rowcountfixedgen == NULL || $rowcountfixedgen == '') {
                                    $rowcountfixedgennew = 0;
                                } else {
                                    $rowcountfixedgennew = $rowcountfixedgen;
                                }

                                $sqlgettechpresent = mysqli_query($link, "SELECT * FROM `student_attendences` INNER JOIN student_session ON student_attendences.student_session_id=student_session.id WHERE student_attendences.`session` = '$session' AND student_attendences.`term` = '$term2' AND student_session.`student_id`='$id' AND student_session.`session_id`='$session' AND student_session.`class_id`='$classid' AND `attendence_type_id`='1'");
                                $fetchfixedpresent = mysqli_fetch_assoc($sqlgettechpresent);
                                $rowcountfixedpresent = mysqli_num_rows($sqlgettechpresent);

                                if ($rowcountfixedpresent == NULL || $rowcountfixedpresent == '') {
                                    $rowcountfixedpresent = 0;
                                } else {
                                    $rowcountfixedpresent = $rowcountfixedpresent;
                                }

                                $sqlgettechabsent = mysqli_query($link, "SELECT * FROM `student_attendences` INNER JOIN student_session ON student_attendences.student_session_id=student_session.id WHERE student_attendences.`session` = '$session' AND student_attendences.`term` = '$term2' AND student_session.`student_id`='$id' AND student_session.`session_id`='$session' AND student_session.`class_id`='$classid' AND `attendence_type_id`='4'");
                                $fetchfixedabsent = mysqli_fetch_assoc($sqlgettechabsent);
                                $rowcountfixedabsent = mysqli_num_rows($sqlgettechabsent);

                                if ($rowcountfixedabsent == NULL || $rowcountfixedabsent == '') {
                                    $rowcountfixedabsent = 0;
                                } else {
                                    $rowcountfixedabsent = $rowcountfixedabsent;
                                }
                            }
                            ?>
                            <div align="center" class="summDD">
                                <p>Total Score: <?php echo $gettotscore; ?> </p>
                                <p>Average Score: <?php echo $gettotgrade; ?> </p>
                                <p>Class Average: <?php echo $decStubsubavg; ?> </p>
                                <p>No. of Subjects: <?php echo $row_cntgetscorecheck; ?></p>
                            </div>

                            <div class="performance">
                                <div class="row">
                                    <div class="col-4">
                                        <div class="containerForChart">

                                            <canvas class="newgraph" id="mysunChart" style="width:100%;"></canvas>

                                        </div>
                                    </div>

                                    <?php

                                    $sqlgettechremark = mysqli_query($link, "SELECT * FROM `remark` WHERE `RemarkType` = 'teacher' AND `StudentID` = '$id' AND `Session` = '$session' AND `Term` = '$term' AND `remark` != ''");
                                    $rowcountfixedremark = mysqli_num_rows($sqlgettechremark);
                                    $fetchfixedremark = mysqli_fetch_assoc($sqlgettechremark);


                                    if ($rowcountfixedremark > 0) {
                                        $teacherRemark = $fetchfixedremark['remark'];

                                        $teacherid = $fetchfixedremark['StaffID'];

                                        $sqlgetheadteachsign = ("SELECT * FROM `staffsignature` WHERE staff_id = '$hedteachid'");
                                        $resultgetheadteachsign = mysqli_query($link, $sqlgetheadteachsign);
                                        $rowgetheadteachsign = mysqli_fetch_assoc($resultgetheadteachsign);
                                        $row_cntgetheadteachsign = mysqli_num_rows($resultgetheadteachsign);


                                        if ($row_cntgetheadteachsign > 0) {
                                            $hedteachsign = '<img src="../img/signature/' . $rowgetheadteachsign['Signature'] . '" align="center" class="img-fluid" style="width: 70%;">';
                                        } else {
                                            $hedteachsign = '';
                                        }
                                    } else {

                                        $sqlgetteachremark = ("SELECT * FROM `defaultcomment` INNER JOIN class_teacher ON defaultcomment.PrincipalOrDeanOrHeadTeacherUserID=class_teacher.staff_id WHERE $gettotgrade BETWEEN RangeStart AND RangeEnd AND CommentType = 'teacher' AND class_id = '$classid'");
                                        $resultgetteachremark = mysqli_query($link, $sqlgetteachremark);
                                        $rowgetteachremark = mysqli_fetch_assoc($resultgetteachremark);
                                        $row_cntgetteachremark = mysqli_num_rows($resultgetteachremark);

                                        if ($row_cntgetteachremark > 0) {
                                            $teacherRemark = $rowgetteachremark['DefaultComment'];

                                            $teacherid = $rowgetteachremark['PrincipalOrDeanOrHeadTeacherUserID'];


                                            $sqlgetheadteachsign = ("SELECT * FROM `staffsignature` WHERE staff_id = '$teacherid'");
                                            $resultgetheadteachsign = mysqli_query($link, $sqlgetheadteachsign);
                                            $rowgetheadteachsign = mysqli_fetch_assoc($resultgetheadteachsign);
                                            $row_cntgetheadteachsign = mysqli_num_rows($resultgetheadteachsign);


                                            if ($row_cntgetheadteachsign > 0) {
                                                $hedteachsign = '<img src="../img/signature/' . $rowgetheadteachsign['Signature'] . '" align="center" class="img-fluid" style="width: 70%;">';
                                            } else {
                                                $hedteachsign = '';
                                            }
                                        } else {
                                            $teacherRemark = 'N/A';

                                            $hedteachsign = '';
                                        }
                                    }

                                    $sqlgetprincremark = mysqli_query($link, "SELECT * FROM `remark` WHERE `RemarkType` = 'SchoolHead' AND `StudentID`='$id' AND `Session`='$session' AND `Term`='$term' AND `remark`!=''");
                                    $rowcountprinfixedremark = mysqli_num_rows($sqlgetprincremark);
                                    $fetchfixedprinremark = mysqli_fetch_assoc($sqlgetprincremark);

                                    if ($rowcountprinfixedremark > 0) {
                                        $principalRemark = $fetchfixedprinremark['remark'];

                                        $headteacherid = $fetchfixedprinremark['staff_id'];

                                        $sqlgetheadteachhead = ("SELECT * FROM `staffsignature` WHERE staff_id = 5");
                                        $resultgetheadteachsignhead = mysqli_query($link, $sqlgetheadteachhead);
                                        $rowgetheadteachsignhead = mysqli_fetch_assoc($resultgetheadteachsignhead);
                                        $row_cntgetheadteachsignhead = mysqli_num_rows($resultgetheadteachsignhead);

                                        if ($row_cntgetheadteachsignhead > 0) {
                                            $hedteachsignhead = '<img src="' . $defRUl . 'img/signature/' . $rowgetheadteachsignhead['Signature'] . '" align="center" class="img-fluid" style="width: 70%;">';
                                        } else {
                                            $hedteachsignhead = '';
                                        }
                                    } else {

                                        $sqlgetprincremark = ("SELECT * FROM `defaultcomment` WHERE $gettotgrade BETWEEN RangeStart AND RangeEnd AND CommentType = 'SchoolHead'");
                                        $resultgetprincremark = mysqli_query($link, $sqlgetprincremark);
                                        $rowgetteachremark = mysqli_fetch_assoc($resultgetprincremark);
                                        $row_cntgetprincremark = mysqli_num_rows($resultgetprincremark);

                                        if ($row_cntgetprincremark > 0) {
                                            $principalRemark = $rowgetteachremark['DefaultComment'];

                                            $headteacherid = $rowgetteachremark['PrincipalOrDeanOrHeadTeacherUserID'];

                                            $sqlgetheadteachhead = ("SELECT * FROM `staffsignature` WHERE staff_id = '$headteacherid'");
                                            $resultgetheadteachsignhead = mysqli_query($link, $sqlgetheadteachhead);
                                            $rowgetheadteachsignhead = mysqli_fetch_assoc($resultgetheadteachsignhead);
                                            $row_cntgetheadteachsignhead = mysqli_num_rows($resultgetheadteachsignhead);

                                            if ($row_cntgetheadteachsignhead > 0) {
                                                $hedteachsignhead = '<img src=" https://schoollift.s3.us-east-2.amazonaws.com/' . $rowgetheadteachsignhead['Signature'] . '" align="center" class="img-fluid" style="width: 80%;">';
                                            } else {
                                                $hedteachsignhead = '';
                                            }
                                        } else {
                                            $principalRemark = 'N/A';

                                            $hedteachsignhead = '';
                                        }
                                    }

                                    if ($term == '1st') {
                                        $termnew = '2nd';

                                        $sessionnew = $session;
                                    } elseif ($term == '2nd') {
                                        $termnew = '3rd';
                                        $sessionnew = $session;
                                    } else {
                                        $termnew = '1st';
                                        $sessionnew = $session + 1;
                                    }

                                    $sqlresumdate = ("SELECT * FROM `resumptiondate` WHERE `Session`='$sessionnew' AND `Term`='$termnew'");

                                    $resultresumdate = mysqli_query($link, $sqlresumdate);
                                    $getresumdate = mysqli_fetch_assoc($resultresumdate);
                                    $sqlrow_cntresumdate = mysqli_num_rows($resultresumdate);

                                    if ($sqlrow_cntresumdate > 0) {
                                        $resumdate = date("l jS \of F Y", strtotime($getresumdate['Date']));
                                    } else {
                                        $resumdate = 'N/A';
                                    }
                                    ?>
                                    <div class="col-8">

                                        <div class="container-motto">
                                            <div style="margin: 20px;">
                                                <div class="row">
                                                    <div class="col-sm-10 col-md-10">
                                                        <div align="center">
                                                            <p style="text-align: justify;"><b style="font-weight:600;">CLASS TEACHER'S COMMENT:</b>&nbsp;<?php echo $teacherRemark; ?></p>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-2 col-md-2">
                                                        <div align="center">
                                                            <?php echo $hedteachsign; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-sm-10 col-md-10">
                                                        <div>
                                                            <p style="text-align: justify;"><b style="font-weight:600;">PRINCIPAL/HEAD TEACHER'S COMMENT:</b>&nbsp;&nbsp;&nbsp;<?php echo $principalRemark; ?></p>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-2 col-md-2">
                                                        <div>
                                                            <?php echo $hedteachsignhead; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-sm-12 col-md-12">
                                                        <div>
                                                            <p style="text-align: justify;"><b style="font-weight:600;">NEXT TERM BEGINS:</b>&nbsp;<?php echo $resumdate; ?></b></p>
                                                            <?php
                                                            if ($next_fee > 0) {
                                                                echo '<p style="text-align: center;"><b style="font-weight:600;">NEXT TERM FEE: N' . $next_fee . '</b></p>';
                                                            }
                                                            ?>

                                                        </div>
                                                    </div>

                                                </div>

                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            <?php
                            /*
                                            if($term == '3rd')
                                            {

                                                $sessionnew = $session + 1;

                                                $sqlstudent_session = ("SELECT * FROM `student_session` WHERE `session_id`='$sessionnew' AND student_id = '$id'");

                                                $resultstudent_session = mysqli_query($link, $sqlstudent_session);
                                                $getstudent_session = mysqli_fetch_assoc($resultstudent_session);
                                                $sqlrow_cntstudent_session = mysqli_num_rows($resultstudent_session);

                                                if($sqlrow_cntstudent_session > 0)
                                                {
                                                    echo '<center>
                                                        <p class="promoted">PROMOTED TO THE NEXT CLASS</p>
                                                    </center>';
                                                }
                                                else
                                                {
                                                    echo '<center>
                                                        <p class="promoted">NOT PROMOTED TO THE NEXT CLASS</p>
                                                    </center>';
                                                }

                                            }
                                            else
                                            {

                                            }
                                            */
                            ?>

                        <?php
                        } elseif ($reltype == 'british') {
                        ?>

                            <div class="container-motto">

                                <div class="row" style="margin: 10px; padding-left: 50px;">
                                    <div class="col-6">
                                        <h5 style="color: #000000;"> <b>Name:</b> <?php echo $studname; ?></h5>
                                    </div>
                                    <div class="col-6">
                                        <h5 style="color: #000000;"> <b>Class:</b> <?php echo $studclass . ' ' . $studsection; ?></h5>
                                    </div>
                                </div>
                                <?php
                                $sqlresumdateOld = ("SELECT * FROM `resumptiondate` WHERE `Session`='$session' AND `Term`='$term'");

                                $resultresumdateOld = mysqli_query($link, $sqlresumdateOld);
                                $getresumdateOld = mysqli_fetch_assoc($resultresumdateOld);
                                $sqlrow_cntresumdateOld = mysqli_num_rows($resultresumdateOld);

                                if ($sqlrow_cntresumdateOld > 0) {
                                    $resumdateOld = $getresumdateOld['Date'];
                                } else {
                                    $resumdateOld = 'N/A';
                                }

                                $sqlgetsubscore = ("SELECT * FROM `score` WHERE (`ca1` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND SubjectID = '0' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'");
                                $resultgetsubscore = mysqli_query($link, $sqlgetsubscore);
                                $rowgetsubscore = mysqli_fetch_assoc($resultgetsubscore);
                                $row_cntgetsubscore = mysqli_num_rows($resultgetsubscore);

                                if ($row_cntgetsubscore > 0) {

                                    $rowcountfixedgennew = $rowgetsubscore['ca1'];

                                    $rowcountfixedpresent = $rowgetsubscore['ca2'];
                                    $rowcountfixedlate = $rowgetsubscore['ca3'];
                                    $rowcountfixedabsent = $rowgetsubscore['ca4'];
                                } else {
                                    $sqlgettechgen = mysqli_query($link, "SELECT DISTINCT(student_attendences.`date`) FROM `student_attendences` WHERE student_attendences.`session` = '$session' AND student_attendences.`term` = '$term2'");
                                    $fetchfixedgen = mysqli_fetch_assoc($sqlgettechgen);
                                    $rowcountfixedgen = mysqli_num_rows($sqlgettechgen);

                                    if ($rowcountfixedgen == NULL || $rowcountfixedgen == '') {
                                        $rowcountfixedgen = 0;
                                    } else {
                                        $rowcountfixedgennew = $rowcountfixedgen;
                                    }

                                    $sqlgettechpresent = mysqli_query($link, "SELECT * FROM `student_attendences` INNER JOIN student_session ON student_attendences.student_session_id=student_session.id WHERE student_attendences.`session` = '$session' AND student_attendences.`term` = '$term2' AND student_session.`student_id`='$id' AND student_session.`session_id`='$session' AND student_session.`class_id`='$classid' AND `attendence_type_id`='1'");
                                    $fetchfixedpresent = mysqli_fetch_assoc($sqlgettechpresent);
                                    $rowcountfixedpresent = mysqli_num_rows($sqlgettechpresent);

                                    if ($rowcountfixedpresent == NULL || $rowcountfixedpresent == '') {
                                        $rowcountfixedpresent = 0;
                                    } else {
                                        $rowcountfixedpresent = $rowcountfixedpresent;
                                    }

                                    $sqlgettechabsent = mysqli_query($link, "SELECT * FROM `student_attendences` INNER JOIN student_session ON student_attendences.student_session_id=student_session.id WHERE student_attendences.`session` = '$session' AND student_attendences.`term` = '$term2' AND student_session.`student_id`='$id' AND student_session.`session_id`='$session' AND student_session.`class_id`='$classid' AND `attendence_type_id`='4'");
                                    $fetchfixedabsent = mysqli_fetch_assoc($sqlgettechabsent);
                                    $rowcountfixedabsent = mysqli_num_rows($sqlgettechabsent);

                                    if ($rowcountfixedabsent == NULL || $rowcountfixedabsent == '') {
                                        $rowcountfixedabsent = 0;
                                    } else {
                                        $rowcountfixedabsent = $rowcountfixedabsent;
                                    }
                                }
                                ?>



                                <div class="row" style="margin: 10px; padding-left: 50px;">
                                    <div class="col-6">
                                        <h5 style="color: #000000;"> <b>Days Present:</b> <?php echo $rowcountfixedpresent ?></h5>
                                    </div>
                                    <div class="col-6">
                                        <h5 style="color: #000000;"> <b>Days School Opened:</b> <?php echo $rowcountfixedgennew; ?></h5>
                                    </div>
                                </div>

                                <div class="row" style="margin: 10px; padding-left: 50px;">
                                    <div class="col-6">
                                        <h5 style="color: #000000;"> <b>Days Absent:</b> <?php echo $rowcountfixedabsent; ?></h5>
                                    </div>
                                </div>
                            </div>

                            <div align="center">
                                <h5 style="font-size: 18px; font-weight: 800; color: #000000; margin-bottom: 0px;">ACADEMIC PERFORMANCE</h5>
                            </div>

                            <div class="result table-responsive" style="margin: 10px; margin-top: 5px;">
                                <table class="table-bordered tab table-sm tb-result-border" style="width:98%;">

                                    <tr style="text-align: center;font-size:16px;font-weight:bolder">
                                        <th style="width: 20%; height:45px; background-color:yellow;">Subject(s)</th>
                                        <th style="width: 20%; background-color:red;color:white">Remark</th>
                                        <th style="background-color:blue;color:white">Additional Comments</th>
                                    </tr>

                                    <tbody>
                                        <?php

                                        $sqlsub = ("SELECT subjects.name AS name, subjects.id as id FROM `subject_group_class_sections` INNER JOIN subject_group_subjects ON subject_group_class_sections.subject_group_id=subject_group_subjects.subject_group_id INNER JOIN subjects ON subject_group_subjects.subject_id=subjects.id WHERE subject_group_class_sections.class_section_id = '$classsection' AND subject_group_class_sections.session_id='$session' AND subject_group_subjects.session_id='$session'");
                                        $resultsub = mysqli_query($link, $sqlsub);
                                        $rowGetsub = mysqli_fetch_assoc($resultsub);
                                        $row_cntsub = mysqli_num_rows($resultsub);

                                        $sqlgetscorecheck = ("SELECT * FROM `britishresult` WHERE StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'");
                                        $resultgetscorecheck = mysqli_query($link, $sqlgetscorecheck);
                                        $rowgetscorecheck = mysqli_fetch_assoc($resultgetscorecheck);
                                        $row_cntgetscorecheck = mysqli_num_rows($resultgetscorecheck);

                                        if ($row_cntgetscorecheck > 0) {

                                            do {

                                                $subname = $rowGetsub['name'];
                                                $subid = $rowGetsub['id'];

                                                $sqlgetscore = ("SELECT * FROM `britishresult` WHERE StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                                                $resultgetscore = mysqli_query($link, $sqlgetscore);
                                                $rowgetscore = mysqli_fetch_assoc($resultgetscore);
                                                $row_cntgetscore = mysqli_num_rows($resultgetscore);

                                                if ($rowgetscore['Remark'] != '' || $rowgetscore['Remark'] != NULL) {
                                                    $briremark = $rowgetscore['Remark'];
                                                } else {
                                                    $briremark = 'Nil';
                                                }

                                                if ($rowgetscore['AdditionalComments'] != '' || $rowgetscore['AdditionalComments'] != NULL) {
                                                    $briextcom = $rowgetscore['AdditionalComments'];
                                                } else {
                                                    $briextcom = 'Nil';
                                                }

                                                if ($row_cntgetscore > 0) {
                                                    echo '<tr style="">
                                                                        <th style="height:70px;">' . $subname . '</th>';
                                                    echo '
                                                                        <td>' . $briremark . '</td>
                                                                        <td>' . $briextcom . '</td>
                                                                    </tr>';
                                                } else {
                                                }
                                            } while ($rowGetsub = mysqli_fetch_assoc($resultsub));
                                        } else {
                                            echo '<tr><td align="center" colspan="15" style="font-size: calc(12px + (18 - 12) * ((100vw - 300px) / (1600 - 300)))"><div class="alert alert-info alert-dismissible mb-2" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>No Result Yet.</div></tr></td>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="performance">
                                <div class="row">

                                    <?php

                                    $sqlgettechremark = mysqli_query($link, "SELECT * FROM `remark` WHERE `RemarkType` = 'teacher' AND `StudentID` = '$id' AND `Session` = '$session' AND `Term` = '$term' AND `remark` != ''");
                                    $rowcountfixedremark = mysqli_num_rows($sqlgettechremark);
                                    $fetchfixedremark = mysqli_fetch_assoc($sqlgettechremark);


                                    if ($rowcountfixedremark > 0) {
                                        $teacherRemark = $fetchfixedremark['remark'];

                                        $teacherid = $fetchfixedremark['StaffID'];

                                        $sqlgetheadteachsign = ("SELECT * FROM `staffsignature` WHERE staff_id = '$hedteachid'");
                                        $resultgetheadteachsign = mysqli_query($link, $sqlgetheadteachsign);
                                        $rowgetheadteachsign = mysqli_fetch_assoc($resultgetheadteachsign);
                                        $row_cntgetheadteachsign = mysqli_num_rows($resultgetheadteachsign);


                                        if ($row_cntgetheadteachsign > 0) {
                                            $hedteachsign = '<img src="../img/signature/' . $rowgetheadteachsign['Signature'] . '" align="center" class="img-fluid" style="width: 70%;">';
                                        } else {
                                            $hedteachsign = '';
                                        }
                                    } else {

                                        $sqlgetteachremark = ("SELECT * FROM `defaultcomment` INNER JOIN class_teacher ON defaultcomment.PrincipalOrDeanOrHeadTeacherUserID=class_teacher.staff_id WHERE $gettotgrade BETWEEN RangeStart AND RangeEnd AND CommentType = 'teacher' AND class_id = '$classid'");
                                        $resultgetteachremark = mysqli_query($link, $sqlgetteachremark);
                                        $rowgetteachremark = mysqli_fetch_assoc($resultgetteachremark);
                                        $row_cntgetteachremark = mysqli_num_rows($resultgetteachremark);

                                        if ($row_cntgetteachremark > 0) {
                                            $teacherRemark = $rowgetteachremark['DefaultComment'];

                                            $teacherid = $rowgetteachremark['PrincipalOrDeanOrHeadTeacherUserID'];


                                            $sqlgetheadteachsign = ("SELECT * FROM `staffsignature` WHERE staff_id = '$teacherid'");
                                            $resultgetheadteachsign = mysqli_query($link, $sqlgetheadteachsign);
                                            $rowgetheadteachsign = mysqli_fetch_assoc($resultgetheadteachsign);
                                            $row_cntgetheadteachsign = mysqli_num_rows($resultgetheadteachsign);


                                            if ($row_cntgetheadteachsign > 0) {
                                                $hedteachsign = '<img src="../img/signature/' . $rowgetheadteachsign['Signature'] . '" align="center" class="img-fluid" style="width: 70%;">';
                                            } else {
                                                $hedteachsign = '';
                                            }
                                        } else {
                                            $teacherRemark = 'N/A';

                                            $hedteachsign = '';
                                        }
                                    }

                                    $sqlgetprincremark = mysqli_query($link, "SELECT * FROM `remark` WHERE `RemarkType` = 'SchoolHead' AND `StudentID`='$id' AND `Session`='$session' AND `Term`='$term' AND `remark`!=''");
                                    $rowcountprinfixedremark = mysqli_num_rows($sqlgetprincremark);
                                    $fetchfixedprinremark = mysqli_fetch_assoc($sqlgetprincremark);

                                    if ($rowcountprinfixedremark > 0) {
                                        $principalRemark = $fetchfixedprinremark['remark'];

                                        $headteacherid = $fetchfixedprinremark['staff_id'];

                                        $sqlgetheadteachhead = ("SELECT * FROM `staffsignature` WHERE staff_id = 5");
                                        $resultgetheadteachsignhead = mysqli_query($link, $sqlgetheadteachhead);
                                        $rowgetheadteachsignhead = mysqli_fetch_assoc($resultgetheadteachsignhead);
                                        $row_cntgetheadteachsignhead = mysqli_num_rows($resultgetheadteachsignhead);

                                        if ($row_cntgetheadteachsignhead > 0) {
                                            $hedteachsignhead = '<img src="' . $defRUl . 'img/signature/' . $rowgetheadteachsignhead['Signature'] . '" align="center" class="img-fluid" style="width: 70%;">';
                                        } else {
                                            $hedteachsignhead = '';
                                        }
                                    } else {

                                        $sqlgetprincremark = ("SELECT * FROM `defaultcomment` WHERE $gettotgrade BETWEEN RangeStart AND RangeEnd AND CommentType = 'SchoolHead'");
                                        $resultgetprincremark = mysqli_query($link, $sqlgetprincremark);
                                        $rowgetteachremark = mysqli_fetch_assoc($resultgetprincremark);
                                        $row_cntgetprincremark = mysqli_num_rows($resultgetprincremark);

                                        if ($row_cntgetprincremark > 0) {
                                            $principalRemark = $rowgetteachremark['DefaultComment'];

                                            $headteacherid = $rowgetteachremark['PrincipalOrDeanOrHeadTeacherUserID'];

                                            $sqlgetheadteachhead = ("SELECT * FROM `staffsignature` WHERE staff_id = '$headteacherid'");
                                            $resultgetheadteachsignhead = mysqli_query($link, $sqlgetheadteachhead);
                                            $rowgetheadteachsignhead = mysqli_fetch_assoc($resultgetheadteachsignhead);
                                            $row_cntgetheadteachsignhead = mysqli_num_rows($resultgetheadteachsignhead);

                                            if ($row_cntgetheadteachsignhead > 0) {
                                                $hedteachsignhead = '<img src=" https://schoollift.s3.us-east-2.amazonaws.com/' . $rowgetheadteachsignhead['Signature'] . '" align="center" class="img-fluid" style="width: 80%;">';
                                            } else {
                                                $hedteachsignhead = '';
                                            }
                                        } else {
                                            $principalRemark = 'N/A';

                                            $hedteachsignhead = '';
                                        }
                                    }

                                    if ($term == '1st') {
                                        $termnew = '2nd';

                                        $sessionnew = $session;
                                    } elseif ($term == '2nd') {
                                        $termnew = '3rd';
                                        $sessionnew = $session;
                                    } else {
                                        $termnew = '1st';
                                        $sessionnew = $session + 1;
                                    }

                                    $sqlresumdate = ("SELECT * FROM `resumptiondate` WHERE `Session`='$sessionnew' AND `Term`='$termnew'");

                                    $resultresumdate = mysqli_query($link, $sqlresumdate);
                                    $getresumdate = mysqli_fetch_assoc($resultresumdate);
                                    $sqlrow_cntresumdate = mysqli_num_rows($resultresumdate);

                                    if ($sqlrow_cntresumdate > 0) {
                                        $resumdate = date("l jS \of F Y", strtotime($getresumdate['Date']));
                                    } else {
                                        $resumdate = 'N/A';
                                    }
                                    ?>
                                    <div class="col-12">

                                        <div class="container-motto">
                                            <div style="margin: 20px;">
                                                <div class="row">
                                                    <div class="col-sm-10 col-md-10">
                                                        <div align="center">
                                                            <p style="text-align: justify;"><b style="font-weight:600;">CLASS TEACHER'S COMMENT:</b>&nbsp;<?php echo $teacherRemark; ?></p>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-2 col-md-2">
                                                        <div align="center">
                                                            <?php echo $hedteachsign; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-sm-10 col-md-10">
                                                        <div>
                                                            <p style="text-align: justify;"><b style="font-weight:600;">PRINCIPAL/HEAD TEACHER'S COMMENT:</b>&nbsp;&nbsp;&nbsp;<?php echo $principalRemark; ?></p>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-2 col-md-2">
                                                        <div>
                                                            <?php echo $hedteachsignhead; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-sm-12 col-md-12">
                                                        <div>
                                                            <p style="text-align: justify;"><b style="font-weight:600;">NEXT TERM BEGINS:</b>&nbsp;<?php echo $resumdate; ?></b></p>
                                                            <?php
                                                            if ($next_fee > 0) {
                                                                echo '<p style="text-align: center;"><b style="font-weight:600;">NEXT TERM FEE: N' . $next_fee . '</b></p>';
                                                            }
                                                            ?>

                                                        </div>
                                                    </div>

                                                </div>

                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            <?php
                            /*
                                            if($term == '3rd')
                                            {

                                                $sessionnew = $session + 1;

                                                $sqlstudent_session = ("SELECT * FROM `student_session` WHERE `session_id`='$sessionnew' AND student_id = '$id'");

                                                $resultstudent_session = mysqli_query($link, $sqlstudent_session);
                                                $getstudent_session = mysqli_fetch_assoc($resultstudent_session);
                                                $sqlrow_cntstudent_session = mysqli_num_rows($resultstudent_session);

                                                if($sqlrow_cntstudent_session > 0)
                                                {
                                                    echo '<center>
                                                        <p class="promoted">PROMOTED TO THE NEXT CLASS</p>
                                                    </center>';
                                                }
                                                else
                                                {
                                                    echo '<center>
                                                        <p class="promoted">NOT PROMOTED TO THE NEXT CLASS</p>
                                                    </center>';
                                                }

                                            }
                                            else
                                            {

                                            }
                                            */
                            ?>
                        <?php
                        } else {
                            echo 'No result type has been set for this class';
                        }
                    } else {

                        if ($reltype == 'alphabetic') {
                        ?>
                            <div class="container-motto">
                                <?php

                                $sqlgetsubscore = ("SELECT * FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND SubjectID != '0' AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual'");
                                $resultgetsubscore = mysqli_query($link, $sqlgetsubscore);
                                $rowgetsubscore = mysqli_fetch_assoc($resultgetsubscore);
                                $row_cntgetsubscore = mysqli_num_rows($resultgetsubscore);

                                $sqlgettotalgrade = "SELECT SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS average FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual'";
                                $resultgettotalgrade = mysqli_query($link, $sqlgettotalgrade);
                                $rowgettotalgrade = mysqli_fetch_assoc($resultgettotalgrade);
                                $row_cntgettotalgrade = mysqli_num_rows($resultgettotalgrade);

                                $gettotgrade = floatval(round($rowgettotalgrade['average'] / $row_cntgetsubscore, 2));

                                $gettotscore = $rowgettotalgrade['average'];

                                $sqlgetClasscount = ("SELECT DISTINCT(StudentID) FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual'");
                                $resultgetClasscount = mysqli_query($link, $sqlgetClasscount);
                                $rowgetClasscount = mysqli_fetch_assoc($resultgetClasscount);
                                $row_cntClasscount = mysqli_num_rows($resultgetClasscount);

                                $sqlgetsubscoreALL = ("SELECT DISTINCT(SubjectID) FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual'");
                                $resultgetsubscoreALL = mysqli_query($link, $sqlgetsubscoreALL);
                                $rowgetsubscoreALL = mysqli_fetch_assoc($resultgetsubscoreALL);
                                $row_cntgetsubscoreALL = mysqli_num_rows($resultgetsubscoreALL);

                                $sqlgettotclassscor = "SELECT SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS totalScore FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual' ORDER BY exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10";
                                $resultgettotclassscor = mysqli_query($link, $sqlgettotclassscor);
                                $rowgettotclassscor = mysqli_fetch_assoc($resultgettotclassscor);
                                $row_cntgettotclassscor = mysqli_num_rows($resultgettotclassscor);

                                $totsubjects = $row_cntClasscount * $row_cntgetsubscore;
                                $totsubjectsALL = $row_cntClasscount * $row_cntgetsubscoreALL;

                                $decStubsubavg = round($rowgettotclassscor['totalScore'] / $totsubjectsALL, 2);

                                $sqlsunnyhihhscoreuname = "SELECT DISTINCT(StudentID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10),COUNT(ID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) / COUNT(ID) AS total FROM score WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual' GROUP BY StudentID order by total DESC LIMIT 1";
                                $resultsunnyhihhscoreuname = mysqli_query($link, $sqlsunnyhihhscoreuname);
                                $rowsunnyhihhscoreuname = mysqli_fetch_assoc($resultsunnyhihhscoreuname);
                                $row_cntsunnyhihhscoreuname = mysqli_num_rows($resultsunnyhihhscoreuname);

                                $sunhihscrun = round($rowsunnyhihhscoreuname['total'], 2);

                                $sqlsunnylowwscoreuname = "SELECT DISTINCT(StudentID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10),COUNT(ID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) / COUNT(ID) AS total FROM score WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual' GROUP BY StudentID order by total ASC LIMIT 1";
                                $resultsunnylowwscoreuname = mysqli_query($link, $sqlsunnylowwscoreuname);
                                $rowsunnylowwscoreuname = mysqli_fetch_assoc($resultsunnylowwscoreuname);
                                $row_cntsunnylowwscoreuname = mysqli_num_rows($resultsunnylowwscoreuname);

                                $sunlowscrun = round($rowsunnylowwscoreuname['total'], 2);

                                if ($row_cntgettotalgrade > 0) {
                                    $sqlgettotgradstuc = ("SELECT * FROM `gradingstructure` INNER JOIN assigngradingtclass ON gradingstructure.GradingTitle = assigngradingtclass.GradingTitle WHERE $gettotgrade >= RangeStart AND $gettotgrade <= RangeEnd AND ClassID = '$classid'");
                                    $resultgettotgradstuc = mysqli_query($link, $sqlgettotgradstuc);
                                    $rowgettotgradstuc = mysqli_fetch_assoc($resultgettotgradstuc);
                                    $row_cntgettotgradstuc = mysqli_num_rows($resultgettotgradstuc);

                                    if ($row_cntgettotgradstuc > 0) {

                                        $totscorgrade = $rowgettotgradstuc['Grade'];
                                    } else {

                                        $totscorgrade = 'NA';
                                    }
                                } else {
                                    $gettotgrade = 'NA';
                                }

                                ?>
                                <div class="row" style="margin: 10px;">
                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>NAME:</b> <?php echo $studname; ?></h5>
                                    </div>

                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>CLASS:</b> <?php echo $studclass . ' ' . $studsection; ?></h5>
                                    </div>

                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>SEX:</b> <?php echo $studgender; ?></h5>
                                    </div>


                                </div>

                                <div class="row" style="margin: 10px;">


                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>HIGHEST IN CLASS AVE:</b> <?php echo $sunhihscrun; ?></h5>
                                    </div>

                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>LOWEST IN CLASS AVE:</b> <?php echo $sunlowscrun; ?></h5>
                                    </div>

                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>OVERALL GRADE:</b> <?php echo $totscorgrade; ?></h5>
                                    </div>
                                </div>


                            </div>

                            <div align="center">
                                <h5 style="font-size: 18px; font-weight: 800; color: #000000; margin-bottom: 0px;">ACADEMIC PERFORMANCE</h5>
                            </div>

                            <div class="result table-responsive" style="margin: 10px; margin-top: 5px;">

                                <table class="table-bordered tab table-sm tb-result-border">

                                    <tr>
                                        <th>SUBJECT(s)</th>
                                        <th>1ST TERM</th>
                                        <th>2ND TERM</th>
                                        <th>3RD TERM</th>
                                        <th>TOTAL</th>
                                        <th>AVERAGE</th>
                                        <th>GRADE</th>
                                        <th>LOWEST IN CLASS</th>
                                        <th>HIGHEST IN CLASS</th>
                                        <th>REMARK</th>
                                    </tr>

                                    <tbody>
                                        <?php

                                        $sqlsub = ("SELECT subjects.name AS name, subjects.id as id FROM `subject_group_class_sections` INNER JOIN subject_group_subjects ON subject_group_class_sections.subject_group_id=subject_group_subjects.subject_group_id INNER JOIN subjects ON subject_group_subjects.subject_id=subjects.id WHERE subject_group_class_sections.class_section_id = '$classsection' AND subject_group_class_sections.session_id='$session' AND subject_group_subjects.session_id='$session'");
                                        $resultsub = mysqli_query($link, $sqlsub);
                                        $rowGetsub = mysqli_fetch_assoc($resultsub);
                                        $row_cntsub = mysqli_num_rows($resultsub);

                                        $sqlgetscorecheck = ("SELECT * FROM `score` WHERE StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual'");
                                        $resultgetscorecheck = mysqli_query($link, $sqlgetscorecheck);
                                        $rowgetscorecheck = mysqli_fetch_assoc($resultgetscorecheck);
                                        $row_cntgetscorecheck = mysqli_num_rows($resultgetscorecheck);

                                        if ($row_cntgetscorecheck > 0) {

                                            do {

                                                $subname = $rowGetsub['name'];
                                                $subid = $rowGetsub['id'];

                                                $sqlgetscore = ("SELECT * FROM `score` WHERE StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                                                $resultgetscore = mysqli_query($link, $sqlgetscore);
                                                $rowgetscore = mysqli_fetch_assoc($resultgetscore);
                                                $row_cntgetscore = mysqli_num_rows($resultgetscore);


                                                $sqlgetscore = ("SELECT * FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                                                $resultgetscore = mysqli_query($link, $sqlgetscore);
                                                $rowgetscore = mysqli_fetch_assoc($resultgetscore);
                                                $row_cntgetscore = mysqli_num_rows($resultgetscore);


                                                if ($row_cntgetscore > 0) {
                                                    $sqlgetscoreCUMFirst = ("SELECT SUM(`exam` + `ca1` + `ca2` + `ca3` + `ca4` + `ca5` + `ca6` + `ca7` + `ca8` + `ca9` + `ca10`) AS Total FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '1st' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                                                    $resultgetscoreCUMFirst = mysqli_query($link, $sqlgetscoreCUMFirst);
                                                    $rowgetscoreCUMFirst = mysqli_fetch_assoc($resultgetscoreCUMFirst);
                                                    $row_cntgetscoreCUMFirst = mysqli_num_rows($resultgetscoreCUMFirst);

                                                    if ($row_cntgetscoreCUMFirst == NULL) {

                                                        $totalCUMFirst = 0;
                                                    } else {
                                                        $totalCUMFirst = round($rowgetscoreCUMFirst['Total'], 2);
                                                    }

                                                    $sqlgetscoreCUMsec = ("SELECT SUM(`exam` + `ca1` + `ca2` + `ca3` + `ca4` + `ca5` + `ca6` + `ca7` + `ca8` + `ca9` + `ca10`) AS Total FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '2nd' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                                                    $resultgetscoreCUMsec = mysqli_query($link, $sqlgetscoreCUMsec);
                                                    $rowgetscoreCUMsec = mysqli_fetch_assoc($resultgetscoreCUMsec);
                                                    $row_cntgetscoreCUMsec = mysqli_num_rows($resultgetscoreCUMsec);

                                                    if ($row_cntgetscoreCUMsec == NULL) {

                                                        $totalCUMsec = 0;
                                                    } else {
                                                        $totalCUMsec = round($rowgetscoreCUMsec['Total'], 2);
                                                    }

                                                    $sqlgetscoreCUMthr = ("SELECT SUM(`exam` + `ca1` + `ca2` + `ca3` + `ca4` + `ca5` + `ca6` + `ca7` + `ca8` + `ca9` + `ca10`) AS Total FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '3rd' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                                                    $resultgetscoreCUMthr = mysqli_query($link, $sqlgetscoreCUMthr);
                                                    $rowgetscoreCUMthr = mysqli_fetch_assoc($resultgetscoreCUMthr);
                                                    $row_cntgetscoreCUMthr = mysqli_num_rows($resultgetscoreCUMthr);

                                                    if ($row_cntgetscoreCUMthr == NULL) {

                                                        $totalCUMthr = 0;
                                                    } else {
                                                        $totalCUMthr = round($rowgetscoreCUMthr['Total'], 2);
                                                    }

                                                    $total = $totalCUMFirst + $totalCUMsec + $totalCUMthr;

                                                    $sqlgettermtodivide = ("SELECT DISTINCT(Term) AS newterm FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                                                    $resultgettermtodivide = mysqli_query($link, $sqlgettermtodivide);
                                                    $rowgettermtodivide = mysqli_fetch_assoc($resultgettermtodivide);
                                                    $row_cntgettermtodivide = mysqli_num_rows($resultgettermtodivide);

                                                    $subavg =  round(($total / $row_cntgettermtodivide), 2);

                                                    $sqlgetgradstuc = ("SELECT * FROM `gradingstructure` INNER JOIN assigngradingtclass ON gradingstructure.GradingTitle = assigngradingtclass.GradingTitle WHERE $subavg >= RangeStart AND $subavg <= RangeEnd AND ClassID = '$classid'");
                                                    $resultgetgradstuc = mysqli_query($link, $sqlgetgradstuc);
                                                    $rowgetgradstuc = mysqli_fetch_assoc($resultgetgradstuc);
                                                    $row_cntgetgradstuc = mysqli_num_rows($resultgetgradstuc);

                                                    if ($row_cntgetgradstuc > 0) {
                                                        $grade = $rowgetgradstuc['Grade'];
                                                        $remark = $rowgetgradstuc['Remark'];

                                                        $sqlsunnyhihhscoreunamepersub = "SELECT DISTINCT(StudentID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS total FROM score WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual' AND SubjectID = '$subid' GROUP BY StudentID order by total DESC LIMIT 1";
                                                        $resultsunnyhihhscoreunamepersub = mysqli_query($link, $sqlsunnyhihhscoreunamepersub);
                                                        $rowsunnyhihhscoreunamepersub = mysqli_fetch_assoc($resultsunnyhihhscoreunamepersub);
                                                        $row_cntsunnyhihhscoreunamepersub = mysqli_num_rows($resultsunnyhihhscoreunamepersub);

                                                        $sunhihscrunpersub = round($rowsunnyhihhscoreunamepersub['total']);

                                                        $sqlsunnylowwscoreunamepersub = "SELECT DISTINCT(StudentID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS total FROM score WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual' AND SubjectID = '$subid' GROUP BY StudentID order by total ASC LIMIT 1";
                                                        $resultsunnylowwscoreunamepersub = mysqli_query($link, $sqlsunnylowwscoreunamepersub);
                                                        $rowsunnylowwscoreunamepersub = mysqli_fetch_assoc($resultsunnylowwscoreunamepersub);
                                                        $row_cntsunnylowwscoreunamepersub = mysqli_num_rows($resultsunnylowwscoreunamepersub);

                                                        $sunlowscrunpersub = round($rowsunnylowwscoreunamepersub['total']);
                                                    } else {
                                                    }

                                                    echo '<tr>
                                                                    <th>' . $subname . '</th>
                                                                    <th>' . $totalCUMFirst . '</th>
                                                                    <th>' . $totalCUMsec . '</th>
                                                                    <th>' . $totalCUMthr . '</th>
                                                                    <td>' . $total . '</td>
                                                                    <td>' . $subavg . '</td>
                                                                    <td>' . $grade . '</td>
                                                                    <td>' . $sunlowscrunpersub . '</td>
                                                                    <td>' . $sunhihscrunpersub . '</td>
                                                                    <td>' . $remark . '</td>
                                                                </tr>';
                                                } else {
                                                }
                                            } while ($rowGetsub = mysqli_fetch_assoc($resultsub));
                                        } else {
                                            echo '<tr><td align="center" colspan="15" style="font-size: calc(12px + (18 - 12) * ((100vw - 300px) / (1600 - 300)))"><div class="alert alert-info alert-dismissible mb-2" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>No Result Yet</div></tr></td>';
                                        }

                                        ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php
                            $sqlresumdateOld = ("SELECT * FROM `resumptiondate` WHERE `Session`='$session' AND `Term`='3rd'");

                            $resultresumdateOld = mysqli_query($link, $sqlresumdateOld);
                            $getresumdateOld = mysqli_fetch_assoc($resultresumdateOld);
                            $sqlrow_cntresumdateOld = mysqli_num_rows($resultresumdateOld);

                            if ($sqlrow_cntresumdateOld > 0) {
                                $resumdateOld = $getresumdateOld['Date'];
                            } else {
                                $resumdateOld = 'N/A';
                            }

                            $sqlgetsubscore = ("SELECT * FROM `score` WHERE (`ca1` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND SubjectID = '0' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'");
                            $resultgetsubscore = mysqli_query($link, $sqlgetsubscore);
                            $rowgetsubscore = mysqli_fetch_assoc($resultgetsubscore);
                            $row_cntgetsubscore = mysqli_num_rows($resultgetsubscore);

                            if ($row_cntgetsubscore > 0) {

                                $rowcountfixedgen = $rowgetsubscore['ca1'];
                                $rowcountfixedpresent = $rowgetsubscore['ca2'];
                                $rowcountfixedlate = $rowgetsubscore['ca3'];
                                $rowcountfixedabsent = $rowgetsubscore['ca4'];
                            } else {
                                $sqlgettechgen = mysqli_query($link, "SELECT DISTINCT(student_attendences.`date`) FROM `student_attendences` WHERE student_attendences.`session` = '$session' AND student_attendences.`term` = '$term2'");
                                $fetchfixedgen = mysqli_fetch_assoc($sqlgettechgen);
                                $rowcountfixedgen = mysqli_num_rows($sqlgettechgen);

                                if ($rowcountfixedgen == NULL || $rowcountfixedgen == '') {
                                    $rowcountfixedgennew = 0;
                                } else {
                                    $rowcountfixedgennew = $rowcountfixedgen;
                                }

                                $sqlgettechpresent = mysqli_query($link, "SELECT * FROM `student_attendences` INNER JOIN student_session ON student_attendences.student_session_id=student_session.id WHERE student_attendences.`session` = '$session' AND student_attendences.`term` = '$term2' AND student_session.`student_id`='$id' AND student_session.`session_id`='$session' AND student_session.`class_id`='$classid' AND `attendence_type_id`='1'");
                                $fetchfixedpresent = mysqli_fetch_assoc($sqlgettechpresent);
                                $rowcountfixedpresent = mysqli_num_rows($sqlgettechpresent);

                                if ($rowcountfixedpresent == NULL || $rowcountfixedpresent == '') {
                                    $rowcountfixedpresent = 0;
                                } else {
                                    $rowcountfixedpresent = $rowcountfixedpresent;
                                }

                                $sqlgettechabsent = mysqli_query($link, "SELECT * FROM `student_attendences` INNER JOIN student_session ON student_attendences.student_session_id=student_session.id WHERE student_attendences.`session` = '$session' AND student_attendences.`term` = '$term2' AND student_session.`student_id`='$id' AND student_session.`session_id`='$session' AND student_session.`class_id`='$classid' AND `attendence_type_id`='4'");
                                $fetchfixedabsent = mysqli_fetch_assoc($sqlgettechabsent);
                                $rowcountfixedabsent = mysqli_num_rows($sqlgettechabsent);

                                if ($rowcountfixedabsent == NULL || $rowcountfixedabsent == '') {
                                    $rowcountfixedabsent = 0;
                                } else {
                                    $rowcountfixedabsent = $rowcountfixedabsent;
                                }
                            }
                            ?>
                            <div align="center" class="summDD">
                                <p>Total Score: <?php echo $gettotscore; ?> </p>
                                <p>Average Score: <?php echo $gettotgrade; ?> </p>
                                <p>Class Average: <?php echo $decStubsubavg; ?> </p>
                                <p>No. of Subjects: <?php echo $row_cntgetscorecheck; ?></p>
                            </div>

                            <div class="performance">
                                <div class="row">
                                    <div class="col-4">
                                        <div class="containerForChart">

                                            <canvas class="newgraph" id="mysunChart" style="width:100%;"></canvas>

                                        </div>
                                    </div>
                                    <div class="col-4" style="padding-right: 0px">
                                        <div class="container-motto" style="margin-right: 2px;">
                                            <div class="result table-responsive" style="margin: 10px; margin-top: 5px;">
                                                <table class="tab table-sm" style="width:98%;">
                                                    <tr>
                                                        <th colspan="4" style="text-align: center;">AFFECTIVE DOMAIN </th>
                                                    </tr>
                                                    <tbody>
                                                        <?php

                                                        $sqlrelset = ("SELECT * FROM `affective_domain_settings` INNER JOIN assignsaftoclass ON affective_domain_settings.id=assignsaftoclass.AffectiveDomainSettingsId WHERE ClassID = '$classid'");
                                                        $resultrelset = mysqli_query($link, $sqlrelset);
                                                        $rowGetrelset = mysqli_fetch_assoc($resultrelset);
                                                        $row_cntrelset = mysqli_num_rows($resultrelset);

                                                        if ($row_cntrelset > 0) {
                                                            $sqlgetscore = ("SELECT * FROM `affective_domain_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND term = '3rd' AND sectionid = '$classsectionactual'");
                                                            $resultgetscore = mysqli_query($link, $sqlgetscore);
                                                            $rowgetscore = mysqli_fetch_assoc($resultgetscore);
                                                            $row_cntgetscore = mysqli_num_rows($resultgetscore);

                                                            if ($row_cntgetscore > 0) {
                                                                if ($rowGetrelset['NumberofAD'] == '1') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '2') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '3') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '4') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '5') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '6') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '7') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '8') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '9') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '10') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '11') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '12') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain12"] . '</td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '13') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain12"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD13Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain13"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '14') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain12"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD13Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain13"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD14Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain14"] . '</td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofAD'] == '15') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['AD1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain1"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain2"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain3"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain4"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain12"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain5"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD13Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain13"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain6"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD14Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain14"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain7"] . '</td>
                                                                                        <th>' . $rowGetrelset['AD15Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain15"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['AD8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["domain8"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } else {
                                                                }
                                                            } else {
                                                                echo '<tr><td align="center" colspan="15" style="font-size: calc(12px + (18 - 12) * ((100vw - 300px) / (1600 - 300)))"><div class="alert alert-info alert-dismissible mb-2" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>No Result Yet</div></tr></td>';
                                                            }
                                                        }
                                                        ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-4" style="padding-left: 0px">
                                        <div class="container-motto" style="margin-left: 2px;">
                                            <div class="result table-responsive" style="margin: 10px; margin-top: 5px;">
                                                <table class="tab table-sm" style="width:98%;">
                                                    <tr>
                                                        <th colspan="4" style="text-align: center;">PSYCOMOTOR</th>
                                                    </tr>
                                                    <tbody>
                                                        <?php

                                                        $sqlrelset = ("SELECT * FROM `psycomotor_settings` INNER JOIN assignspsycomotortoclass ON psycomotor_settings.id=assignspsycomotortoclass.PsycomotorSettingsId WHERE ClassID = '$classid'");
                                                        $resultrelset = mysqli_query($link, $sqlrelset);
                                                        $rowGetrelset = mysqli_fetch_assoc($resultrelset);
                                                        $row_cntrelset = mysqli_num_rows($resultrelset);

                                                        if ($row_cntrelset > 0) {
                                                            $sqlgetscore = ("SELECT * FROM `psycomotor_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND term = '3rd' AND sectionid = '$classsectionactual'");
                                                            $resultgetscore = mysqli_query($link, $sqlgetscore);
                                                            $rowgetscore = mysqli_fetch_assoc($resultgetscore);
                                                            $row_cntgetscore = mysqli_num_rows($resultgetscore);

                                                            if ($row_cntgetscore > 0) {
                                                                if ($rowGetrelset['NumberofP'] == '1') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '2') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '3') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '4') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '5') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '6') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '7') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '8') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '9') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td
>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '10') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <th>' . $rowGetrelset['P10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '11') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <th>' . $rowGetrelset['P10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <th>' . $rowGetrelset['P11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '12') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <th>' . $rowGetrelset['P10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <th>' . $rowGetrelset['P11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <th>' . $rowGetrelset['P12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor12"] . '</td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '13') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <th>' . $rowGetrelset['P11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <th>' . $rowGetrelset['P12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor12"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <th>' . $rowGetrelset['P13Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor13"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '14') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <th>' . $rowGetrelset['P11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <th>' . $rowGetrelset['P12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor12"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <th>' . $rowGetrelset['P13Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor13"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                        <th>' . $rowGetrelset['P14Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor14"] . '</td>
                                                                                    </tr>';
                                                                } elseif ($rowGetrelset['NumberofP'] == '15') {
                                                                    echo '<tr>
                                                                                        <th>' . $rowGetrelset['P1Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor1"] . '</td>
                                                                                        <th>' . $rowGetrelset['P9Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor9"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P2Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor2"] . '</td>
                                                                                        <th>' . $rowGetrelset['P10Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor10"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P3Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor3"] . '</td>
                                                                                        <th>' . $rowGetrelset['P11Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor11"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P4Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor4"] . '</td>
                                                                                        <th>' . $rowGetrelset['P12Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor12"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P5Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor5"] . '</td>
                                                                                        <th>' . $rowGetrelset['P13Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor13"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P6Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor6"] . '</td>
                                                                                        <th>' . $rowGetrelset['P14Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor14"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P7Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor7"] . '</td>
                                                                                        <th>' . $rowGetrelset['P15Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor15"] . '</td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>' . $rowGetrelset['P8Title'] . '</th>
                                                                                        <td>' . $rowgetscore["psycomotor8"] . '</td>
                                                                                        <td></td>
                                                                                        <td></td>
                                                                                    </tr>';
                                                                } else {
                                                                }
                                                            } else {
                                                                echo '<tr><td align="center" colspan="15" style="font-size: calc(12px + (18 - 12) * ((100vw - 300px) / (1600 - 300)))"><div class="alert alert-info alert-dismissible mb-2" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>No Result Yet</div></tr></td>';
                                                            }
                                                        }
                                                        ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <?php

                                    $sqlgettechremark = mysqli_query($link, "SELECT * FROM `remark` WHERE `RemarkType` = 'teacher' AND `StudentID`='$id' AND `Session`='$session' AND `Term`='3rd' AND `remark`!=''");
                                    $rowcountfixedremark = mysqli_num_rows($sqlgettechremark);
                                    $fetchfixedremark = mysqli_fetch_assoc($sqlgettechremark);


                                    if ($rowcountfixedremark > 0) {
                                        $teacherRemark = $fetchfixedremark['remark'];

                                        $teacherid = $fetchfixedremark['StaffID'];

                                        $sqlgetheadteachsign = ("SELECT * FROM `staffsignature` WHERE staff_id = '$hedteachid'");
                                        $resultgetheadteachsign = mysqli_query($link, $sqlgetheadteachsign);
                                        $rowgetheadteachsign = mysqli_fetch_assoc($resultgetheadteachsign);
                                        $row_cntgetheadteachsign = mysqli_num_rows($resultgetheadteachsign);


                                        if ($row_cntgetheadteachsign > 0) {
                                            $hedteachsign = '<img src="../img/signature/' . $rowgetheadteachsign['Signature'] . '" align="center" class="img-fluid" style="width: 70%;">';
                                        } else {
                                            $hedteachsign = '';
                                        }
                                    } else {

                                        $sqlgetteachremark = ("SELECT * FROM `defaultcomment` INNER JOIN class_teacher ON defaultcomment.PrincipalOrDeanOrHeadTeacherUserID=class_teacher.staff_id WHERE $gettotgrade BETWEEN RangeStart AND RangeEnd AND CommentType = 'teacher' AND class_id = '$classid'");
                                        $resultgetteachremark = mysqli_query($link, $sqlgetteachremark);
                                        $rowgetteachremark = mysqli_fetch_assoc($resultgetteachremark);
                                        $row_cntgetteachremark = mysqli_num_rows($resultgetteachremark);

                                        if ($row_cntgetteachremark > 0) {
                                            $teacherRemark = $rowgetteachremark['DefaultComment'];

                                            $teacherid = $rowgetteachremark['PrincipalOrDeanOrHeadTeacherUserID'];


                                            $sqlgetheadteachsign = ("SELECT * FROM `staffsignature` WHERE staff_id = '$teacherid'");
                                            $resultgetheadteachsign = mysqli_query($link, $sqlgetheadteachsign);
                                            $rowgetheadteachsign = mysqli_fetch_assoc($resultgetheadteachsign);
                                            $row_cntgetheadteachsign = mysqli_num_rows($resultgetheadteachsign);


                                            if ($row_cntgetheadteachsign > 0) {
                                                $hedteachsign = '<img src="../img/signature/' . $rowgetheadteachsign['Signature'] . '" align="center" class="img-fluid" style="width: 70%;">';
                                            } else {
                                                $hedteachsign = '';
                                            }
                                        } else {
                                            $teacherRemark = 'N/A';

                                            $hedteachsign = '';
                                        }
                                    }

                                    $sqlgetprincremark = mysqli_query($link, "SELECT * FROM `remark` WHERE `RemarkType` = 'SchoolHead' AND `StudentID`='$id' AND `Session`='$session' AND `Term`='3rd' AND `remark`!=''");
                                    $rowcountprinfixedremark = mysqli_num_rows($sqlgetprincremark);
                                    $fetchfixedprinremark = mysqli_fetch_assoc($sqlgetprincremark);

                                    if ($rowcountprinfixedremark > 0) {
                                        $principalRemark = $fetchfixedprinremark['remark'];

                                        $headteacherid = $fetchfixedprinremark['staff_id'];

                                        $sqlgetheadteachhead = ("SELECT * FROM `staffsignature` WHERE staff_id = 5");
                                        $resultgetheadteachsignhead = mysqli_query($link, $sqlgetheadteachhead);
                                        $rowgetheadteachsignhead = mysqli_fetch_assoc($resultgetheadteachsignhead);
                                        $row_cntgetheadteachsignhead = mysqli_num_rows($resultgetheadteachsignhead);

                                        if ($row_cntgetheadteachsignhead > 0) {
                                            $hedteachsignhead = '<img src="' . $defRUl . 'img/signature/' . $rowgetheadteachsignhead['Signature'] . '" align="center" class="img-fluid" style="width: 70%;">';
                                        } else {
                                            $hedteachsignhead = '';
                                        }
                                    } else {

                                        $sqlgetprincremark = ("SELECT * FROM `defaultcomment` WHERE $gettotgrade BETWEEN RangeStart AND RangeEnd AND CommentType = 'SchoolHead'");
                                        $resultgetprincremark = mysqli_query($link, $sqlgetprincremark);
                                        $rowgetteachremark = mysqli_fetch_assoc($resultgetprincremark);
                                        $row_cntgetprincremark = mysqli_num_rows($resultgetprincremark);

                                        if ($row_cntgetprincremark > 0) {
                                            $principalRemark = $rowgetteachremark['DefaultComment'];

                                            $headteacherid = $rowgetteachremark['PrincipalOrDeanOrHeadTeacherUserID'];

                                            $sqlgetheadteachhead = ("SELECT * FROM `staffsignature` WHERE staff_id = '$headteacherid'");
                                            $resultgetheadteachsignhead = mysqli_query($link, $sqlgetheadteachhead);
                                            $rowgetheadteachsignhead = mysqli_fetch_assoc($resultgetheadteachsignhead);
                                            $row_cntgetheadteachsignhead = mysqli_num_rows($resultgetheadteachsignhead);

                                            if ($row_cntgetheadteachsignhead > 0) {
                                                $hedteachsignhead = '<img src=" https://schoollift.s3.us-east-2.amazonaws.com/' . $rowgetheadteachsignhead['Signature'] . '" align="center" class="img-fluid" style="width: 80%;">';
                                            } else {
                                                $hedteachsignhead = '';
                                            }
                                        } else {
                                            $principalRemark = 'N/A';

                                            $hedteachsignhead = '';
                                        }
                                    }

                                    $sqlresumdate = ("SELECT * FROM `resumptiondate` WHERE `Session`='$sessionnew' AND `Term`='3rd'");

                                    $resultresumdate = mysqli_query($link, $sqlresumdate);
                                    $getresumdate = mysqli_fetch_assoc($resultresumdate);
                                    $sqlrow_cntresumdate = mysqli_num_rows($resultresumdate);

                                    if ($sqlrow_cntresumdate > 0) {
                                        $resumdate = date("l jS \of F Y", strtotime($getresumdate['Date']));
                                    } else {
                                        $resumdate = 'N/A';
                                    }
                                    ?>
                                    <div class="col-12">

                                        <div class="container-motto">
                                            <div style="margin: 20px;">
                                                <div class="row">
                                                    <div class="col-sm-10 col-md-10">
                                                        <div align="center">
                                                            <p style="text-align: justify;"><b style="font-weight:600;">CLASS TEACHER'S COMMENT:</b>&nbsp;<?php echo $teacherRemark; ?></p>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-2 col-md-2">
                                                        <div align="center">
                                                            <?php echo $hedteachsign; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-sm-10 col-md-10">
                                                        <div>
                                                            <p style="text-align: justify;"><b style="font-weight:600;">PRINCIPAL/HEAD TEACHER'S COMMENT:</b>&nbsp;&nbsp;&nbsp;<?php echo $principalRemark; ?></p>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-2 col-md-2">
                                                        <div>
                                                            <?php echo $hedteachsignhead; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-sm-12 col-md-12">
                                                        <div>
                                                            <p style="text-align: justify;"><b style="font-weight:600;">NEXT TERM BEGINS:</b>&nbsp;<?php echo $resumdate; ?></b></p>
                                                            <?php
                                                            if ($next_fee > 0) {
                                                                echo '<p style="text-align: center;"><b style="font-weight:600;">NEXT TERM FEE: N' . $next_fee . '</b></p>';
                                                            }
                                                            ?>

                                                        </div>
                                                    </div>

                                                </div>

                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            <?php



                            $sqlstudent_session = ("SELECT * FROM `student_session` WHERE `session_id`='$sessionnew' AND student_id = '$id'");

                            $resultstudent_session = mysqli_query($link, $sqlstudent_session);
                            $getstudent_session = mysqli_fetch_assoc($resultstudent_session);
                            $sqlrow_cntstudent_session = mysqli_num_rows($resultstudent_session);
                            /*
                                                if($sqlrow_cntstudent_session > 0)
                                                {
                                                    echo '<center>
                                                        <p class="promoted">PROMOTED TO THE NEXT CLASS</p>
                                                    </center>';
                                                }
                                                else
                                                {
                                                    echo '<center>
                                                        <p class="promoted">NOT PROMOTED TO THE NEXT CLASS</p>
                                                    </center>';
                                                }
                                                */

                            ?>

                        <?php
                        } elseif ($reltype == 'numeric') {
                        ?>
                            <div class="container-motto">
                                <?php

                                $sqlgetsubscore = ("SELECT * FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND SubjectID != '0' AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual'");
                                $resultgetsubscore = mysqli_query($link, $sqlgetsubscore);
                                $rowgetsubscore = mysqli_fetch_assoc($resultgetsubscore);
                                $row_cntgetsubscore = mysqli_num_rows($resultgetsubscore);

                                $sqlgettotalgrade = "SELECT SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS average FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual'";
                                $resultgettotalgrade = mysqli_query($link, $sqlgettotalgrade);
                                $rowgettotalgrade = mysqli_fetch_assoc($resultgettotalgrade);
                                $row_cntgettotalgrade = mysqli_num_rows($resultgettotalgrade);

                                $gettotgrade = floatval(round($rowgettotalgrade['average'] / $row_cntgetsubscore, 2));

                                $gettotscore = $rowgettotalgrade['average'];

                                $sqlgetClasscount = ("SELECT DISTINCT(StudentID) FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual'");
                                $resultgetClasscount = mysqli_query($link, $sqlgetClasscount);
                                $rowgetClasscount = mysqli_fetch_assoc($resultgetClasscount);
                                $row_cntClasscount = mysqli_num_rows($resultgetClasscount);

                                $sqlgetsubscoreALL = ("SELECT DISTINCT(SubjectID) FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual'");
                                $resultgetsubscoreALL = mysqli_query($link, $sqlgetsubscoreALL);
                                $rowgetsubscoreALL = mysqli_fetch_assoc($resultgetsubscoreALL);
                                $row_cntgetsubscoreALL = mysqli_num_rows($resultgetsubscoreALL);

                                $sqlgettotclassscor = "SELECT SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS totalScore FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual' ORDER BY exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10";
                                $resultgettotclassscor = mysqli_query($link, $sqlgettotclassscor);
                                $rowgettotclassscor = mysqli_fetch_assoc($resultgettotclassscor);
                                $row_cntgettotclassscor = mysqli_num_rows($resultgettotclassscor);

                                $totsubjects = $row_cntClasscount * $row_cntgetsubscore;
                                $totsubjectsALL = $row_cntClasscount * $row_cntgetsubscoreALL;

                                $decStubsubavg = round($rowgettotclassscor['totalScore'] / $totsubjectsALL, 2);

                                $sqlsunnyhihhscoreuname = "SELECT DISTINCT(StudentID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10),COUNT(ID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) / COUNT(ID) AS total FROM score WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual' GROUP BY StudentID order by total DESC LIMIT 1";
                                $resultsunnyhihhscoreuname = mysqli_query($link, $sqlsunnyhihhscoreuname);
                                $rowsunnyhihhscoreuname = mysqli_fetch_assoc($resultsunnyhihhscoreuname);
                                $row_cntsunnyhihhscoreuname = mysqli_num_rows($resultsunnyhihhscoreuname);

                                $sunhihscrun = round($rowsunnyhihhscoreuname['total'], 2);

                                $sqlsunnylowwscoreuname = "SELECT DISTINCT(StudentID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10),COUNT(ID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) / COUNT(ID) AS total FROM score WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual' GROUP BY StudentID order by total ASC LIMIT 1";
                                $resultsunnylowwscoreuname = mysqli_query($link, $sqlsunnylowwscoreuname);
                                $rowsunnylowwscoreuname = mysqli_fetch_assoc($resultsunnylowwscoreuname);
                                $row_cntsunnylowwscoreuname = mysqli_num_rows($resultsunnylowwscoreuname);

                                $sunlowscrun = round($rowsunnylowwscoreuname['total'], 2);

                                $sqlgetscoretotalscorpositon = "SELECT * FROM (SELECT *, @n := @n + 1 n FROM (SELECT SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS total, StudentID FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual' GROUP BY StudentID ORDER BY total DESC) as sunny, (SELECT @n := 0) as m) as sunito WHERE sunito.StudentID='$id'";
                                $resultgetscoretotalscorpositon = mysqli_query($link, $sqlgetscoretotalscorpositon);
                                $rowgetscoretotalscorpositon = mysqli_fetch_assoc($resultgetscoretotalscorpositon);
                                $row_cntgetscoretotalscorpositon = mysqli_num_rows($resultgetscoretotalscorpositon);

                                $gettotalscorpositon = $rowgetscoretotalscorpositon['n'];

                                function addOrdinalNumberSuffix($num)
                                {
                                    if (!in_array(($num % 100), array(11, 12, 13))) {
                                        switch ($num % 10) {
                                                // Handle 1st, 2nd, 3rd
                                            case 1:
                                                return $num . 'st';
                                            case 2:
                                                return $num . 'nd';
                                            case 3:
                                                return $num . 'rd';
                                        }
                                    }
                                    return $num . 'th';
                                }

                                if ($row_cntgettotalgrade > 0) {
                                    $sqlgettotgradstuc = ("SELECT * FROM `gradingstructure` INNER JOIN assigngradingtclass ON gradingstructure.GradingTitle = assigngradingtclass.GradingTitle WHERE $gettotgrade >= RangeStart AND $gettotgrade <= RangeEnd AND ClassID = '$classid'");
                                    $resultgettotgradstuc = mysqli_query($link, $sqlgettotgradstuc);
                                    $rowgettotgradstuc = mysqli_fetch_assoc($resultgettotgradstuc);
                                    $row_cntgettotgradstuc = mysqli_num_rows($resultgettotgradstuc);

                                    if ($row_cntgettotgradstuc > 0) {

                                        $totscorgrade = $rowgettotgradstuc['Grade'];
                                    } else {

                                        $totscorgrade = 'NA';
                                    }
                                } else {
                                    $gettotgrade = 'NA';
                                }

                                ?>

                                <div class="row" style="margin: 10px;">
                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>NAME:</b> <?php echo $studname; ?></h5>
                                    </div>

                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>CLASS:</b> <?php echo $studclass . ' ' . $studsection; ?></h5>
                                    </div>

                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>SEX:</b> <?php echo $studgender; ?></h5>
                                    </div>


                                </div>

                                <div class="row" style="margin: 10px;">


                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>CLASS POSITION:</b> <?php
                                                                                            echo addOrdinalNumberSuffix($gettotalscorpositon) . "\t";

                                                                                            if ($gettotalscorpositon % 10 == 0) {
                                                                                                echo "\n";
                                                                                            }
                                                                                            ?> </h5>
                                    </div>

                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>HIGHEST IN CLASS AVE:</b> <?php echo $sunhihscrun; ?></h5>
                                    </div>

                                    <div class="col-4">
                                        <h5 style="color: #000000;"> <b>LOWEST IN CLASS AVE:</b> <?php echo $sunlowscrun; ?></h5>
                                    </div>

                                </div>


                            </div>

                            <div align="center">
                                <h5 style="font-size: 18px; font-weight: 800; color: #000000; margin-bottom: 0px;">ACADEMIC PERFORMANCE</h5>
                            </div>

                            <div class="result table-responsive" style="margin: 10px; margin-top: 5px;">

                                <table class="table-bordered tab table-sm tb-result-border">

                                    <tr>
                                        <th>SUBJECT(s)</th>
                                        <th>1ST TERM</th>
                                        <th>2ND TERM</th>
                                        <th>3RD TERM</th>
                                        <th>TOTAL</th>
                                        <th>AVERAGE</th>
                                        <th>POSITION</th>
                                        <th>LOWEST IN CLASS</th>
                                        <th>HIGHEST IN CLASS</th>
                                        <th>REMARK</th>
                                    </tr>

                                    <tbody>
                                        <?php

                                        $sqlsub = ("SELECT subjects.name AS name, subjects.id as id FROM `subject_group_class_sections` INNER JOIN subject_group_subjects ON subject_group_class_sections.subject_group_id=subject_group_subjects.subject_group_id INNER JOIN subjects ON subject_group_subjects.subject_id=subjects.id WHERE subject_group_class_sections.class_section_id = '$classsection' AND subject_group_class_sections.session_id='$session' AND subject_group_subjects.session_id='$session'");
                                        $resultsub = mysqli_query($link, $sqlsub);
                                        $rowGetsub = mysqli_fetch_assoc($resultsub);
                                        $row_cntsub = mysqli_num_rows($resultsub);

                                        $sqlgetscorecheck = ("SELECT * FROM `score` WHERE StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual'");
                                        $resultgetscorecheck = mysqli_query($link, $sqlgetscorecheck);
                                        $rowgetscorecheck = mysqli_fetch_assoc($resultgetscorecheck);
                                        $row_cntgetscorecheck = mysqli_num_rows($resultgetscorecheck);

                                        if ($row_cntgetscorecheck > 0) {

                                            do {

                                                $subname = $rowGetsub['name'];
                                                $subid = $rowGetsub['id'];

                                                $sqlgetscore = ("SELECT * FROM `score` WHERE StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                                                $resultgetscore = mysqli_query($link, $sqlgetscore);
                                                $rowgetscore = mysqli_fetch_assoc($resultgetscore);
                                                $row_cntgetscore = mysqli_num_rows($resultgetscore);


                                                $sqlgetscore = ("SELECT * FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                                                $resultgetscore = mysqli_query($link, $sqlgetscore);
                                                $rowgetscore = mysqli_fetch_assoc($resultgetscore);
                                                $row_cntgetscore = mysqli_num_rows($resultgetscore);

                                                if ($row_cntgetscore > 0) {
                                                    $sqlgetscoreCUMFirst = ("SELECT SUM(`exam` + `ca1` + `ca2` + `ca3` + `ca4` + `ca5` + `ca6` + `ca7` + `ca8` + `ca9` + `ca10`) AS Total FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '1st' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                                                    $resultgetscoreCUMFirst = mysqli_query($link, $sqlgetscoreCUMFirst);
                                                    $rowgetscoreCUMFirst = mysqli_fetch_assoc($resultgetscoreCUMFirst);
                                                    $row_cntgetscoreCUMFirst = mysqli_num_rows($resultgetscoreCUMFirst);

                                                    if ($row_cntgetscoreCUMFirst == NULL) {

                                                        $totalCUMFirst = 0;
                                                    } else {
                                                        $totalCUMFirst = round($rowgetscoreCUMFirst['Total'], 2);
                                                    }

                                                    $sqlgetscoreCUMsec = ("SELECT SUM(`exam` + `ca1` + `ca2` + `ca3` + `ca4` + `ca5` + `ca6` + `ca7` + `ca8` + `ca9` + `ca10`) AS Total FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '2nd' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                                                    $resultgetscoreCUMsec = mysqli_query($link, $sqlgetscoreCUMsec);
                                                    $rowgetscoreCUMsec = mysqli_fetch_assoc($resultgetscoreCUMsec);
                                                    $row_cntgetscoreCUMsec = mysqli_num_rows($resultgetscoreCUMsec);

                                                    if ($row_cntgetscoreCUMsec == NULL) {

                                                        $totalCUMsec = 0;
                                                    } else {
                                                        $totalCUMsec = round($rowgetscoreCUMsec['Total'], 2);
                                                    }

                                                    $sqlgetscoreCUMthr = ("SELECT SUM(`exam` + `ca1` + `ca2` + `ca3` + `ca4` + `ca5` + `ca6` + `ca7` + `ca8` + `ca9` + `ca10`) AS Total FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND Term = '3rd' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                                                    $resultgetscoreCUMthr = mysqli_query($link, $sqlgetscoreCUMthr);
                                                    $rowgetscoreCUMthr = mysqli_fetch_assoc($resultgetscoreCUMthr);
                                                    $row_cntgetscoreCUMthr = mysqli_num_rows($resultgetscoreCUMthr);

                                                    if ($row_cntgetscoreCUMthr == NULL) {

                                                        $totalCUMthr = 0;
                                                    } else {
                                                        $totalCUMthr = round($rowgetscoreCUMthr['Total'], 2);
                                                    }

                                                    $total = $totalCUMFirst + $totalCUMsec + $totalCUMthr;

                                                    $sqlgettermtodivide = ("SELECT DISTINCT(Term) AS newterm FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual' AND SubjectID='$subid'");
                                                    $resultgettermtodivide = mysqli_query($link, $sqlgettermtodivide);
                                                    $rowgettermtodivide = mysqli_fetch_assoc($resultgettermtodivide);
                                                    $row_cntgettermtodivide = mysqli_num_rows($resultgettermtodivide);

                                                    $subavg =  round(($total / $row_cntgettermtodivide), 2);

                                                    $sqlgetgradstuc = ("SELECT * FROM `gradingstructure` INNER JOIN assigngradingtclass ON gradingstructure.GradingTitle = assigngradingtclass.GradingTitle WHERE $subavg >= RangeStart AND $subavg <= RangeEnd AND ClassID = '$classid'");
                                                    $resultgetgradstuc = mysqli_query($link, $sqlgetgradstuc);
                                                    $rowgetgradstuc = mysqli_fetch_assoc($resultgetgradstuc);
                                                    $row_cntgetgradstuc = mysqli_num_rows($resultgetgradstuc);

                                                    if ($row_cntgetgradstuc > 0) {
                                                        $grade = $rowgetgradstuc['Grade'];
                                                        $remark = $rowgetgradstuc['Remark'];

                                                        $sqlsunnyhihhscoreunamepersub = "SELECT DISTINCT(StudentID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS total FROM score WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual' AND SubjectID = '$subid' GROUP BY StudentID order by total DESC LIMIT 1";
                                                        $resultsunnyhihhscoreunamepersub = mysqli_query($link, $sqlsunnyhihhscoreunamepersub);
                                                        $rowsunnyhihhscoreunamepersub = mysqli_fetch_assoc($resultsunnyhihhscoreunamepersub);
                                                        $row_cntsunnyhihhscoreunamepersub = mysqli_num_rows($resultsunnyhihhscoreunamepersub);

                                                        $sunhihscrunpersub = round($rowsunnyhihhscoreunamepersub['total']);

                                                        $sqlsunnylowwscoreunamepersub = "SELECT DISTINCT(StudentID), SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS total FROM score WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual' AND SubjectID = '$subid' GROUP BY StudentID order by total ASC LIMIT 1";
                                                        $resultsunnylowwscoreunamepersub = mysqli_query($link, $sqlsunnylowwscoreunamepersub);
                                                        $rowsunnylowwscoreunamepersub = mysqli_fetch_assoc($resultsunnylowwscoreunamepersub);
                                                        $row_cntsunnylowwscoreunamepersub = mysqli_num_rows($resultsunnylowwscoreunamepersub);

                                                        $sunlowscrunpersub = round($rowsunnylowwscoreunamepersub['total']);

                                                        $sqlgetscorepos = "SELECT * FROM (SELECT *, @n := @n + 1 n FROM (SELECT SUM(exam + ca1 + ca2 + ca3 + ca4 + ca5 + ca6 + ca7 + ca8 + ca9 + ca10) AS total, StudentID FROM `score` WHERE (`exam` !='0' OR `ca1` !='0' OR `ca2` !='0' OR `ca3` !='0' OR `ca4` !='0' OR `ca5` !='0' OR `ca6` !='0' OR `ca7` !='0' OR `ca8` !='0' OR `ca9` !='0' OR `ca10` !='0') AND ClassID = '$classid' AND Session = '$session' AND SectionID = '$classsectionactual' GROUP BY StudentID ORDER BY total DESC) as sunny, (SELECT @n := 0) as m) as sunito WHERE sunito.StudentID='$id'";
                                                        $resultgetscorepos = mysqli_query($link, $sqlgetscorepos);
                                                        $rowgetscorepos = mysqli_fetch_assoc($resultgetscorepos);
                                                        $row_cntgetscorepos = mysqli_num_rows($resultgetscorepos);

                                                        $getsco = round($rowgetscorepos['total'], 2);

                                                        $getscorpos = $rowgetscorepos['n'];
                                                    } else {
                                                    }

                                                    echo '<tr>
                                                                        <th>' . $subname . '</th>
                                                                        <th>' . $totalCUMFirst . '</th>
                                                                        <th>' . $totalCUMsec . '</th>
                                                                        <th>' . $totalCUMthr . '</th>
                                                                        <td>' . $total . '</td>
                                                                        <td>' . $subavg . '</td>
                                                                        <td>';
                                                    echo addOrdinalNumberSuffix($getscorpos) . "\t";

                                                    if ($getscorpos % 10 == 0) {
                                                        echo "\n";
                                                    }
                                                    echo '</td>
                                                                        <td>' . $sunlowscrunpersub . '</td>
                                                                        <td>' . $sunhihscrunpersub . '</td>
                                                                        <td>' . $remark . '</td>
                                                                    </tr>';
                                                } else {
                                                }
                                            } while ($rowGetsub = mysqli_fetch_assoc($resultsub));
                                        } else {
                                            echo '<tr><td align="center" colspan="15" style="font-size: calc(12px + (18 - 12) * ((100vw - 300px) / (1600 - 300)))"><div class="alert alert-info alert-dismissible mb-2" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>No Result Yet</div></tr></td>';
                                        }

                                        ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php
                            $sqlresumdateOld = ("SELECT * FROM `resumptiondate` WHERE `Session`='$session' AND `Term`='$term'");

                            $resultresumdateOld = mysqli_query($link, $sqlresumdateOld);
                            $getresumdateOld = mysqli_fetch_assoc($resultresumdateOld);
                            $sqlrow_cntresumdateOld = mysqli_num_rows($resultresumdateOld);

                            if ($sqlrow_cntresumdateOld > 0) {
                                $resumdateOld = $getresumdateOld['Date'];
                            } else {
                                $resumdateOld = 'N/A';
                            }

                            $sqlgetsubscore = ("SELECT * FROM `score` WHERE (`ca1` !='0') AND StudentID = '$id' AND ClassID = '$classid' AND SubjectID = '0' AND Session = '$session' AND Term = '$term' AND SectionID = '$classsectionactual'");
                            $resultgetsubscore = mysqli_query($link, $sqlgetsubscore);
                            $rowgetsubscore = mysqli_fetch_assoc($resultgetsubscore);
                            $row_cntgetsubscore = mysqli_num_rows($resultgetsubscore);

                            if ($row_cntgetsubscore > 0) {

                                $rowcountfixedgen = $rowgetsubscore['ca1'];
                                $rowcountfixedpresent = $rowgetsubscore['ca2'];
                                $rowcountfixedlate = $rowgetsubscore['ca3'];
                                $rowcountfixedabsent = $rowgetsubscore['ca4'];
                            } else {
                                $sqlgettechgen = mysqli_query($link, "SELECT DISTINCT(student_attendences.`date`) FROM `student_attendences` WHERE student_attendences.`session` = '$session' AND student_attendences.`term` = '$term2'");
                                $fetchfixedgen = mysqli_fetch_assoc($sqlgettechgen);
                                $rowcountfixedgen = mysqli_num_rows($sqlgettechgen);

                                if ($rowcountfixedgen == NULL || $rowcountfixedgen == '') {
                                    $rowcountfixedgennew = 0;
                                } else {
                                    $rowcountfixedgennew = $rowcountfixedgen;
                                }

                                $sqlgettechpresent = mysqli_query($link, "SELECT * FROM `student_attendences` INNER JOIN student_session ON student_attendences.student_session_id=student_session.id WHERE student_attendences.`session` = '$session' AND student_attendences.`term` = '$term2' AND student_session.`student_id`='$id' AND student_session.`session_id`='$session' AND student_session.`class_id`='$classid' AND `attendence_type_id`='1'");
                                $fetchfixedpresent = mysqli_fetch_assoc($sqlgettechpresent);
                                $rowcountfixedpresent = mysqli_num_rows($sqlgettechpresent);

                                if ($rowcountfixedpresent == NULL || $rowcountfixedpresent == '') {
                                    $rowcountfixedpresent = 0;
                                } else {
                                    $rowcountfixedpresent = $rowcountfixedpresent;
                                }

                                $sqlgettechabsent = mysqli_query($link, "SELECT * FROM `student_attendences` INNER JOIN student_session ON student_attendences.student_session_id=student_session.id WHERE student_attendences.`session` = '$session' AND student_attendences.`term` = '$term2' AND student_session.`student_id`='$id' AND student_session.`session_id`='$session' AND student_session.`class_id`='$classid' AND `attendence_type_id`='4'");
                                $fetchfixedabsent = mysqli_fetch_assoc($sqlgettechabsent);
                                $rowcountfixedabsent = mysqli_num_rows($sqlgettechabsent);

                                if ($rowcountfixedabsent == NULL || $rowcountfixedabsent == '') {
                                    $rowcountfixedabsent = 0;
                                } else {
                                    $rowcountfixedabsent = $rowcountfixedabsent;
                                }
                            }
                            ?>
                            <div align="center" class="summDD">
                                <p>Total Score: <?php echo $gettotscore; ?> </p>
                                <p>Average Score: <?php echo $gettotgrade; ?> </p>
                                <p>Class Average: <?php echo $decStubsubavg; ?> </p>
                                <p>No. of Subjects: <?php echo $row_cntgetscorecheck; ?></p>
                            </div>

                            <div class="performance">
                                <div class="row">
                                    <div class="col-4">
                                        <div class="containerForChart">

                                            <canvas class="newgraph" id="mysunChart" style="width:100%;height:100%;"></canvas>

                                        </div>
                                    </div>
                                    <?php
                                    $sqlGetGradingSystem = "SELECT * FROM `assignsaftoclass` INNER JOIN affective_domain_settings ON assignsaftoclass.AffectiveDomainSettingsId=affective_domain_settings.id WHERE assignsaftoclass.ClassID='$classid'";
                                    $queryGetGradingSystem = mysqli_query($link, $sqlGetGradingSystem);
                                    $rowGetGradingSystem = mysqli_fetch_assoc($queryGetGradingSystem);
                                    $countGetGradingSystem = mysqli_num_rows($queryGetGradingSystem);

                                    if ($countGetGradingSystem > 0) {
                                        if ($rowGetGradingSystem['NumberofAD'] == '1') {
                                            $ad1title = $rowGetGradingSystem['AD1Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `affective_domain_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $domain1 = $rowGetstudent_session["domain1"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofAD'] == '2') {
                                            $ad1title = $rowGetGradingSystem['AD1Title'];
                                            $ad2title = $rowGetGradingSystem['AD2Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `affective_domain_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $domain1 = $rowGetstudent_session["domain1"];
                                                $domain2 = $rowGetstudent_session["domain2"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofAD'] == '3') {
                                            $ad1title = $rowGetGradingSystem['AD1Title'];
                                            $ad2title = $rowGetGradingSystem['AD2Title'];
                                            $ad3title = $rowGetGradingSystem['AD3Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `affective_domain_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $domain1 = $rowGetstudent_session["domain1"];
                                                $domain2 = $rowGetstudent_session["domain2"];
                                                $domain3 = $rowGetstudent_session["domain3"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofAD'] == '4') {
                                            $ad1title = $rowGetGradingSystem['AD1Title'];
                                            $ad2title = $rowGetGradingSystem['AD2Title'];
                                            $ad3title = $rowGetGradingSystem['AD3Title'];
                                            $ad4title = $rowGetGradingSystem['AD4Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `affective_domain_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $domain1 = $rowGetstudent_session["domain1"];
                                                $domain2 = $rowGetstudent_session["domain2"];
                                                $domain3 = $rowGetstudent_session["domain3"];
                                                $domain4 = $rowGetstudent_session["domain4"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofAD'] == '5') {
                                            $ad1title = $rowGetGradingSystem['AD1Title'];
                                            $ad2title = $rowGetGradingSystem['AD2Title'];
                                            $ad3title = $rowGetGradingSystem['AD3Title'];
                                            $ad4title = $rowGetGradingSystem['AD4Title'];
                                            $ad5title = $rowGetGradingSystem['AD5Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `affective_domain_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $domain1 = $rowGetstudent_session["domain1"];
                                                $domain2 = $rowGetstudent_session["domain2"];
                                                $domain3 = $rowGetstudent_session["domain3"];
                                                $domain4 = $rowGetstudent_session["domain4"];
                                                $domain5 = $rowGetstudent_session["domain5"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofAD'] == '6') {
                                            $ad1title = $rowGetGradingSystem['AD1Title'];
                                            $ad2title = $rowGetGradingSystem['AD2Title'];
                                            $ad3title = $rowGetGradingSystem['AD3Title'];
                                            $ad4title = $rowGetGradingSystem['AD4Title'];
                                            $ad5title = $rowGetGradingSystem['AD5Title'];
                                            $ad6title = $rowGetGradingSystem['AD6Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `affective_domain_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $domain1 = $rowGetstudent_session["domain1"];
                                                $domain2 = $rowGetstudent_session["domain2"];
                                                $domain3 = $rowGetstudent_session["domain3"];
                                                $domain4 = $rowGetstudent_session["domain4"];
                                                $domain5 = $rowGetstudent_session["domain5"];
                                                $domain6 = $rowGetstudent_session["domain6"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofAD'] == '7') {
                                            $ad1title = $rowGetGradingSystem['AD1Title'];
                                            $ad2title = $rowGetGradingSystem['AD2Title'];
                                            $ad3title = $rowGetGradingSystem['AD3Title'];
                                            $ad4title = $rowGetGradingSystem['AD4Title'];
                                            $ad5title = $rowGetGradingSystem['AD5Title'];
                                            $ad6title = $rowGetGradingSystem['AD6Title'];
                                            $ad7title = $rowGetGradingSystem['AD7Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `affective_domain_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $domain1 = $rowGetstudent_session["domain1"];
                                                $domain2 = $rowGetstudent_session["domain2"];
                                                $domain3 = $rowGetstudent_session["domain3"];
                                                $domain4 = $rowGetstudent_session["domain4"];
                                                $domain5 = $rowGetstudent_session["domain5"];
                                                $domain6 = $rowGetstudent_session["domain6"];
                                                $domain7 = $rowGetstudent_session["domain7"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofAD'] == '8') {
                                            $ad1title = $rowGetGradingSystem['AD1Title'];
                                            $ad2title = $rowGetGradingSystem['AD2Title'];
                                            $ad3title = $rowGetGradingSystem['AD3Title'];
                                            $ad4title = $rowGetGradingSystem['AD4Title'];
                                            $ad5title = $rowGetGradingSystem['AD5Title'];
                                            $ad6title = $rowGetGradingSystem['AD6Title'];
                                            $ad7title = $rowGetGradingSystem['AD7Title'];
                                            $ad8title = $rowGetGradingSystem['AD8Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `affective_domain_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $domain1 = $rowGetstudent_session["domain1"];
                                                $domain2 = $rowGetstudent_session["domain2"];
                                                $domain3 = $rowGetstudent_session["domain3"];
                                                $domain4 = $rowGetstudent_session["domain4"];
                                                $domain5 = $rowGetstudent_session["domain5"];
                                                $domain6 = $rowGetstudent_session["domain6"];
                                                $domain7 = $rowGetstudent_session["domain7"];
                                                $domain8 = $rowGetstudent_session["domain8"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofAD'] == '9') {
                                            $ad1title = $rowGetGradingSystem['AD1Title'];
                                            $ad2title = $rowGetGradingSystem['AD2Title'];
                                            $ad3title = $rowGetGradingSystem['AD3Title'];
                                            $ad4title = $rowGetGradingSystem['AD4Title'];
                                            $ad5title = $rowGetGradingSystem['AD5Title'];
                                            $ad6title = $rowGetGradingSystem['AD6Title'];
                                            $ad7title = $rowGetGradingSystem['AD7Title'];
                                            $ad8title = $rowGetGradingSystem['AD8Title'];
                                            $ad9title = $rowGetGradingSystem['AD9Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `affective_domain_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $domain1 = $rowGetstudent_session["domain1"];
                                                $domain2 = $rowGetstudent_session["domain2"];
                                                $domain3 = $rowGetstudent_session["domain3"];
                                                $domain4 = $rowGetstudent_session["domain4"];
                                                $domain5 = $rowGetstudent_session["domain5"];
                                                $domain6 = $rowGetstudent_session["domain6"];
                                                $domain7 = $rowGetstudent_session["domain7"];
                                                $domain8 = $rowGetstudent_session["domain8"];
                                                $domain9 = $rowGetstudent_session["domain9"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofAD'] == '10') {
                                            $ad1title = $rowGetGradingSystem['AD1Title'];
                                            $ad2title = $rowGetGradingSystem['AD2Title'];
                                            $ad3title = $rowGetGradingSystem['AD3Title'];
                                            $ad4title = $rowGetGradingSystem['AD4Title'];
                                            $ad5title = $rowGetGradingSystem['AD5Title'];
                                            $ad6title = $rowGetGradingSystem['AD6Title'];
                                            $ad7title = $rowGetGradingSystem['AD7Title'];
                                            $ad8title = $rowGetGradingSystem['AD8Title'];
                                            $ad9title = $rowGetGradingSystem['AD9Title'];
                                            $ad10title = $rowGetGradingSystem['AD10Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `affective_domain_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $domain1 = $rowGetstudent_session["domain1"];
                                                $domain2 = $rowGetstudent_session["domain2"];
                                                $domain3 = $rowGetstudent_session["domain3"];
                                                $domain4 = $rowGetstudent_session["domain4"];
                                                $domain5 = $rowGetstudent_session["domain5"];
                                                $domain6 = $rowGetstudent_session["domain6"];
                                                $domain7 = $rowGetstudent_session["domain7"];
                                                $domain8 = $rowGetstudent_session["domain8"];
                                                $domain9 = $rowGetstudent_session["domain9"];
                                                $domain10 = $rowGetstudent_session["domain10"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofAD'] == '11') {
                                            $ad1title = $rowGetGradingSystem['AD1Title'];
                                            $ad2title = $rowGetGradingSystem['AD2Title'];
                                            $ad3title = $rowGetGradingSystem['AD3Title'];
                                            $ad4title = $rowGetGradingSystem['AD4Title'];
                                            $ad5title = $rowGetGradingSystem['AD5Title'];
                                            $ad6title = $rowGetGradingSystem['AD6Title'];
                                            $ad7title = $rowGetGradingSystem['AD7Title'];
                                            $ad8title = $rowGetGradingSystem['AD8Title'];
                                            $ad9title = $rowGetGradingSystem['AD9Title'];
                                            $ad10title = $rowGetGradingSystem['AD10Title'];
                                            $ad11title = $rowGetGradingSystem['AD11Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `affective_domain_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $domain1 = $rowGetstudent_session["domain1"];
                                                $domain2 = $rowGetstudent_session["domain2"];
                                                $domain3 = $rowGetstudent_session["domain3"];
                                                $domain4 = $rowGetstudent_session["domain4"];
                                                $domain5 = $rowGetstudent_session["domain5"];
                                                $domain6 = $rowGetstudent_session["domain6"];
                                                $domain7 = $rowGetstudent_session["domain7"];
                                                $domain8 = $rowGetstudent_session["domain8"];
                                                $domain9 = $rowGetstudent_session["domain9"];
                                                $domain10 = $rowGetstudent_session["domain10"];
                                                $domain11 = $rowGetstudent_session["domain11"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofAD'] == '12') {
                                            $ad1title = $rowGetGradingSystem['AD1Title'];
                                            $ad2title = $rowGetGradingSystem['AD2Title'];
                                            $ad3title = $rowGetGradingSystem['AD3Title'];
                                            $ad4title = $rowGetGradingSystem['AD4Title'];
                                            $ad5title = $rowGetGradingSystem['AD5Title'];
                                            $ad6title = $rowGetGradingSystem['AD6Title'];
                                            $ad7title = $rowGetGradingSystem['AD7Title'];
                                            $ad8title = $rowGetGradingSystem['AD8Title'];
                                            $ad9title = $rowGetGradingSystem['AD9Title'];
                                            $ad10title = $rowGetGradingSystem['AD10Title'];
                                            $ad11title = $rowGetGradingSystem['AD11Title'];
                                            $ad12title = $rowGetGradingSystem['AD12Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `affective_domain_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $domain1 = $rowGetstudent_session["domain1"];
                                                $domain2 = $rowGetstudent_session["domain2"];
                                                $domain3 = $rowGetstudent_session["domain3"];
                                                $domain4 = $rowGetstudent_session["domain4"];
                                                $domain5 = $rowGetstudent_session["domain5"];
                                                $domain6 = $rowGetstudent_session["domain6"];
                                                $domain7 = $rowGetstudent_session["domain7"];
                                                $domain8 = $rowGetstudent_session["domain8"];
                                                $domain9 = $rowGetstudent_session["domain9"];
                                                $domain10 = $rowGetstudent_session["domain10"];
                                                $domain11 = $rowGetstudent_session["domain11"];
                                                $domain12 = $rowGetstudent_session["domain12"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofAD'] == '13') {
                                            $ad1title = $rowGetGradingSystem['AD1Title'];
                                            $ad2title = $rowGetGradingSystem['AD2Title'];
                                            $ad3title = $rowGetGradingSystem['AD3Title'];
                                            $ad4title = $rowGetGradingSystem['AD4Title'];
                                            $ad5title = $rowGetGradingSystem['AD5Title'];
                                            $ad6title = $rowGetGradingSystem['AD6Title'];
                                            $ad7title = $rowGetGradingSystem['AD7Title'];
                                            $ad8title = $rowGetGradingSystem['AD8Title'];
                                            $ad9title = $rowGetGradingSystem['AD9Title'];
                                            $ad10title = $rowGetGradingSystem['AD10Title'];
                                            $ad11title = $rowGetGradingSystem['AD11Title'];
                                            $ad12title = $rowGetGradingSystem['AD12Title'];
                                            $ad13title = $rowGetGradingSystem['AD13Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `affective_domain_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $domain1 = $rowGetstudent_session["domain1"];
                                                $domain2 = $rowGetstudent_session["domain2"];
                                                $domain3 = $rowGetstudent_session["domain3"];
                                                $domain4 = $rowGetstudent_session["domain4"];
                                                $domain5 = $rowGetstudent_session["domain5"];
                                                $domain6 = $rowGetstudent_session["domain6"];
                                                $domain7 = $rowGetstudent_session["domain7"];
                                                $domain8 = $rowGetstudent_session["domain8"];
                                                $domain9 = $rowGetstudent_session["domain9"];
                                                $domain10 = $rowGetstudent_session["domain10"];
                                                $domain11 = $rowGetstudent_session["domain11"];
                                                $domain12 = $rowGetstudent_session["domain12"];
                                                $domain13 = $rowGetstudent_session["domain13"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofAD'] == '14') {
                                            $ad1title = $rowGetGradingSystem['AD1Title'];
                                            $ad2title = $rowGetGradingSystem['AD2Title'];
                                            $ad3title = $rowGetGradingSystem['AD3Title'];
                                            $ad4title = $rowGetGradingSystem['AD4Title'];
                                            $ad5title = $rowGetGradingSystem['AD5Title'];
                                            $ad6title = $rowGetGradingSystem['AD6Title'];
                                            $ad7title = $rowGetGradingSystem['AD7Title'];
                                            $ad8title = $rowGetGradingSystem['AD8Title'];
                                            $ad9title = $rowGetGradingSystem['AD9Title'];
                                            $ad10title = $rowGetGradingSystem['AD10Title'];
                                            $ad11title = $rowGetGradingSystem['AD11Title'];
                                            $ad12title = $rowGetGradingSystem['AD12Title'];
                                            $ad13title = $rowGetGradingSystem['AD13Title'];
                                            $ad14title = $rowGetGradingSystem['AD14Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `affective_domain_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $domain1 = $rowGetstudent_session["domain1"];
                                                $domain2 = $rowGetstudent_session["domain2"];
                                                $domain3 = $rowGetstudent_session["domain3"];
                                                $domain4 = $rowGetstudent_session["domain4"];
                                                $domain5 = $rowGetstudent_session["domain5"];
                                                $domain6 = $rowGetstudent_session["domain6"];
                                                $domain7 = $rowGetstudent_session["domain7"];
                                                $domain8 = $rowGetstudent_session["domain8"];
                                                $domain9 = $rowGetstudent_session["domain9"];
                                                $domain10 = $rowGetstudent_session["domain10"];
                                                $domain11 = $rowGetstudent_session["domain11"];
                                                $domain12 = $rowGetstudent_session["domain12"];
                                                $domain13 = $rowGetstudent_session["domain13"];
                                                $domain14 = $rowGetstudent_session["domain14"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofAD'] == '15') {
                                            $ad1title = $rowGetGradingSystem['AD1Title'];
                                            $ad2title = $rowGetGradingSystem['AD2Title'];
                                            $ad3title = $rowGetGradingSystem['AD3Title'];
                                            $ad4title = $rowGetGradingSystem['AD4Title'];
                                            $ad5title = $rowGetGradingSystem['AD5Title'];
                                            $ad6title = $rowGetGradingSystem['AD6Title'];
                                            $ad7title = $rowGetGradingSystem['AD7Title'];
                                            $ad8title = $rowGetGradingSystem['AD8Title'];
                                            $ad9title = $rowGetGradingSystem['AD9Title'];
                                            $ad10title = $rowGetGradingSystem['AD10Title'];
                                            $ad11title = $rowGetGradingSystem['AD11Title'];
                                            $ad12title = $rowGetGradingSystem['AD12Title'];
                                            $ad13title = $rowGetGradingSystem['AD13Title'];
                                            $ad14title = $rowGetGradingSystem['AD14Title'];
                                            $ad15title = $rowGetGradingSystem['AD15Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `affective_domain_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $domain1 = $rowGetstudent_session["domain1"];
                                                $domain2 = $rowGetstudent_session["domain2"];
                                                $domain3 = $rowGetstudent_session["domain3"];
                                                $domain4 = $rowGetstudent_session["domain4"];
                                                $domain5 = $rowGetstudent_session["domain5"];
                                                $domain6 = $rowGetstudent_session["domain6"];
                                                $domain7 = $rowGetstudent_session["domain7"];
                                                $domain8 = $rowGetstudent_session["domain8"];
                                                $domain9 = $rowGetstudent_session["domain9"];
                                                $domain10 = $rowGetstudent_session["domain10"];
                                                $domain11 = $rowGetstudent_session["domain11"];
                                                $domain12 = $rowGetstudent_session["domain12"];
                                                $domain13 = $rowGetstudent_session["domain13"];
                                                $domain14 = $rowGetstudent_session["domain14"];
                                                $domain15 = $rowGetstudent_session["domain15"];
                                            }
                                        }
                                    }

                                    $sqlGetGradingSystem = "SELECT * FROM `assignspsycomotortoclass` INNER JOIN psycomotor_settings ON assignspsycomotortoclass.PsycomotorSettingsId=psycomotor_settings.id WHERE assignspsycomotortoclass.ClassID='$classid'";
                                    $queryGetGradingSystem = mysqli_query($link, $sqlGetGradingSystem);
                                    $rowGetGradingSystem = mysqli_fetch_assoc($queryGetGradingSystem);
                                    $countGetGradingSystem = mysqli_num_rows($queryGetGradingSystem);

                                    if ($countGetGradingSystem > 0) {
                                        if ($rowGetGradingSystem['NumberofP'] == '1') {
                                            $p1title = $rowGetGradingSystem['P1Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `psycomotor_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $psycomotor1 = $rowGetstudent_session["psycomotor1"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofP'] == '2') {
                                            $p1title = $rowGetGradingSystem['P1Title'];
                                            $p2title = $rowGetGradingSystem['P2Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `psycomotor_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $psycomotor1 = $rowGetstudent_session["psycomotor1"];
                                                $psycomotor2 = $rowGetstudent_session["psycomotor2"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofP'] == '3') {
                                            $p1title = $rowGetGradingSystem['P1Title'];
                                            $p2title = $rowGetGradingSystem['P2Title'];
                                            $p3title = $rowGetGradingSystem['P3Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `psycomotor_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $psycomotor1 = $rowGetstudent_session["psycomotor1"];
                                                $psycomotor2 = $rowGetstudent_session["psycomotor2"];
                                                $psycomotor3 = $rowGetstudent_session["psycomotor3"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofP'] == '4') {
                                            $p1title = $rowGetGradingSystem['P1Title'];
                                            $p2title = $rowGetGradingSystem['P2Title'];
                                            $p3title = $rowGetGradingSystem['P3Title'];
                                            $p4title = $rowGetGradingSystem['P4Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `psycomotor_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $psycomotor1 = $rowGetstudent_session["psycomotor1"];
                                                $psycomotor2 = $rowGetstudent_session["psycomotor2"];
                                                $psycomotor3 = $rowGetstudent_session["psycomotor3"];
                                                $psycomotor4 = $rowGetstudent_session["psycomotor4"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofP'] == '5') {
                                            $p1title = $rowGetGradingSystem['P1Title'];
                                            $p2title = $rowGetGradingSystem['P2Title'];
                                            $p3title = $rowGetGradingSystem['P3Title'];
                                            $p4title = $rowGetGradingSystem['P4Title'];
                                            $p5title = $rowGetGradingSystem['P5Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `psycomotor_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $psycomotor1 = $rowGetstudent_session["psycomotor1"];
                                                $psycomotor2 = $rowGetstudent_session["psycomotor2"];
                                                $psycomotor3 = $rowGetstudent_session["psycomotor3"];
                                                $psycomotor4 = $rowGetstudent_session["psycomotor4"];
                                                $psycomotor5 = $rowGetstudent_session["psycomotor5"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofP'] == '6') {
                                            $p1title = $rowGetGradingSystem['P1Title'];
                                            $p2title = $rowGetGradingSystem['P2Title'];
                                            $p3title = $rowGetGradingSystem['P3Title'];
                                            $p4title = $rowGetGradingSystem['P4Title'];
                                            $p5title = $rowGetGradingSystem['P5Title'];
                                            $p6title = $rowGetGradingSystem['P6Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `psycomotor_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $psycomotor1 = $rowGetstudent_session["psycomotor1"];
                                                $psycomotor2 = $rowGetstudent_session["psycomotor2"];
                                                $psycomotor3 = $rowGetstudent_session["psycomotor3"];
                                                $psycomotor4 = $rowGetstudent_session["psycomotor4"];
                                                $psycomotor5 = $rowGetstudent_session["psycomotor5"];
                                                $psycomotor6 = $rowGetstudent_session["psycomotor6"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofP'] == '7') {
                                            $p1title = $rowGetGradingSystem['P1Title'];
                                            $p2title = $rowGetGradingSystem['P2Title'];
                                            $p3title = $rowGetGradingSystem['P3Title'];
                                            $p4title = $rowGetGradingSystem['P4Title'];
                                            $p5title = $rowGetGradingSystem['P5Title'];
                                            $p6title = $rowGetGradingSystem['P6Title'];
                                            $p7title = $rowGetGradingSystem['P7Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `psycomotor_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $psycomotor1 = $rowGetstudent_session["psycomotor1"];
                                                $psycomotor2 = $rowGetstudent_session["psycomotor2"];
                                                $psycomotor3 = $rowGetstudent_session["psycomotor3"];
                                                $psycomotor4 = $rowGetstudent_session["psycomotor4"];
                                                $psycomotor5 = $rowGetstudent_session["psycomotor5"];
                                                $psycomotor6 = $rowGetstudent_session["psycomotor6"];
                                                $psycomotor7 = $rowGetstudent_session["psycomotor7"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofP'] == '8') {
                                            $p1title = $rowGetGradingSystem['P1Title'];
                                            $p2title = $rowGetGradingSystem['P2Title'];
                                            $p3title = $rowGetGradingSystem['P3Title'];
                                            $p4title = $rowGetGradingSystem['P4Title'];
                                            $p5title = $rowGetGradingSystem['P5Title'];
                                            $p6title = $rowGetGradingSystem['P6Title'];
                                            $p7title = $rowGetGradingSystem['P7Title'];
                                            $p8title = $rowGetGradingSystem['P8Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `psycomotor_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $psycomotor1 = $rowGetstudent_session["psycomotor1"];
                                                $psycomotor2 = $rowGetstudent_session["psycomotor2"];
                                                $psycomotor3 = $rowGetstudent_session["psycomotor3"];
                                                $psycomotor4 = $rowGetstudent_session["psycomotor4"];
                                                $psycomotor5 = $rowGetstudent_session["psycomotor5"];
                                                $psycomotor6 = $rowGetstudent_session["psycomotor6"];
                                                $psycomotor7 = $rowGetstudent_session["psycomotor7"];
                                                $psycomotor8 = $rowGetstudent_session["psycomotor8"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofP'] == '9') {
                                            $p1title = $rowGetGradingSystem['P1Title'];
                                            $p2title = $rowGetGradingSystem['P2Title'];
                                            $p3title = $rowGetGradingSystem['P3Title'];
                                            $p4title = $rowGetGradingSystem['P4Title'];
                                            $p5title = $rowGetGradingSystem['P5Title'];
                                            $p6title = $rowGetGradingSystem['P6Title'];
                                            $p7title = $rowGetGradingSystem['P7Title'];
                                            $p8title = $rowGetGradingSystem['P8Title'];
                                            $p9title = $rowGetGradingSystem['P9Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `psycomotor_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $psycomotor1 = $rowGetstudent_session["psycomotor1"];
                                                $psycomotor2 = $rowGetstudent_session["psycomotor2"];
                                                $psycomotor3 = $rowGetstudent_session["psycomotor3"];
                                                $psycomotor4 = $rowGetstudent_session["psycomotor4"];
                                                $psycomotor5 = $rowGetstudent_session["psycomotor5"];
                                                $psycomotor6 = $rowGetstudent_session["psycomotor6"];
                                                $psycomotor7 = $rowGetstudent_session["psycomotor7"];
                                                $psycomotor8 = $rowGetstudent_session["psycomotor8"];
                                                $psycomotor9 = $rowGetstudent_session["psycomotor9"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofP'] == '10') {
                                            $p1title = $rowGetGradingSystem['P1Title'];
                                            $p2title = $rowGetGradingSystem['P2Title'];
                                            $p3title = $rowGetGradingSystem['P3Title'];
                                            $p4title = $rowGetGradingSystem['P4Title'];
                                            $p5title = $rowGetGradingSystem['P5Title'];
                                            $p6title = $rowGetGradingSystem['P6Title'];
                                            $p7title = $rowGetGradingSystem['P7Title'];
                                            $p8title = $rowGetGradingSystem['P8Title'];
                                            $p9title = $rowGetGradingSystem['P9Title'];
                                            $p10title = $rowGetGradingSystem['P10Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `psycomotor_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $psycomotor1 = $rowGetstudent_session["psycomotor1"];
                                                $psycomotor2 = $rowGetstudent_session["psycomotor2"];
                                                $psycomotor3 = $rowGetstudent_session["psycomotor3"];
                                                $psycomotor4 = $rowGetstudent_session["psycomotor4"];
                                                $psycomotor5 = $rowGetstudent_session["psycomotor5"];
                                                $psycomotor6 = $rowGetstudent_session["psycomotor6"];
                                                $psycomotor7 = $rowGetstudent_session["psycomotor7"];
                                                $psycomotor8 = $rowGetstudent_session["psycomotor8"];
                                                $psycomotor9 = $rowGetstudent_session["psycomotor9"];
                                                $psycomotor10 = $rowGetstudent_session["psycomotor10"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofP'] == '11') {
                                            $p1title = $rowGetGradingSystem['P1Title'];
                                            $p2title = $rowGetGradingSystem['P2Title'];
                                            $p3title = $rowGetGradingSystem['P3Title'];
                                            $p4title = $rowGetGradingSystem['P4Title'];
                                            $p5title = $rowGetGradingSystem['P5Title'];
                                            $p6title = $rowGetGradingSystem['P6Title'];
                                            $p7title = $rowGetGradingSystem['P7Title'];
                                            $p8title = $rowGetGradingSystem['P8Title'];
                                            $p9title = $rowGetGradingSystem['P9Title'];
                                            $p10title = $rowGetGradingSystem['P10Title'];
                                            $p11title = $rowGetGradingSystem['P11Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `psycomotor_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $psycomotor1 = $rowGetstudent_session["psycomotor1"];
                                                $psycomotor2 = $rowGetstudent_session["psycomotor2"];
                                                $psycomotor3 = $rowGetstudent_session["psycomotor3"];
                                                $psycomotor4 = $rowGetstudent_session["psycomotor4"];
                                                $psycomotor5 = $rowGetstudent_session["psycomotor5"];
                                                $psycomotor6 = $rowGetstudent_session["psycomotor6"];
                                                $psycomotor7 = $rowGetstudent_session["psycomotor7"];
                                                $psycomotor8 = $rowGetstudent_session["psycomotor8"];
                                                $psycomotor9 = $rowGetstudent_session["psycomotor9"];
                                                $psycomotor10 = $rowGetstudent_session["psycomotor10"];
                                                $psycomotor11 = $rowGetstudent_session["psycomotor11"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofP'] == '12') {
                                            $p1title = $rowGetGradingSystem['P1Title'];
                                            $p2title = $rowGetGradingSystem['P2Title'];
                                            $p3title = $rowGetGradingSystem['P3Title'];
                                            $p4title = $rowGetGradingSystem['P4Title'];
                                            $p5title = $rowGetGradingSystem['P5Title'];
                                            $p6title = $rowGetGradingSystem['P6Title'];
                                            $p7title = $rowGetGradingSystem['P7Title'];
                                            $p8title = $rowGetGradingSystem['P8Title'];
                                            $p9title = $rowGetGradingSystem['P9Title'];
                                            $p10title = $rowGetGradingSystem['P10Title'];
                                            $p11title = $rowGetGradingSystem['P11Title'];
                                            $p12title = $rowGetGradingSystem['P12Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `psycomotor_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $psycomotor1 = $rowGetstudent_session["psycomotor1"];
                                                $psycomotor2 = $rowGetstudent_session["psycomotor2"];
                                                $psycomotor3 = $rowGetstudent_session["psycomotor3"];
                                                $psycomotor4 = $rowGetstudent_session["psycomotor4"];
                                                $psycomotor5 = $rowGetstudent_session["psycomotor5"];
                                                $psycomotor6 = $rowGetstudent_session["psycomotor6"];
                                                $psycomotor7 = $rowGetstudent_session["psycomotor7"];
                                                $psycomotor8 = $rowGetstudent_session["psycomotor8"];
                                                $psycomotor9 = $rowGetstudent_session["psycomotor9"];
                                                $psycomotor10 = $rowGetstudent_session["psycomotor10"];
                                                $psycomotor11 = $rowGetstudent_session["psycomotor11"];
                                                $psycomotor12 = $rowGetstudent_session["psycomotor12"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofP'] == '13') {
                                            $p1title = $rowGetGradingSystem['P1Title'];
                                            $p2title = $rowGetGradingSystem['P2Title'];
                                            $p3title = $rowGetGradingSystem['P3Title'];
                                            $p4title = $rowGetGradingSystem['P4Title'];
                                            $p5title = $rowGetGradingSystem['P5Title'];
                                            $p6title = $rowGetGradingSystem['P6Title'];
                                            $p7title = $rowGetGradingSystem['P7Title'];
                                            $p8title = $rowGetGradingSystem['P8Title'];
                                            $p9title = $rowGetGradingSystem['P9Title'];
                                            $p10title = $rowGetGradingSystem['P10Title'];
                                            $p11title = $rowGetGradingSystem['P11Title'];
                                            $p12title = $rowGetGradingSystem['P12Title'];
                                            $p13title = $rowGetGradingSystem['P13Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `psycomotor_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $psycomotor1 = $rowGetstudent_session["psycomotor1"];
                                                $psycomotor2 = $rowGetstudent_session["psycomotor2"];
                                                $psycomotor3 = $rowGetstudent_session["psycomotor3"];
                                                $psycomotor4 = $rowGetstudent_session["psycomotor4"];
                                                $psycomotor5 = $rowGetstudent_session["psycomotor5"];
                                                $psycomotor6 = $rowGetstudent_session["psycomotor6"];
                                                $psycomotor7 = $rowGetstudent_session["psycomotor7"];
                                                $psycomotor8 = $rowGetstudent_session["psycomotor8"];
                                                $psycomotor9 = $rowGetstudent_session["psycomotor9"];
                                                $psycomotor10 = $rowGetstudent_session["psycomotor10"];
                                                $psycomotor11 = $rowGetstudent_session["psycomotor11"];
                                                $psycomotor12 = $rowGetstudent_session["psycomotor12"];
                                                $psycomotor13 = $rowGetstudent_session["psycomotor13"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofP'] == '14') {
                                            $p1title = $rowGetGradingSystem['P1Title'];
                                            $p2title = $rowGetGradingSystem['P2Title'];
                                            $p3title = $rowGetGradingSystem['P3Title'];
                                            $p4title = $rowGetGradingSystem['P4Title'];
                                            $p5title = $rowGetGradingSystem['P5Title'];
                                            $p6title = $rowGetGradingSystem['P6Title'];
                                            $p7title = $rowGetGradingSystem['P7Title'];
                                            $p8title = $rowGetGradingSystem['P8Title'];
                                            $p9title = $rowGetGradingSystem['P9Title'];
                                            $p10title = $rowGetGradingSystem['P10Title'];
                                            $p11title = $rowGetGradingSystem['P11Title'];
                                            $p12title = $rowGetGradingSystem['P12Title'];
                                            $p13title = $rowGetGradingSystem['P13Title'];
                                            $p14title = $rowGetGradingSystem['P14Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `psycomotor_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $psycomotor1 = $rowGetstudent_session["psycomotor1"];
                                                $psycomotor2 = $rowGetstudent_session["psycomotor2"];
                                                $psycomotor3 = $rowGetstudent_session["psycomotor3"];
                                                $psycomotor4 = $rowGetstudent_session["psycomotor4"];
                                                $psycomotor5 = $rowGetstudent_session["psycomotor5"];
                                                $psycomotor6 = $rowGetstudent_session["psycomotor6"];
                                                $psycomotor7 = $rowGetstudent_session["psycomotor7"];
                                                $psycomotor8 = $rowGetstudent_session["psycomotor8"];
                                                $psycomotor9 = $rowGetstudent_session["psycomotor9"];
                                                $psycomotor10 = $rowGetstudent_session["psycomotor10"];
                                                $psycomotor11 = $rowGetstudent_session["psycomotor11"];
                                                $psycomotor12 = $rowGetstudent_session["psycomotor12"];
                                                $psycomotor13 = $rowGetstudent_session["psycomotor13"];
                                                $psycomotor14 = $rowGetstudent_session["psycomotor14"];
                                            }
                                        } elseif ($rowGetGradingSystem['NumberofP'] == '15') {
                                            $p1title = $rowGetGradingSystem['P1Title'];
                                            $p2title = $rowGetGradingSystem['P2Title'];
                                            $p3title = $rowGetGradingSystem['P3Title'];
                                            $p4title = $rowGetGradingSystem['P4Title'];
                                            $p5title = $rowGetGradingSystem['P5Title'];
                                            $p6title = $rowGetGradingSystem['P6Title'];
                                            $p7title = $rowGetGradingSystem['P7Title'];
                                            $p8title = $rowGetGradingSystem['P8Title'];
                                            $p9title = $rowGetGradingSystem['P9Title'];
                                            $p10title = $rowGetGradingSystem['P10Title'];
                                            $p11title = $rowGetGradingSystem['P11Title'];
                                            $p12title = $rowGetGradingSystem['P12Title'];
                                            $p13title = $rowGetGradingSystem['P13Title'];
                                            $p14title = $rowGetGradingSystem['P14Title'];
                                            $p15title = $rowGetGradingSystem['P15Title'];

                                            $sqlGetstudent_session = ("SELECT * FROM `psycomotor_score` WHERE studentid = '$id' AND classid = '$classid' AND session = '$session' AND sectionid = '$classsectionactual'");
                                            $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                                            $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                                            $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);

                                            if ($row_cntgetscorecheck > 0) {
                                                $psycomotor1 = $rowGetstudent_session["psycomotor1"];
                                                $psycomotor2 = $rowGetstudent_session["psycomotor2"];
                                                $psycomotor3 = $rowGetstudent_session["psycomotor3"];
                                                $psycomotor4 = $rowGetstudent_session["psycomotor4"];
                                                $psycomotor5 = $rowGetstudent_session["psycomotor5"];
                                                $psycomotor6 = $rowGetstudent_session["psycomotor6"];
                                                $psycomotor7 = $rowGetstudent_session["psycomotor7"];
                                                $psycomotor8 = $rowGetstudent_session["psycomotor8"];
                                                $psycomotor9 = $rowGetstudent_session["psycomotor9"];
                                                $psycomotor10 = $rowGetstudent_session["psycomotor10"];
                                                $psycomotor11 = $rowGetstudent_session["psycomotor11"];
                                                $psycomotor12 = $rowGetstudent_session["psycomotor12"];
                                                $psycomotor13 = $rowGetstudent_session["psycomotor13"];
                                                $psycomotor14 = $rowGetstudent_session["psycomotor14"];
                                                $psycomotor15 = $rowGetstudent_session["psycomotor15"];
                                            }
                                        }
                                    }
                                    ?>
                                    <div class="col-8">

                                        <div class="container-motto">
                                            <div style="margin: 20px;">
                                                <div class="row">
                                                    <table>
                                                        <tr>
                                                            <th colspan="4">AFFECTIVE DOMAIN</th>
                                                            <th colspan="4">PSYCOMOTOR</th>
                                                        </tr>
                                                        <?php if ($ad1title) { ?>
                                                            <tr>
                                                                <td><?php echo $ad1title; ?></td>
                                                                <td><?php echo $domain1; ?></td>
                                                                <td><?php echo $ad2title; ?></td>
                                                                <td><?php echo $domain2; ?></td>
                                                                <td><?php echo $p1title; ?></td>
                                                                <td><?php echo $psycomotor1; ?></td>
                                                                <td><?php echo $p2title; ?></td>
                                                                <td><?php echo $psycomotor2; ?></td>
                                                            </tr>
                                                        <?php }
                                                        if ($ad3title) { ?>
                                                            <tr>
                                                                <td><?php echo $ad3title; ?></td>
                                                                <td><?php echo $domain3; ?></td>
                                                                <td><?php echo $ad4title; ?></td>
                                                                <td><?php echo $domain4; ?></td>
                                                                <td><?php echo $p3title; ?></td>
                                                                <td><?php echo $psycomotor3; ?></td>
                                                                <td><?php echo $p4title; ?></td>
                                                                <td><?php echo $psycomotor4; ?></td>
                                                            </tr>
                                                        <?php }
                                                        if ($ad5title) { ?>
                                                            <tr>
                                                                <td><?php echo $ad5title; ?></td>
                                                                <td><?php echo $domain5; ?></td>
                                                                <td><?php echo $ad6title; ?></td>
                                                                <td><?php echo $domain6; ?></td>
                                                                <td><?php echo $p5title; ?></td>
                                                                <td><?php echo $psycomotor5; ?></td>
                                                                <td><?php echo $p6title; ?></td>
                                                                <td><?php echo $psycomotor6; ?></td>
                                                            </tr>
                                                        <?php }
                                                        if ($ad7title) { ?>
                                                            <tr>
                                                                <td><?php echo $ad7title; ?></td>
                                                                <td><?php echo $domain7; ?></td>
                                                                <td><?php echo $ad8title; ?></td>
                                                                <td><?php echo $domain8; ?></td>
                                                                <td><?php echo $p7title; ?></td>
                                                                <td><?php echo $psycomotor7; ?></td>
                                                                <td><?php echo $p8title; ?></td>
                                                                <td><?php echo $psycomotor8; ?></td>
                                                            </tr>
                                                        <?php }
                                                        if ($ad9title) { ?>
                                                            <tr>
                                                                <td><?php echo $ad9title; ?></td>
                                                                <td><?php echo $domain9; ?></td>
                                                                <td><?php echo $ad10title; ?></td>
                                                                <td><?php echo $domain10; ?></td>
                                                                <td><?php echo $p9title; ?></td>
                                                                <td><?php echo $psycomotor9; ?></td>
                                                                <td><?php echo $p10title; ?></td>
                                                                <td><?php echo $psycomotor10; ?></td>
                                                            </tr>
                                                        <?php }
                                                        if ($ad11title) { ?>
                                                            <tr>
                                                                <td><?php echo $ad11title; ?></td>
                                                                <td><?php echo $domain11; ?></td>
                                                                <td><?php echo $ad12title; ?></td>
                                                                <td><?php echo $domain12; ?></td>
                                                                <td><?php echo $p11title; ?></td>
                                                                <td><?php echo $psycomotor11; ?></td>
                                                                <td><?php echo $p12title; ?></td>
                                                                <td><?php echo $psycomotor12; ?></td>
                                                            </tr>
                                                        <?php }
                                                        if ($ad13title) { ?>
                                                            <tr>
                                                                <td><?php echo $ad13title; ?></td>
                                                                <td><?php echo $domain13; ?></td>
                                                                <td><?php echo $ad14title; ?></td>
                                                                <td><?php echo $domain14; ?></td>
                                                                <td><?php echo $p13title; ?></td>
                                                                <td><?php echo $psycomotor13; ?></td>
                                                                <td><?php echo $p14title; ?></td>
                                                                <td><?php echo $psycomotor14; ?></td>
                                                            </tr>
                                                        <?php }
                                                        if ($ad15title) { ?>
                                                            <tr>
                                                                <td><?php echo $ad15title; ?></td>
                                                                <td><?php echo $domain15; ?></td>
                                                                <tb></tb>
                                                                <tb></tb>
                                                                <td><?php echo $p15title; ?></td>
                                                                <td><?php echo $psycomotor15; ?></td>
                                                                <tb></tb>
                                                                <tb></tb>
                                                            </tr>
                                                        <?php } ?>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>

                                    </div>


                                    <?php

                                    $sqlgettechremark = mysqli_query($link, "SELECT * FROM `remark` WHERE `RemarkType` = 'teacher' AND `StudentID`='$id' AND `Session`='$session' AND `Term`='$term'");
                                    $rowcountfixedremark = mysqli_num_rows($sqlgettechremark);
                                    $fetchfixedremark = mysqli_fetch_assoc($sqlgettechremark);


                                    if ($rowcountfixedremark > 0) {
                                        $teacherRemark = $fetchfixedremark['RemarkComment'];

                                        $teacherid = $fetchfixedremark['StaffID'];

                                        $sqlgetheadteachsign = ("SELECT * FROM `staffsignature` WHERE staff_id = '$hedteachid'");
                                        $resultgetheadteachsign = mysqli_query($link, $sqlgetheadteachsign);
                                        $rowgetheadteachsign = mysqli_fetch_assoc($resultgetheadteachsign);
                                        $row_cntgetheadteachsign = mysqli_num_rows($resultgetheadteachsign);


                                        if ($row_cntgetheadteachsign > 0) {
                                            $hedteachsign = '<img src="../img/signature/' . $rowgetheadteachsign['Signature'] . '" align="center" class="img-fluid" style="width: 70%;">';
                                        } else {
                                            $hedteachsign = '';
                                        }
                                    } else {

                                        $sqlgetteachremark = ("SELECT * FROM `defaultcomment` INNER JOIN class_teacher ON defaultcomment.PrincipalOrDeanOrHeadTeacherUserID=class_teacher.staff_id WHERE $gettotgrade BETWEEN RangeStart AND RangeEnd AND CommentType = 'teacher' AND class_id = '$classid'");
                                        $resultgetteachremark = mysqli_query($link, $sqlgetteachremark);
                                        $rowgetteachremark = mysqli_fetch_assoc($resultgetteachremark);
                                        $row_cntgetteachremark = mysqli_num_rows($resultgetteachremark);

                                        if ($row_cntgetteachremark > 0) {
                                            $teacherRemark = $rowgetteachremark['DefaultComment'];

                                            $teacherid = $rowgetteachremark['PrincipalOrDeanOrHeadTeacherUserID'];


                                            $sqlgetheadteachsign = ("SELECT * FROM `staffsignature` WHERE staff_id = '$teacherid'");
                                            $resultgetheadteachsign = mysqli_query($link, $sqlgetheadteachsign);
                                            $rowgetheadteachsign = mysqli_fetch_assoc($resultgetheadteachsign);
                                            $row_cntgetheadteachsign = mysqli_num_rows($resultgetheadteachsign);


                                            if ($row_cntgetheadteachsign > 0) {
                                                $hedteachsign = '<img src="../img/signature/' . $rowgetheadteachsign['Signature'] . '" align="center" class="img-fluid" style="width: 70%;">';
                                            } else {
                                                $hedteachsign = '';
                                            }
                                        } else {
                                            $teacherRemark = 'N/A';

                                            $hedteachsign = '';
                                        }
                                    }

                                    $sqlgetprincremark = mysqli_query($link, "SELECT * FROM `remark` WHERE `RemarkType` = 'SchoolHead' AND `StudentID`='$id' AND `Session`='$session' AND `Term`='$term'");
                                    $rowcountprinfixedremark = mysqli_num_rows($sqlgetprincremark);
                                    $fetchfixedprinremark = mysqli_fetch_assoc($sqlgetprincremark);

                                    if ($rowcountprinfixedremark > 0) {
                                        $principalRemark = $fetchfixedprinremark['Remark'];

                                        $headteacherid = $fetchfixedprinremark['staff_id'];

                                        $sqlgetheadteachhead = ("SELECT * FROM `staffsignature` WHERE staff_id = 5");
                                        $resultgetheadteachsignhead = mysqli_query($link, $sqlgetheadteachhead);
                                        $rowgetheadteachsignhead = mysqli_fetch_assoc($resultgetheadteachsignhead);
                                        $row_cntgetheadteachsignhead = mysqli_num_rows($resultgetheadteachsignhead);

                                        if ($row_cntgetheadteachsignhead > 0) {
                                            $hedteachsignhead = '<img src="' . $defRUl . 'img/signature/' . $rowgetheadteachsignhead['Signature'] . '" align="center" class="img-fluid" style="width: 70%;">';
                                        } else {
                                            $hedteachsignhead = '';
                                        }
                                    } else {

                                        $sqlgetprincremark = ("SELECT * FROM `defaultcomment` WHERE $gettotgrade BETWEEN RangeStart AND RangeEnd AND CommentType = 'SchoolHead'");
                                        $resultgetprincremark = mysqli_query($link, $sqlgetprincremark);
                                        $rowgetteachremark = mysqli_fetch_assoc($resultgetprincremark);
                                        $row_cntgetprincremark = mysqli_num_rows($resultgetprincremark);

                                        if ($row_cntgetprincremark > 0) {
                                            $principalRemark = $rowgetteachremark['DefaultComment'];

                                            $headteacherid = $rowgetteachremark['PrincipalOrDeanOrHeadTeacherUserID'];

                                            $sqlgetheadteachhead = ("SELECT * FROM `staffsignature` WHERE staff_id = '$headteacherid'");
                                            $resultgetheadteachsignhead = mysqli_query($link, $sqlgetheadteachhead);
                                            $rowgetheadteachsignhead = mysqli_fetch_assoc($resultgetheadteachsignhead);
                                            $row_cntgetheadteachsignhead = mysqli_num_rows($resultgetheadteachsignhead);

                                            if ($row_cntgetheadteachsignhead > 0) {
                                                $hedteachsignhead = '<img src=" https://schoollift.s3.us-east-2.amazonaws.com/' . $rowgetheadteachsignhead['Signature'] . '" align="center" class="img-fluid" style="width: 80%;">';
                                            } else {
                                                $hedteachsignhead = '';
                                            }
                                        } else {
                                            $principalRemark = 'N/A';

                                            $hedteachsignhead = '';
                                        }
                                    }

                                    if ($term == '1st') {
                                        $termnew = '2nd';

                                        $sessionnew = $session;
                                    } elseif ($term == '2nd') {
                                        $termnew = '3rd';
                                        $sessionnew = $session;
                                    } else {
                                        $termnew = '1st';
                                        $sessionnew = $session + 1;
                                    }

                                    $sqlresumdate = ("SELECT * FROM `resumptiondate` WHERE `Session`='$sessionnew' AND `Term`='$termnew'");

                                    $resultresumdate = mysqli_query($link, $sqlresumdate);
                                    $getresumdate = mysqli_fetch_assoc($resultresumdate);
                                    $sqlrow_cntresumdate = mysqli_num_rows($resultresumdate);

                                    if ($sqlrow_cntresumdate > 0) {
                                        $resumdate = date("l jS \of F Y", strtotime($getresumdate['Date']));
                                    } else {
                                        $resumdate = 'N/A';
                                    }
                                    ?>
                                    <div class="col-12">

                                        <div class="container-motto">
                                            <div style="margin: 20px;">
                                                <div class="row">
                                                    <div class="col-sm-10 col-md-10">
                                                        <div align="center">
                                                            <p style="text-align: justify;"><b style="font-weight:600;">CLASS TEACHER'S COMMENT:</b>&nbsp;<?php echo $teacherRemark; ?></p>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-2 col-md-2">
                                                        <div align="center">
                                                            <?php echo $hedteachsign; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-sm-10 col-md-10">
                                                        <div>
                                                            <p style="text-align: justify;"><b style="font-weight:600;">PRINCIPAL/HEAD TEACHER'S COMMENT:</b>&nbsp;&nbsp;&nbsp;<?php echo $principalRemark; ?></p>
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-2 col-md-2">
                                                        <div>
                                                            <?php echo $hedteachsignhead; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-sm-12 col-md-12">
                                                        <div>
                                                            <p style="text-align: justify;"><b style="font-weight:600;">NEXT TERM BEGINS:</b>&nbsp;<?php echo $resumdate; ?></b></p>
                                                            <?php
                                                            if ($next_fee > 0) {
                                                                echo '<p style="text-align: center;"><b style="font-weight:600;">NEXT TERM FEE: N' . $next_fee . '</b></p>';
                                                            }
                                                            ?>

                                                        </div>
                                                    </div>

                                                </div>

                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            <?php

                            if ($term == '3rd') {

                                $sessionnew = $session + 1;

                                $sqlstudent_session = ("SELECT * FROM `student_session` WHERE `session_id`='$sessionnew' AND student_id = '$id'");

                                $resultstudent_session = mysqli_query($link, $sqlstudent_session);
                                $getstudent_session = mysqli_fetch_assoc($resultstudent_session);
                                $sqlrow_cntstudent_session = mysqli_num_rows($resultstudent_session);
                                /*
                                                if($sqlrow_cntstudent_session > 0)
                                                {
                                                    echo '<center>
                                                        <p class="promoted">PROMOTED TO THE NEXT CLASS</p>
                                                    </center>';
                                                }
                                                else
                                                {
                                                    echo '<center>
                                                        <p class="promoted">NOT PROMOTED TO THE NEXT CLASS</p>
                                                    </center>';
                                                }
                                                */
                            } else {
                            }

                            ?>

                    <?php
                        } else {
                            echo 'No result type has been set for this class';
                        }
                    }

                    ?>
                </div>
            </div>

        </div>



    </div>





    <!-- ============================================================ -->
    <!-- All Jquery -->
    <!-- ============================================================== -->
    <!-- My own external JS file -->
    <script src="../assets/js/myScript.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.5.0/Chart.min.js"></script>

    <script>
        var ctx = document.getElementById("mysunChart");
        var chart = new Chart(ctx, {
            responsive: "true",
            maintainAspectRatio: "false",
            type: "bar",
            data: {
                labels: ["Class Avg", "Avg Score", "Highest", "Lowest"],
                datasets: [{
                        type: "bar",
                        backgroundColor: [
                            'rgba(0, 255, 0)',
                            'rgba(54, 162, 235)',
                            'rgba(255, 0, 0)',
                            'rgba(128, 128, 128)',
                        ],
                        borderColor: [
                            'rgba(0, 255, 0, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 0, 0, 1)',
                            'rgba(128, 128, 128, 1)',
                        ],
                        borderWidth: 1,
                        label: "ACADEMIC PERFORMANCE",
                        order: 1,
                        data: [<?php echo $decStubsubavg; ?>, <?php echo $gettotgrade; ?>, <?php echo $sunhihscrun; ?>, <?php echo $sunlowscrun; ?>]

                    },


                ]
            },
            options: {
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                },
                animation: {
                    duration: 1,
                    onComplete: function() {
                        var chartInstance = this.chart,
                            ctx = chartInstance.ctx;
                        ctx.font = Chart.helpers.fontString(12, 16, Chart.defaults.global.defaultFontFamily);
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';

                        this.data.datasets.forEach(function(dataset, i) {
                            var meta = chartInstance.controller.getDatasetMeta(i);
                            meta.data.forEach(function(bar, index) {
                                var data = dataset.data[index];
                                ctx.fillText(data, bar._model.x, bar._model.y - 2);
                            });
                        });
                    }
                },
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: "ACADEMIC PERFORMANCE"

                }
            }
        });
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