<?php

defined('BASEPATH') OR define('BASEPATH', __DIR__);

/**
 * Canonical ID-card design schema, conversion and validation.
 *
 * This class deliberately has no CodeIgniter dependency so the same rules can
 * be exercised by the command-line regression tests and by future renderers.
 */
class Idcard_design
{
    const SCHEMA_VERSION = 1;
    const MAX_DOCUMENT_BYTES = 262144;
    const MAX_OBJECTS = 150;
    const MIN_CARD_MM = 40.0;
    const MAX_CARD_MM = 220.0;

    private $fontFamilies = array('Arial', 'Helvetica', 'Times New Roman', 'Georgia', 'Verdana', 'Courier New');
    private $objectTypes = array('text', 'rect', 'ellipse', 'line', 'image', 'qr', 'barcode');

    public function subjectTypes()
    {
        return array('student', 'staff');
    }

    public function bindingDefinitions($subjectType)
    {
        $common = array(
            'school.name' => 'School name',
            'school.address' => 'School address',
            'school.logo' => 'School logo',
            'school.signature' => 'Authorised signature',
            'school.background' => 'Legacy card background',
            'card.title' => 'Card title',
            'attendance.credential' => 'Trusted attendance credential',
        );

        $student = array(
            'student.full_name' => 'Student name',
            'student.admission_no' => 'Admission number',
            'student.class_section' => 'Class and section',
            'student.father_name' => 'Father name',
            'student.mother_name' => 'Mother name',
            'student.address' => 'Address',
            'student.phone' => 'Phone',
            'student.dob' => 'Date of birth',
            'student.blood_group' => 'Blood group',
            'student.photo' => 'Student photograph',
        );

        $staff = array(
            'staff.full_name' => 'Staff name',
            'staff.employee_id' => 'Employee ID',
            'staff.role' => 'Role',
            'staff.department' => 'Department',
            'staff.designation' => 'Designation',
            'staff.father_name' => 'Father name',
            'staff.mother_name' => 'Mother name',
            'staff.joining_date' => 'Date of joining',
            'staff.address' => 'Address',
            'staff.phone' => 'Phone',
            'staff.dob' => 'Date of birth',
            'staff.photo' => 'Staff photograph',
        );

        return array_merge($common, $subjectType === 'staff' ? $staff : $student);
    }

    public function sampleData($subjectType, $legacy = null)
    {
        $schoolName = $this->legacyValue($legacy, 'school_name', 'Your School Name');
        $schoolAddress = $this->legacyValue($legacy, 'school_address', 'School address, Nigeria');
        $cardTitle = $this->legacyValue($legacy, 'title', $subjectType === 'staff' ? 'STAFF ID CARD' : 'STUDENT ID CARD');

        $data = array(
            'school.name' => $schoolName,
            'school.address' => $schoolAddress,
            'school.logo' => '',
            'school.signature' => '',
            'school.background' => '',
            'card.title' => $cardTitle,
            'attendance.credential' => 'SL-DEMO-CREDENTIAL',
        );

        if ($subjectType === 'staff') {
            return array_merge($data, array(
                'staff.full_name' => 'Amina Okafor',
                'staff.employee_id' => 'STF-0001',
                'staff.role' => 'Teacher',
                'staff.department' => 'Academics',
                'staff.designation' => 'Senior Teacher',
                'staff.father_name' => 'Chinedu Okafor',
                'staff.mother_name' => 'Ngozi Okafor',
                'staff.joining_date' => '12 September 2022',
                'staff.address' => '12 School Road, Lagos',
                'staff.phone' => '0800 000 0000',
                'staff.dob' => '14 February 1990',
                'staff.photo' => '',
            ));
        }

        return array_merge($data, array(
            'student.full_name' => 'Chidera Adeyemi',
            'student.admission_no' => 'ADM-0001',
            'student.class_section' => 'JSS 2 - A',
            'student.father_name' => 'Tunde Adeyemi',
            'student.mother_name' => 'Ngozi Adeyemi',
            'student.address' => '12 School Road, Lagos',
            'student.phone' => '0800 000 0000',
            'student.dob' => '25 June 2012',
            'student.blood_group' => 'O+',
            'student.photo' => '',
        ));
    }

