<?php
// Local browser fixture only: no application bootstrap, database, or production endpoints.
if (PHP_SAPI !== 'cli-server' || getenv('STUDIO_BROWSER_TESTS') !== '1') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 3);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (strpos($path, '/backend/') === 0 && is_file($root . $path)) { return false; }
require_once $root . '/application/libraries/Idcard_design.php';
$library = new Idcard_design();
session_start();
if ($path === '/fixture/save') {
    header('Content-Type: application/json');
    usleep(350000); // Exercise edits arriving during an in-flight save.
    if (isset($_SESSION['checksum']) && $_POST['expected_checksum'] !== $_SESSION['checksum']) {
        http_response_code(409); echo json_encode(array('message' => 'Draft changed. Reload before saving.')); exit;
    }
    try {
        $front = $library->validateDocument($_POST['front_json'], $_SESSION['subject'], $_POST['width_mm'], $_POST['height_mm'], 'front');
        $back = $library->validateDocument($_POST['back_json'], $_SESSION['subject'], $_POST['width_mm'], $_POST['height_mm'], 'back');
        $print = $library->validatePrintSettings($_POST['print_settings_json']);
        $_SESSION['checksum'] = $library->checksum($front, $back, $print);
        $_SESSION['saved'] = array('front' => $front, 'back' => $back);
        echo json_encode(array('status' => 'saved', 'checksum' => $_SESSION['checksum'], 'documents' => $_SESSION['saved']));
    } catch (InvalidArgumentException $error) {
        http_response_code(422); echo json_encode(array('message' => $error->getMessage()));
    }
    exit;
}
if ($path !== '/' && $path !== '/fixture' && $path !== '/fixture/runtime') { http_response_code(404); exit; }
function base_url($path) { return '/' . $path; }
function site_url($path) { return strpos($path, '/save/') !== false ? '/fixture/save' : '/fixture'; }
function html_escape($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
$subject_type = isset($_GET['staff']) ? 'staff' : 'student';
$_SESSION['subject'] = $subject_type;
$defaults = $library->defaultDocuments($subject_type);
$front = $defaults['front']; $back = $defaults['back'];
if (isset($_GET['empty'])) {
    $front = array('schemaVersion' => 2, 'side' => 'front', 'background' => array('type' => 'color', 'value' => '#ffffff'), 'objects' => array());
    $back = $front; $back['side'] = 'back';
}
if (isset($_GET['reload']) && isset($_SESSION['saved'])) {
    $front = $_SESSION['saved']['front']; $back = $_SESSION['saved']['back'];
}
$assets = $history = array();
$design = (object) array('id' => 1, 'title' => 'Browser fixture', 'width_mm' => 85.6, 'height_mm' => 53.98, 'published_version_id' => null);
$draft = (object) array('checksum' => isset($_SESSION['checksum']) ? $_SESSION['checksum'] : 'fixture', 'front_json' => json_encode($front), 'back_json' => json_encode($back), 'print_settings_json' => json_encode($defaults['printSettings']));
$bindings = $library->bindingDefinitions($subject_type);
$sample_data = array('school.name' => 'SchoolLift Academy', 'school.address' => 'Lagos, Nigeria', $subject_type . '.full_name' => 'Alexandra A Very Long Student Or Staff Full Name', 'attendance.credential' => 'FIXTURE-CREDENTIAL', 'student.admission_no' => '1001', 'staff.employee_id' => '1001');
$sample_image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAQAAAACCAIAAADwyuo0AAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAGUlEQVQImWN8KCfCwMBgLHeWgYGBiQEJAAAuZQI1CnjMwAAAAABJRU5ErkJggg==';
foreach (array('school.logo', 'school.signature', 'school.background', $subject_type . '.photo') as $binding) { $sample_data[$binding] = $sample_image; }
$studio_csrf = 'fixture';
if ($path === '/fixture/runtime') {
    $studio_assets = array();
    $studio_design = (object) array_merge((array) $design, (array) $draft);
    $studio_cards = array(array('bindings' => $sample_data), array('bindings' => array_merge($sample_data, array($subject_type . '.full_name' => str_repeat('Long Name ', 25)))));
    echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Runtime fixture</title></head><body>';
    include $root . '/application/views/admin/idcardstudio/runtime_cards.php';
    echo '</body></html>';
    exit;
}
?>
<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Studio browser fixture</title>
<link rel="stylesheet" href="/backend/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="/backend/dist/css/AdminLTE.min.css">
<link rel="stylesheet" href="/backend/dist/font-awesome-4.5.0/css/font-awesome.min.css">
<style>.content-wrapper{margin-left:0!important} .main-header{height:50px}</style>
</head><body><header class="main-header"></header>
<?php include $root . '/application/views/admin/idcardstudio/editor.php'; ?>
</body></html>
