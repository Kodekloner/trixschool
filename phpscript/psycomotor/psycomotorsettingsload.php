<?php
    include ('../../database/config.php');
    
    $examNumber = $_POST['examNumber'];
    
    $catitle = $_POST['catitle'];
    
    if($examNumber > 0)
    {
        $sqltogetresultsettings = mysqli_query($link,"SELECT * FROM `psycomotor_settings` WHERE PTitle= '$catitle'");
        $countresultsettings = mysqli_num_rows($sqltogetresultsettings);
        $rowresultsettings = mysqli_fetch_array($sqltogetresultsettings);
        
        if($rowresultsettings > 0)
        {   
            $CA1Title = $rowresultsettings['P1Title'];
            $CA1Score = $rowresultsettings['P1Score'];
            $CA2Title = $rowresultsettings['P2Title'];
            $CA2Score = $rowresultsettings['P2Score'];
            $CA3Title = $rowresultsettings['P3Title'];
            $CA3Score = $rowresultsettings['P3Score'];
            $CA4Title = $rowresultsettings['P4Title'];
            $CA4Score = $rowresultsettings['P4Score'];
            $CA5Title = $rowresultsettings['P5Title'];
            $CA5Score = $rowresultsettings['P5Score'];
    
            $CA6Title = $rowresultsettings['P6Title'];
            $CA6Score = $rowresultsettings['P6Score'];
            $CA7Title = $rowresultsettings['P7Title'];
            $CA7Score = $rowresultsettings['P7Score'];
            $CA8Title = $rowresultsettings['P8Title'];
            $CA8Score = $rowresultsettings['P8Score'];
            $CA9Title = $rowresultsettings['P9Title'];
            $CA9Score = $rowresultsettings['P9Score'];
            $CA10Title = $rowresultsettings['P10Title'];
            $CA10Score = $rowresultsettings['P10Score'];
            $CA11Title = $rowresultsettings['P11Title'];
            $CA11Score = $rowresultsettings['P11Score'];
            $CA12Title = $rowresultsettings['P12Title'];
            $CA12Score = $rowresultsettings['P12Score'];
            $CA13Title = $rowresultsettings['P13Title'];
            $CA13Score = $rowresultsettings['P13Score'];
            $CA14Title = $rowresultsettings['P14Title'];
            $CA14Score = $rowresultsettings['P14Score'];
            $CA15Title = $rowresultsettings['P15Title'];
            $CA15Score = $rowresultsettings['P15Score'];
        
            $MidTermCaToUse = $rowresultsettings['MidTermPToUse'];
    
            $MidTermCaToUseArr = explode(',',$MidTermCaToUse);
    
            if(in_array("1", $MidTermCaToUseArr))
            {
                $check1 = 'checked';
            }
            else{
                $check1 = '';
            }
    
            if(in_array("2", $MidTermCaToUseArr))
            {
                $check2 = 'checked';
            }
            else{
                $check2 = '';
            }
            if(in_array("3", $MidTermCaToUseArr))
            {
                $check3 = 'checked';
            }
            else{
                $check3 = '';
            }
            if(in_array("4", $MidTermCaToUseArr))
            {
                $check4 = 'checked';
            }
            else{
                $check4 = '';
            }
            if(in_array("5", $MidTermCaToUseArr))
            {
                $check5 = 'checked';
            }
            else{
                $check5 = '';
            }
            if(in_array("6", $MidTermCaToUseArr))
            {
                $check6 = 'checked';
            }
            else{
                $check6 = '';
            }
            if(in_array("7", $MidTermCaToUseArr))
            {
                $check7 = 'checked';
            }
            else{
                $check7 = '';
            }
            if(in_array("8", $MidTermCaToUseArr))
            {
                $check8 = 'checked';
            }
            else{
                $check8 = '';
            }
            if(in_array("9", $MidTermCaToUseArr))
            {
                $check9 = 'checked';
            }
            else{
                $check9 = '';
            }
            if(in_array("10", $MidTermCaToUseArr))
            {
                $check10 = 'checked';
            }
            else{
                $check10 = '';
            }
            if(in_array("11", $MidTermCaToUseArr))
            {
                $check11 = 'checked';
            }
            else{
                $check11 = '';
            }
            if(in_array("12", $MidTermCaToUseArr))
            {
                $check12 = 'checked';
            }
            else{
                $check12 = '';
            }
            if(in_array("13", $MidTermCaToUseArr))
            {
                $check13 = 'checked';
            }
            else{
                $check13 = '';
            }
            if(in_array("14", $MidTermCaToUseArr))
            {
                $check14 = 'checked';
            }
            else{
                $check14 = '';
            }
            if(in_array("15", $MidTermCaToUseArr))
            {
                $check15 = 'checked';
            }
            else{
                $check15 = '';
            }
            
            echo'<div class="form-row one two three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca1id" class="form-control" placeholder="Psycomotor Name" value="'.$CA1Title.'">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca1maxid" class="form-control" placeholder="Max" value="'.$CA1Score.'">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" '.$check1.' id="minimal-checkbox-1" name="selForMidTerm" type="checkbox" data-id="1" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="one two three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row two three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca2id" class="form-control" placeholder="Psycomotor Name" value="'.$CA2Title.'">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca2maxid" class="form-control" placeholder="Max" value="'.$CA2Score.'">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" '.$check2.' id="minimal-checkbox-2" name="selForMidTerm" type="checkbox" data-id="2" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="two three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca3id" class="form-control" placeholder="Psycomotor Name" value="'.$CA3Title.'">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca3maxid" class="form-control" placeholder="Max" value="'.$CA3Score.'">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" '.$check3.' id="minimal-checkbox-3" name="selForMidTerm" type="checkbox" data-id="3" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row four five six seven eight nine ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca4id" class="form-control" placeholder="Psycomotor Name" value="'.$CA4Title.'">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca4maxid" class="form-control" placeholder="Max" value="'.$CA4Score.'">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" '.$check4.' id="minimal-checkbox-4" name="selForMidTerm" type="checkbox" data-id="4" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="four five six seven eight nine ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row five six seven eight nine ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca5id" class="form-control" placeholder="Psycomotor Name" value="'.$CA5Title.'">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca5maxid" class="form-control" placeholder="Max" value="'.$CA5Score.'">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" '.$check5.' id="minimal-checkbox-5" name="selForMidTerm" type="checkbox" data-id="5" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="five six seven eight nine ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row six seven eight nine ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca6id" class="form-control" placeholder="Psycomotor Name" value="'.$CA6Title.'">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca6maxid" class="form-control" placeholder="Max" value="'.$CA6Score.'">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" '.$check6.' id="minimal-checkbox-6" name="selForMidTerm" type="checkbox" data-id="6" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="six seven eight nine ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row seven eight nine ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca7id" class="form-control" placeholder="Psycomotor Name" value="'.$CA7Title.'">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca7maxid" class="form-control" placeholder="Max" value="'.$CA7Score.'">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" '.$check7.' id="minimal-checkbox-7" name="selForMidTerm" type="checkbox" data-id="7" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="seven eight nine ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row eight nine ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca8id" class="form-control" placeholder="Psycomotor Name" value="'.$CA8Title.'">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca8maxid" class="form-control" placeholder="Max" value="'.$CA8Score.'">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" '.$check8.' id="minimal-checkbox-8" name="selForMidTerm" type="checkbox" data-id="8" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="eight nine ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row nine ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca9id" class="form-control" placeholder="Psycomotor Name" value="'.$CA9Title.'">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca9maxid" class="form-control" placeholder="Max" value="'.$CA9Score.'">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" '.$check9.' id="minimal-checkbox-9" name="selForMidTerm" type="checkbox" data-id="9" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="nine ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca10id" class="form-control" placeholder="Psycomotor Name" value="'.$CA10Title.'">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca10maxid" class="form-control" placeholder="Max" value="'.$CA10Score.'">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" '.$check10.' id="minimal-checkbox-10" name="selForMidTerm" type="checkbox" data-id="10" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="ten eleven twelve thirteen fourteen fifteen">
				
				
				<div class="form-row eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca11id" class="form-control" placeholder="Psycomotor Name" value="'.$CA11Title.'">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca11maxid" class="form-control" placeholder="Max" value="'.$CA11Score.'">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" '.$check11.' id="minimal-checkbox-11" name="selForMidTerm" type="checkbox" data-id="11" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="eleven twelve thirteen fourteen fifteen">
				
				
				<div class="form-row twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca12id" class="form-control" placeholder="Psycomotor Name" value="'.$CA12Title.'">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca12maxid" class="form-control" placeholder="Max" value="'.$CA12Score.'">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" '.$check12.' id="minimal-checkbox-12" name="selForMidTerm" type="checkbox" data-id="12" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="twelve thirteen fourteen fifteen">
				
				
				<div class="form-row thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca13id" class="form-control" placeholder="Psycomotor Name" value="'.$CA13Title.'">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca13maxid" class="form-control" placeholder="Max" value="'.$CA13Score.'">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" '.$check13.' id="minimal-checkbox-13" name="selForMidTerm" type="checkbox" data-id="13" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="thirteen fourteen fifteen">
				
				
				<div class="form-row fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca14id" class="form-control" placeholder="Psycomotor Name" value="'.$CA14Title.'">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca14maxid" class="form-control" placeholder="Max" value="'.$CA14Score.'">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" '.$check14.' id="minimal-checkbox-14" name="selForMidTerm" type="checkbox" data-id="14" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="fourteen fifteen">
				
				
				<div class="form-row fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca15id" class="form-control" placeholder="Psycomotor Name" value="'.$CA15Title.'">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca15maxid" class="form-control" placeholder="Max" value="'.$CA15Score.'">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" '.$check15.' id="minimal-checkbox-15" name="selForMidTerm" type="checkbox" data-id="15" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="fifteen">';
				
        }
        else
        {
            echo'<div class="form-row one two three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca1id" class="form-control" placeholder="Psycomotor Name">
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
				</div>  <br class="one two three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row two three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca2id" class="form-control" placeholder="Psycomotor Name">
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
				</div>  <br class="two three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca3id" class="form-control" placeholder="Psycomotor Name">
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
				</div>  <br class="three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row four five six seven eight nine ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca4id" class="form-control" placeholder="Psycomotor Name">
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
				</div>  <br class="four five six seven eight nine ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row five six seven eight nine ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca5id" class="form-control" placeholder="Psycomotor Name">
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
				</div>  <br class="five six seven eight nine ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row six seven eight nine ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca6id" class="form-control" placeholder="Psycomotor Name">
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
				</div>  <br class="six seven eight nine ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row seven eight nine ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca7id" class="form-control" placeholder="Psycomotor Name">
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
				</div>  <br class="seven eight nine ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row eight nine ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca8id" class="form-control" placeholder="Psycomotor Name">
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
				</div>  <br class="eight nine ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row nine ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca9id" class="form-control" placeholder="Psycomotor Name">
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
				</div>  <br class="nine ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row ten eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca10id" class="form-control" placeholder="Psycomotor Name">
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
				</div>  <br class="ten eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row eleven twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca11id" class="form-control" placeholder="Psycomotor Name">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca11maxid" class="form-control" placeholder="Max">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" id="minimal-checkbox-11" name="selForMidTerm" type="checkbox" data-id="11" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="eleven twelve thirteen fourteen fifteen">
				
				<div class="form-row twelve thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca12id" class="form-control" placeholder="Psycomotor Name">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca12maxid" class="form-control" placeholder="Max">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" id="minimal-checkbox-12" name="selForMidTerm" type="checkbox" data-id="12" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="twelve thirteen fourteen fifteen">
				
				<div class="form-row thirteen fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca13id" class="form-control" placeholder="Psycomotor Name">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca13maxid" class="form-control" placeholder="Max">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" id="minimal-checkbox-13" name="selForMidTerm" type="checkbox" data-id="13" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="thirteen fourteen fifteen">
				
				<div class="form-row fourteen fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca14id" class="form-control" placeholder="Psycomotor Name">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca14maxid" class="form-control" placeholder="Max">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" id="minimal-checkbox-14" name="selForMidTerm" type="checkbox" data-id="14" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="fourteen fifteen">
				
				<div class="form-row fifteen"> 
					<div class="col-sm col-md">
                        <input type="text" id="ca15id" class="form-control" placeholder="Psycomotor Name">
					</div>
					<div class="col-sm col-md">
						<input type="number" id="ca15maxid" class="form-control" placeholder="Max">
                    </div>
                    <div class="col-sm col-md">
						<div class="form-check form-check-inline">
							<label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;">
								Use For Mid-Term
							</label>
							<input class="form-check-input" id="minimal-checkbox-15" name="selForMidTerm" type="checkbox" data-id="15" style="margin-left: 4px; margin-top: 4px;">
						</div>
					</div>
				</div>  <br class="fifteen">';
        }
    
    }
    else
    {
        echo'<div class="alert alert-info alert-dismissible fade show" role="alert">
                    Please Select Number of Exams
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>';
    }
?>