    public function dimensionsFromLegacy($legacy)
    {
        $portrait = (int) $this->legacyValue($legacy, 'enable_vertical_card', 0) === 1;
        $hasStoredDimensions = $this->legacyHas($legacy, 'card_width')
            && $this->legacyHas($legacy, 'card_height')
            && (float) $this->legacyValue($legacy, 'card_width', 0) > 0
            && (float) $this->legacyValue($legacy, 'card_height', 0) > 0;

        if (!$hasStoredDimensions) {
            return array(
                'width_mm' => $portrait ? 53.98 : 85.60,
                'height_mm' => $portrait ? 85.60 : 53.98,
                'orientation' => $portrait ? 'portrait' : 'landscape',
            );
        }

        $unit = strtolower((string) $this->legacyValue($legacy, 'card_unit', 'mm'));
        $width = $this->toMillimetres((float) $this->legacyValue($legacy, 'card_width', 85.60), $unit);
        $height = $this->toMillimetres((float) $this->legacyValue($legacy, 'card_height', 53.98), $unit);

        return array(
            'width_mm' => round($width, 3),
            'height_mm' => round($height, 3),
            'orientation' => $height > $width ? 'portrait' : 'landscape',
        );
    }

    public function defaultDocuments($subjectType, $legacy = null)
    {
        $this->assertSubjectType($subjectType);
        $dimensions = $this->dimensionsFromLegacy($legacy);
        $width = $dimensions['width_mm'];
        $height = $dimensions['height_mm'];
        $portrait = $height > $width;
        $headerColor = $this->safeColor($this->legacyValue($legacy, 'header_color', '#1f3c88'), '#1f3c88');

        $front = array(
            'schemaVersion' => self::SCHEMA_VERSION,
            'side' => 'front',
            'background' => !empty($this->legacyValue($legacy, 'background', ''))
                ? array('type' => 'binding', 'binding' => 'school.background', 'fit' => 'cover')
                : array('type' => 'color', 'value' => '#ffffff'),
            'objects' => array(),
        );

        $front['objects'][] = $this->shape('header', 'rect', 0, 0, $width, $height * 0.23, $headerColor, $headerColor, 0);
        $front['objects'][] = $this->imageBinding('school-logo', 'school.logo', 3, 3, 10, 10);
        $front['objects'][] = $this->bindingText('school-name', 'school.name', 15, 4, $width - 18, 6, $portrait ? 3.3 : 4.0, '#ffffff', 'bold', 'center');
        $front['objects'][] = $this->bindingText('school-address', 'school.address', 5, 11, $width - 10, 5, 2.0, '#ffffff', 'normal', 'center');
        $front['objects'][] = $this->bindingText('card-title', 'card.title', 5, $height * 0.19, $width - 10, 5, 2.6, '#ffffff', 'bold', 'center');

        $photoX = $portrait ? ($width - 24) / 2 : 5;
        $photoY = $portrait ? $height * 0.28 : $height * 0.31;
        $front['objects'][] = $this->imageBinding('photo', $subjectType . '.photo', $photoX, $photoY, 24, 27);

        if ($portrait) {
            $textX = 4;
            $textY = $photoY + 30;
            $textWidth = $width - 8;
        } else {
            $textX = 32;
            $textY = $height * 0.31;
            $textWidth = $width - 36;
        }

        $bindings = $subjectType === 'staff'
            ? array('staff.full_name', 'staff.employee_id', 'staff.designation', 'staff.department')
            : array('student.full_name', 'student.admission_no', 'student.class_section', 'student.blood_group');
        $labels = $subjectType === 'staff'
            ? array('Name', 'Staff ID', 'Designation', 'Department')
            : array('Name', 'Admission No.', 'Class', 'Blood Group');

        foreach ($bindings as $index => $binding) {
            $front['objects'][] = $this->bindingText(
                'field-' . ($index + 1),
                $binding,
                $textX,
                $textY + ($index * 5.2),
                $textWidth,
                4.6,
                $index === 0 ? 3.2 : 2.4,
                '#111827',
                $index === 0 ? 'bold' : 'normal',
                $portrait ? 'center' : 'left',
                $labels[$index] . ': '
            );
        }

        $front['objects'][] = array(
            'id' => 'attendance-qr',
            'type' => 'qr',
            'binding' => 'attendance.credential',
            'x' => $width - 19,
            'y' => $height - 18,
            'width' => 15,
            'height' => 15,
            'rotation' => 0,
            'opacity' => 1,
            'visible' => true,
            'locked' => false,
            'foreground' => '#111827',
            'background' => '#ffffff',
            'label' => '',
        );

        $back = array(
            'schemaVersion' => self::SCHEMA_VERSION,
            'side' => 'back',
            'background' => array('type' => 'color', 'value' => '#f8fafc'),
            'objects' => array(
                $this->shape('back-header', 'rect', 0, 0, $width, 8, $headerColor, $headerColor, 0),
                $this->bindingText('back-school-name', 'school.name', 5, 11, $width - 10, 7, 3.2, '#111827', 'bold', 'center'),
                $this->staticText('terms', 'This card remains the property of the school. If found, please return it to the school office.', 7, 22, $width - 14, 14, 2.4, '#334155', 'center'),
                $this->imageBinding('signature', 'school.signature', 8, $height - 16, 25, 9),
                $this->bindingText('back-address', 'school.address', 36, $height - 16, $width - 42, 10, 2.0, '#475569', 'normal', 'right'),
            ),
        );

        $front = $this->applyLegacyPercentageLayout($front, $subjectType, $legacy, $width, $height);

        return array(
            'dimensions' => $dimensions,
            'front' => $front,
            'back' => $back,
            'printSettings' => $this->defaultPrintSettings(),
        );
    }

