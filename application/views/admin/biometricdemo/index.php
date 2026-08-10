<?php
$decision_class = function ($status) {
    switch ($status) {
        case 'accepted':
            return 'success';
        case 'duplicate':
            return 'warning';
        case 'quarantined':
            return 'warning';
        case 'rejected':
            return 'danger';
        default:
            return 'default';
    }
};

$provider_class = 'success';
if (!empty($result) && (int) $result['provider']['http_status'] >= 400) {
    $provider_class = (int) $result['provider']['http_status'] === 429 ? 'warning' : 'danger';
}

$scenario_descriptions = array();
foreach ($scenarios as $scenario_key => $scenario_details) {
    $scenario_descriptions[$scenario_key] = $scenario_details['description'];
}
?>

<style>
    .biometric-demo-flow {
        display: flex;
        align-items: stretch;
        gap: 8px;
        margin: 15px 0 20px;
    }

    .biometric-demo-flow .flow-step {
        flex: 1;
        min-width: 0;
        padding: 14px 10px;
        border: 1px solid #d2d6de;
        border-radius: 4px;
        background: #fff;
        text-align: center;
    }

    .biometric-demo-flow .flow-step i {
        display: block;
        margin-bottom: 7px;
        color: #3c8dbc;
        font-size: 25px;
    }

    .biometric-demo-flow .flow-arrow {
        align-self: center;
        color: #999;
        font-size: 20px;
    }

    .biometric-demo-stat {
        min-height: 94px;
        padding: 15px;
        border-left: 4px solid #3c8dbc;
        background: #f7f7f7;
        margin-bottom: 15px;
    }

    .biometric-demo-stat .stat-number {
        display: block;
        font-size: 25px;
        font-weight: 600;
        line-height: 1.2;
    }

    .biometric-demo-stat .stat-label {
        color: #666;
        font-size: 12px;
        text-transform: uppercase;
    }

    .biometric-demo-json {
        max-height: 430px;
        overflow: auto;
        padding: 12px;
        border-radius: 3px;
        background: #1f2630;
        color: #d8dee9;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .biometric-demo-synthetic {
        border-left-width: 5px;
    }

    .biometric-demo-status-line {
        margin-bottom: 7px;
    }

    @media (max-width: 767px) {
        .biometric-demo-flow {
            display: block;
        }

        .biometric-demo-flow .flow-step {
            margin-bottom: 7px;
        }

        .biometric-demo-flow .flow-arrow {
            display: none;
        }
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <h1>
            <i class="fa fa-id-card-o"></i> Biometric Attendance Demo
            <small>No physical device required</small>
        </h1>
    </section>

    <section class="content">
        <div class="alert alert-info biometric-demo-synthetic">
            <h4><i class="fa fa-shield"></i> Synthetic demonstration — no attendance is saved</h4>
            This page does not look up a real student, connect to a device, call the live biometric endpoint, or write to any school database. It safely previews how IN/OUT events should be handled.
        </div>

        <?php if (validation_errors()) { ?>
            <div class="alert alert-danger"><?php echo validation_errors(); ?></div>
        <?php } ?>

        <?php if (!empty($simulation_error)) { ?>
            <div class="alert alert-danger">
                <i class="fa fa-exclamation-triangle"></i>
                <?php echo html_escape($simulation_error); ?>
            </div>
        <?php } ?>

        <div class="biometric-demo-flow" aria-label="Biometric attendance demonstration flow">
            <div class="flow-step">
                <i class="fa fa-user"></i>
                <strong>1. Student</strong><br>
                <small>Synthetic identity</small>
            </div>
            <div class="flow-arrow"><i class="fa fa-angle-right"></i></div>
            <div class="flow-step">
                <i class="fa fa-id-card-o"></i>
                <strong>2. Terminal</strong><br>
                <small>Fixed IN or OUT device</small>
            </div>
            <div class="flow-arrow"><i class="fa fa-angle-right"></i></div>
            <div class="flow-step">
                <i class="fa fa-cloud"></i>
                <strong>3. Vendor API</strong><br>
                <small>ZKBio-style event</small>
            </div>
            <div class="flow-arrow"><i class="fa fa-angle-right"></i></div>
            <div class="flow-step">
                <i class="fa fa-random"></i>
                <strong>4. Validation</strong><br>
                <small>Map, deduplicate, quarantine</small>
            </div>
            <div class="flow-arrow"><i class="fa fa-angle-right"></i></div>
            <div class="flow-step">
                <i class="fa fa-calendar-check-o"></i>
                <strong>5. Preview</strong><br>
                <small>Entry and checkout summary</small>
            </div>
        </div>

        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-play-circle"></i> Run a demonstration</h3>
            </div>

            <form method="post" action="<?php echo site_url('admin/biometricdemo'); ?>" autocomplete="off">
                <input type="hidden" name="biometric_demo_token" value="<?php echo html_escape($biometric_demo_token); ?>">

                <div class="box-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="scenario">Scenario</label>
                                <select id="scenario" name="scenario" class="form-control">
                                    <?php foreach ($scenarios as $scenario_key => $scenario_details) { ?>
                                        <option value="<?php echo html_escape($scenario_key); ?>" <?php echo $form['scenario'] === $scenario_key ? 'selected' : ''; ?>>
                                            <?php echo html_escape($scenario_details['label']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                                <span id="scenario-description" class="help-block"></span>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="employee">Synthetic student code</label>
                                <input id="employee" name="employee" type="text" maxlength="64" class="form-control" value="<?php echo html_escape($form['employee']); ?>">
                                <span class="help-block">A label only; the student database is never searched.</span>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="event_date">Attendance date</label>
                                <input id="event_date" name="event_date" type="date" class="form-control" value="<?php echo html_escape($form['event_date']); ?>">
                                <span class="help-block">Interpreted in Africa/Lagos.</span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="entry_serial">IN terminal serial</label>
                                <input id="entry_serial" name="entry_serial" type="text" maxlength="64" class="form-control" value="<?php echo html_escape($form['entry_serial']); ?>">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="exit_serial">OUT terminal serial</label>
                                <input id="exit_serial" name="exit_serial" type="text" maxlength="64" class="form-control" value="<?php echo html_escape($form['exit_serial']); ?>">
                            </div>
                        </div>

                        <div class="col-md-4" id="delay-field">
                            <div class="form-group">
                                <label for="delay_seconds">Delayed-event wait (seconds)</label>
                                <input id="delay_seconds" name="delay_seconds" type="number" min="0" max="300" step="1" class="form-control" value="<?php echo html_escape($form['delay_seconds']); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="box-footer">
                    <p class="text-muted pull-left" style="margin:7px 0 0;">
                        <i class="fa fa-lock"></i> Authenticated, session-protected, and database-free.
                    </p>
                    <button type="submit" class="btn btn-primary pull-right">
                        <i class="fa fa-play"></i> Run demonstration
                    </button>
                    <div class="clearfix"></div>
                </div>
            </form>
        </div>

        <?php if (!empty($result)) { ?>
            <div id="demo-results" class="box box-<?php echo $provider_class; ?>">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="fa fa-flask"></i>
                        <?php echo html_escape($result['scenario_label']); ?>
                    </h3>
                    <span class="label label-<?php echo $provider_class; ?> pull-right">
                        Provider HTTP <?php echo (int) $result['provider']['http_status']; ?>
                    </span>
                </div>

                <div class="box-body">
                    <div class="alert alert-<?php echo $provider_class; ?>">
                        <strong><?php echo html_escape($result['description']); ?></strong><br>
                        <?php echo html_escape($result['expected_behaviour']); ?>
                    </div>

                    <div class="row">
                        <div class="col-sm-6 col-md-2">
                            <div class="biometric-demo-stat">
                                <span class="stat-number"><?php echo (int) $result['provider']['http_status']; ?></span>
                                <span class="stat-label">Provider status</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-2">
                            <div class="biometric-demo-stat">
                                <span class="stat-number"><?php echo (int) $result['counts']['received']; ?></span>
                                <span class="stat-label">Events received</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-2">
                            <div class="biometric-demo-stat">
                                <span class="stat-number text-green"><?php echo (int) $result['counts']['accepted']; ?></span>
                                <span class="stat-label">Accepted</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-2">
                            <div class="biometric-demo-stat">
                                <span class="stat-number text-yellow"><?php echo (int) $result['counts']['duplicates']; ?></span>
                                <span class="stat-label">Duplicates ignored</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-2">
                            <div class="biometric-demo-stat">
                                <span class="stat-number text-yellow"><?php echo (int) $result['counts']['quarantined']; ?></span>
                                <span class="stat-label">Quarantined</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-2">
                            <div class="biometric-demo-stat">
                                <span class="stat-number text-red"><?php echo (int) $result['counts']['rejected']; ?></span>
                                <span class="stat-label">Rejected</span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="box box-solid box-info">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-clock-o"></i> Expected attendance preview</h3>
                                </div>
                                <div class="box-body">
                                    <div class="biometric-demo-status-line">
                                        <strong>Student:</strong>
                                        <?php echo html_escape($result['attendance_preview']['student_code']); ?>
                                    </div>
                                    <div class="biometric-demo-status-line">
                                        <strong>Date:</strong>
                                        <?php echo $result['attendance_preview']['date'] === null ? '—' : html_escape($result['attendance_preview']['date']); ?>
                                    </div>
                                    <div class="biometric-demo-status-line">
                                        <strong>First entry:</strong>
                                        <?php echo $result['attendance_preview']['entry_time'] === null ? '—' : html_escape($result['attendance_preview']['entry_time']); ?>
                                    </div>
                                    <div class="biometric-demo-status-line">
                                        <strong>Final checkout:</strong>
                                        <?php echo $result['attendance_preview']['checkout_time'] === null ? '—' : html_escape($result['attendance_preview']['checkout_time']); ?>
                                    </div>
                                    <div class="biometric-demo-status-line">
                                        <strong>Outcome:</strong>
                                        <?php echo html_escape($result['attendance_preview']['status']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="box box-solid box-default">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-refresh"></i> Provider delivery sequence</h3>
                                </div>
                                <div class="box-body table-responsive no-padding">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Poll</th>
                                                <th>HTTP</th>
                                                <th>Events</th>
                                                <th>Explanation</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($result['delivery_batches'] as $batch) { ?>
                                                <tr>
                                                    <td><?php echo (int) $batch['poll']; ?></td>
                                                    <td><?php echo (int) $batch['http_status']; ?></td>
                                                    <td><?php echo (int) $batch['event_count']; ?></td>
                                                    <td><?php echo html_escape($batch['message']); ?></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($result['provider']['retry_message'])) { ?>
                        <div class="alert alert-warning">
                            <i class="fa fa-refresh"></i>
                            <strong>Retry guidance:</strong>
                            <?php echo html_escape($result['provider']['retry_message']); ?>
                        </div>
                    <?php } ?>

                    <div class="box box-solid box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-list-alt"></i> Synthetic device events and decisions</h3>
                        </div>
                        <div class="box-body table-responsive no-padding">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>Vendor ID</th>
                                        <th>Student code</th>
                                        <th>Punch time</th>
                                        <th>Direction</th>
                                        <th>Terminal</th>
                                        <th>Decision</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($result['events'])) { ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">No events were delivered in this scenario.</td>
                                        </tr>
                                    <?php } ?>

                                    <?php foreach ($result['events'] as $event) { ?>
                                        <tr>
                                            <td><?php echo html_escape(isset($event['id']) ? $event['id'] : '—'); ?></td>
                                            <td><?php echo html_escape(isset($event['emp_code']) ? $event['emp_code'] : '—'); ?></td>
                                            <td><?php echo html_escape(isset($event['punch_time']) ? $event['punch_time'] : '—'); ?></td>
                                            <td><?php echo html_escape($event['_demo_direction']); ?></td>
                                            <td>
                                                <?php echo html_escape(isset($event['terminal_alias']) ? $event['terminal_alias'] : '—'); ?><br>
                                                <small><?php echo html_escape(isset($event['terminal_sn']) ? $event['terminal_sn'] : '—'); ?></small>
                                            </td>
                                            <td>
                                                <span class="label label-<?php echo $decision_class($event['_demo_status']); ?>">
                                                    <?php echo html_escape(strtoupper($event['_demo_status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo html_escape($event['_demo_message']); ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="alert alert-success biometric-demo-synthetic">
                        <i class="fa fa-check-circle"></i>
                        <strong>Isolation confirmed:</strong>
                        persisted = <?php echo $result['persisted'] ? 'true' : 'false'; ?>,
                        database writes = <?php echo (int) $result['database_writes']; ?>.
                        <?php echo html_escape($result['safety_notice']); ?>
                    </div>

                    <details>
                        <summary><strong>Show complete synthetic JSON</strong></summary>
                        <pre class="biometric-demo-json"><?php echo html_escape(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                    </details>
                </div>
            </div>
        <?php } ?>

        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> What this demonstration does not prove</h3>
            </div>
            <div class="box-body">
                <p>This page demonstrates event shapes and expected software decisions. A physical pilot is still required to verify facial/fingerprint accuracy, liveness detection, enrollment quality, queue speed, firmware, device storage, power recovery, and the final production synchronizer.</p>
                <p class="no-margin">The current legacy <code>/biometric</code> receiver records only one Present row per student per day and cannot store proper checkout. This demo intentionally does not call that endpoint.</p>
            </div>
        </div>
    </section>
</div>

<script>
    (function () {
        var descriptions = <?php echo json_encode($scenario_descriptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var scenario = document.getElementById('scenario');
        var description = document.getElementById('scenario-description');
        var delayField = document.getElementById('delay-field');

        function updateScenarioHelp() {
            var selected = scenario.value;
            description.textContent = descriptions[selected] || '';
            delayField.style.opacity = selected === 'delayed' ? '1' : '0.55';
        }

        scenario.addEventListener('change', updateScenarioHelp);
        updateScenarioHelp();

        if (document.getElementById('demo-results')) {
            document.getElementById('demo-results').scrollIntoView({block: 'start'});
        }
    }());
</script>
