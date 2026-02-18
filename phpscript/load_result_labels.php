<?php
$num = $_POST['num'];
$html = '';
for ($i = 1; $i <= $num; $i++) {
	$default = ($i == 1) ? 'Mastered Concept' : (($i == 2) ? 'Needs Work' : 'Outcome ' . $i);
	$html .= '<div class="form-group">
                <label>Result Label ' . $i . '</label>
                <input type="text" class="form-control result-label-input" value="' . $default . '" placeholder="e.g. ' . $default . '">
              </div>';
}
echo $html;