    public function defaultPrintSettings()
    {
        return array(
            'schemaVersion' => self::SCHEMA_VERSION,
            'paper' => 'a4',
            'orientation' => 'portrait',
            'marginMm' => 8,
            'gapMm' => 4,
            'cropMarks' => true,
            'duplex' => false,
            'flip' => 'long-edge',
            'dpi' => 300,
            'maxCardsPerPart' => 100,
        );
    }

    public function validateDocument($document, $subjectType, $widthMm, $heightMm, $expectedSide = null)
    {
        $this->assertSubjectType($subjectType);
        $widthMm = $this->validateDimension($widthMm, 'width');
        $heightMm = $this->validateDimension($heightMm, 'height');
        $data = $this->decodeObject($document, 'design document');

        if ((int) $this->value($data, 'schemaVersion', 0) !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported ID card design schema version.');
        }

        $side = (string) $this->value($data, 'side', '');
        if (!in_array($side, array('front', 'back'), true) || ($expectedSide !== null && $side !== $expectedSide)) {
            throw new InvalidArgumentException('The design side must be front or back.');
        }

        $background = $this->validateBackground($this->value($data, 'background', array('type' => 'color', 'value' => '#ffffff')));
        $objects = $this->value($data, 'objects', array());
        if (!is_array($objects) || count($objects) > self::MAX_OBJECTS) {
            throw new InvalidArgumentException('A card side may contain no more than ' . self::MAX_OBJECTS . ' objects.');
        }

        $validated = array();
        $ids = array();
        foreach ($objects as $object) {
            $item = $this->validateObject($object, $subjectType, $widthMm, $heightMm);
            if (isset($ids[$item['id']])) {
                throw new InvalidArgumentException('Object IDs must be unique within a card side.');
            }
            $ids[$item['id']] = true;
            $validated[] = $item;
        }

        return array(
            'schemaVersion' => self::SCHEMA_VERSION,
            'side' => $side,
            'background' => $background,
            'objects' => $validated,
        );
    }

