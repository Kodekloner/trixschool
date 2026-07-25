<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
    <section class="content-header">
        <h1><i class="fa fa-desktop"></i> Online Examination <small>Nigerian assessment operations</small></h1>
    </section>
    <section class="content">
        <?php echo $this->session->flashdata('msg'); ?>
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><?php echo html_escape($exam->exam); ?></h3>
                <div class="box-tools pull-right">
                    <a class="btn btn-info btn-sm" href="<?php echo base_url('admin/onlineexam/analysis/' . $exam->id); ?>"><i class="fa fa-bar-chart"></i> Analysis</a>
                    <a class="btn btn-default btn-sm" href="<?php echo base_url('admin/onlineexam/builder/' . $exam->id); ?>"><i class="fa fa-sitemap"></i> Assessment builder</a>
                    <a class="btn btn-default btn-sm" target="_blank" href="<?php echo base_url('admin/onlineexam/printpaper/' . $exam->id); ?>"><i class="fa fa-print"></i> Print papers</a>
                </div>
            </div>
            <div class="box-body">
                <p>
                    <strong><?php echo html_escape($exam->session_name); ?></strong> &middot;
                    <?php echo html_escape(strtoupper($exam->term)); ?> Term &middot;
                    <?php echo html_escape($exam->class_name); ?> (<?php echo html_escape($exam->section_names); ?>) &middot;
                    <?php echo html_escape($exam->subject_name); ?>
                    <span class="label label-info"><?php echo html_escape(ucwords(str_replace('_', ' ', $exam->lifecycle_status))); ?></span>
                </p>
                <div class="alert alert-info">
                    Completed scores are posted automatically to <strong><?php echo html_escape(ucwords(str_replace('_', ' ', $exam->result_adapter))); ?></strong>.
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
            <div class="box-body table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead><tr><th>Candidate</th><th>Arm</th><th>Status</th><th>Attempt</th><th style="min-width:330px">Accommodation / authorized make-up</th></tr></thead>
                    <tbody>
                    <?php foreach ($candidates as $candidate) { ?>
                        <tr>
                            <td><?php echo html_escape($candidate['student_name']); ?><br><small><?php echo html_escape($candidate['admission_no']); ?></small></td>
                            <td><?php echo html_escape($candidate['section']); ?></td>
                            <td><span class="label label-<?php echo $candidate['candidate_status'] === 'assigned' ? 'success' : 'default'; ?>"><?php echo html_escape(ucfirst($candidate['candidate_status'])); ?></span></td>
                            <td>
                                <?php if ($candidate['candidate_status'] === 'assigned') { ?>
                                    <form method="post" action="<?php echo base_url('admin/onlineexam/operationEnsureAttempt/' . $exam->id); ?>">
                                        <?php echo $this->customlib->getCSRF(); ?>
                                        <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                                        <input type="hidden" name="onlineexam_student_id" value="<?php echo (int) $candidate['onlineexam_student_id']; ?>">
                                        <?php if (!empty($candidate['latest_attempt_id'])) { ?><small>#<?php echo (int) $candidate['latest_attempt_id']; ?> &middot; <?php echo html_escape($candidate['latest_attempt_status']); ?></small><br><?php } ?>
                                        <label class="small"><input type="checkbox" name="create_makeup" value="1"> use authorized make-up</label><br>
                                        <button class="btn btn-xs btn-primary" type="submit"><i class="fa fa-check-square-o"></i> Prepare/resume</button>
                                    </form>
                                <?php } else { ?>—<?php } ?>
                            </td>
                            <td>
                                <form class="form-inline" method="post" action="<?php echo base_url('admin/onlineexam/operationAccommodation/' . $exam->id); ?>">
                                    <?php echo $this->customlib->getCSRF(); ?>
                                    <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                                    <input type="hidden" name="onlineexam_student_id" value="<?php echo (int) $candidate['onlineexam_student_id']; ?>">
                                    <div class="form-group"><label class="small">Extra min</label> <input class="form-control input-sm" style="width:75px" type="number" min="0" max="1440" name="extra_time_minutes" value="<?php echo (int) $candidate['extra_time_minutes']; ?>"></div>
                                    <div class="form-group"><label class="small">Make-ups</label> <input class="form-control input-sm" style="width:65px" type="number" min="0" max="20" name="makeup_attempts" value="<?php echo (int) $candidate['makeup_attempts']; ?>"></div>
                                    <div class="form-group"><label class="small">Make-up closes</label> <input class="form-control input-sm" type="datetime-local" name="makeup_expires_at" value="<?php echo !empty($candidate['makeup_expires_at']) ? date('Y-m-d\TH:i', strtotime($candidate['makeup_expires_at'])) : ''; ?>"></div>
                                    <input class="form-control input-sm" style="width:150px" name="notes" maxlength="5000" placeholder="Accommodation notes" value="<?php echo html_escape($candidate['accommodation_notes']); ?>">
                                    <button class="btn btn-default btn-sm" type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($candidates)) { ?><tr><td colspan="5" class="text-center text-muted">No candidates are assigned. Use Assign/View to build the roster.</td></tr><?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="box box-warning">
            <div class="box-header with-border"><h3 class="box-title">Attempts and marking</h3></div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-bordered">
                    <thead><tr><th>Candidate</th><th>Attempt</th><th>Status</th><th>Saved/submitted</th><th>Manual work</th><th>Result sync</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($attempts as $attempt) { ?>
                        <tr>
                            <td><?php echo html_escape($attempt['student_name']); ?><br><small><?php echo html_escape($attempt['admission_no'] . ' · ' . $attempt['section']); ?></small></td>
                            <td>#<?php echo (int) $attempt['attempt_no']; ?><br><small>revision <?php echo (int) $attempt['revision']; ?></small></td>
                            <td><span class="label label-<?php echo $attempt['status'] === 'completed' ? 'success' : ($attempt['status'] === 'voided' ? 'default' : 'warning'); ?>"><?php echo html_escape(ucwords(str_replace('_', ' ', $attempt['status']))); ?></span></td>
                            <td><small>Saved: <?php echo html_escape($attempt['last_saved_at']); ?><br>Submitted: <?php echo html_escape($attempt['submitted_at']); ?></small></td>
                            <td><?php echo (int) $attempt['pending_manual_answers']; ?> answer(s)<br><?php echo (int) $attempt['pending_manual_papers']; ?> paper(s)</td>
                            <td><?php echo (int) $attempt['sync_conflicts']; ?> conflict(s)</td>
                            <td style="min-width:220px">
                                <a class="btn btn-primary btn-xs" href="<?php echo base_url('admin/onlineexam/attemptmarking/' . $exam->id . '/' . $attempt['id']); ?>"><i class="fa fa-pencil"></i> Mark/view</a>
                                <?php if ($attempt['status'] !== 'voided') { ?>
                                    <form method="post" action="<?php echo base_url('admin/onlineexam/operationVoidAttempt/' . $exam->id); ?>" style="display:inline" onsubmit="return confirm('Void this attempt? Its incident and audit history will be retained.');">
                                        <?php echo $this->customlib->getCSRF(); ?>
                                        <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                                        <input type="hidden" name="attempt_id" value="<?php echo (int) $attempt['id']; ?>">
                                        <input type="text" name="reason" required maxlength="5000" placeholder="Void reason" class="form-control input-sm" style="display:inline;width:105px">
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
            <div class="col-md-6">
                <div class="box box-danger">
                    <div class="box-header with-border"><h3 class="box-title">Incident register</h3></div>
                    <div class="box-body">
                        <form method="post" action="<?php echo base_url('admin/onlineexam/operationIncident/' . $exam->id); ?>">
                            <?php echo $this->customlib->getCSRF(); ?>
                            <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                            <div class="row">
                                <div class="form-group col-sm-4"><label>Attempt (optional)</label><select class="form-control" name="attempt_id"><option value="">Assessment-wide</option><?php foreach ($attempts as $attempt) { ?><option value="<?php echo (int) $attempt['id']; ?>">#<?php echo (int) $attempt['id']; ?> — <?php echo html_escape($attempt['student_name']); ?></option><?php } ?></select></div>
                                <div class="form-group col-sm-4"><label>Type</label><input class="form-control" name="incident_type" required pattern="[a-zA-Z0-9_-]{2,50}" placeholder="network_disruption"></div>
                                <div class="form-group col-sm-4"><label>Severity</label><select class="form-control" name="severity"><option>info</option><option>warning</option><option>critical</option></select></div>
                            </div>
                            <div class="form-group"><label>Details</label><textarea class="form-control" name="details" required maxlength="20000"></textarea></div>
                            <button class="btn btn-danger" type="submit">Record incident</button>
                        </form>
                        <hr>
                        <?php foreach ($incidents as $incident) { ?>
                            <div class="callout callout-<?php echo $incident['status'] === 'resolved' ? 'success' : ($incident['severity'] === 'critical' ? 'danger' : 'warning'); ?>">
                                <h5>#<?php echo (int) $incident['id']; ?> <?php echo html_escape(ucwords(str_replace('_', ' ', $incident['incident_type']))); ?> <small><?php echo html_escape($incident['student_name']); ?></small></h5>
                                <p><?php echo nl2br(html_escape($incident['details'])); ?></p>
                                <?php if ($incident['status'] === 'open') { ?>
                                    <form class="form-inline" method="post" action="<?php echo base_url('admin/onlineexam/operationResolveIncident/' . $exam->id); ?>">
                                        <?php echo $this->customlib->getCSRF(); ?>
                                        <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                                        <input type="hidden" name="incident_id" value="<?php echo (int) $incident['id']; ?>">
                                        <input class="form-control input-sm" style="width:75%" name="resolution_note" required maxlength="5000" placeholder="Resolution note">
                                        <button class="btn btn-success btn-sm" type="submit">Resolve</button>
                                    </form>
                                <?php } else { ?><span class="label label-success">Resolved</span><?php } ?>
                            </div>
                        <?php } ?>
                        <?php if (empty($incidents)) { ?><p class="text-muted">No incidents recorded.</p><?php } ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box box-default">
                    <div class="box-header with-border"><h3 class="box-title">Automatic result-posting ledger</h3></div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-condensed">
                            <thead><tr><th>Candidate</th><th>Destination</th><th>Status</th><th>Posted/current</th><th>Reason</th></tr></thead>
                            <tbody>
                            <?php foreach ($sync_rows as $row) { ?>
                                <tr class="<?php echo $row['status'] === 'conflict' ? 'danger' : ($row['status'] === 'posted' ? 'success' : 'warning'); ?>">
                                    <td><?php echo html_escape($row['student_name']); ?><br><small>attempt #<?php echo (int) $row['attempt_no']; ?></small></td>
                                    <td><?php echo html_escape($row['adapter']); ?><br><small><?php echo html_escape($row['target_table'] . ($row['target_field'] ? '.' . $row['target_field'] : '')); ?></small></td>
                                    <td><?php echo html_escape($row['status']); ?></td>
                                    <td><?php echo html_escape($row['applied_value']); ?><br><small>previous: <?php echo html_escape($row['previous_value']); ?></small></td>
                                    <td>
                                        <?php echo html_escape($row['conflict_reason']); ?>
                                        <?php if (in_array($row['status'], array('conflict', 'error'), true)) { ?>
                                            <form method="post" action="<?php echo base_url('admin/onlineexam/operationRetrySync/' . $exam->id); ?>" style="margin-top:5px">
                                                <?php echo $this->customlib->getCSRF(); ?>
                                                <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                                                <input type="hidden" name="attempt_id" value="<?php echo (int) $row['attempt_id']; ?>">
                                                <button class="btn btn-default btn-xs" type="submit"><i class="fa fa-repeat"></i> Retry after resolving destination</button>
                                            </form>
                                        <?php } ?>
                                        <?php if ($row['status'] === 'conflict' && $row['adapter'] === 'standard_component') { ?>
                                            <form method="post" action="<?php echo base_url('admin/onlineexam/operationAuthorizeSyncReplacement/' . $exam->id); ?>" style="margin-top:5px" onsubmit="return confirm('Replace the reviewed existing score? This explicit decision will be audited.');">
                                                <?php echo $this->customlib->getCSRF(); ?>
                                                <input type="hidden" name="onlineexam_workflow_token" value="<?php echo html_escape($workflow_csrf); ?>">
                                                <input type="hidden" name="sync_id" value="<?php echo (int) $row['id']; ?>">
                                                <input class="form-control input-sm" name="override_reason" maxlength="1000" required placeholder="Reason this slot may be replaced">
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
