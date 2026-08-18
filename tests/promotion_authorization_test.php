<?php

define('BASEPATH', __DIR__);

class PromotionAuthorizationHttpException extends RuntimeException
{
    public $status;

    public function __construct($message, $status)
    {
        parent::__construct((string) $message, (int) $status);
        $this->status = (int) $status;
    }
}

function show_error($message, $status = 500)
{
    throw new PromotionAuthorizationHttpException($message, $status);
}

function show_404()
{
    throw new PromotionAuthorizationHttpException('Not found', 404);
}

function access_denied()
{
    throw new PromotionAuthorizationHttpException('Access denied', 403);
}

class Admin_Controller
{
    public $session;
    public $rbac;
    public $customlib;
    public $promotioncriteria_model;
    public $input;
}

class PromotionAuthorizationSession
{
    private $values;

    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function userdata($key)
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : null;
    }
}

class PromotionAuthorizationRbac
{
    private $grants;

    public function __construct(array $grants)
    {
        $this->grants = $grants;
    }

    public function hasPrivilege($category, $permission)
    {
        return !empty($this->grants[$category . ':' . $permission]);
    }
}

class PromotionAuthorizationCustomlib
{
    private $staffId;

    public function __construct($staffId)
    {
        $this->staffId = (int) $staffId;
    }

    public function getStaffID()
    {
        return $this->staffId;
    }
}

class PromotionAuthorizationInput
{
    private $method;
    private $post;

    public function __construct($method, array $post = array())
    {
        $this->method = (string) $method;
        $this->post = $post;
    }

    public function server($key)
    {
        return $key === 'REQUEST_METHOD' ? $this->method : null;
    }

    public function post($key, $xssClean = false)
    {
        return array_key_exists($key, $this->post) ? $this->post[$key] : null;
    }
}

class PromotionAuthorizationModel
{
    public $teacherScopeCalls = array();

    public function teacherHasScope($staffId, $sessionId, $classId, $sectionId)
    {
        $scope = array_map('intval', array($staffId, $sessionId, $classId, $sectionId));
        $this->teacherScopeCalls[] = $scope;

        return $scope === array(99, 10, 7, 12);
    }

    public function sessionExists($sessionId)
    {
        return (int) $sessionId === 10;
    }

    public function classSectionExists($classId, $sectionId)
    {
        return (int) $classId === 7 && (int) $sectionId === 12;
    }

    public function studentMatchesScope($studentId, $sessionId, $classId, $sectionId)
    {
        return array_map('intval', array($studentId, $sessionId, $classId, $sectionId))
            === array(89, 10, 7, 12);
    }
}

