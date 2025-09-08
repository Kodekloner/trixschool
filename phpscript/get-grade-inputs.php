<?php
    include ('../database/config.php');
    
    $gradeNumber = $_POST['gradeNumber'];
    
    $gradingtitle = $_POST['gradingtitle'];
    
    $cnt=0;
    
    if($gradeNumber > 0)
    {
        
        $sqltogetresultsettings = mysqli_query($link,"SELECT * FROM `gradingstructure` WHERE GradingTitle = '$gradingtitle'");
        $rowresultsettings = mysqli_fetch_assoc($sqltogetresultsettings);
        $countresultsettings = mysqli_num_rows($sqltogetresultsettings);
        
        if($countresultsettings > 0)
        {
           if($countresultsettings >= $gradeNumber)
           {
               $sqltogetresultsettingsfnal = mysqli_query($link,"SELECT * FROM `gradingstructure` WHERE GradingTitle = '$gradingtitle' LIMIT $gradeNumber");
                $rowresultsettingsfnal = mysqli_fetch_assoc($sqltogetresultsettingsfnal);
                $countresultsettingsfnal = mysqli_num_rows($sqltogetresultsettingsfnal);
               do{
                    $cnt++;
                    echo'<div class="form-row"> 
        					<div class="col-sm-3">
        						<input type="text" class="form-control form-control-sm grade" placeholder="A" value="'.$rowresultsettingsfnal['Grade'].'">
        					</div>
        					<div class="col-sm-3">
        						<input type="number" class="form-control form-control-sm gradefrom" placeholder="79.99" value="'.$rowresultsettingsfnal['RangeStart'].'">
        					</div>
        					<div class="col-sm-3">
        						<input type="number" class="form-control form-control-sm gradeto" placeholder="100" value="'.$rowresultsettingsfnal['RangeEnd'].'">
        					</div>
        					<div class="col-sm-3">
        						<input type="text" class="form-control form-control-sm graderemark" placeholder="Excellent" value="'.$rowresultsettingsfnal['Remark'].'">
        					</div>
        				</div> <br>';
                        
                }while($rowresultsettingsfnal = mysqli_fetch_assoc($sqltogetresultsettingsfnal));
           }
           elseif($countresultsettings < $gradeNumber)
           {
               $newcnt = $countresultsettings;
               
               do{
                
                    echo'<div class="form-row"> 
    					<div class="col-sm-3">
    						<input type="text" class="form-control form-control-sm grade" placeholder="A" value="'.$rowresultsettings['Grade'].'">
    					</div>
    					<div class="col-sm-3">
    						<input type="number" class="form-control form-control-sm gradefrom" placeholder="79.99" value="'.$rowresultsettings['RangeStart'].'">
    					</div>
    					<div class="col-sm-3">
    						<input type="number" class="form-control form-control-sm gradeto" placeholder="100" value="'.$rowresultsettings['RangeEnd'].'">
    					</div>
    					<div class="col-sm-3">
    						<input type="text" class="form-control form-control-sm graderemark" placeholder="Excellent" value="'.$rowresultsettings['Remark'].'">
    					</div>
    				</div> <br>';
                    
                }while($rowresultsettings = mysqli_fetch_array($sqltogetresultsettings));
                
                do{
                    $newcnt++;
                    
                    echo'<div class="form-row"> 
        					<div class="col-sm-3">
        						<input type="text" class="form-control form-control-sm grade" placeholder="A">
        					</div>
        					<div class="col-sm-3">
        						<input type="number" class="form-control form-control-sm gradefrom" placeholder="79.99">
        					</div>
        					<div class="col-sm-3">
        						<input type="number" class="form-control form-control-sm gradeto" placeholder="100">
        					</div>
        					<div class="col-sm-3">
        						<input type="text" class="form-control form-control-sm graderemark" placeholder="Excellent">
        					</div>
        				</div> <br>';
                    
                }while($newcnt < $gradeNumber);
           }
           else
           {
                do{
                
                echo'<div class="form-row"> 
    					<div class="col-sm-3">
    						<input type="text" class="form-control form-control-sm grade" placeholder="A" value="'.$rowresultsettings['Grade'].'">
    					</div>
    					<div class="col-sm-3">
    						<input type="number" class="form-control form-control-sm gradefrom" placeholder="79.99" value="'.$rowresultsettings['RangeStart'].'">
    					</div>
    					<div class="col-sm-3">
    						<input type="number" class="form-control form-control-sm gradeto" placeholder="100" value="'.$rowresultsettings['RangeEnd'].'">
    					</div>
    					<div class="col-sm-3">
    						<input type="text" class="form-control form-control-sm graderemark" placeholder="Excellent" value="'.$rowresultsettings['Remark'].'">
    					</div>
    				</div> <br>';
                    
                }while($rowresultsettings = mysqli_fetch_array($sqltogetresultsettings));
                
           }
           
        }
        else
        {
            do{
                $cnt++;
                echo'<div class="form-row"> 
    					<div class="col-sm-3">
    						<input type="text" class="form-control form-control-sm grade" placeholder="A">
    					</div>
    					<div class="col-sm-3">
    						<input type="number" class="form-control form-control-sm gradefrom" placeholder="79.99">
    					</div>
    					<div class="col-sm-3">
    						<input type="number" class="form-control form-control-sm gradeto" placeholder="100">
    					</div>
    					<div class="col-sm-3">
    						<input type="text" class="form-control form-control-sm graderemark" placeholder="Excellent">
    					</div>
    				</div> <br>';
                
            }while($cnt < $gradeNumber);
        }
        
    }
    else
    {
        echo'<div class="alert alert-info alert-dismissible fade show" role="alert">
                    Please Select Grade Number
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>';
    }
?>