    public function validatePrintSettings($settings)
    {
        $data = $this->decodeObject($settings, 'print settings');
        $paper = (string) $this->value($data, 'paper', 'a4');
        if (!in_array($paper, array('a4', 'card'), true)) {
            throw new InvalidArgumentException('Print paper must be A4 or exact card size.');
        }

        $orientation = (string) $this->value($data, 'orientation', 'portrait');
        if (!in_array($orientation, array('portrait', 'landscape'), true)) {
            throw new InvalidArgumentException('Print orientation is invalid.');
        }

        $flip = (string) $this->value($data, 'flip', 'long-edge');
        if (!in_array($flip, array('long-edge', 'short-edge'), true)) {
            throw new InvalidArgumentException('Duplex flip mode is invalid.');
        }

        return array(
            'schemaVersion' => self::SCHEMA_VERSION,
            'paper' => $paper,
            'orientation' => $orientation,
            'marginMm' => $this->numberBetween($this->value($data, 'marginMm', 8), 0, 30, 'Print margin'),
            'gapMm' => $this->numberBetween($this->value($data, 'gapMm', 4), 0, 30, 'Card gap'),
            'cropMarks' => (bool) $this->value($data, 'cropMarks', true),
            'duplex' => (bool) $this->value($data, 'duplex', false),
            'flip' => $flip,
            'dpi' => (int) $this->numberBetween($this->value($data, 'dpi', 300), 150, 600, 'DPI'),
            'maxCardsPerPart' => (int) $this->numberBetween($this->value($data, 'maxCardsPerPart', 100), 1, 250, 'Cards per part'),
        );
    }