function promotion_authorization_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function promotion_authorization_invoke($controller, $method, array $arguments = array())
{
    $reflection = new ReflectionMethod('Promotioncriteria', $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($controller, $arguments);
}

function promotion_authorization_expect_status($status, callable $callback, $message)
{
    try {
        $callback();
    } catch (PromotionAuthorizationHttpException $exception) {
        promotion_authorization_assert(
            $exception->status === (int) $status,
            $message . ' Expected HTTP ' . (int) $status . ', got ' . $exception->status . '.'
        );
        return;
    }

    promotion_authorization_assert(false, $message . ' No authorization error was raised.');
}

require_once __DIR__ . '/../helper/promotion_helper.php';
require_once __DIR__ . '/../application/controllers/admin/Promotioncriteria.php';

$controllerReflection = new ReflectionClass('Promotioncriteria');

function promotion_authorization_controller(
    ReflectionClass $reflection,
    array $roles,
    array $grants = array(),
    $method = 'POST',
    array $post = array('promotion_csrf' => 'known-token')
) {
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->session = new PromotionAuthorizationSession(array(
        'admin' => array('roles' => $roles),
        'promotion_criteria_csrf' => 'known-token',
    ));
    $controller->rbac = new PromotionAuthorizationRbac($grants);
    $controller->customlib = new PromotionAuthorizationCustomlib(99);
    $controller->promotioncriteria_model = new PromotionAuthorizationModel();
    $controller->input = new PromotionAuthorizationInput($method, $post);

    return $controller;
}

$scope = array(10, 7, 12);
foreach (array('Admin', 'Head Teacher', 'Super Admin') as $seniorRole) {
    $controller = promotion_authorization_controller($controllerReflection, array($seniorRole => 1));
    promotion_authorization_assert(
        promotion_authorization_invoke($controller, 'canAccessScope', $scope) === true,
        $seniorRole . ' must be able to review every valid class scope.'
    );
}

$teacher = promotion_authorization_controller($controllerReflection, array('Teacher' => 4));
promotion_authorization_assert(
    promotion_authorization_invoke($teacher, 'canAccessScope', array(10, 7, 12)) === true,
    'An exact class teacher assignment must be accepted.'
);
foreach (array(
    array(11, 7, 12),
    array(10, 8, 12),
    array(10, 7, 13),
) as $wrongScope) {
    promotion_authorization_assert(
        promotion_authorization_invoke($teacher, 'canAccessScope', $wrongScope) === false,
        'A teacher assignment from another session, class, or section must be rejected.'
    );
}

foreach (array('Staff', 'Parent', 'Student') as $unauthorizedRole) {
    $controller = promotion_authorization_controller($controllerReflection, array($unauthorizedRole => 9));
    promotion_authorization_assert(
        promotion_authorization_invoke($controller, 'canAccessScope', $scope) === false,
        $unauthorizedRole . ' must not acquire promotion scope from a role name alone.'
    );
}

$addGrant = array('override_promotion_note:can_add' => true);
$editGrant = array('override_promotion_note:can_edit' => true);
$viewGrant = array('override_promotion_note:can_view' => true);
promotion_authorization_assert(
    promotion_authorization_invoke(
        promotion_authorization_controller($controllerReflection, array('Teacher' => 4), $addGrant),
        'canOverride'
    ) === true,
    'The override add permission must authorize a change.'
);
promotion_authorization_assert(
    promotion_authorization_invoke(
        promotion_authorization_controller($controllerReflection, array('Teacher' => 4), $editGrant),
        'canOverride'
    ) === true,
    'The override edit permission must authorize a change.'
);
promotion_authorization_assert(
    promotion_authorization_invoke(
        promotion_authorization_controller($controllerReflection, array('Teacher' => 4), $viewGrant),
        'canOverride'
    ) === false,
    'View-only access must not authorize a promotion-note change.'
);

$teacherWithGrant = promotion_authorization_controller($controllerReflection, array('Teacher' => 4), $addGrant);
promotion_authorization_invoke($teacherWithGrant, 'validatePostedScope', array(array(
    'student_id' => 89,
    'session_id' => 10,
    'class_id' => 7,
    'section_id' => 12,
)));

foreach (array(
    array('student_id' => 90, 'session_id' => 10, 'class_id' => 7, 'section_id' => 12),
    array('student_id' => 89, 'session_id' => 11, 'class_id' => 7, 'section_id' => 12),
    array('student_id' => 89, 'session_id' => 10, 'class_id' => 8, 'section_id' => 12),
    array('student_id' => 89, 'session_id' => 10, 'class_id' => 7, 'section_id' => 13),
) as $tamperedScope) {
    promotion_authorization_expect_status(403, function () use ($teacherWithGrant, $tamperedScope) {
        promotion_authorization_invoke($teacherWithGrant, 'validatePostedScope', array($tamperedScope));
    }, 'Altered student scope identifiers must fail closed.');
}

$incompleteScope = array('student_id' => 0, 'session_id' => 10, 'class_id' => 7, 'section_id' => 12);
promotion_authorization_expect_status(400, function () use ($teacherWithGrant, $incompleteScope) {
    promotion_authorization_invoke($teacherWithGrant, 'validatePostedScope', array($incompleteScope));
}, 'An incomplete override scope must be rejected.');

$staffWithPermission = promotion_authorization_controller($controllerReflection, array('Staff' => 2), $addGrant);
promotion_authorization_expect_status(403, function () use ($staffWithPermission) {
    promotion_authorization_invoke($staffWithPermission, 'validatePostedScope', array(array(
        'student_id' => 89,
        'session_id' => 10,
        'class_id' => 7,
        'section_id' => 12,
    )));
}, 'An override permission without a senior or assigned-teacher scope must not authorize access.');

promotion_authorization_invoke($teacherWithGrant, 'requirePostAndToken');

$badToken = promotion_authorization_controller(
    $controllerReflection,
    array('Teacher' => 4),
    $addGrant,
    'POST',
    array('promotion_csrf' => 'tampered-token')
);
promotion_authorization_expect_status(403, function () use ($badToken) {
    promotion_authorization_invoke($badToken, 'requirePostAndToken');
}, 'A bad module CSRF token must be rejected.');

$getRequest = promotion_authorization_controller(
    $controllerReflection,
    array('Teacher' => 4),
    $addGrant,
    'GET',
    array('promotion_csrf' => 'known-token')
);
promotion_authorization_expect_status(405, function () use ($getRequest) {
    promotion_authorization_invoke($getRequest, 'requirePostAndToken');
}, 'State-changing promotion actions must reject non-POST requests.');

$teacherWithoutGrant = promotion_authorization_controller($controllerReflection, array('Teacher' => 4));
promotion_authorization_expect_status(403, function () use ($teacherWithoutGrant) {
    promotion_authorization_invoke($teacherWithoutGrant, 'requireOverridePrivilege');
}, 'An assigned teacher without the override permission must still be rejected.');

$manageGrant = array('manage_promotion_criteria:can_add' => true);
$criteriaManager = promotion_authorization_controller($controllerReflection, array('Admin' => 1), $manageGrant);
promotion_authorization_invoke($criteriaManager, 'requireCriteriaPrivilege', array('can_add'));
promotion_authorization_expect_status(403, function () use ($teacherWithoutGrant) {
    promotion_authorization_invoke($teacherWithoutGrant, 'requireCriteriaPrivilege', array('can_add'));
}, 'A teacher without the senior criteria-management permission must not create criteria.');

echo "promotion authorization tests passed" . PHP_EOL;
