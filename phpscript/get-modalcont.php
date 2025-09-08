<?php
    include ('../database/config.php');
    
    $id = $_POST['id'];
    
    $sqlexamsubjects = "SELECT * FROM `defaultcomment` WHERE defaultcommentID= '$id'";
    $resultexamsubjects = mysqli_query($link, $sqlexamsubjects);
    $rowexamsubjects = mysqli_fetch_assoc($resultexamsubjects);
    $row_cntexamsubjects = mysqli_num_rows($resultexamsubjects);

    if($row_cntexamsubjects > 0)
    {
        do{
           
            echo'<div class="modal-body">
                    <div class="row">
                        <div class="col">
                            <label style="font-weight: 500;">Ranges:</label>
                            <input type="number" step=".01" name="commentfrom" class="form-control" placeholder="80" value="'.$rowexamsubjects['RangeStart'].'">
                        </div>
                        <div class="col">
                            <label style="font-weight: 500;">&nbsp;&nbsp;&nbsp;</label>
                          <input type="number" step=".01" name="commentfromto" class="form-control" placeholder="100" value="'.$rowexamsubjects['RangeEnd'].'">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="exampleFormControlTextarea1" style="font-weight: 500;">Comment:</label>
                        <textarea class="form-control" name="comment" id="exampleFormControlTextarea1" rows="3" placeholder="exmple: excellet result">'.$rowexamsubjects['DefaultComment'].'</textarea>
                    </div>
                    <input type="hidden" id="principalID" name="defaultcommentID" value="'.$rowexamsubjects['defaultcommentID'].'">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" name="editgradebtn" class="btn btn-primary"><i class="fa fa-check"></i> Submit</button>
                </div>';
            
        }while($rowexamsubjects = mysqli_fetch_assoc($resultexamsubjects));
    }
    else
    {
        echo '<tr colspan="12">
                <td>No Records Found</td>
                
            </tr>';
    }

?>