    public function encodeCanonical($value)
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false || strlen($encoded) > self::MAX_DOCUMENT_BYTES) {
            throw new InvalidArgumentException('The ID card design is too large to save.');
        }
        return $encoded;
    }

    public function checksum($front, $back, $printSettings)
    {
        return hash('sha256', $this->encodeCanonical(array(
            'front' => $front,
            'back' => $back,
            'printSettings' => $printSettings,
        )));
    }

    private function validateObject($object, $subjectType, $cardWidth, $cardHeight)
    {
        if (!is_array($object)) {
            throw new InvalidArgumentException('Every design object must be a JSON object.');
        }

        $id = (string) $this->value($object, 'id', '');
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $id)) {
            throw new InvalidArgumentException('Every design object requires a safe, stable ID.');
        }

        $type = (string) $this->value($object, 'type', '');
        if (!in_array($type, $this->objectTypes, true)) {
            throw new InvalidArgumentException('Unsupported design object type: ' . $type);
        }

        $minimum = in_array($type, array('line'), true) ? 0.1 : 0.5;
        $x = $this->numberBetween($this->value($object, 'x', 0), 0, $cardWidth, 'Object X position');
        $y = $this->numberBetween($this->value($object, 'y', 0), 0, $cardHeight, 'Object Y position');
        $width = $this->numberBetween($this->value($object, 'width', 10), $minimum, $cardWidth, 'Object width');
        $height = $this->numberBetween($this->value($object, 'height', 5), $minimum, $cardHeight, 'Object height');
        if ($x + $width > $cardWidth + 0.01 || $y + $height > $cardHeight + 0.01) {
            throw new InvalidArgumentException('Design objects must remain inside the card boundary.');
        }

        $validated = array(
            'id' => $id,
            'type' => $type,
            'x' => round($x, 3),
            'y' => round($y, 3),
            'width' => round($width, 3),
            'height' => round($height, 3),
            'rotation' => round($this->numberBetween($this->value($object, 'rotation', 0), -360, 360, 'Rotation'), 2),
            'opacity' => round($this->numberBetween($this->value($object, 'opacity', 1), 0, 1, 'Opacity'), 3),
            'visible' => (bool) $this->value($object, 'visible', true),
            'locked' => (bool) $this->value($object, 'locked', false),
        );
        $group = (string) $this->value($object, 'group', '');
        if ($group !== '' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $group)) {
            throw new InvalidArgumentException('Object group identifiers must be safe and stable.');
        }
        $validated['group'] = $group;

        if ($type === 'text') {
            $binding = (string) $this->value($object, 'binding', '');
            if ($binding !== '' && !array_key_exists($binding, $this->bindingDefinitions($subjectType))) {
                throw new InvalidArgumentException('Unsupported dynamic field: ' . $binding);
            }
            $validated['binding'] = $binding;
            $validated['text'] = $this->plainText($this->value($object, 'text', ''), 500, 'Text');
            $validated['prefix'] = $this->plainText($this->value($object, 'prefix', ''), 80, 'Text prefix');
            $validated['suffix'] = $this->plainText($this->value($object, 'suffix', ''), 80, 'Text suffix');
            $fontFamily = (string) $this->value($object, 'fontFamily', 'Arial');
            $validated['fontFamily'] = in_array($fontFamily, $this->fontFamilies, true) ? $fontFamily : 'Arial';
            $validated['fontSize'] = round($this->numberBetween($this->value($object, 'fontSize', 3), 1.5, 20, 'Font size'), 2);
            $validated['fontWeight'] = in_array((string) $this->value($object, 'fontWeight', 'normal'), array('normal', 'bold'), true)
                ? (string) $this->value($object, 'fontWeight', 'normal') : 'normal';
            $validated['fontStyle'] = in_array((string) $this->value($object, 'fontStyle', 'normal'), array('normal', 'italic'), true)
                ? (string) $this->value($object, 'fontStyle', 'normal') : 'normal';
            $validated['align'] = in_array((string) $this->value($object, 'align', 'left'), array('left', 'center', 'right'), true)
                ? (string) $this->value($object, 'align', 'left') : 'left';
            $validated['fill'] = $this->safeColor($this->value($object, 'fill', '#111827'), '#111827');
            $validated['lineHeight'] = round($this->numberBetween($this->value($object, 'lineHeight', 1.16), 0.7, 3, 'Line height'), 2);
            $validated['charSpacing'] = (int) $this->numberBetween($this->value($object, 'charSpacing', 0), -200, 1000, 'Letter spacing');
        } elseif (in_array($type, array('rect', 'ellipse', 'line'), true)) {
            $validated['fill'] = $type === 'line' ? 'transparent' : $this->safeColor($this->value($object, 'fill', '#e2e8f0'), '#e2e8f0', true);
            $validated['stroke'] = $this->safeColor($this->value($object, 'stroke', '#64748b'), '#64748b', true);
            $validated['strokeWidth'] = round($this->numberBetween($this->value($object, 'strokeWidth', 0.25), 0, 5, 'Stroke width'), 2);
            $validated['radius'] = round($this->numberBetween($this->value($object, 'radius', 0), 0, 30, 'Corner radius'), 2);
        } elseif ($type === 'image') {
            $binding = (string) $this->value($object, 'binding', '');
            $assetId = (int) $this->value($object, 'assetId', 0);
            $imageBindings = array('school.logo', 'school.signature', 'school.background', $subjectType . '.photo');
            if ($assetId < 1 && !in_array($binding, $imageBindings, true)) {
                throw new InvalidArgumentException('Images must use an uploaded studio asset or an allowed image field.');
            }
            $validated['assetId'] = $assetId > 0 ? $assetId : null;
            $validated['binding'] = $assetId > 0 ? '' : $binding;
            $validated['fit'] = in_array((string) $this->value($object, 'fit', 'cover'), array('cover', 'contain', 'fill'), true)
                ? (string) $this->value($object, 'fit', 'cover') : 'cover';
            $validated['radius'] = round($this->numberBetween($this->value($object, 'radius', 0), 0, 50, 'Image corner radius'), 2);
            $validated['stroke'] = $this->safeColor($this->value($object, 'stroke', 'transparent'), 'transparent', true);
            $validated['strokeWidth'] = round($this->numberBetween($this->value($object, 'strokeWidth', 0), 0, 5, 'Image border width'), 2);
        } else {
            $binding = (string) $this->value($object, 'binding', 'attendance.credential');
            $allowedCodeBindings = array('attendance.credential');
            if ($type === 'barcode') {
                $allowedCodeBindings[] = $subjectType === 'student' ? 'student.admission_no' : 'staff.employee_id';
            }
            if (!in_array($binding, $allowedCodeBindings, true)) {
                throw new InvalidArgumentException('QR and barcode objects must use a trusted credential or ID binding.');
            }
            $validated['binding'] = $binding;
            $validated['foreground'] = $this->safeColor($this->value($object, 'foreground', '#111827'), '#111827');
            $validated['background'] = $this->safeColor($this->value($object, 'background', '#ffffff'), '#ffffff');
            $validated['label'] = $this->plainText($this->value($object, 'label', ''), 80, 'Code label');
        }

        return $validated;
    }

    private function validateBackground($background)
    {
        if (!is_array($background)) {
            throw new InvalidArgumentException('Card background must be an object.');
        }
        $type = (string) $this->value($background, 'type', 'color');
        if ($type === 'color') {
            return array('type' => 'color', 'value' => $this->safeColor($this->value($background, 'value', '#ffffff'), '#ffffff'));
        }
        if ($type === 'asset' && (int) $this->value($background, 'assetId', 0) > 0) {
            return array('type' => 'asset', 'assetId' => (int) $background['assetId'], 'fit' => 'cover');
        }
        if ($type === 'binding' && (string) $this->value($background, 'binding', '') === 'school.background') {
            return array('type' => 'binding', 'binding' => 'school.background', 'fit' => 'cover');
        }
        throw new InvalidArgumentException('Backgrounds must be a safe colour or an uploaded studio asset.');
    }

    private function decodeObject($value, $label)
    {
        if (is_string($value)) {
            if (strlen($value) > self::MAX_DOCUMENT_BYTES) {
                throw new InvalidArgumentException(ucfirst($label) . ' is too large.');
            }
            $value = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException(ucfirst($label) . ' contains invalid JSON.');
            }
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException(ucfirst($label) . ' must be a JSON object.');
        }
        return $value;
    }

    private function shape($id, $type, $x, $y, $width, $height, $fill, $stroke, $strokeWidth)
    {
        return array(
            'id' => $id, 'type' => $type, 'x' => $x, 'y' => $y,
            'width' => $width, 'height' => $height, 'rotation' => 0,
            'opacity' => 1, 'visible' => true, 'locked' => false,
            'fill' => $fill, 'stroke' => $stroke, 'strokeWidth' => $strokeWidth, 'radius' => 0,
        );
    }

    private function bindingText($id, $binding, $x, $y, $width, $height, $fontSize, $fill, $weight, $align, $prefix = '')
    {
        return array(
            'id' => $id, 'type' => 'text', 'binding' => $binding, 'text' => '',
            'prefix' => $prefix, 'suffix' => '', 'x' => $x, 'y' => $y,
            'width' => $width, 'height' => $height, 'rotation' => 0,
            'opacity' => 1, 'visible' => true, 'locked' => false,
            'fontFamily' => 'Arial', 'fontSize' => $fontSize, 'fontWeight' => $weight,
            'fontStyle' => 'normal', 'align' => $align, 'fill' => $fill,
            'lineHeight' => 1.16, 'charSpacing' => 0,
        );
    }

    private function staticText($id, $text, $x, $y, $width, $height, $fontSize, $fill, $align)
    {
        $object = $this->bindingText($id, '', $x, $y, $width, $height, $fontSize, $fill, 'normal', $align);
        $object['text'] = $text;
        return $object;
    }

    private function imageBinding($id, $binding, $x, $y, $width, $height)
    {
        return array(
            'id' => $id, 'type' => 'image', 'binding' => $binding, 'assetId' => null,
            'x' => $x, 'y' => $y, 'width' => $width, 'height' => $height,
            'rotation' => 0, 'opacity' => 1, 'visible' => true, 'locked' => false,
            'fit' => 'cover', 'radius' => 2, 'stroke' => '#cbd5e1', 'strokeWidth' => 0.25,
        );
    }

    private function applyLegacyPercentageLayout(array $document, $subjectType, $legacy, $width, $height)
    {
        $raw = $this->legacyValue($legacy, 'layout_json', '');
        if (!is_string($raw) || trim($raw) === '') {
            return $document;
        }
        $layout = json_decode($raw, true);
        if (!is_array($layout)) {
            return $document;
        }

        $bindingMap = $subjectType === 'staff' ? array(
            'name' => 'staff.full_name',
            'staff_id' => 'staff.employee_id',
            'designation' => 'staff.designation',
            'department' => 'staff.department',
            'father_name' => 'staff.father_name',
            'mother_name' => 'staff.mother_name',
            'date_of_joining' => 'staff.joining_date',
            'phone' => 'staff.phone',
            'dob' => 'staff.dob',
            'address' => 'staff.address',
        ) : array(
            'student_name' => 'student.full_name',
            'admission_no' => 'student.admission_no',
            'class' => 'student.class_section',
            'father_name' => 'student.father_name',
            'mother_name' => 'student.mother_name',
            'phone' => 'student.phone',
            'dob' => 'student.dob',
            'blood_group' => 'student.blood_group',
            'address' => 'student.address',
        );

        foreach ($document['objects'] as &$object) {
            $layoutKey = null;
            if (!empty($object['binding'])) {
                $layoutKey = array_search($object['binding'], $bindingMap, true);
                if ($object['binding'] === 'school.logo') {
                    $layoutKey = 'logo';
                } elseif ($object['binding'] === 'school.name') {
                    $layoutKey = 'school_name';
                } elseif ($object['binding'] === 'school.address') {
                    $layoutKey = 'school_address';
                } elseif ($object['binding'] === 'card.title') {
                    $layoutKey = 'title';
                } elseif ($object['binding'] === $subjectType . '.photo') {
                    $layoutKey = 'photo';
                } elseif ($object['binding'] === 'attendance.credential') {
                    $layoutKey = 'qr';
                }
            }
            if (!$layoutKey || empty($layout[$layoutKey]) || !is_array($layout[$layoutKey])) {
                continue;
            }
            $box = $layout[$layoutKey];
            if (!isset($box['x'], $box['y'], $box['w'], $box['h'])) {
                continue;
            }
            $x = max(0, min(100, (float) $box['x']));
            $y = max(0, min(100, (float) $box['y']));
            $w = max(0.5, min(100 - $x, (float) $box['w']));
            $h = max(0.5, min(100 - $y, (float) $box['h']));
            $object['x'] = round($x * $width / 100, 3);
            $object['y'] = round($y * $height / 100, 3);
            $object['width'] = round($w * $width / 100, 3);
            $object['height'] = round($h * $height / 100, 3);
        }
        unset($object);
        return $document;
    }

    private function validateDimension($value, $label)
    {
        return $this->numberBetween($value, self::MIN_CARD_MM, self::MAX_CARD_MM, 'Card ' . $label);
    }

    private function numberBetween($value, $minimum, $maximum, $label)
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException($label . ' must be numeric.');
        }
        $number = (float) $value;
        if (!is_finite($number) || $number < $minimum || $number > $maximum) {
            throw new InvalidArgumentException($label . ' must be between ' . $minimum . ' and ' . $maximum . '.');
        }
        return $number;
    }

    private function plainText($value, $maximumLength, $label)
    {
        $value = trim((string) $value);
        if (preg_match('/<[^>]*>/', $value)) {
            throw new InvalidArgumentException($label . ' cannot contain HTML.');
        }
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if ($length > $maximumLength) {
            throw new InvalidArgumentException($label . ' is too long.');
        }
        return $value;
    }

    private function safeColor($value, $fallback, $transparent = false)
    {
        $value = trim((string) $value);
        if ($transparent && $value === 'transparent') {
            return 'transparent';
        }
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return strtolower($value);
        }
        return $fallback;
    }

    private function assertSubjectType($subjectType)
    {
        if (!in_array($subjectType, $this->subjectTypes(), true)) {
            throw new InvalidArgumentException('ID card type must be student or staff.');
        }
    }

    private function toMillimetres($value, $unit)
    {
        if ($unit === 'in') {
            return $value * 25.4;
        }
        if ($unit === 'cm') {
            return $value * 10;
        }
        if ($unit === 'px') {
            return $value * 25.4 / 96;
        }
        return $value;
    }

    private function legacyHas($legacy, $key)
    {
        return is_array($legacy) ? array_key_exists($key, $legacy) : (is_object($legacy) && property_exists($legacy, $key));
    }

    private function legacyValue($legacy, $key, $default)
    {
        if (is_array($legacy) && array_key_exists($key, $legacy)) {
            return $legacy[$key];
        }
        if (is_object($legacy) && property_exists($legacy, $key)) {
            return $legacy->{$key};
        }
        return $default;
    }

    private function value($array, $key, $default)
    {
        return is_array($array) && array_key_exists($key, $array) ? $array[$key] : $default;
    }
}
