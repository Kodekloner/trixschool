<?php
$purpose_labels = array(
    'ca'                    => 'Continuous Assessment',
    'continuous_assessment' => 'Continuous Assessment',
    'midterm'               => 'Midterm Assessment',
    'mid_term'              => 'Midterm Assessment',
    'holiday'               => 'Holiday Assessment',
    'kindergarten'          => 'Kindergarten Assessment',
);

$format_duration = function ($duration) {
    $duration = trim((string) $duration);
    if ($duration === '') {
        return 'Not specified';
    }

    if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $duration, $matches)) {
        $minutes = ((int) $matches[1] * 60) + (int) $matches[2];
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }

    return $duration;
};

$status_classes = array(
    'not_started' => 'label-info',
    'in_progress' => 'label-warning',
    'submitted'   => 'label-primary',
    'marking'     => 'label-warning',
    'completed'   => 'label-success',
    'timed_out'   => 'label-danger',
    'voided'      => 'label-default',
);
?>
<div class="content-wrapper online-assessment-list">
    <section class="content-header">
        <h1><i class="fa fa-laptop" aria-hidden="true"></i> Online Assessments</h1>
    </section>

    <section class="content">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">My assessments</h3>
            </div>
            <div class="box-body">
                <p class="text-muted assessment-list-intro">Open an assessment to read its instructions, check availability, and start or resume your work.</p>

                <?php if (empty($onlineexam)) { ?>
                    <div class="alert alert-info" role="status">You do not have any online assessments at the moment.</div>
                <?php } else { ?>
                <div class="table-responsive assessment-table-wrap">
                    <div class="download_label">Online Assessments</div>
                    <table class="table table-striped table-bordered table-hover example online-assessment-table">
                        <thead>
                            <tr>
                                <th>Assessment</th>
                                <th>Academic period</th>
                                <th>Opens</th>
                                <th>Closes</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($onlineexam as $exam) {
                                    $purpose_key = isset($exam->purpose) ? strtolower((string) $exam->purpose) : '';
                                    $purpose = isset($purpose_labels[$purpose_key]) ? $purpose_labels[$purpose_key] : ($purpose_key !== '' ? ucwords(str_replace('_', ' ', $purpose_key)) : 'Online Assessment');
                                    $term = isset($exam->term) ? trim((string) $exam->term) : '';
                                    $status_key = !empty($exam->candidate_attempt_status) ? strtolower((string) $exam->candidate_attempt_status) : 'not_started';
                                    $status_label = $status_key === 'not_started' ? 'Available' : ucwords(str_replace('_', ' ', $status_key));
                                    $status_class = isset($status_classes[$status_key]) ? $status_classes[$status_key] : 'label-default';
                                    ?>
                                    <tr>
                                        <td data-label="Assessment" class="assessment-name">
                                            <strong><?php echo html_escape($exam->exam); ?></strong>
                                            <span class="assessment-purpose"><?php echo html_escape($purpose); ?></span>
                                        </td>
                                        <td data-label="Academic period">
                                            <?php echo $term !== '' ? html_escape(ucwords($term) . ' term') : '<span class="text-muted">Not specified</span>'; ?>
                                        </td>
                                        <td data-label="Opens"><?php echo $this->customlib->dateyyyymmddToDateTimeformat($exam->exam_from, false); ?></td>
                                        <td data-label="Closes"><?php echo $this->customlib->dateyyyymmddToDateTimeformat($exam->exam_to, false); ?></td>
                                        <td data-label="Duration"><?php echo html_escape($format_duration($exam->duration)); ?></td>
                                        <td data-label="Status"><span class="label <?php echo $status_class; ?> assessment-status"><?php echo html_escape($status_label); ?></span></td>
                                        <td data-label="Action" class="text-right assessment-action">
                                            <a href="<?php echo site_url('user/onlineexam/view/' . (int) $exam->id); ?>" class="btn btn-primary btn-sm" aria-label="View <?php echo html_escape($exam->exam); ?>">
                                                <i class="fa fa-eye" aria-hidden="true"></i> <span>View details</span>
                                            </a>
                                        </td>
                                    </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
        </div>
    </section>
</div>

<style>
.online-assessment-list .assessment-list-intro{margin-bottom:18px}
.online-assessment-list .assessment-table-wrap{max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
.online-assessment-list .online-assessment-table{width:100%;min-width:860px;margin-bottom:0}
.online-assessment-list .online-assessment-table th,.online-assessment-list .online-assessment-table td{vertical-align:middle;white-space:normal;overflow-wrap:anywhere;word-wrap:break-word}
.online-assessment-list .assessment-name strong{display:block;color:#2f3f4f}
.online-assessment-list .assessment-purpose{display:block;margin-top:4px;color:#6b7280;font-size:12px}
.online-assessment-list .assessment-status{display:inline-block;padding:5px 8px;font-size:11px}
.online-assessment-list .assessment-action .btn{min-height:34px;white-space:nowrap}
@media (max-width:767px){
    .online-assessment-list .content{padding:10px}
    .online-assessment-list .box-body{padding:12px}
    .online-assessment-list .dataTables_wrapper .dataTables_length,.online-assessment-list .dataTables_wrapper .dataTables_filter{text-align:left}
    .online-assessment-list .dataTables_wrapper .dataTables_filter label,.online-assessment-list .dataTables_wrapper .dataTables_filter input{display:block;width:100%;margin-left:0}
    .online-assessment-list .dataTables_wrapper .dt-buttons{margin-bottom:8px}
    .online-assessment-list .assessment-action .btn{min-height:40px;padding:8px 12px}
}
@media (max-width:380px){
    .online-assessment-list .content-header h1{font-size:20px}
}
</style>
