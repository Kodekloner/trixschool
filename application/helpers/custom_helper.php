<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

if (!function_exists('is_subAttendence')) {

    function is_subAttendence() {

        $CI = &get_instance();
        $CI->db->select('sch_settings.id,sch_settings.lang_id,sch_settings.attendence_type,sch_settings.is_rtl,sch_settings.timezone,
          sch_settings.name,sch_settings.email,sch_settings.biometric,sch_settings.biometric_device,sch_settings.phone,languages.language,
          sch_settings.address,sch_settings.dise_code,sch_settings.date_format,sch_settings.currency,sch_settings.currency_symbol,sch_settings.start_month,sch_settings.session_id,sch_settings.image,sch_settings.theme,sessions.session'
        );
        $CI->db->from('sch_settings');
        $CI->db->join('sessions', 'sessions.id = sch_settings.session_id');
        $CI->db->join('languages', 'languages.id = sch_settings.lang_id');
        $CI->db->order_by('sch_settings.id');
        $query = $CI->db->get();
        $result = $query->row();

        if ($result->attendence_type) {
            return true;
        }
        return false;
    }

}

if (!function_exists('get_subjects')) {

    function get_subjects($class_batch_id) {
        $CI = &get_instance();
        $CI->db->select('class_batch_subjects.*,subjects.name as `subject_name`');
        $CI->db->from('class_batch_subjects');
        $CI->db->join('subjects', 'subjects.id = class_batch_subjects.subject_id');
        $CI->db->where('class_batch_id', $class_batch_id);
        $CI->db->order_by('class_batch_subjects.id', 'asc');

        $query = $CI->db->get();
        $return_string = '<option value="">--Select--</option>';
        $result = $query->result();
        if (!empty($result)) {
            foreach ($result as $result_key => $result_value) {
                $return_string .= '<option value="' . $result_value->id . '">' . $result_value->subject_name . '</option>';
            }
        }
        return $return_string;
    }

}

if (!function_exists('readmorelink')) {

    function readmorelink($string, $link = false) {
        $string = strip_tags($string);
        if (strlen($string) > 150) {

            // truncate string
            $stringCut = substr($string, 0, 150);
            $endPoint = strrpos($stringCut, ' ');

            //if the string doesn't contain any space then it will cut without word basis.
            $string = $endPoint ? substr($stringCut, 0, $endPoint) : substr($stringCut, 0);
            $string .= ($link) ? "<a href='" . $link . "' target='_blank'>Read more...</a>" : "....";
        }

        return $string;
    }

}

if (!function_exists('readmorelinkUser')) {

    function readmorelinkUser($string, $link = false) {
        $string = strip_tags($string);
        if (strlen($string) > 150) {

            // truncate string
            $stringCut = substr($string, 0, 150);
            $endPoint = strrpos($stringCut, ' ');

            //if the string doesn't contain any space then it will cut without word basis.
            $string = $endPoint ? substr($stringCut, 0, $endPoint) : substr($stringCut, 0);

            $string .= ($link) ? "<a href='#" . $link . "' data-toggle='collapse' aria-expanded='false' aria-controls='" . $link . "' >Read more...</a>" : "....";
        }

        return $string;
    }

}

function expensegraphColors($color = null) {

    $colors = array(
        '1' => "#9966ff",
        '2' => "#36a2eb",
        '3' => "#ff9f40",
        '4' => "#715d20",
        '5' => "#c9cbcf",
        '6' => "#4bc0c0",
        '7' => "#ffcd56",
        '8' => "#66aa18",
    );
    if ($color == null) {
        return $colors;
    } else {
        return $colors[$color];
    }
}

function incomegraphColors($color = null) {

    $colors = array(
        '1' => "#66aa18",
        '2' => "#ffcd56",
        '3' => "#4bc0c0",
        '4' => "#c9cbcf",
        '5' => "#715d20",
        '6' => "#ff9f40",
        '7' => "#36a2eb",
        '8' => "#9966ff",
    );
    if ($color == null) {
        return $colors;
    } else {
        return $colors[$color];
    }
}

function isJSON($string) {
    return is_string($string) && is_array(json_decode($string, true)) && (json_last_error() == JSON_ERROR_NONE) ? true : false;
}

