<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Idcardstudio extends Admin_Controller
{
    private $csrfSessionKey = 'idcard_studio_csrf';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Idcardstudio_model');
        $this->load->library('Idcard_design');
        if (!$this->session->userdata($this->csrfSessionKey)) {
            $this->session->set_userdata($this->csrfSessionKey, bin2hex(random_bytes(32)));
        }
    }

    public function index($subjectType = 'student')
    {
        $subjectType = $this->subjectType($subjectType);
        $this->requirePrivilege($subjectType, 'can_view');
        $this->setMenu($subjectType);

        $data = array(
            'subject_type' => $subjectType,
            'templates' => $this->Idcardstudio_model->listTemplates($subjectType),
            'studio_ready' => $this->Idcardstudio_model->isReady(),
            'studio_csrf' => $this->session->userdata($this->csrfSessionKey),
        );
        $this->load->view('layout/header', $data);
        $this->load->view('admin/idcardstudio/index', $data);
        $this->load->view('layout/footer', $data);
    }

    public function convert($subjectType, $legacyId)
    {
        $subjectType = $this->subjectType($subjectType);
        $this->requirePrivilege($subjectType, 'can_edit');
        $this->requirePost();
        $this->requireCsrf();
        $this->requireReady();

        $legacy = $this->Idcardstudio_model->getLegacyTemplate($subjectType, $legacyId);
        if (!$legacy) {
            show_404();
        }
        $documents = $this->idcard_design->defaultDocuments($subjectType, $legacy);
        $design = $this->Idcardstudio_model->createFromLegacy(
            $subjectType,
            $legacy,
            $documents,
            (int) $this->customlib->getStaffID()
        );
        if (!$design) {
            $this->session->set_flashdata('msg', '<div class="alert alert-danger">The template could not be converted. No legacy data was changed.</div>');
            redirect('admin/idcardstudio/index/' . $subjectType);
        }
        redirect('admin/idcardstudio/editor/' . $subjectType . '/' . (int) $legacyId);
    }

    public function editor($subjectType, $legacyId)
    {
        $subjectType = $this->subjectType($subjectType);
        $this->requirePrivilege($subjectType, 'can_edit');
        $this->requireReady();
        $this->setMenu($subjectType);

        $legacy = $this->Idcardstudio_model->getLegacyTemplate($subjectType, $legacyId);
        $design = $this->Idcardstudio_model->getDesignForLegacy($subjectType, $legacyId);
        if (!$legacy || !$design) {
            $this->session->set_flashdata('msg', '<div class="alert alert-warning">Convert the legacy template before opening the Design Studio.</div>');
            redirect('admin/idcardstudio/index/' . $subjectType);
        }
        $draft = $this->Idcardstudio_model->getVersion($design->draft_version_id);
        if (!$draft) {
            show_error('The active ID card draft is missing. Restore the database backup or contact support.', 409);
        }

        $sampleData = $this->idcard_design->sampleData($subjectType, $legacy);
        $sampleData['school.logo'] = !empty($legacy->logo)
            ? site_url('admin/idcardstudio/legacy_asset/' . $subjectType . '/' . (int) $legacy->id . '/logo') : '';
        $sampleData['school.signature'] = !empty($legacy->sign_image)
            ? site_url('admin/idcardstudio/legacy_asset/' . $subjectType . '/' . (int) $legacy->id . '/sign_image') : '';
        $sampleData['school.background'] = !empty($legacy->background)
            ? site_url('admin/idcardstudio/legacy_asset/' . $subjectType . '/' . (int) $legacy->id . '/background') : '';

        $data = array(
            'subject_type' => $subjectType,
            'legacy' => $legacy,
            'design' => $design,
            'draft' => $draft,
            'history' => $this->Idcardstudio_model->history($design->id),
            'assets' => $this->Idcardstudio_model->listAssets($design->id),
            'bindings' => $this->idcard_design->bindingDefinitions($subjectType),
            'sample_data' => $sampleData,
            'studio_csrf' => $this->session->userdata($this->csrfSessionKey),
        );
        $this->load->view('layout/header', $data);
        $this->load->view('admin/idcardstudio/editor', $data);
        $this->load->view('layout/footer', $data);
    }

    public function save($designId)
    {
        $this->requirePost();
        $this->requireCsrf();
        $this->requireReady();
        $design = $this->Idcardstudio_model->getDesign($designId);
        if (!$design) {
            return $this->json(array('status' => 'error', 'message' => 'Design not found.'), 404);
        }
        $this->requirePrivilege($design->subject_type, 'can_edit');

        try {
            $payload = $this->validatedSavePayload($design);
        } catch (InvalidArgumentException $exception) {
            return $this->json(array('status' => 'error', 'message' => $exception->getMessage()), 422);
        }

        $result = $this->Idcardstudio_model->saveDraft(
            $design->id,
            $payload,
            (string) $this->input->post('expected_checksum'),
            (int) $this->customlib->getStaffID()
        );
        if ($result['status'] === 'conflict') {
            return $this->json(array(
                'status' => 'conflict',
                'message' => 'Another browser saved this draft. Reload it before making more changes.',
                'checksum' => $result['checksum'],
            ), 409);
        }
        if ($result['status'] !== 'saved') {
            return $this->json(array('status' => 'error', 'message' => 'The draft could not be saved.'), 409);
        }
        return $this->json($result);
    }

    public function publish($designId)
    {
        $this->requirePost();
        $this->requireCsrf();
        $this->requireReady();
        $design = $this->Idcardstudio_model->getDesign($designId);
        if (!$design) {
            return $this->json(array('status' => 'error', 'message' => 'Design not found.'), 404);
        }
        $this->requirePrivilege($design->subject_type, 'can_edit');
        $result = $this->Idcardstudio_model->publish(
            $design->id,
            (string) $this->input->post('expected_checksum'),
            (int) $this->customlib->getStaffID()
        );
        if ($result['status'] === 'conflict') {
            return $this->json(array(
                'status' => 'conflict',
                'message' => 'Save and reload the latest draft before publishing.',
                'checksum' => $result['checksum'],
            ), 409);
        }
        if ($result['status'] !== 'published') {
            return $this->json(array('status' => 'error', 'message' => 'The design could not be published.'), 409);
        }
        return $this->json($result);
    }

    public function use_legacy($designId)
    {
        $this->requirePost();
        $this->requireCsrf();
        $this->requireReady();
        $design = $this->Idcardstudio_model->getDesign($designId);
        if (!$design) {
            return $this->json(array('status' => 'error', 'message' => 'Design not found.'), 404);
        }
        $this->requirePrivilege($design->subject_type, 'can_edit');
        $result = $this->Idcardstudio_model->useLegacyRenderer(
            $design->id,
            (int) $this->input->post('expected_published_version_id'),
            (int) $this->customlib->getStaffID()
        );
        if ($result['status'] === 'conflict') {
            return $this->json(array(
                'status' => 'conflict',
                'message' => 'The published design changed in another browser. Reload before selecting the legacy renderer.',
                'published_version_id' => $result['published_version_id'],
            ), 409);
        }
        if ($result['status'] !== 'legacy') {
            return $this->json(array('status' => 'error', 'message' => 'The legacy renderer could not be restored.'), 409);
        }
        return $this->json(array(
            'status' => 'legacy',
            'message' => 'Future card generation will use the unchanged legacy template. Studio versions were retained.',
        ));
    }

    public function upload_asset($designId)
    {
        $this->requirePost();
        $this->requireCsrf();
        $this->requireReady();
        $design = $this->Idcardstudio_model->getDesign($designId);
        if (!$design) {
            return $this->json(array('status' => 'error', 'message' => 'Design not found.'), 404);
        }
        $this->requirePrivilege($design->subject_type, 'can_edit');

        if (!isset($_FILES['asset']) || (int) $_FILES['asset']['error'] !== UPLOAD_ERR_OK) {
            return $this->json(array('status' => 'error', 'message' => 'Choose a PNG or JPEG image to upload.'), 422);
        }
        $file = $_FILES['asset'];
        if ((int) $file['size'] < 1 || (int) $file['size'] > 5 * 1024 * 1024) {
            return $this->json(array('status' => 'error', 'message' => 'Studio images must be smaller than 5 MB.'), 422);
        }
        $image = @getimagesize($file['tmp_name']);
        $allowed = array('image/jpeg' => 'jpg', 'image/png' => 'png');
        if (!$image || !isset($allowed[$image['mime']])) {
            return $this->json(array('status' => 'error', 'message' => 'Only genuine PNG and JPEG images are accepted.'), 422);
        }

        $extension = $allowed[$image['mime']];
        $fileInfo = array('extension' => $extension);
        $fileName = 'studio-' . (int) $design->id . '-' . bin2hex(random_bytes(12));
        $upload = upload_to_s3($file['tmp_name'], $fileInfo, $fileName, 'uploads/id_card_studio/');
        if (empty($upload['success']) || empty($upload['s3_key'])) {
            return $this->json(array('status' => 'error', 'message' => 'The image could not be stored. Try again.'), 500);
        }

        $assetId = $this->Idcardstudio_model->addAsset(array(
            'design_id' => (int) $design->id,
            'storage_key' => (string) $upload['s3_key'],
            'original_name' => $this->safeFilename($file['name']),
            'mime_type' => $image['mime'],
            'byte_size' => (int) $file['size'],
            'pixel_width' => (int) $image[0],
            'pixel_height' => (int) $image[1],
            'sha256' => hash_file('sha256', $file['tmp_name']),
            'created_by' => (int) $this->customlib->getStaffID() ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ));
        $this->Idcardstudio_model->audit($design->id, $design->draft_version_id, (int) $this->customlib->getStaffID(), 'upload_asset', array('asset_id' => $assetId));

        return $this->json(array(
            'status' => 'uploaded',
            'asset' => array(
                'id' => $assetId,
                'name' => $this->safeFilename($file['name']),
                'mime' => $image['mime'],
                'url' => site_url('admin/idcardstudio/asset/' . $assetId),
            ),
        ));
    }

    /** Same-origin image stream used by Fabric exports to keep the canvas clean. */
    public function asset($assetId)
    {
        $this->requireReady();
        $asset = $this->Idcardstudio_model->getAsset($assetId);
        if (!$asset) {
            show_404();
        }
        $design = $this->Idcardstudio_model->getDesign($asset->design_id);
        if (!$design) {
            show_404();
        }
        $this->requireViewPrivilege($design->subject_type);

        $url = get_school_asset_url($asset->storage_key);
        $this->streamImageUrl($url, $asset->mime_type);
    }

    public function legacy_asset($subjectType, $legacyId, $field)
    {
        $subjectType = $this->subjectType($subjectType);
        $this->requireViewPrivilege($subjectType);
        $allowed = array('background', 'logo', 'sign_image');
        if (!in_array($field, $allowed, true) || !ctype_digit((string) $legacyId)) {
            show_404();
        }
        $legacy = $this->Idcardstudio_model->getLegacyTemplate($subjectType, $legacyId);
        if (!$legacy || empty($legacy->{$field})) {
            show_404();
        }
        $baseDirectory = 'uploads/' . ($subjectType === 'staff' ? 'staff_id_card' : 'student_id_card') . '/'
            . ($field === 'sign_image' ? 'signature' : $field);
        $this->streamImageUrl(get_school_asset_url($legacy->{$field}, $baseDirectory));
    }

    public function subject_photo($subjectType, $subjectId)
    {
        $subjectType = $this->subjectType($subjectType);
        $this->requireViewPrivilege($subjectType);
        if (!ctype_digit((string) $subjectId)) {
            show_404();
        }
        $table = $subjectType === 'staff' ? 'staff' : 'students';
        $record = $this->db->select('image')->where('id', (int) $subjectId)->get($table)->row();
        if (!$record || empty($record->image)) {
            show_404();
        }
        $directory = $subjectType === 'staff' ? 'uploads/staff_images' : 'uploads/student_images';
        $this->streamImageUrl(get_school_asset_url($record->image, $directory));
    }

    private function streamImageUrl($url, $knownMime = null)
    {
        $parts = parse_url($url);
        $allowedHosts = array('schoollift.s3.us-east-2.amazonaws.com', (string) $this->input->server('HTTP_HOST'));
        $host = isset($parts['host']) ? strtolower($parts['host']) : '';
        $hostWithoutPort = preg_replace('/:\d+$/', '', $host);
        $requestHost = preg_replace('/:\d+$/', '', strtolower((string) $this->input->server('HTTP_HOST')));
        if (!in_array($hostWithoutPort, array($allowedHosts[0], $requestHost), true)) {
            show_error('Asset location is not permitted.', 403);
        }

        $content = $this->fetchAsset($url);
        if ($content === false) {
            show_404();
        }
        $mime = $knownMime;
        if (!$mime || strpos($mime, 'image/') !== 0) {
            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
            $mime = $finfo ? finfo_buffer($finfo, $content) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
        }
        if (!in_array($mime, array('image/jpeg', 'image/png', 'image/gif', 'image/webp'), true)) {
            show_error('Stored asset is not a supported image.', 415);
        }
        $this->output
            ->set_content_type($mime)
            ->set_header('Cache-Control: private, max-age=300')
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_output($content);
    }

    private function validatedSavePayload($design)
    {
        $title = trim(strip_tags((string) $this->input->post('title')));
        if ($title === '' || strlen($title) > 191) {
            throw new InvalidArgumentException('Design title is required and must be 191 characters or fewer.');
        }
        $width = (float) $this->input->post('width_mm');
        $height = (float) $this->input->post('height_mm');
        $front = $this->idcard_design->validateDocument($this->input->post('front_json'), $design->subject_type, $width, $height, 'front');
        $back = $this->idcard_design->validateDocument($this->input->post('back_json'), $design->subject_type, $width, $height, 'back');
        $print = $this->idcard_design->validatePrintSettings($this->input->post('print_settings_json'));
        $frontJson = $this->idcard_design->encodeCanonical($front);
        $backJson = $this->idcard_design->encodeCanonical($back);
        $printJson = $this->idcard_design->encodeCanonical($print);

        return array(
            'title' => $title,
            'width_mm' => round($width, 3),
            'height_mm' => round($height, 3),
            'orientation' => $height > $width ? 'portrait' : 'landscape',
            'front_json' => $frontJson,
            'back_json' => $backJson,
            'print_settings_json' => $printJson,
            'checksum' => $this->idcard_design->checksum($front, $back, $print),
        );
    }

    private function subjectType($subjectType)
    {
        if (!in_array($subjectType, array('student', 'staff'), true)) {
            show_404();
        }
        return $subjectType;
    }

    private function requirePrivilege($subjectType, $action)
    {
        $privilege = $subjectType === 'staff' ? 'staff_id_card' : 'student_id_card';
        if (!$this->rbac->hasPrivilege($privilege, $action)) {
            access_denied();
        }
    }

    private function requireViewPrivilege($subjectType)
    {
        $templatePrivilege = $subjectType === 'staff' ? 'staff_id_card' : 'student_id_card';
        $generationPrivilege = $subjectType === 'staff' ? 'generate_staff_id_card' : 'generate_id_card';
        if (!$this->rbac->hasPrivilege($templatePrivilege, 'can_view')
            && !$this->rbac->hasPrivilege($generationPrivilege, 'can_view')) {
            access_denied();
        }
    }

    private function requireReady()
    {
        if (!$this->Idcardstudio_model->isReady()) {
            show_error('ID Card Design Studio migration 131 has not been installed.', 503);
        }
    }

    private function requirePost()
    {
        if (strtoupper((string) $this->input->server('REQUEST_METHOD')) !== 'POST') {
            show_error('This operation accepts POST requests only.', 405);
        }
    }

    private function requireCsrf()
    {
        $expected = (string) $this->session->userdata($this->csrfSessionKey);
        $received = (string) $this->input->post('studio_csrf');
        if ($expected === '' || $received === '' || !hash_equals($expected, $received)) {
            show_error('The ID Card Studio form expired or failed its security check. Reload the page and try again.', 403);
        }
    }

    private function json(array $payload, $status = 200)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function safeFilename($filename)
    {
        $filename = preg_replace('/[^A-Za-z0-9._ -]/', '', basename((string) $filename));
        return substr($filename !== '' ? $filename : 'studio-image', 0, 191);
    }

    private function fetchAsset($url)
    {
        $parts = parse_url($url);
        $requestHost = preg_replace('/:\d+$/', '', strtolower((string) $this->input->server('HTTP_HOST')));
        $assetHost = isset($parts['host']) ? preg_replace('/:\d+$/', '', strtolower($parts['host'])) : '';
        if ($requestHost !== '' && $assetHost === $requestHost && !empty($parts['path'])) {
            $basePath = (string) parse_url(base_url(), PHP_URL_PATH);
            $relativePath = ltrim($parts['path'], '/');
            $normalizedBasePath = trim($basePath, '/');
            if ($normalizedBasePath !== '' && strpos($relativePath, $normalizedBasePath . '/') === 0) {
                $relativePath = substr($relativePath, strlen($normalizedBasePath) + 1);
            }
            $root = realpath(FCPATH);
            $candidate = realpath(FCPATH . str_replace(array('../', '..\\'), '', $relativePath));
            if ($root && $candidate && strpos($candidate, $root . DIRECTORY_SEPARATOR) === 0 && is_file($candidate)) {
                return @file_get_contents($candidate);
            }
            return false;
        }
        if (!function_exists('curl_init')) {
            return @file_get_contents($url);
        }
        $curl = curl_init($url);
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ));
        $content = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return $status >= 200 && $status < 300 ? $content : false;
    }

    private function setMenu($subjectType)
    {
        $this->session->set_userdata('top_menu', 'Certificate');
        $this->session->set_userdata('sub_menu', 'admin/idcardstudio/index/' . $subjectType);
    }
}
