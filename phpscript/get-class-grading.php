<?php 
    include ('../database/config.php');
    
    $GradingTitle = $_POST['GradingTitle'];
    
    $sqlchecker = "SELECT * FROM `assigngradingtclass` WHERE GradingTitle='$GradingTitle' LIMIT 1";
    $resultchecker = mysqli_query($link, $sqlchecker);
    $rowchecker = mysqli_fetch_assoc($resultchecker);
    $row_cntchecker = mysqli_num_rows($resultchecker);
    
    if($row_cntchecker > 0)
    {
        
        echo '<input type="hidden" id="GradingTitleid" value="'.$GradingTitle.'">

        <div class="col-sm-12">';
            
            $sqlclass = "SELECT * FROM classes ORDER BY class";
            $resultclass = mysqli_query($link, $sqlclass);
            $rowclass = mysqli_fetch_assoc($resultclass);
            $row_cntclass = mysqli_num_rows($resultclass);
           
           if($row_cntclass > 0)
           {
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
                   
                    $sqlassigncatoclass = "SELECT * FROM `assigngradingtclass` WHERE ClassID='$class' AND GradingTitle='$GradingTitle'";
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
        
        echo '<input type="hidden" id="GradingTitleid" value="'.$GradingTitle.'">

        <div class="col-sm-12">';
            
            $sqlclass = "SELECT * FROM classes ORDER BY class";
            $resultclass = mysqli_query($link, $sqlclass);
            $rowclass = mysqli_fetch_assoc($resultclass);
            $row_cntclass = mysqli_num_rows($resultclass);
           
           if($row_cntclass > 0)
           {
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