function currentTime() {
    return date("d/m/y : H:i:s", time());
}

function markSheetDigit() {
    $number = 190908100.25;
    $no = floor($number);
    $point = round($number - $no, 2) * 100;
    $hundred = null;
    $digits_1 = strlen($no);
    $i = 0;
    $str = array();
    $words = array('0' => '', '1' => 'one', '2' => 'two',
        '3' => 'three', '4' => 'four', '5' => 'five', '6' => 'six',
        '7' => 'seven', '8' => 'eight', '9' => 'nine',
        '10' => 'ten', '11' => 'eleven', '12' => 'twelve',
        '13' => 'thirteen', '14' => 'fourteen',
        '15' => 'fifteen', '16' => 'sixteen', '17' => 'seventeen',
        '18' => 'eighteen', '19' => 'nineteen', '20' => 'twenty',
        '30' => 'thirty', '40' => 'forty', '50' => 'fifty',
        '60' => 'sixty', '70' => 'seventy',
        '80' => 'eighty', '90' => 'ninety');
    $digits = array('', 'hundred', 'thousand', 'lakh', 'crore');
    while ($i < $digits_1) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += ($divider == 10) ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str[] = ($number < 21) ? $words[$number] .
                    " " . $digits[$counter] . $plural . " " . $hundred :
                    $words[floor($number / 10) * 10]
                    . " " . $words[$number % 10] . " "
                    . $digits[$counter] . $plural . " " . $hundred;
        } else {
            $str[] = null;
        }
    }
    $str = array_reverse($str);
    $result = implode('', $str);
    $points = ($point) ?
            "." . $words[$point / 10] . " " .
            $words[$point = $point % 10] : '';
    return $result . $points;
}

function getSecondsFromHMS($time) {
    $timeArr = array_reverse(explode(":", $time));    
    $seconds = 0;
    foreach ($timeArr as $key => $value) {
        if ($key > 2)
            break;
        $seconds += pow(60, $key) * $value;
    }
    return $seconds;
}

function getHMSFromSeconds($seconds) {
  $t = round($seconds);
  return sprintf('%02d:%02d:%02d', ($t/3600),($t/60%60), $t%60);
}


function array_insert(&$array, $position, $insert)
{
    if (is_int($position)) {
        array_splice($array, $position, 0, $insert);
    } else {
        $pos   = array_search($position, array_keys($array));
        $array = array_merge(
            array_slice($array, 0, $pos),
            $insert,
            array_slice($array, $pos)
        );
    }
}

function amountFormat($amount){
    return number_format((float)$amount, 2, '.', '');
}

if (!function_exists('get_student_attendance_qr_hash')) {
    function get_student_attendance_qr_hash($student_session_id)
    {
        $student_session_id = (int) $student_session_id;
        $secret             = (string) config_item('encryption_key');

        if ($secret === '') {
            $secret = (string) site_url();
        }

        return hash_hmac('sha256', 'student-attendance|' . $student_session_id, $secret);
    }
}

if (!function_exists('get_student_attendance_qr_url')) {
    function get_student_attendance_qr_url($student_session_id)
    {
        $student_session_id = (int) $student_session_id;

        return site_url('qrattendance/mark/' . $student_session_id . '/' . get_student_attendance_qr_hash($student_session_id));
    }
}

if (!function_exists('get_student_attendance_qr_image_url')) {
    function get_student_attendance_qr_image_url($student_session_id, $size = 110)
    {
        $size = (int) $size;
        if ($size <= 0) {
            $size = 110;
        }

        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . rawurlencode(get_student_attendance_qr_url($student_session_id));
    }
}

if (!function_exists('get_qr_attendance_demo_log_file')) {
    function get_qr_attendance_demo_log_file($date = null)
    {
        if (empty($date)) {
            $date = date('Y-m-d');
        }

        return FCPATH . 'uploads/qr_attendance_logs/qr_attendance_demo_' . $date . '.csv';
    }
}

