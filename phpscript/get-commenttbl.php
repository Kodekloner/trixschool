<?php
    include ('../database/config.php');
    
    $id = $_POST['id'];
    
    $sqlexamsubjects = "SELECT * FROM `defaultcomment` WHERE PrincipalOrDeanOrHeadTeacherUserID= '$id'";
    $resultexamsubjects = mysqli_query($link, $sqlexamsubjects);
    $rowexamsubjects = mysqli_fetch_assoc($resultexamsubjects);
    $row_cntexamsubjects = mysqli_num_rows($resultexamsubjects);

    if($row_cntexamsubjects > 0)
    {
        do{
           
            echo'<tr>
                    <td>'.$rowexamsubjects['RangeStart'].'</td>
                    <td>'.$rowexamsubjects['RangeEnd'].'</td>
                    <td>'.$rowexamsubjects['DefaultComment'].'</td>
                    <td>
                    	<a href="#" data-toggle="modal" data-target="#exampleModalEdit" data-id="'.$rowexamsubjects['defaultcommentID'].'" id="editbtn" style="color: #000000;">
                    		<i class="fa fa-pencil" title="Edit" data-toggle="tooltip" aria-hidden="true"></i>
                    	</a>
                    	<a href="#" style="color: #ff0000; margin-left: 10px;" data-id="'.$rowexamsubjects['defaultcommentID'].'" id="delbtn" data-toggle="modal" data-target="#deleteModal" data-toggle="tooltip" aria-hidden="true">
                    		<i class="fa fa-trash" title="Delete" data-toggle="tooltip" aria-hidden="true"></i>
                    	</a>
                    	
                    </td>
                </tr>';
            
        }while($rowexamsubjects = mysqli_fetch_assoc($resultexamsubjects));
    }
    else
    {
        echo '<tr colspan="12">
                <td>No Records Found</td>
                
            </tr>';
    }

?>
