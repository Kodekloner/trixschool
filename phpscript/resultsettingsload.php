<?php
include('../database/config.php');

$examNumber = $_POST['examNumber'];

$catitle = $_POST['catitle'];

if ($examNumber > 0) {
	$sqltogetresultsettings = mysqli_query($link, "SELECT * FROM `resultsetting` WHERE CaTitle= '$catitle'");
	$countresultsettings = mysqli_num_rows($sqltogetresultsettings);
	$rowresultsettings = mysqli_fetch_array($sqltogetresultsettings);

	if ($rowresultsettings > 0) {
		$CA1Title = $rowresultsettings['CA1Title'];
		$CA1Score = $rowresultsettings['CA1Score'];
		$CA2Title = $rowresultsettings['CA2Title'];
		$CA2Score = $rowresultsettings['CA2Score'];
		$CA3Title = $rowresultsettings['CA3Title'];
		$CA3Score = $rowresultsettings['CA3Score'];
		$CA4Title = $rowresultsettings['CA4Title'];
		$CA4Score = $rowresultsettings['CA4Score'];
		$CA5Title = $rowresultsettings['CA5Title'];
		$CA5Score = $rowresultsettings['CA5Score'];

		$CA6Title = $rowresultsettings['CA6Title'];
		$CA6Score = $rowresultsettings['CA6Score'];
		$CA7Title = $rowresultsettings['CA7Title'];
		$CA7Score = $rowresultsettings['CA7Score'];
		$CA8Title = $rowresultsettings['CA8Title'];
		$CA8Score = $rowresultsettings['CA8Score'];
		$CA9Title = $rowresultsettings['CA9Title'];
		$CA9Score = $rowresultsettings['CA9Score'];
		$CA10Title = $rowresultsettings['CA10Title'];
		$CA10Score = $rowresultsettings['CA10Score'];

		$MidTermCaToUse = $rowresultsettings['MidTermCaToUse'];

		$MidTermCaToUseArr = explode(',', $MidTermCaToUse);

		$MidTermMaxScore = $rowresultsettings['MidTermMaxScore'];

		if (in_array("1", $MidTermCaToUseArr)) {
			$check1 = 'checked';
		} else {
			$check1 = '';
		}

		if (in_array("2", $MidTermCaToUseArr)) {
			$check2 = 'checked';
		} else {
			$check2 = '';
		}
		if (in_array("3", $MidTermCaToUseArr)) {
			$check3 = 'checked';
		} else {
			$check3 = '';
		}
		if (in_array("4", $MidTermCaToUseArr)) {
			$check4 = 'checked';
		} else {
			$check4 = '';
		}
		if (in_array("5", $MidTermCaToUseArr)) {
			$check5 = 'checked';
		} else {
			$check5 = '';
		}
		if (in_array("6", $MidTermCaToUseArr)) {
			$check6 = 'checked';
		} else {
			$check6 = '';
		}
		if (in_array("7", $MidTermCaToUseArr)) {
			$check7 = 'checked';
		} else {
			$check7 = '';
		}
		if (in_array("8", $MidTermCaToUseArr)) {
			$check8 = 'checked';
		} else {
			$check8 = '';
		}
		if (in_array("9", $MidTermCaToUseArr)) {
			$check9 = 'checked';
		} else {
			$check9 = '';
		}
		if (in_array("10", $MidTermCaToUseArr)) {
			$check10 = 'checked';
		} else {
			$check10 = '';
		}

		echo '<div class="form-row one two three four five six seven eight nine ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca1id" class="form-control" placeholder="Exam Name" value="' . $CA1Title . '">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca1maxid" class="form-control" placeholder="Max" value="' . $CA1Score . '">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" ' . $check1 . ' id="minimal-checkbox-1" name="selForMidTerm" type="checkbox" data-id="1" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="one two three four five six seven eight nine ten">
				
				<div class="form-row two three four five six seven eight nine ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca2id" class="form-control" placeholder="Exam Name" value="' . $CA2Title . '">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca2maxid" class="form-control" placeholder="Max" value="' . $CA2Score . '">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" ' . $check2 . ' id="minimal-checkbox-2" name="selForMidTerm" type="checkbox" data-id="2" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="two three four five six seven eight nine ten">
				
				<div class="form-row three four five six seven eight nine ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca3id" class="form-control" placeholder="Exam Name" value="' . $CA3Title . '">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca3maxid" class="form-control" placeholder="Max" value="' . $CA3Score . '">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" ' . $check3 . ' id="minimal-checkbox-3" name="selForMidTerm" type="checkbox" data-id="3" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="three four five six seven eight nine ten">
				
				<div class="form-row four five six seven eight nine ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca4id" class="form-control" placeholder="Exam Name" value="' . $CA4Title . '">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca4maxid" class="form-control" placeholder="Max" value="' . $CA4Score . '">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" ' . $check4 . ' id="minimal-checkbox-4" name="selForMidTerm" type="checkbox" data-id="4" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="four five six seven eight nine ten">
				
				<div class="form-row five six seven eight nine ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca5id" class="form-control" placeholder="Exam Name" value="' . $CA5Title . '">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca5maxid" class="form-control" placeholder="Max" value="' . $CA5Score . '">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" ' . $check5 . ' id="minimal-checkbox-5" name="selForMidTerm" type="checkbox" data-id="5" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="five six seven eight nine ten">
				
				<div class="form-row six seven eight nine ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca6id" class="form-control" placeholder="Exam Name" value="' . $CA6Title . '">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca6maxid" class="form-control" placeholder="Max" value="' . $CA6Score . '">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" ' . $check6 . ' id="minimal-checkbox-6" name="selForMidTerm" type="checkbox" data-id="6" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="six seven eight nine ten">
				
				<div class="form-row seven eight nine ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca7id" class="form-control" placeholder="Exam Name" value="' . $CA7Title . '">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca7maxid" class="form-control" placeholder="Max" value="' . $CA7Score . '">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" ' . $check7 . ' id="minimal-checkbox-7" name="selForMidTerm" type="checkbox" data-id="7" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="seven eight nine ten">
				
				<div class="form-row eight nine ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca8id" class="form-control" placeholder="Exam Name" value="' . $CA8Title . '">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca8maxid" class="form-control" placeholder="Max" value="' . $CA8Score . '">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" ' . $check8 . ' id="minimal-checkbox-8" name="selForMidTerm" type="checkbox" data-id="8" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="eight nine ten">
				
				<div class="form-row nine ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca9id" class="form-control" placeholder="Exam Name" value="' . $CA9Title . '">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca9maxid" class="form-control" placeholder="Max" value="' . $CA9Score . '">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" ' . $check9 . ' id="minimal-checkbox-9" name="selForMidTerm" type="checkbox" data-id="9" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="nine ten">
				
				<div class="form-row ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca10id" class="form-control" placeholder="Exam Name" value="' . $CA10Title . '">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca10maxid" class="form-control" placeholder="Max" value="' . $CA10Score . '">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" ' . $check10 . ' id="minimal-checkbox-10" name="selForMidTerm" type="checkbox" data-id="10" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="ten">';
		echo '<script>$("#MidTermMaxScore").val("' . htmlspecialchars($MidTermMaxScore) . '");</script>';
	} else {
		echo '<div class="form-row one two three four five six seven eight nine ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca1id" class="form-control" placeholder="Exam Name">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca1maxid" class="form-control" placeholder="Max">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" id="minimal-checkbox-1" name="selForMidTerm" type="checkbox" data-id="1" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="one two three four five six seven eight nine ten">
				
				<div class="form-row two three four five six seven eight nine ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca2id" class="form-control" placeholder="Exam Name">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca2maxid" class="form-control" placeholder="Max">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" id="minimal-checkbox-2" name="selForMidTerm" type="checkbox" data-id="2" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="two three four five six seven eight nine ten">
				
				<div class="form-row three four five six seven eight nine ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca3id" class="form-control" placeholder="Exam Name">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca3maxid" class="form-control" placeholder="Max">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" id="minimal-checkbox-3" name="selForMidTerm" type="checkbox" data-id="3" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="three four five six seven eight nine ten">
				
				<div class="form-row four five six seven eight nine ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca4id" class="form-control" placeholder="Exam Name">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca4maxid" class="form-control" placeholder="Max">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" id="minimal-checkbox-4" name="selForMidTerm" type="checkbox" data-id="4" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="four five six seven eight nine ten">
				
				<div class="form-row five six seven eight nine ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca5id" class="form-control" placeholder="Exam Name">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca5maxid" class="form-control" placeholder="Max">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" id="minimal-checkbox-5" name="selForMidTerm" type="checkbox" data-id="5" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="five six seven eight nine ten">
				
				<div class="form-row six seven eight nine ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca6id" class="form-control" placeholder="Exam Name">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca6maxid" class="form-control" placeholder="Max">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" id="minimal-checkbox-6" name="selForMidTerm" type="checkbox" data-id="6" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="six seven eight nine ten">
				
				<div class="form-row seven eight nine ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca7id" class="form-control" placeholder="Exam Name">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca7maxid" class="form-control" placeholder="Max">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" id="minimal-checkbox-7" name="selForMidTerm" type="checkbox" data-id="7" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="seven eight nine ten">
				
				<div class="form-row eight nine ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca8id" class="form-control" placeholder="Exam Name">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca8maxid" class="form-control" placeholder="Max">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" id="minimal-checkbox-8" name="selForMidTerm" type="checkbox" data-id="8" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="eight nine ten">
				
				<div class="form-row nine ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca9id" class="form-control" placeholder="Exam Name">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca9maxid" class="form-control" placeholder="Max">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" id="minimal-checkbox-9" name="selForMidTerm" type="checkbox" data-id="9" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="nine ten">
				
				<div class="form-row ten"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca10id" class="form-control" placeholder="Exam Name">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca10maxid" class="form-control" placeholder="Max">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" id="minimal-checkbox-10" name="selForMidTerm" type="checkbox" data-id="10" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="ten">';
		echo '<script>$("#MidTermMaxScore").val("0");</script>';
	}
} else {
	echo '<div class="alert alert-info alert-dismissible fade show" role="alert">
                    Please Select Number of Exams
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>';
}
