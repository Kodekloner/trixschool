<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$result_component_label = !empty($exam->target_component)
    ? strtoupper($exam->target_component)
    : ucwords(str_replace('_', ' ', $exam->purpose));
$can_edit_operations = $this->rbac->hasPrivilege('online_examination', 'can_edit');
$can_view_builder = $this->rbac->hasPrivilege('add_questions_in_exam', 'can_view');
?>
<?php $this->load->view('admin/onlineexam/_assessment_styles'); ?>
<div class="content-wrapper onlineexam-ui onlineexam-operations-page">
    <section class="content-header">
        <h1><i class="fa fa-desktop"></i> Online Examination <small>Assessment operations</small></h1>
    </section>
    <section class="content">
        <?php echo $this->session->flashdata('msg'); ?>
        <div class="box box-primary">
            <div class="box-header with-border assessment-box-header">
                <h3 class="box-title"><?php echo html_escape($exam->exam); ?></h3>
                <div class="box-tools pull-right">
                    <a class="btn btn-info btn-sm" href="<?php echo base_url('admin/onlineexam/analysis/' . $exam->id); ?>"><i class="fa fa-bar-chart"></i> Analysis</a>
                    <?php if ($can_view_builder) { ?><a class="btn btn-default btn-sm" href="<?php echo base_url('admin/onlineexam/builder/' . $exam->id); ?>"><i class="fa fa-sitemap"></i> Assessment builder</a><?php } ?>
                </div>
            </div>
            <div class="box-body">
                <p class="operations-meta">
                    <strong><?php echo html_escape($exam->session_name); ?></strong> &middot;
                    <?php echo html_escape(strtoupper($exam->term)); ?> Term &middot;
                    <?php echo html_escape($exam->class_name); ?> (<?php echo html_escape(implode(', ', (array) $exam->section_names)); ?>) &middot;
                    <?php echo html_escape($exam->subject_name); ?>
                    <span class="label label-info"><?php echo html_escape(ucwords(str_replace('_', ' ', $exam->lifecycle_status))); ?></span>
                </p>
                <div class="alert alert-info">
                    Completed scores are posted automatically to the configured result component (<strong><?php echo html_escape($result_component_label); ?></strong>).
                    This page never publishes the official report card; the existing Result Publication screen still controls student/parent visibility.
                </div>
                <div class="row">
                    <?php
                    $cards = array(
                        array('Candidates', $dashboard['candidates']['assigned'], 'users', 'aqua'),
                        array('Not started', $dashboard['candidates']['not_started'], 'clock-o', 'yellow'),
                        array('Live attempts', $dashboard['attempts']['live'], 'play-circle', 'green'),
                        array('Marking pending', $dashboard['marking']['attempts_pending'], 'pencil', 'yellow'),
                        array('Open incidents', $dashboard['incidents']['open'], 'exclamation-triangle', 'red'),
                        array('Posting conflicts', $dashboard['result_sync']['conflicts'], 'exchange', 'red'),
                    );
                    foreach ($cards as $card) { ?>
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="small-box bg-<?php echo $card[3]; ?>">
                                <div class="inner"><h3><?php echo (int) $card[1]; ?></h3><p><?php echo html_escape($card[0]); ?></p></div>
                                <div class="icon"><i class="fa fa-<?php echo $card[2]; ?>"></i></div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div class="box box-info">
            <div class="box-header with-border"><h3 class="box-title">Candidate roster, attendance and accommodations</h3></div>
            <div class="box-body table-responsive onlineexam-scroll" role="region" aria-label="Candidate roster and accommodations" tabindex="0">
                <table class="table table-striped table-bordered table-hover operations-wide-table">
                    <caption class="sr-only">Candidate roster, attendance and accommodations</caption>
                    <thead><tr><th scope="col">Candidate</th><th scope="col">Arm</th><th scope="col">Status</th><th scope="col">Official attempt</th><th scope="col" style="min-width:300px">Accommodation</th></tr></thead>
                    <tbody>
                    <?php foreach ($candidates as $candidate) { ?>
                        <tr>
                            <td><?php echo html_escape($candidate['student_name']); ?><br><small><?php echo html_escape($candidate['admission_no']); ?></small></td>
                            <td><?php echo html_escape($candidate['section']); ?></td>
                            <td><span class="label label-<?php echo $candidate['candidate_status'] === 'assigned' ? 'success' : 'default'; ?>"><?php echo html_escape(ucfirst($candidate['candidate_status'])); ?></span></td>
                            <td>
                                <?php if (!empty($candidate['latest_attempt_id'])) { ?>
                                    <small>#<?php echo (int) $candidate['latest_attempt_id']; ?> &middot; <?php echo html_escape(ucwords(str_replace('_', ' ', $candidate['latest_attempt_status']))); ?></small>
                                <?php } elseif ($candidate['candidate_status'] === 'assigned' && $can_edit_operations) { ?>
                                    <form method="post" action="<?php echo base_url('admin/onlineexam/operationEnsureAttempt/' . $exam->id); ?>" onsubmit="return confirm('Create and start this candidate’s one official attempt now? The start time and incident history will be recorded.');">
                                        <?php echo $this->customlib->getCSRF(); ?>
                                        <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                                        <input type="hidden" name="onlineexam_student_id" value="<?php echo (int) $candidate['onlineexam_student_id']; ?>">
                                        <button class="btn btn-xs btn-primary" type="submit"><i class="fa fa-play-circle"></i> Create official attempt</button>
                                    </form>
                                <?php } else { ?>—<?php } ?>
                            </td>
                            <td>
                                <?php if ($can_edit_operations) { ?>
                                <form class="operations-inline-form" method="post" action="<?php echo base_url('admin/onlineexam/operationAccommodation/' . $exam->id); ?>">
                                    <?php echo $this->customlib->getCSRF(); ?>
                                    <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                                    <input type="hidden" name="onlineexam_student_id" value="<?php echo (int) $candidate['onlineexam_student_id']; ?>">
                                    <div class="form-group"><label class="small" for="extra-time-<?php echo (int) $candidate['onlineexam_student_id']; ?>">Extra min</label> <input id="extra-time-<?php echo (int) $candidate['onlineexam_student_id']; ?>" class="form-control input-sm operations-extra-time" type="number" min="0" max="1440" name="extra_time_minutes" value="<?php echo (int) $candidate['extra_time_minutes']; ?>"></div>
                                    <label class="sr-only" for="accommodation-notes-<?php echo (int) $candidate['onlineexam_student_id']; ?>">Accommodation notes for <?php echo html_escape($candidate['student_name']); ?></label>
                                    <input id="accommodation-notes-<?php echo (int) $candidate['onlineexam_student_id']; ?>" class="form-control input-sm operations-note-field" name="notes" maxlength="5000" placeholder="Accommodation notes" value="<?php echo html_escape($candidate['accommodation_notes']); ?>">
                                    <button class="btn btn-default btn-sm" type="submit">Save</button>
                                </form>
                                <?php } else { ?>
                                    <strong>Extra time:</strong> <?php echo (int) $candidate['extra_time_minutes']; ?> min
                                    <?php if (trim((string) $candidate['accommodation_notes']) !== '') { ?><br><small><?php echo nl2br(html_escape($candidate['accommodation_notes'])); ?></small><?php } ?>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($candidates)) { ?><tr><td colspan="5" class="text-center text-muted">No candidates are assigned. Use Assign/View to build the roster.</td></tr><?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="box box-warning">
            <div class="box-header with-border"><h3 class="box-title">Attempts and answer review</h3></div>
            <div class="box-body table-responsive onlineexam-scroll" role="region" aria-label="Attempts and answer review" tabindex="0">
                <table class="table table-striped table-bordered operations-table">
                    <caption class="sr-only">Attempts and answer-review status</caption>
                    <thead><tr><th scope="col">Candidate</th><th scope="col">Attempt</th><th scope="col">Status</th><th scope="col">Saved/submitted</th><th scope="col">Theory review</th><th scope="col">Result sync</th><th scope="col">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($attempts as $attempt) { ?>
                        <tr>
                            <td><?php echo html_escape($attempt['student_name']); ?><br><small><?php echo html_escape($attempt['admission_no'] . ' · ' . $attempt['section']); ?></small></td>
                            <td>#<?php echo (int) $attempt['attempt_no']; ?><br><small>revision <?php echo (int) $attempt['revision']; ?></small></td>
                            <td><span class="label label-<?php echo $attempt['status'] === 'completed' ? 'success' : ($attempt['status'] === 'voided' ? 'default' : 'warning'); ?>"><?php echo html_escape(ucwords(str_replace('_', ' ', $attempt['status']))); ?></span></td>
                            <td><small>Saved: <?php echo html_escape($attempt['last_saved_at']); ?><br>Submitted: <?php echo html_escape($attempt['submitted_at']); ?></small></td>
                            <td><?php echo (int) $attempt['pending_manual_answers']; ?> answer(s)<br><?php echo (int) $attempt['pending_manual_papers']; ?> paper(s)</td>
                            <td><?php echo (int) $attempt['sync_conflicts']; ?> conflict(s)</td>
                            <td class="attempt-actions-cell">
                                <a class="btn btn-primary btn-xs" href="<?php echo base_url('admin/onlineexam/attemptmarking/' . $exam->id . '/' . $attempt['id']); ?>"><i class="fa fa-pencil"></i> Review</a>
                                <?php if ($can_edit_operations && $attempt['status'] !== 'voided') { ?>
                                    <form class="attempt-void-form" method="post" action="<?php echo base_url('admin/onlineexam/operationVoidAttempt/' . $exam->id); ?>" onsubmit="return confirm('Void this attempt? Its incident and audit history will be retained.');">
                                        <?php echo $this->customlib->getCSRF(); ?>
                                        <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                                        <input type="hidden" name="attempt_id" value="<?php echo (int) $attempt['id']; ?>">
                                        <label class="sr-only" for="void-reason-<?php echo (int) $attempt['id']; ?>">Reason for voiding attempt <?php echo (int) $attempt['attempt_no']; ?></label>
                                        <input id="void-reason-<?php echo (int) $attempt['id']; ?>" type="text" name="reason" required maxlength="5000" placeholder="Void reason" class="form-control input-sm">
                                        <button class="btn btn-danger btn-xs" type="submit">Void</button>
                                    </form>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($attempts)) { ?><tr><td colspan="7" class="text-center text-muted">No candidate has started and no paper attempt has been prepared.</td></tr><?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="box box-danger">
                        <div class="box-header with-border"><h3 class="box-title">Incident register</h3></div>
                        <div class="box-body">
                            <?php if ($can_edit_operations) { ?>
                        <form method="post" action="<?php echo base_url('admin/onlineexam/operationIncident/' . $exam->id); ?>">
                            <?php echo $this->customlib->getCSRF(); ?>
                            <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                            <div class="row">
                                <div class="form-group col-sm-4"><label for="incident-attempt">Attempt (optional)</label><select id="incident-attempt" class="form-control" name="attempt_id"><option value="">Assessment-wide</option><?php foreach ($attempts as $attempt) { ?><option value="<?php echo (int) $attempt['id']; ?>">#<?php echo (int) $attempt['id']; ?> — <?php echo html_escape($attempt['student_name']); ?></option><?php } ?></select></div>
                                <div class="form-group col-sm-4"><label for="incident-type">Type</label><input id="incident-type" class="form-control" name="incident_type" required pattern="[a-zA-Z0-9_-]{2,50}" placeholder="network_disruption"></div>
                                <div class="form-group col-sm-4"><label for="incident-severity">Severity</label><select id="incident-severity" class="form-control" name="severity"><option>info</option><option>warning</option><option>critical</option></select></div>
                            </div>
                            <div class="form-group"><label for="incident-details">Details</label><textarea id="incident-details" class="form-control" name="details" required maxlength="20000"></textarea></div>
                            <button class="btn btn-danger" type="submit">Record incident</button>
                        </form>
                        <hr>
                            <?php } ?>
                        <?php foreach ($incidents as $incident) { ?>
                            <div class="callout callout-<?php echo $incident['status'] === 'resolved' ? 'success' : ($incident['severity'] === 'critical' ? 'danger' : 'warning'); ?>">
                                <h5>#<?php echo (int) $incident['id']; ?> <?php echo html_escape(ucwords(str_replace('_', ' ', $incident['incident_type']))); ?> <small><?php echo html_escape($incident['student_name']); ?></small></h5>
                                <p><strong>Severity:</strong> <?php echo html_escape(ucfirst($incident['severity'])); ?> &middot; <strong>Status:</strong> <?php echo html_escape(ucfirst($incident['status'])); ?></p>
                                <p><?php echo nl2br(html_escape($incident['details'])); ?></p>
                                <?php if ($incident['status'] === 'open' && $can_edit_operations) { ?>
                                    <form class="incident-resolution-form" method="post" action="<?php echo base_url('admin/onlineexam/operationResolveIncident/' . $exam->id); ?>">
                                        <?php echo $this->customlib->getCSRF(); ?>
                                        <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                                        <input type="hidden" name="incident_id" value="<?php echo (int) $incident['id']; ?>">
                                        <label class="sr-only" for="resolution-note-<?php echo (int) $incident['id']; ?>">Resolution note for incident <?php echo (int) $incident['id']; ?></label>
                                        <input id="resolution-note-<?php echo (int) $incident['id']; ?>" class="form-control input-sm" name="resolution_note" required maxlength="5000" placeholder="Resolution note">
                                        <button class="btn btn-success btn-sm" type="submit">Resolve</button>
                                    </form>
                                <?php } elseif ($incident['status'] === 'resolved') { ?><span class="label label-success">Resolved</span><?php } ?>
                            </div>
                        <?php } ?>
                        <?php if (empty($incidents)) { ?><p class="text-muted">No incidents recorded.</p><?php } ?>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="box box-default">
                    <div class="box-header with-border"><h3 class="box-title">Automatic result-posting ledger</h3></div>
                    <div class="box-body table-responsive onlineexam-scroll" role="region" aria-label="Automatic result-posting ledger" tabindex="0">
                        <table class="table table-bordered table-condensed operations-table">
                            <caption class="sr-only">Automatic result-posting ledger</caption>
                            <thead><tr><th scope="col">Candidate</th><th scope="col">Result component</th><th scope="col">Status</th><th scope="col">Posted/current</th><th scope="col">Reason</th></tr></thead>
                            <tbody>
                            <?php foreach ($sync_rows as $row) { ?>
                                <tr class="<?php echo $row['status'] === 'conflict' ? 'danger' : ($row['status'] === 'posted' ? 'success' : 'warning'); ?>">
                                    <td><?php echo html_escape($row['student_name']); ?><br><small>attempt #<?php echo (int) $row['attempt_no']; ?></small></td>
                                    <td><?php echo html_escape(!empty($row['target_field']) ? strtoupper($row['target_field']) : $result_component_label); ?></td>
                                    <td><?php echo html_escape($row['status']); ?></td>
                                    <td><?php echo html_escape($row['applied_value']); ?><br><small>previous: <?php echo html_escape($row['previous_value']); ?></small></td>
                                    <td>
                                        <?php echo html_escape($row['conflict_reason']); ?>
                                        <?php if ($can_edit_operations && in_array($row['status'], array('conflict', 'error'), true)) { ?>
                                            <form class="sync-action-form" method="post" action="<?php echo base_url('admin/onlineexam/operationRetrySync/' . $exam->id); ?>">
                                                <?php echo $this->customlib->getCSRF(); ?>
                                                <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                                                <input type="hidden" name="attempt_id" value="<?php echo (int) $row['attempt_id']; ?>">
                                                <button class="btn btn-default btn-xs" type="submit"><i class="fa fa-repeat"></i> Retry after resolving result</button>
                                            </form>
                                        <?php } ?>
                                        <?php if ($can_edit_operations && $row['status'] === 'conflict' && in_array($row['adapter'], array('standard_component', 'holiday_assessment'), true)) { ?>
                                            <form class="sync-action-form" method="post" action="<?php echo base_url('admin/onlineexam/operationAuthorizeSyncReplacement/' . $exam->id); ?>" onsubmit="return confirm('Replace the reviewed existing score? This explicit decision will be audited.');">
                                                <?php echo $this->customlib->getCSRF(); ?>
                                                <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                                                <input type="hidden" name="sync_id" value="<?php echo (int) $row['id']; ?>">
                                                <label class="sr-only" for="override-reason-<?php echo (int) $row['id']; ?>">Reason this result slot may be replaced</label>
                                                <input id="override-reason-<?php echo (int) $row['id']; ?>" class="form-control input-sm" name="override_reason" maxlength="1000" required placeholder="Reason this slot may be replaced">
                                                <button class="btn btn-danger btn-xs" type="submit"><i class="fa fa-check"></i> Authorize reviewed replacement</button>
                                            </form>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            <?php if (empty($sync_rows)) { ?><tr><td colspan="5" class="text-center text-muted">No posting activity yet.</td></tr><?php } ?>
                            </tbody>
                        </table>
                        <p class="help-block">A conflict means an unrelated manual value was found and was deliberately not overwritten. Resolve or clear that value in its existing result-entry screen, then retry here.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
