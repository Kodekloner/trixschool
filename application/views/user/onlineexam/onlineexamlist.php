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
                <p class="text-muted assessment-list-intro">View your assessment, available papers, and released results.</p>

                <?php if (empty($onlineexam)) { ?>
                    <div class="alert alert-info" role="status">You do not have any online assessments at the moment.</div>
                <?php } else { ?>
                <div class="table-responsive assessment-table-wrap" role="region" aria-label="My online assessments" tabindex="0">
                    <div class="download_label">Online Assessments</div>
                    <table class="table table-striped table-bordered table-hover example online-assessment-table">
                        <caption class="sr-only">Online assessments assigned to this student</caption>
                        <thead>
                            <tr>
                                <th scope="col">Assessment</th>
                                <th scope="col">Academic period</th>
                                <th scope="col">Opens</th>
                                <th scope="col">Closes</th>
                                <th scope="col">Duration</th>
                                <th scope="col">Status</th>
                                <th scope="col">Result</th>
                                <th scope="col" class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($onlineexam as $exam) {
                                    $purpose_key = isset($exam->purpose) ? strtolower((string) $exam->purpose) : '';
                                    $purpose = isset($purpose_labels[$purpose_key]) ? $purpose_labels[$purpose_key] : ($purpose_key !== '' ? ucwords(str_replace('_', ' ', $purpose_key)) : 'Online Assessment');
                                    $term = isset($exam->term) ? trim((string) $exam->term) : '';
                                    $status_label = $exam->student_state['label'];
                                    $status_class = $exam->student_state['class'];
                                    $result = $exam->student_result;
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
                                        <td data-label="Result">
                                            <?php if ($result['visible']) { ?>
                                                <strong><?php echo number_format($result['score'], 2); ?><?php echo $result['maximum'] !== null ? ' / ' . number_format($result['maximum'], 2) : ''; ?></strong>
                                                <?php if ($result['outcome']) { ?><span class="label <?php echo $result['outcome'] === 'Pass' ? 'label-success' : 'label-danger'; ?> assessment-status"><?php echo html_escape($result['outcome']); ?></span><?php } ?>
                                            <?php } else { ?><span class="text-muted"><?php echo $status_label === 'Completed' ? 'Awaiting release' : '—'; ?></span><?php } ?>
                                        </td>
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
.online-assessment-list .content-header h1,.online-assessment-list .box-title{max-width:100%;white-space:normal;overflow-wrap:anywhere}
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
    .online-assessment-list .dataTables_wrapper .dt-buttons{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px}
    .online-assessment-list .assessment-table-wrap{overflow-x:visible}
    .online-assessment-list .online-assessment-table{display:block;width:100%!important;min-width:0;border:0;background:transparent}
    .online-assessment-list .online-assessment-table thead{display:none}
    .online-assessment-list .online-assessment-table tbody,.online-assessment-list .online-assessment-table tr,.online-assessment-list .online-assessment-table td{display:block;width:100%!important}
    .online-assessment-list .online-assessment-table tr{margin-bottom:12px;border:1px solid #dce2e7;border-radius:6px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.04);overflow:hidden}
    .online-assessment-list .online-assessment-table td{position:relative;min-height:42px;padding:10px 10px 10px 42%!important;border:0!important;border-top:1px solid #edf0f2!important;text-align:left!important;overflow-wrap:anywhere}
    .online-assessment-list .online-assessment-table td:first-child{border-top:0!important}
    .online-assessment-list .online-assessment-table td:before{content:attr(data-label);position:absolute;left:10px;top:10px;width:36%;color:#596570;font-weight:600;line-height:1.35}
    .online-assessment-list .online-assessment-table .assessment-action{padding-left:10px!important}
    .online-assessment-list .online-assessment-table .assessment-action:before{position:static;display:block;width:auto;margin-bottom:7px}
    .online-assessment-list .assessment-action .btn{width:100%;min-height:42px;padding:9px 12px;white-space:normal}
}
@media (max-width:380px){
    .online-assessment-list .content-header h1{font-size:20px}
}
</style>
