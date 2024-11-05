<?php 
    include ('../../database/config.php');
    
    $resultsettingid = $_POST['resultsettingid'];
    
    $sqlchecker = "SELECT * FROM `assignsaftoclass` WHERE AffectiveDomainSettingsId='$resultsettingid' LIMIT 1";
    $resultchecker = mysqli_query($link, $sqlchecker);
    $rowchecker = mysqli_fetch_assoc($resultchecker);
    $row_cntchecker = mysqli_num_rows($resultchecker);
    
    if($row_cntchecker > 0)
    {
        $ResultType = $rowchecker['ResultType'];
        
        if($ResultType == 'british')
        {
            $selectedbritish ='selected';
        }
        else
        {
            $selectedbritish ='';
        }
        
        if($ResultType == 'alphabetic')
        {
            $selectedalphabetic ='selected';
        }
        else
        {
            $selectedalphabetic ='';
        }
        
        if($ResultType == 'numeric')
        {
            $selectednumeric='selected';
        }
        else
        {
            $selectednumeric ='';
        }
        
        echo '<input type="hidden" id="resultsettingid" value="'.$resultsettingid.'">
                                                            
        <div class="col-sm-12">
            <select class="form-control" id="resulttype">
                <option value="0">Select Result Type</option>
                <option value="british" '.$selectedbritish.'>British</option>
                <option value="alphabetic" '.$selectedalphabetic.'>Alphabetic</option>
                <option value="numeric" '.$selectednumeric.'>Numeric</option>
            </select>
        </div><br><br>
        <div class="col-sm-12">';
            
            $sqlclass = "SELECT * FROM classes ORDER BY class";
            $resultclass = mysqli_query($link, $sqlclass);
            $rowclass = mysqli_fetch_assoc($resultclass);
            $row_cntclass = mysqli_num_rows($resultclass);
           
           if($row_cntclass > 0)
           {
               echo '<div class="form-row" style="margin-top: 10px;">
                    <div class="col-sm-12" align="left">
                        <div class="form-check" style="font-weight: bold;">
                            Select Class
                        </div>
                    </div>
                    ';
               echo '<div class="form-row" style="margin-top: 10px;">
                    <div class="col-sm-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" onclick=\'selects()\' ondblclick="deSelect()" value="Select All">
                            <label class="form-check-label" style="font-weight: bold;">
                                All
                            </label>
                        </div>
                    </div>
                    ';
               do{    
                   
                   $class = $rowclass['id'];
                   
                    $sqlassigncatoclass = "SELECT * FROM `assignsaftoclass` WHERE ClassID='$class' AND AffectiveDomainSettingsId='$resultsettingid'";
                    $resultassigncatoclass = mysqli_query($link, $sqlassigncatoclass);
                    $rowassigncatoclass = mysqli_fetch_assoc($resultassigncatoclass);
                    $row_cntassigncatoclass = mysqli_num_rows($resultassigncatoclass);
                    
                        if($rowassigncatoclass['ClassID'] == $class)
                        {
                            $checked= 'checked';
                        }
                        else
                        {
                            $checked= '';
                        }
                    
                    echo '<div class="col-sm-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="chkBoc" '.$checked.' value="'.$rowclass['id'].'" id="classcheck'.$rowclass['id'].'">
                                <label for="classcheck'.$rowclass['id'].'">'.$rowclass['class'].'</label>
                            </div>
                        </div>';
            
                }while($rowclass = mysqli_fetch_assoc($resultclass));
                
                echo '</div>';
           }
           else{
               echo '<div class="alert alert-warning" role="alert"> No records found!</div>';
           }
            
        echo '</div>';

    }
    else
    {
        
        echo '<input type="hidden" id="resultsettingid" value="'.$resultsettingid.'">
                                                            
        <div class="col-sm-12">
            <select class="form-control" id="resulttype">
                <option value="0">Select Result Type</option>
                <option value="british">British</option>
                <option value="alphabetic">Alphabetic</option>
                <option value="numeric">Numeric</option>
            </select>
        </div><br><br>
        <div class="col-sm-12">';
            
            $sqlclass = "SELECT * FROM classes ORDER BY class";
            $resultclass = mysqli_query($link, $sqlclass);
            $rowclass = mysqli_fetch_assoc($resultclass);
            $row_cntclass = mysqli_num_rows($resultclass);
           
           if($row_cntclass > 0)
           {
               echo '<div class="form-row" style="margin-top: 10px;">
                    <div class="col-sm-12" align="left">
                        <div class="form-check" style="font-weight: bold;">
                            Select Class
                        </div>
                    </div>
                    ';
               echo '<div class="form-row" style="margin-top: 10px;">
                    <div class="col-sm-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" onclick=\'selects()\' ondblclick="deSelect()" value="Select All">
                            <label class="form-check-label" style="font-weight: bold;">
                                All
                            </label>
                        </div>
                    </div>
                    ';
               do{    
                   
                   $class = $rowclass['id'];
                   
                    echo '<div class="col-sm-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="chkBoc" value="'.$rowclass['id'].'" id="classcheck'.$rowclass['id'].'">
                                <label for="classcheck'.$rowclass['id'].'">'.$rowclass['class'].'</label>
                            </div>
                        </div>';
            
                }while($rowclass = mysqli_fetch_assoc($resultclass));
                
                echo '</div>';
           }
           else{
               echo '<div class="alert alert-warning" role="alert"> No records found!</div>';
           }
            
        echo '</div>';

    }
    
?>