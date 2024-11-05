<?php
    include ('../database/config.php');

    $id = $_POST['id'];

    $sqlexamsubjects = "SELECT * FROM `staffsignature` WHERE staff_id= '$id'";
    $resultexamsubjects = mysqli_query($link, $sqlexamsubjects);
    $rowexamsubjects = mysqli_fetch_assoc($resultexamsubjects);
    $row_cntexamsubjects = mysqli_num_rows($resultexamsubjects);

    if($row_cntexamsubjects > 0)
    {
            if (isset($_POST['uri']) && !empty($_POST['uri']))
            {
                $uri = $_POST['uri'];
                if ($uri == "/admin/settingTeacherDefaultComments.php")
                {
                  echo'<div align="center" style="padding:5%;">
                          <img src="../img/signature/'.$rowexamsubjects['Signature'].'" width="20%" height="auto"/>
                      </div>';
                }
            }
            else {
                echo'<div align="center" style="padding:5%;">
                        <img src="'. 'https://schoollift.s3.us-east-2.amazonaws.com/'. $rowexamsubjects['Signature'].'" width="20%" height="auto"/>
                    </div>';
            }
    }
    else
    {
        echo 'No Signature has been uploaded';
    }

?>
