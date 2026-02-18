<?php
include('../database/config.php');

if (isset($_POST['ScoreID'])) {
	$selDeleteID = $_POST['ScoreID'];
	$studname = $_POST['studname'];

	echo '<h2 class="modal-title">Are you sure?</h2>
          <p>
          <div>
            <i class="fa fa-remove fa-lg" style="color:red; font-size:56px;"></i>
          </div>
          <input id="selDeleteID" name="selDeleteID" type="hidden" value="' . $selDeleteID . '" />
          <p style="color:black;">Are you sure you want to delete <b>' . $studname . '</b> scores for the selected subject?<br>Note that this action can not be reversed.</p>';
}