if (!function_exists('get_storage_file_url')) {
    function get_storage_file_url($file_path, $fallback = '')
    {
        $file_path = trim((string) $file_path);
        $fallback  = trim((string) $fallback);

        if ($file_path === '') {
            $file_path = $fallback;
        }

        if ($file_path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $file_path)) {
            return $file_path;
        }

        $normalized_path = ltrim($file_path, '/');
        if (file_exists(FCPATH . $normalized_path)) {
            return base_url($normalized_path);
        }

        return 'https://schoollift.s3.us-east-2.amazonaws.com/' . $normalized_path;
    }
}

if (!function_exists('get_student_image_url')) {
    function get_student_image_url($image_path, $gender = '')
    {
        $gender = strtolower(trim((string) $gender));

        if ($gender === 'female') {
            $default_image = 'uploads/student_images/default_female.jpg';
        } else {
            $default_image = 'uploads/student_images/default_male.jpg';
        }

        return get_storage_file_url($image_path, $default_image);
    }
}

if (!function_exists('get_staff_photo_url')) {
    function get_staff_photo_url($image_path)
    {
        $image_path = trim((string) $image_path);
        if ($image_path !== '' && !preg_match('#^https?://#i', $image_path) && strpos($image_path, '/') === false) {
            $image_path = 'uploads/staff_images/' . $image_path;
        }

        return get_storage_file_url($image_path, 'uploads/staff_images/no_image.png');
    }
}

if (!function_exists('normalize_id_card_unit')) {
    function normalize_id_card_unit($unit)
    {
        $unit = strtolower(trim((string) $unit));
        $allowed_units = array('in', 'mm', 'cm', 'px');

        return in_array($unit, $allowed_units, true) ? $unit : 'in';
    }
}

