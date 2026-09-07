<?php
/** Real-model integration test; requires an EMPTY, isolated local test database. */
$socket = getenv('ONLINEEXAM_REVIEW_TEST_SOCKET');
$database = getenv('ONLINEEXAM_REVIEW_TEST_DATABASE');
if (!$socket || !$database) {
    echo "candidate review MySQL tests skipped; set ONLINEEXAM_REVIEW_TEST_SOCKET and ONLINEEXAM_REVIEW_TEST_DATABASE\n";
    exit(0);
}
if ($socket[0] !== '/' || !preg_match('/^[a-zA-Z0-9_]*test[a-zA-Z0-9_]*$/', $database)) {
    throw new RuntimeException('Use a local socket and a dedicated database name containing test.');
}
define('BASEPATH', __DIR__ . '/../system/');
define('APPPATH', __DIR__ . '/../application/');
define('ENVIRONMENT', 'testing');
function log_message($level, $message) {}
function is_php($version) { return version_compare(PHP_VERSION, $version, '>='); }
function show_error($message) { throw new RuntimeException(is_array($message) ? implode('; ', $message) : $message); }
function review_assert($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function config_item($key) { return $key === 'subclass_prefix' ? 'MY_' : false; }

class CI_Model {
    public function __construct() {}
    public function __get($name) { return isset($GLOBALS['review_services'][$name]) ? $GLOBALS['review_services'][$name] : null; }
    public function __isset($name) { return isset($GLOBALS['review_services'][$name]); }
}
class CI_Migration extends CI_Model {}
class MY_model extends CI_Model {}
class ReviewTestSettings { public function getCurrentSession() { return 1; } }
class ReviewTestInput { public function ip_address() { return '127.0.0.1'; } }
class ReviewTestLoader {
    public function library($name) {
        if (!isset($GLOBALS['review_services'][$name])) {
            require_once APPPATH . 'libraries/' . ucfirst($name) . '.php';
            $GLOBALS['review_services'][$name] = new $name();
        }
    }
    public function model($name) {
        if (is_array($name)) { foreach ($name as $item) { $this->model($item); } return; }
        if (!isset($GLOBALS['review_services'][$name])) {
            require_once APPPATH . 'models/' . ucfirst($name) . '.php';
            $GLOBALS['review_services'][$name] = new $name();
        }
    }
}
require_once BASEPATH . 'database/DB.php';
$db = DB(array('hostname' => $socket, 'username' => 'root', 'password' => '', 'database' => $database,
    'dbdriver' => 'mysqli', 'db_debug' => true, 'char_set' => 'utf8mb4', 'dbcollat' => 'utf8mb4_unicode_ci'), true);
review_assert($db->query('SHOW TABLES')->num_rows() === 0, 'The test database must be empty; existing tables will not be changed.');
$GLOBALS['review_services'] = array('db' => $db, 'load' => new ReviewTestLoader(), 'setting_model' => new ReviewTestSettings(), 'input' => new ReviewTestInput());
require_once BASEPATH . 'database/DB_forge.php';
require_once BASEPATH . 'database/drivers/mysqli/mysqli_forge.php';
$GLOBALS['review_services']['dbforge'] = new CI_DB_mysqli_forge($db);

// Reuse actual published v128 table definitions rather than approximating them.
$migration = file_get_contents(APPPATH . 'migrations/128_localize_online_examination.php');
preg_match_all('/CREATE TABLE `([^`]+)` \((.*?)\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4/s', $migration, $matches);
foreach ($matches[0] as $statement) { $db->query($statement); }
$db->query("CREATE TABLE onlineexam (id INT PRIMARY KEY, workflow_version INT, revision INT, session_id INT, class_id INT, subject_id INT,
    term VARCHAR(20), purpose VARCHAR(24), result_adapter VARCHAR(32), target_component VARCHAR(20), target_max_score DECIMAL(10,2),
    lifecycle_status VARCHAR(24), frozen_at DATETIME, exam_from DATETIME, exam_to DATETIME, is_active INT, is_random_question INT DEFAULT 0,
    is_neg_marking INT DEFAULT 0, lifecycle_checked_at DATETIME NULL, exam VARCHAR(100) DEFAULT 'Test assessment')");
$db->query("CREATE TABLE onlineexam_students (id INT AUTO_INCREMENT PRIMARY KEY, onlineexam_id INT, student_session_id INT,
    candidate_status VARCHAR(24) DEFAULT 'assigned', is_attempted INT DEFAULT 0, UNIQUE KEY candidate (onlineexam_id,student_session_id))");
$db->query("CREATE TABLE students (id INT PRIMARY KEY, is_active VARCHAR(10), firstname VARCHAR(30) DEFAULT 'Test', middlename VARCHAR(30) DEFAULT '', lastname VARCHAR(30) DEFAULT 'Student', admission_no VARCHAR(30) DEFAULT '')");
$db->query("CREATE TABLE student_session (id INT PRIMARY KEY, student_id INT, session_id INT, class_id INT, section_id INT)");
$db->query("CREATE TABLE onlineexam_questions (id INT PRIMARY KEY, is_compulsory TINYINT DEFAULT 0)");
$db->query("CREATE TABLE score (ID INT AUTO_INCREMENT PRIMARY KEY, StudentID INT, ClassID INT, SectionID INT, SubjectID INT,
    Session VARCHAR(20), Term VARCHAR(20), ca1 DECIMAL(10,2) DEFAULT 0)");
$db->query("CREATE TABLE subjects (id INT PRIMARY KEY, name VARCHAR(100), code VARCHAR(10))");
$db->query("CREATE TABLE sections (id INT PRIMARY KEY, section VARCHAR(30))");
$db->query("CREATE TABLE class_sections (id INT PRIMARY KEY, class_id INT, section_id INT)");
$db->query("CREATE TABLE teacher_subjects (id INT AUTO_INCREMENT PRIMARY KEY, teacher_id INT, subject_id INT, session_id INT, class_section_id INT)");
$db->query("CREATE TABLE subject_group_class_sections (id INT AUTO_INCREMENT PRIMARY KEY, subject_group_id INT, class_section_id INT, session_id INT)");
$db->query("CREATE TABLE subject_group_subjects (id INT AUTO_INCREMENT PRIMARY KEY, subject_group_id INT, subject_id INT, session_id INT)");
$db->query("INSERT INTO onlineexam_questions VALUES (1,0)");
$fixture_time = date('Y-m-d H:i:s');
$db->insert('onlineexam', array('id'=>98,'workflow_version'=>2,'revision'=>1,'target_max_score'=>20,
    'lifecycle_status'=>'published','frozen_at'=>$fixture_time));
$db->insert('onlineexam', array('id'=>99,'workflow_version'=>2,'revision'=>1,'target_max_score'=>20,
    'lifecycle_status'=>'draft','frozen_at'=>null));
foreach (array(98, 99) as $fixture_exam_id) {
    $db->insert('onlineexam_papers', array('onlineexam_id'=>$fixture_exam_id,'title'=>'Migration fixture',
        'paper_type'=>'objective','delivery_mode'=>'cbt','duration_minutes'=>30,'raw_max_score'=>50,
        'contribution_score'=>100,'created_at'=>$fixture_time,'updated_at'=>$fixture_time));
}
require_once APPPATH . 'migrations/137_onlineexam_candidate_paper_review.php';
$upgrade = new Migration_Onlineexam_candidate_paper_review();
$upgrade->up();
$upgrade->up();
review_assert((int) $db->get('onlineexam_questions')->row()->is_compulsory === 0, 'Migration must preserve existing optional assignment rules.');
$db->insert('onlineexam_questions', array('id'=>2));
review_assert((int) $db->where('id',2)->get('onlineexam_questions')->row()->is_compulsory === 1, 'New assignments must default to compulsory after migration.');
review_assert((float) $db->where('onlineexam_id',99)->get('onlineexam_papers')->row()->raw_max_score === 20.0, 'An editable draft paper must inherit the selected component maximum.');
review_assert((float) $db->where('onlineexam_id',98)->get('onlineexam_papers')->row()->raw_max_score === 50.0, 'Migration must not rewrite a published paper maximum.');
$db->where_in('onlineexam_id', array(98, 99))->delete('onlineexam_papers');
$db->where_in('id', array(98, 99))->delete('onlineexam');
// Run standalone phpMyAdmin SQL twice as well as the framework migration.
foreach (array(1, 2) as $run) {
    $sql = file_get_contents(__DIR__ . '/../docs/online_examination_candidate_review_migration.sql');
    $db->conn_id->multi_query($sql);
    do { if ($result = $db->conn_id->store_result()) { $result->free(); } } while ($db->conn_id->more_results() && $db->conn_id->next_result());
}
$GLOBALS['review_services']['load']->model('onlineexamoperations_model');
$GLOBALS['review_services']['load']->model('onlineexamattempt_model');
$GLOBALS['review_services']['load']->model('onlineexamworkflow_model');
$GLOBALS['review_services']['load']->model('onlineexamresultsync_model');
$GLOBALS['review_services']['load']->model('onlineexamreview_model');
$operations = $GLOBALS['review_services']['onlineexamoperations_model'];
$attempts = $GLOBALS['review_services']['onlineexamattempt_model'];
$workflow = $GLOBALS['review_services']['onlineexamworkflow_model'];
$sync = $GLOBALS['review_services']['onlineexamresultsync_model'];
$review = $GLOBALS['review_services']['onlineexamreview_model'];
$legacy = $GLOBALS['review_services']['onlineexam_model'];
$recovery_rule = new ReflectionMethod(Onlineexamoperations_model::class, 'candidatePaperRecoveryError');
$recovery_rule->setAccessible(true);
$answer_any_context = array(
    'attempt_paper' => array('status'=>'submitted','completion_source'=>'timed_out','deadline_at'=>'2026-09-07 10:00:00','submitted_at'=>'2026-09-07 10:00:01'),
    'paper' => array('sections'=>array(array('id'=>7,'answer_rule'=>'answer_any','answer_count'=>1))),
    'answers' => array(
        array('paper_section_id'=>7,'is_compulsory'=>0,'is_answered'=>1),
        array('paper_section_id'=>7,'is_compulsory'=>0,'is_answered'=>0),
    ),
    'question_count' => 2,
);
review_assert($recovery_rule->invoke($operations,$answer_any_context) !== null, 'A satisfied answer-any timeout must not be reopened as incomplete.');
$answer_any_context['answers'][0]['is_answered'] = 0;
review_assert($recovery_rule->invoke($operations,$answer_any_context) === null, 'An unfinished answer-any timeout remains recoverable.');
$now = date('Y-m-d H:i:s');
$past = date('Y-m-d H:i:s', time() - 7200);
$close = date('Y-m-d H:i:s', time() - 3600);
$db->insert('onlineexam', array('id'=>1,'workflow_version'=>2,'revision'=>1,'session_id'=>1,'class_id'=>1,'subject_id'=>1,'term'=>'1st',
    'purpose'=>'ca','result_adapter'=>'standard_component','target_component'=>'ca1','target_max_score'=>20,'lifecycle_status'=>'marking',
    'frozen_at'=>$past,'exam_from'=>$past,'exam_to'=>$close,'is_active'=>1));
$db->insert('onlineexam_class_sections', array('onlineexam_id'=>1,'section_id'=>1,'created_at'=>$past));
$db->query("INSERT INTO subjects VALUES (1,'Science','SCI'),(2,'English','ENG'),(3,'Previous session','OLD')");
$db->query("INSERT INTO sections VALUES (1,'A'),(2,'B')");
$db->query("INSERT INTO class_sections VALUES (1,1,1),(2,1,2)");
$db->query("INSERT INTO subject_group_class_sections(subject_group_id,class_section_id,session_id) VALUES (1,1,1),(2,2,1),(3,1,2)");
$db->query("INSERT INTO subject_group_subjects(subject_group_id,subject_id,session_id) VALUES (1,1,1),(2,2,1),(3,3,2),(1,3,2)");
$db->query("INSERT INTO teacher_subjects(teacher_id,subject_id,session_id,class_section_id) VALUES (10,1,1,1),(10,2,2,2),(11,2,1,2)");
$choices = $legacy->getWorkflowAcademicChoices(1,1,'1st',1);
review_assert(array_column($choices['subjects'],'id') === array(2,1), 'Subjects must be filtered by both curriculum session records.');
review_assert(array_column($choices['sections'],'id') === array(1), 'Class arms must be filtered by the chosen subject.');
$choices = $legacy->getWorkflowAcademicChoices(1,1,'2nd',1,10);
review_assert(array_column($choices['subjects'],'id') === array(1), 'Teacher assignments from other sessions must not grant subject access.');
review_assert($legacy->getWorkflowAcademicChoices(1,1,'4th',1)['subjects'] === array(), 'Invalid terms must be rejected.');
for ($id = 1; $id <= 4; $id++) {
    $db->insert('students', array('id'=>$id,'is_active'=>'yes'));
    $db->insert('student_session', array('id'=>$id,'student_id'=>$id,'session_id'=>1,'class_id'=>1,'section_id'=>$id === 4 ? 2 : 1));
}
$papers = array();
for ($id = 1; $id <= 2; $id++) {
    $papers[] = array('id'=>$id,'paper_type'=>'objective','delivery_mode'=>'cbt','duration_minutes'=>30,
        'raw_max_score'=>20,'contribution_score'=>50,'starts_at'=>$past,'ends_at'=>$close,'is_active'=>1,'display_order'=>$id,'title'=>'Paper '.$id,'sections'=>array());
}
$configuration = array('academic_context'=>array('session_id'=>1,'class_id'=>1,'subject_id'=>1,'term'=>'1st','section_ids'=>array(1)),
    'result'=>array('adapter'=>'standard_component','target_component'=>'ca1','target_maximum'=>20),'papers'=>$papers);
$db->insert('onlineexam_revision_snapshots', array('onlineexam_id'=>1,'revision'=>1,'configuration_json'=>json_encode($configuration),
    'checksum'=>str_repeat('a',64),'frozen_at'=>$past));
for ($id = 1; $id <= 4; $id++) {
    $db->insert('onlineexam_question_snapshots', array('id'=>$id,'onlineexam_id'=>1,'paper_id'=>$id <= 2 ? 1 : 2,'revision'=>1,
        'question_type'=>'singlechoice','question_text'=>'Choose A','options_json'=>json_encode(array('a'=>'A','b'=>'B')),
        'correct_answer_json'=>'"a"','marks'=>10,'is_compulsory'=>1,'checksum'=>str_repeat('b',64),'created_at'=>$past));
}
$scope = array('section_ids'=>array(1),'allow_assign_candidate'=>true);
$future_start = date('Y-m-d H:i:s', time()+3600);
$future_end = date('Y-m-d H:i:s', time()+7200);
$denied = $operations->rescheduleCandidatePaper(1,1,2,$future_start,$future_end,'Network disruption',10,array('section_ids'=>array(1)));
review_assert(empty($denied['success']) && $denied['code'] === 'assignment_permission_required', 'Assignment permission must be enforced in the model: ' . json_encode($denied));
review_assert($db->count_all('onlineexam_students') === 0, 'A denied action must not create an assignment.');
$wrong_arm = $operations->rescheduleCandidatePaper(1,4,2,$future_start,$future_end,'Network disruption',10,$scope);
review_assert(empty($wrong_arm['success']), 'Other class arms cannot be assigned by crafted identifiers.');
$too_short = $operations->rescheduleCandidatePaper(1,1,2,$future_start,date('Y-m-d H:i:s',strtotime($future_start)+60),'Too short',10,$scope);
review_assert(empty($too_short['success']), 'A recovery window shorter than duration must fail.');
review_assert($db->count_all('onlineexam_students') === 0 && $db->count_all('onlineexam_candidate_attempts') === 0, 'Validation rollback must include nested official attempt creation.');

$db->insert('onlineexam_students', array('onlineexam_id'=>1,'student_session_id'=>1));
$candidate_id = (int) $db->insert_id();
$prepared = $operations->ensureOfficialAttempt(1,$candidate_id,10,$scope);
review_assert(!empty($prepared['success']), 'The real model should prepare a frozen candidate attempt.');
$attempt_id = (int) $prepared['attempt']['id'];
foreach (array(1,2) as $id) {
    $db->insert('onlineexam_attempt_answers', array('attempt_id'=>$attempt_id,'question_snapshot_id'=>$id,'response_json'=>'"a"',
        'is_answered'=>1,'auto_mark'=>10,'final_mark'=>10,'marking_status'=>'auto_marked','saved_at'=>$past,'created_at'=>$past,'updated_at'=>$past));
}
$db->where('attempt_id',$attempt_id)->update('onlineexam_attempt_papers', array('status'=>'submitted','started_at'=>$past,'deadline_at'=>$close,'submitted_at'=>$now,'completion_source'=>'timed_out'));
$db->where('attempt_id',$attempt_id)->where('paper_id',1)->update('onlineexam_attempt_papers', array('submitted_at'=>$past,'completion_source'=>'submitted'));
$db->where('id',$attempt_id)->update('onlineexam_candidate_attempts',array('status'=>'submitted','submitted_at'=>$now));
review_assert(!empty($workflow->finalizeAttempt($attempt_id,10)['success']), 'Initial two-paper calculation should finalize.');
review_assert(!empty($sync->syncCompletedAttempt($attempt_id,10)['success']), 'The real result adapter should post the initial score.');
review_assert((float)$db->get('score')->row()->ca1 === 10.0, 'One full paper plus one missed paper should initially post 10/20.');
$criteria = array('session_id'=>1,'term'=>'1st','assessment_type'=>'term','class_id'=>1,'section_id'=>1);
$matrix = $review->overview($criteria,$scope);
review_assert(count($matrix['students']) === 3 && count($matrix['columns']) === 2, 'Review matrix should include every active enrolled student and both frozen papers.');
review_assert($matrix['cells'][1]['1_2']['key'] === 'missed' && $matrix['cells'][2]['1_1']['key'] === 'unassigned', 'Review matrix must distinguish missed papers from unassigned students.');
review_assert($review->cell(1,4,1,$scope) === false, 'Review cells must enforce candidate class-arm scope.');
review_assert(empty($review->archiveCompleted(1,10)['success']), 'An automatically finalized missed paper must block completed-assessment deletion.');
$first_before = $db->where('attempt_id',$attempt_id)->where('paper_id',1)->get('onlineexam_attempt_papers')->row_array();
$retry = $operations->rescheduleCandidatePaper(1,1,2,$future_start,$future_end,'Network disruption',10,$scope);
review_assert(!empty($retry['success']) && $retry['attempt_id'] === $attempt_id, 'Rescheduling must reuse the official attempt.');
review_assert($first_before === $db->where('attempt_id',$attempt_id)->where('paper_id',1)->get('onlineexam_attempt_papers')->row_array(), 'Rescheduling must not change completed sibling paper data.');
review_assert((float)$db->get('score')->row()->ca1 === 10.0, 'Reopening must preserve the previously owned result until correction is ready.');
$attempts->processExpiredPapers();
review_assert($db->where('attempt_id',$attempt_id)->where('paper_id',2)->get('onlineexam_attempt_papers')->row()->status === 'pending', 'Cron must not expire a future individual window.');
review_assert(empty($attempts->startPaper(1,1,2)['status']), 'A future individual window cannot start early.');
$start_now = date('Y-m-d H:i:s',time()-60);
$end_later = date('Y-m-d H:i:s',time()+3600);
review_assert(!empty($operations->rescheduleCandidatePaper(1,1,2,$start_now,$end_later,'Now invigilated',10,$scope)['success']), 'Staff may move an unstarted recovery window.');
$start = $attempts->startPaper(1,1,2);
review_assert(!empty($start['status']), 'A candidate can start their individually rescheduled paper after the original assessment closes.');
review_assert(strtotime($start['attempt_paper']->deadline_at) >= time()+1795, 'A rescheduled timer must allow the full duration.');
$expire = new ReflectionMethod(Onlineexamattempt_model::class, 'submitExpiredPaper');
$expire->setAccessible(true);
$stale_cron = $expire->invoke($attempts,1,$attempt_id,2,$start['attempt']->submission_key);
review_assert(!empty($stale_cron['skipped']), 'A cron worker holding an old selection must recheck the new deadline and skip submission.');
$saved = $attempts->saveAnswer(1,$attempt_id,3,'a',100);
review_assert(!empty($saved['status']), 'Autosave must work under the rescheduled deadline.');
$submitted = $attempts->submitPaper(1,$attempt_id,2,$start['attempt']->submission_key,array(array('question_snapshot_id'=>4,'response'=>'a','client_sequence'=>101)));
review_assert(!empty($submitted['status']) && $submitted['attempt_status']==='submitted', 'Submission must count the already-completed sibling paper as done.');
review_assert(!empty($workflow->finalizeAttempt($attempt_id,10)['success']), 'Corrected calculation should finalize.');
review_assert(!empty($sync->syncCompletedAttempt($attempt_id,10)['success']), 'Correction must post through the original adapter.');
review_assert((float)$db->get('score')->row()->ca1 === 20.0 && $db->count_all('score') === 1, 'Correction must update the same owned result row to 20/20.');
review_assert(empty($operations->rescheduleCandidatePaper(1,1,2,$start_now,$end_later,'Retry completed',10,$scope)['success']), 'A valid completed submission must not be reopened.');

// A second student has a missed assessment conducted on paper. Whole-paper
// scores must combine normally and retain the exact versioned marking source.
$manual1 = $operations->recordCandidatePaperScore(1,2,1,12,'Paper script invigilated',10,$scope);
review_assert(!empty($manual1['success']) && empty($manual1['attempt_finalized']), 'The first manual paper must wait for remaining papers.');
$manual2 = $operations->recordCandidatePaperScore(1,2,2,16,'Paper script invigilated',10,$scope);
review_assert(!empty($manual2['success']) && !empty($manual2['attempt_finalized']), 'The last manual paper should finalize automatically.');
review_assert((float)$db->where('StudentID',2)->get('score')->row()->ca1 === 14.0, 'Manual scores 12 and 16 out of 20 should post 14/20.');
$history_count = $db->count_all('onlineexam_candidate_paper_history');
$same = $operations->recordCandidatePaperScore(1,2,2,16,'Paper script invigilated',10,$scope);
review_assert(!empty($same['success']) && !empty($same['idempotent']), 'Duplicate manual entry must be idempotent.');
review_assert($db->count_all('onlineexam_candidate_paper_history') === $history_count, 'Duplicate entry must not create a second recovery history event.');
review_assert(empty($operations->recordCandidatePaperScore(1,2,2,17,'Changed completed',10,$scope)['success']), 'Completed manual paper scores cannot be silently changed.');
$db->insert('score',array('StudentID'=>3,'ClassID'=>1,'SectionID'=>1,'SubjectID'=>1,'Session'=>'1','Term'=>'1st','ca1'=>9));
$operations->recordCandidatePaperScore(1,3,1,20,'Paper script invigilated',10,$scope);
$conflict = $operations->recordCandidatePaperScore(1,3,2,20,'Paper script invigilated',10,$scope);
review_assert(!empty($conflict['requires_conflict_resolution']), 'An unrelated manual result must be held as a conflict.');
review_assert((float)$db->where('StudentID',3)->get('score')->row()->ca1 === 9.0, 'A manual result conflict must never be overwritten.');
review_assert(empty($review->archiveCompleted(1,10)['success']), 'Unrelated manual score conflicts must block completed-assessment deletion.');
$ledger = $db->where('student_session_id',3)->where('status','conflict')->order_by('id','DESC')->get('onlineexam_result_sync')->row_array();
$permission = $sync->authorizeConflictReplacement(1,$ledger['id'],10,'School confirms supervised paper replaces old manual score');
review_assert(!empty($permission['success']), 'An explicit authorized conflict resolution should be accepted.');
review_assert(!empty($sync->syncCompletedAttempt($permission['attempt_id'],10)['success']), 'Explicitly resolved manual conflicts can post through the normal adapter.');
// A fourth completed candidate models interruption after calculation commits
// but before the result adapter runs. Archiving must reconcile this safely.
$db->insert('students',array('id'=>5,'is_active'=>'yes'));
$db->insert('student_session',array('id'=>5,'student_id'=>5,'session_id'=>1,'class_id'=>1,'section_id'=>1));
$db->insert('onlineexam_students',array('onlineexam_id'=>1,'student_session_id'=>5));
$late_candidate = (int)$db->insert_id();
$late = $operations->ensureOfficialAttempt(1,$late_candidate,10,$scope);
$late_id = (int)$late['attempt']['id'];
foreach (array(1,2,3,4) as $question_id) {
    $db->insert('onlineexam_attempt_answers',array('attempt_id'=>$late_id,'question_snapshot_id'=>$question_id,'response_json'=>'"a"','is_answered'=>1,
        'auto_mark'=>10,'final_mark'=>10,'marking_status'=>'auto_marked','saved_at'=>$past,'created_at'=>$past,'updated_at'=>$past));
}
$db->where('attempt_id',$late_id)->update('onlineexam_attempt_papers',array('status'=>'submitted','submitted_at'=>$now,'completion_source'=>'submitted'));
$db->where('id',$late_id)->update('onlineexam_candidate_attempts',array('status'=>'submitted','submitted_at'=>$now));
review_assert(!empty($workflow->finalizeAttempt($late_id,10)['success']), 'Interrupted sync fixture must be fully calculated.');
review_assert($db->where('attempt_id',$late_id)->count_all_results('onlineexam_result_sync') === 0, 'Interrupted sync fixture has no posting ledger.');
$score_before_archive = $db->order_by('ID')->get('score')->result_array();
$answers_before_archive = $db->count_all('onlineexam_attempt_answers');
review_assert(!empty($review->archiveCompleted(1,10)['success']), 'After all assigned students complete and results post, assessment removal should succeed.');
review_assert($score_before_archive === $db->where('StudentID !=',5)->order_by('ID')->get('score')->result_array() && $answers_before_archive === $db->count_all('onlineexam_attempt_answers'), 'Removal must preserve posted results and answer history.');
review_assert((float)$db->where('StudentID',5)->get('score')->row()->ca1 === 20.0, 'Removal must reconcile a completed result interrupted before first sync.');
review_assert($review->overview($criteria,$scope)['columns'] === array(), 'Removed assessments must disappear from the working review matrix.');
review_assert($attempts->getCandidateContext(1,1) === null, 'Archived assessments must be hidden from candidate context.');
review_assert(empty($operations->rescheduleCandidatePaper(1,1,2,$start_now,$end_later,'Archived',10,$scope)['success']), 'Archived assessments cannot be rescheduled.');
review_assert($db->count_all('onlineexam_candidate_paper_history') >= 5, 'Recovery must retain immutable history.');
echo "candidate review real-model MySQL tests passed\n";
