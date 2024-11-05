<?php
    include ('../database/config.php');
    
    $classsectionactual = $_POST['classsectionactual'];
            
    $classid = $_POST['classid'];
    
    $session = $_POST['session'];
    
    $term = $_POST['term'];
    
    $reltype = $_POST['reltype'];
    
    $rolefirst = $_POST['rolefirst'];
    
    $staffid = $_POST['staffid'];
    
    $reldate = date('Y-m-d');
    
    $sqlGetassigncatoclass = "SELECT * FROM `assigncatoclass` WHERE `ClassID`='$classid'";
    $queryGetassigncatoclass = mysqli_query($link, $sqlGetassigncatoclass);
    $rowGetassigncatoclass = mysqli_fetch_assoc($queryGetassigncatoclass);
    $countGetassigncatoclass = mysqli_num_rows($queryGetassigncatoclass);
    
    // $rowGetassigncatoclass['ResultType'];'numeric';
    
    $reltypenew = $rowGetassigncatoclass['ResultType'];
    
    if($rolefirst == 'parent'){
        $student_id_sql = "SELECT student_id FROM student_session WHERE id = '$staffid'";
        $student_id_sql_2 = mysqli_query($link, $student_id_sql);
        $staffid = mysqli_fetch_assoc($student_id_sql_2)['student_id'];
    }
    
    if($rolefirst == 'student' || $rolefirst == 'parent'){
        
        if($reltype == 'cummulative')
        {
            $sqlGetstudent_session = "SELECT * FROM `publishresult` WHERE `Session`='$session' AND ResultType= '$reltype' AND Date <= '$reldate'";
        }
        else
        {
            $sqlGetstudent_session = "SELECT * FROM `publishresult` WHERE `Session`='$session' AND ResultType= '$reltype' AND Term = '$term' AND Date <= '$reldate'";
        }
        
        $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
        $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
        $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);
        
        if($countGetstudent_session > 0)
        {
            $sqlclasseschecker = "SELECT * FROM class_sections WHERE section_id = '$classsectionactual' AND class_id = '$classid'";
            $resultclasseschecker = mysqli_query($link, $sqlclasseschecker);
            $rowclasseschecker = mysqli_fetch_assoc($resultclasseschecker);
            $row_cntclasseschecker = mysqli_num_rows($resultclasseschecker);
            
            $classsection = $rowclasseschecker['id'];
        
            $sqlGetclasses = "SELECT * FROM `classes` WHERE `id`='$classid'";
            $queryGetclasses = mysqli_query($link, $sqlGetclasses);
            $rowGetclasses = mysqli_fetch_assoc($queryGetclasses);
            $countGetclasses = mysqli_num_rows($queryGetclasses);
            
            $sqlGetsessions = "SELECT * FROM `sessions` WHERE `id`='$session'";
            $queryGetsessions = mysqli_query($link, $sqlGetsessions);
            $rowGetsessions = mysqli_fetch_assoc($queryGetsessions);
            $countGetsessions = mysqli_num_rows($queryGetsessions);
            
            $sqlGetclass_sections = "SELECT * FROM `class_sections` WHERE `id`='$classsection'";
            $queryGetclass_sections = mysqli_query($link, $sqlGetclass_sections);
            $rowGetclass_sections = mysqli_fetch_assoc($queryGetclass_sections);
            $countGetclass_sections = mysqli_num_rows($queryGetclass_sections);
            
            if($countGetclass_sections > 0){
                
                $sectionnew = $rowGetclass_sections['section_id'];
                
                $class_id = $rowGetclass_sections['class_id'];
                
                  echo '<table class="table table-striped table-bordered" id="editable-datatable" style="margin-top: 30px;">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Full Name</th>
                            <th>Admission No.</th>
                            <th>Class</th>
                            <th>Session</th>
                            <th>Term</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>';
                        $cnt=1;
                        
                        if($reltypenew == 'british')
                        {
                            $sqlGetstudent_session = "SELECT DISTINCT StudentID,lastname,middlename,firstname,admission_no FROM `britishresult` INNER JOIN students ON britishresult.StudentID=students.id AND `Session`='$session' AND ClassID = '$classid' AND SectionID = '$sectionnew' AND Term = '$term' AND britishresult.StudentID='$staffid' AND students.is_active = 'yes'";
                            
                        }
                        else
                        {
                            if($reltype == 'cummulative')
                            {
                                $sqlGetstudent_session = "SELECT DISTINCT StudentID,lastname,middlename,firstname,admission_no FROM `score` INNER JOIN students ON score.StudentID=students.id AND `Session`='$session' AND ClassID = '$classid' AND SectionID = '$sectionnew' AND score.StudentID='$staffid' AND students.is_active = 'yes'";
                            }
                            else
                            {
                                $sqlGetstudent_session = "SELECT DISTINCT StudentID,lastname,middlename,firstname,admission_no FROM `score` INNER JOIN students ON score.StudentID=students.id AND `Session`='$session' AND ClassID = '$classid' AND SectionID = '$sectionnew' AND Term = '$term' AND score.StudentID='$staffid' AND students.is_active = 'yes'";
                            }
                        
                        }
                        
                        $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                        $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                        $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);
                        
                        if($countGetstudent_session > 0)
                        {
                            do{
                                echo '<tr>
                        			<td>'.$cnt++.'</td>
                        			<td>'.$rowGetstudent_session['lastname'].' '.$rowGetstudent_session['middlename'].' '.$rowGetstudent_session['firstname'].'</td>
                        			<td>'.$rowGetstudent_session['admission_no'].'</td>
                        			<td>'.$rowGetclasses['class'].'</td>
                        			<td>'.$rowGetsessions['session'].'</td>';
                        			
                        			if($reltype == 'cummulative')
                                    {
                                        echo '<td>Cummulative</td>';
                        			
                                    }
                                    else
                                    {
                                        echo '<td>'.$term.' Term</td>';
                                    }
                                    
                        			echo '<td>
                                        <a href="resultPage.php?classsection='.$classsection.'&classsectionactual='.$classsectionactual.'&classid='.$classid.'&session='.$session.'&term='.$term.'&id='.$rowGetstudent_session['StudentID'].'&reltype='.$reltype.'" style="font-size: 15px;text-decoration:underline;">
                                            View Result
                                        </a>
                                    </td>';
                        			
                        		echo '</tr>';
                            }while($rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session));
                            
                        }
                        else
                        {
                            echo '<tr><td>No Records Found'.$staffid.'</td></tr>';
                        }
                
                	echo '</tbody>
                </table>';
                
            }
            else
            {
                echo 'Class Section Not Found';
            }
        }
        else
        {
            echo '<div class="alert alert-primary" role="alert">
                Result has not been published
            </div>';
        }
    }
    else
    {
        $sqlclasseschecker = "SELECT * FROM class_sections WHERE section_id = '$classsectionactual' AND class_id = '$classid'";
        $resultclasseschecker = mysqli_query($link, $sqlclasseschecker);
        $rowclasseschecker = mysqli_fetch_assoc($resultclasseschecker);
        $row_cntclasseschecker = mysqli_num_rows($resultclasseschecker);
        
        $classsection = $rowclasseschecker['id'];
    
        $sqlGetclasses = "SELECT * FROM `classes` WHERE `id`='$classid'";
        $queryGetclasses = mysqli_query($link, $sqlGetclasses);
        $rowGetclasses = mysqli_fetch_assoc($queryGetclasses);
        $countGetclasses = mysqli_num_rows($queryGetclasses);
        
        $sqlGetsessions = "SELECT * FROM `sessions` WHERE `id`='$session'";
        $queryGetsessions = mysqli_query($link, $sqlGetsessions);
        $rowGetsessions = mysqli_fetch_assoc($queryGetsessions);
        $countGetsessions = mysqli_num_rows($queryGetsessions);
        
        $sqlGetclass_sections = "SELECT * FROM `class_sections` WHERE `id`='$classsection'";
        $queryGetclass_sections = mysqli_query($link, $sqlGetclass_sections);
        $rowGetclass_sections = mysqli_fetch_assoc($queryGetclass_sections);
        $countGetclass_sections = mysqli_num_rows($queryGetclass_sections);
        
        if($countGetclass_sections > 0){
            
            $sectionnew = $rowGetclass_sections['section_id'];
            
            $class_id = $rowGetclass_sections['class_id'];
            
              echo '<table class="table table-striped table-bordered" id="editable-datatable" style="margin-top: 30px;">
                <thead>
                    <tr>
                        <th>S/N</th>
                        <th>Full Name</th>
                        <th>Admission No.</th>
                        <th>Class</th>
                        <th>Session</th>
                        <th>Term</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>';
                    $cnt=1;
                    
                    if($reltypenew == 'british')
                    {
                        $sqlGetstudent_session = "SELECT DISTINCT StudentID,lastname,middlename,firstname,admission_no FROM `britishresult` INNER JOIN students ON britishresult.StudentID=students.id AND `Session`='$session' AND ClassID = '$classid' AND SectionID = '$sectionnew' AND Term = '$term' AND students.is_active = 'yes'";
                        
                    }
                    else
                    {
                        if($reltype == 'cummulative')
                        {
                            $sqlGetstudent_session = "SELECT DISTINCT StudentID,lastname,middlename,firstname,admission_no FROM `score` INNER JOIN students ON score.StudentID=students.id AND `Session`='$session' AND ClassID = '$classid' AND SectionID = '$sectionnew' AND students.is_active = 'yes'";
                        }
                        else
                        {
                            $sqlGetstudent_session = "SELECT DISTINCT StudentID,lastname,middlename,firstname,admission_no FROM `score` INNER JOIN students ON score.StudentID=students.id AND `Session`='$session' AND ClassID = '$classid' AND SectionID = '$sectionnew' AND Term = '$term' AND students.is_active = 'yes'";
                        }
                    
                    }
                    $queryGetstudent_session = mysqli_query($link, $sqlGetstudent_session);
                    $rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session);
                    $countGetstudent_session = mysqli_num_rows($queryGetstudent_session);
                    
                    if($countGetstudent_session > 0)
                    {
                        do{
                            echo '<tr>
                    			<td>'.$cnt++.'</td>
                    			<td>'.$rowGetstudent_session['lastname'].' '.$rowGetstudent_session['middlename'].' '.$rowGetstudent_session['firstname'].'</td>
                    			<td>'.$rowGetstudent_session['admission_no'].'</td>
                    			<td>'.$rowGetclasses['class'].'</td>
                    			<td>'.$rowGetsessions['session'].'</td>';
                    			
                    			if($reltype == 'cummulative')
                                {
                                    echo '<td>Cummulative</td>';
                    			
                                }
                                else
                                {
                                    echo '<td>'.$term.' Term</td>';
                                }
                                
                    			echo '<td>
                                    <a href="resultPage.php?classsection='.$classsection.'&classsectionactual='.$classsectionactual.'&classid='.$classid.'&session='.$session.'&term='.$term.'&id='.$rowGetstudent_session['StudentID'].'&reltype='.$reltype.'" style="font-size: 15px;text-decoration:underline;">
                                        View Result
                                    </a>
                                </td>';
                    			
                    		echo '</tr>';
                        }while($rowGetstudent_session = mysqli_fetch_assoc($queryGetstudent_session));
                        
                    }
                    else
                    {
                        echo '<tr><td>No Records Found</td></tr>';
                    }
            
            	echo '</tbody>
            </table>';
            
        }
        else
        {
            echo 'Class Section Not Found';
        }
    }
   
?>