if (!function_exists('format_id_card_measurement')) {
    function format_id_card_measurement($value)
    {
        $value = (float) $value;
        $value = round($value, 2);
        $formatted = number_format($value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}

if (!function_exists('get_id_card_dimension_config')) {
    function get_id_card_dimension_config($card = null)
    {
        if (is_array($card)) {
            $unit = isset($card['card_unit']) ? $card['card_unit'] : 'in';
            $width = isset($card['card_width']) ? $card['card_width'] : 2.1;
            $height = isset($card['card_height']) ? $card['card_height'] : 3.3;
        } elseif (is_object($card)) {
            $unit = isset($card->card_unit) ? $card->card_unit : 'in';
            $width = isset($card->card_width) ? $card->card_width : 2.1;
            $height = isset($card->card_height) ? $card->card_height : 3.3;
        } else {
            $unit = 'in';
            $width = 2.1;
            $height = 3.3;
        }

        $unit = normalize_id_card_unit($unit);
        $width = (float) $width;
        $height = (float) $height;

        if ($width <= 0) {
            $width = 2.1;
        }
        if ($height <= 0) {
            $height = 3.3;
        }

        return array(
            'unit' => $unit,
            'width' => $width,
            'height' => $height,
            'portrait' => $height >= $width,
        );
    }
}

if (!function_exists('get_default_id_card_layout')) {
    function get_default_id_card_layout($type = 'student', $portrait = true)
    {
        if ($type === 'staff') {
            if ($portrait) {
                return array(
                    'logo' => array('x' => 6, 'y' => 4, 'w' => 12, 'h' => 10),
                    'school_name' => array('x' => 21, 'y' => 4, 'w' => 72, 'h' => 8),
                    'school_address' => array('x' => 6, 'y' => 14, 'w' => 88, 'h' => 8),
                    'title' => array('x' => 6, 'y' => 24, 'w' => 88, 'h' => 7),
                    'photo' => array('x' => 6, 'y' => 34, 'w' => 28, 'h' => 22),
                    'name' => array('x' => 38, 'y' => 34, 'w' => 56, 'h' => 7),
                    'designation' => array('x' => 38, 'y' => 42, 'w' => 56, 'h' => 6),
                    'staff_id' => array('x' => 38, 'y' => 49, 'w' => 56, 'h' => 6),
                    'department' => array('x' => 6, 'y' => 58, 'w' => 88, 'h' => 6),
                    'father_name' => array('x' => 6, 'y' => 65, 'w' => 88, 'h' => 6),
                    'mother_name' => array('x' => 6, 'y' => 72, 'w' => 88, 'h' => 6),
                    'date_of_joining' => array('x' => 6, 'y' => 79, 'w' => 88, 'h' => 6),
                    'phone' => array('x' => 6, 'y' => 86, 'w' => 42, 'h' => 6),
                    'dob' => array('x' => 52, 'y' => 86, 'w' => 42, 'h' => 6),
                    'address' => array('x' => 6, 'y' => 93, 'w' => 50, 'h' => 6),
                    'signature' => array('x' => 56, 'y' => 92, 'w' => 18, 'h' => 6),
                    'qr' => array('x' => 74, 'y' => 86, 'w' => 20, 'h' => 14),
                );
            }

            return array(
                'logo' => array('x' => 4, 'y' => 5, 'w' => 8, 'h' => 18),
                'school_name' => array('x' => 14, 'y' => 4, 'w' => 56, 'h' => 10),
                'school_address' => array('x' => 14, 'y' => 14, 'w' => 56, 'h' => 8),
                'title' => array('x' => 73, 'y' => 5, 'w' => 23, 'h' => 12),
                'photo' => array('x' => 4, 'y' => 28, 'w' => 20, 'h' => 44),
                'name' => array('x' => 27, 'y' => 28, 'w' => 41, 'h' => 9),
                'designation' => array('x' => 27, 'y' => 37, 'w' => 41, 'h' => 8),
                'staff_id' => array('x' => 27, 'y' => 45, 'w' => 41, 'h' => 7),
                'department' => array('x' => 27, 'y' => 53, 'w' => 41, 'h' => 7),
                'father_name' => array('x' => 27, 'y' => 61, 'w' => 41, 'h' => 7),
                'mother_name' => array('x' => 27, 'y' => 69, 'w' => 41, 'h' => 7),
                'date_of_joining' => array('x' => 71, 'y' => 28, 'w' => 25, 'h' => 8),
                'phone' => array('x' => 71, 'y' => 37, 'w' => 25, 'h' => 7),
                'dob' => array('x' => 71, 'y' => 45, 'w' => 25, 'h' => 7),
                'address' => array('x' => 71, 'y' => 53, 'w' => 25, 'h' => 12),
                'signature' => array('x' => 58, 'y' => 82, 'w' => 18, 'h' => 10),
                'qr' => array('x' => 76, 'y' => 70, 'w' => 20, 'h' => 24),
            );
        }

        if ($portrait) {
            return array(
                'logo' => array('x' => 6, 'y' => 4, 'w' => 12, 'h' => 10),
                'school_name' => array('x' => 21, 'y' => 4, 'w' => 72, 'h' => 8),
                'school_address' => array('x' => 6, 'y' => 14, 'w' => 88, 'h' => 8),
                'title' => array('x' => 6, 'y' => 24, 'w' => 88, 'h' => 7),
                'photo' => array('x' => 6, 'y' => 34, 'w' => 28, 'h' => 22),
                'student_name' => array('x' => 38, 'y' => 34, 'w' => 56, 'h' => 7),
                'admission_no' => array('x' => 38, 'y' => 42, 'w' => 56, 'h' => 6),
                'class' => array('x' => 38, 'y' => 49, 'w' => 56, 'h' => 6),
                'father_name' => array('x' => 6, 'y' => 58, 'w' => 88, 'h' => 6),
                'mother_name' => array('x' => 6, 'y' => 65, 'w' => 88, 'h' => 6),
                'address' => array('x' => 6, 'y' => 72, 'w' => 88, 'h' => 10),
                'phone' => array('x' => 6, 'y' => 84, 'w' => 42, 'h' => 6),
                'dob' => array('x' => 52, 'y' => 84, 'w' => 42, 'h' => 6),
                'blood_group' => array('x' => 6, 'y' => 91, 'w' => 30, 'h' => 5),
                'signature' => array('x' => 36, 'y' => 90, 'w' => 26, 'h' => 8),
                'qr' => array('x' => 66, 'y' => 84, 'w' => 26, 'h' => 14),
            );
        }

        return array(
            'logo' => array('x' => 4, 'y' => 5, 'w' => 8, 'h' => 18),
            'school_name' => array('x' => 14, 'y' => 4, 'w' => 56, 'h' => 10),
            'school_address' => array('x' => 14, 'y' => 14, 'w' => 56, 'h' => 8),
            'title' => array('x' => 73, 'y' => 5, 'w' => 23, 'h' => 12),
            'photo' => array('x' => 4, 'y' => 28, 'w' => 20, 'h' => 44),
            'student_name' => array('x' => 27, 'y' => 28, 'w' => 41, 'h' => 9),
            'admission_no' => array('x' => 27, 'y' => 37, 'w' => 41, 'h' => 7),
            'class' => array('x' => 27, 'y' => 45, 'w' => 41, 'h' => 7),
            'father_name' => array('x' => 27, 'y' => 53, 'w' => 41, 'h' => 7),
            'mother_name' => array('x' => 27, 'y' => 61, 'w' => 41, 'h' => 7),
            'address' => array('x' => 27, 'y' => 69, 'w' => 41, 'h' => 12),
            'phone' => array('x' => 71, 'y' => 28, 'w' => 25, 'h' => 7),
            'dob' => array('x' => 71, 'y' => 36, 'w' => 25, 'h' => 7),
            'blood_group' => array('x' => 71, 'y' => 44, 'w' => 25, 'h' => 7),
            'signature' => array('x' => 58, 'y' => 82, 'w' => 18, 'h' => 10),
            'qr' => array('x' => 76, 'y' => 70, 'w' => 20, 'h' => 24),
        );
    }
}

if (!function_exists('get_id_card_layout_config')) {
    function get_id_card_layout_config($layout_json = '', $type = 'student', $portrait = true)
    {
        $defaults = get_default_id_card_layout($type, $portrait);
        $layout = $defaults;

        if (!empty($layout_json)) {
            $decoded = json_decode($layout_json, true);
            if (is_array($decoded)) {
                foreach ($defaults as $key => $default_box) {
                    if (isset($decoded[$key]) && is_array($decoded[$key])) {
                        $layout[$key] = array_merge($default_box, $decoded[$key]);
                    }
                }
            }
        }

        foreach ($layout as $key => $box) {
            foreach (array('x', 'y') as $axis) {
                $layout[$key][$axis] = max(0, min(100, (float) $box[$axis]));
            }
            foreach (array('w', 'h') as $axis) {
                $minimum = 4;
                if ($key === 'qr') {
                    $minimum = $axis === 'w' ? 18 : 12;
                }
                $layout[$key][$axis] = max($minimum, min(100, (float) $box[$axis]));
            }
        }

        return $layout;
    }
}

if (!function_exists('sanitize_id_card_layout_json')) {
    function sanitize_id_card_layout_json($layout_json = '', $type = 'student', $portrait = true)
    {
        return json_encode(get_id_card_layout_config($layout_json, $type, $portrait));
    }
}

if (!function_exists('get_id_card_box_style')) {
    function get_id_card_box_style($layout, $key)
    {
        if (!isset($layout[$key])) {
            return '';
        }

        $box = $layout[$key];
        return 'left:' . format_id_card_measurement($box['x']) . '%;top:' . format_id_card_measurement($box['y']) . '%;width:' . format_id_card_measurement($box['w']) . '%;height:' . format_id_card_measurement($box['h']) . '%;';
    }
}

if (!function_exists('get_staff_attendance_qr_hash')) {
    function get_staff_attendance_qr_hash($staff_id)
    {
        $staff_id = (int) $staff_id;
        $secret = (string) config_item('encryption_key');

        if ($secret === '') {
            $secret = (string) site_url();
        }

        return hash_hmac('sha256', 'staff-attendance|' . $staff_id, $secret);
    }
}

if (!function_exists('get_staff_attendance_qr_url')) {
    function get_staff_attendance_qr_url($staff_id)
    {
        $staff_id = (int) $staff_id;

        return site_url('qrattendance/staff/' . $staff_id . '/' . get_staff_attendance_qr_hash($staff_id));
    }
}

if (!function_exists('get_staff_attendance_qr_image_url')) {
    function get_staff_attendance_qr_image_url($staff_id, $size = 110)
    {
        $size = (int) $size;
        if ($size <= 0) {
            $size = 110;
        }

        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . rawurlencode(get_staff_attendance_qr_url($staff_id));
    }
}
