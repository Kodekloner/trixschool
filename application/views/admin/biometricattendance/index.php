<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$settings = is_array($settings) ? $settings : array();
$dashboard = is_array($dashboard) ? $dashboard : array();
$pageItems = function ($value) {
    return isset($value['items']) && is_array($value['items']) ? $value['items'] : (is_array($value) ? $value : array());
};
$devicesList = $pageItems($devices);
$mappingList = $pageItems($mappings);
$integrationList = $pageItems($integrations);
$eventList = $pageItems($events);
$dayList = $pageItems($days);
$exceptionList = $pageItems($exceptions);
$auditList = $pageItems($audit);
$stationList = $pageItems($stations);
$credentialList = $pageItems($qr_credentials);
$punchStateMappings = isset($punch_state_mappings) && is_array($punch_state_mappings) ? $punch_state_mappings : array();
$canAddBiometric = !empty($can_add_biometric);
$canEditBiometric = !empty($can_edit_biometric);
$mode = isset($settings['mode']) ? $settings['mode'] : 'disabled';
$modeClass = $mode === 'live' ? 'success' : ($mode === 'shadow' ? 'warning' : ($mode === 'simulation' ? 'info' : 'default'));
$liveReadiness = isset($live_readiness) && is_array($live_readiness) ? $live_readiness : array('ready' => false, 'problems' => array());
$csrfField = '<input type="hidden" name="biometric_token" value="' . html_escape($biometric_token) . '">';
?>
<style>
.bio-mode-banner{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:15px 18px;border-radius:4px;margin-bottom:18px;background:#f5f7fa;border-left:5px solid #777}.bio-mode-banner.mode-live{border-color:#00a65a}.bio-mode-banner.mode-shadow{border-color:#f39c12}.bio-mode-banner.mode-simulation{border-color:#00c0ef}.bio-stat{min-height:92px}.bio-stat .inner h3{font-size:28px}.bio-table-wrap{overflow-x:auto}.bio-table-wrap table{min-width:760px}.bio-code{font-family:Consolas,Monaco,monospace;word-break:break-all}.bio-required{color:#dd4b39}.bio-watermark{background:#e8f7fc;border:1px solid #b7e7f7;color:#14657b;padding:12px;border-radius:4px;margin-bottom:15px}.bio-token{background:#111;color:#8ff58f;padding:12px;border-radius:4px;word-break:break-all}.bio-tab-help{color:#666;margin-bottom:18px}.bio-direction label{margin-right:22px;font-size:16px}.bio-empty{padding:22px;text-align:center;color:#777}.bio-health-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:5px;background:#bbb}.bio-health-dot.ok{background:#00a65a}.bio-health-dot.bad{background:#dd4b39}.bio-scan-result{display:flex;align-items:center;gap:18px;padding:16px;margin-bottom:18px;border:2px solid #ddd;border-radius:6px;background:#fff}.bio-scan-result.accepted{border-color:#00a65a;background:#f1fbf5}.bio-scan-result.rejected,.bio-scan-result.quarantined{border-color:#dd4b39;background:#fff5f5}.bio-scan-result img{width:100px;height:100px;object-fit:cover;border-radius:6px;border:1px solid #ddd}.bio-checklist{list-style:none;padding-left:0}.bio-checklist li{padding:5px 0}.bio-checklist .fa-check-circle{color:#00a65a}.bio-checklist .fa-times-circle{color:#dd4b39}@media(max-width:767px){.bio-mode-banner{display:block}.bio-mode-banner .label{display:inline-block;margin-top:8px}.nav-tabs>li{float:none}.bio-stat{min-height:80px}.content-header h1{font-size:21px}.bio-scan-result{display:block;text-align:center}.bio-scan-result img{margin-bottom:10px}}
</style>

<div class="content-wrapper">
    <section class="content-header">
        <h1><i class="fa fa-id-badge"></i> Biometric Attendance <small>single-terminal IN/OUT workflow</small></h1>
    </section>
    <section class="content">
        <?php if (!empty($message)) { echo $message; } ?>
        <div class="bio-mode-banner mode-<?php echo html_escape($mode); ?>">
            <div><strong>Operating mode:</strong> <?php echo ucfirst(html_escape($mode)); ?><br><small>The server controls this mode; event payloads cannot override it.</small></div>
            <span class="label label-<?php echo $modeClass; ?> label-lg"><?php echo strtoupper(html_escape($mode)); ?></span>
        </div>

        <?php if ($mode === 'simulation') { ?>
            <div class="bio-watermark"><strong>Simulation is active.</strong> Events use the production validation and session pipeline but are excluded from official attendance, payroll, notifications, and legacy attendance tables.</div>
        <?php } ?>

        <div class="row">
            <?php
            $stats = array(
                array('Entries today', 'entries_today', 'bg-green', 'sign-in'),
                array('Checkouts today', 'checkouts_today', 'bg-aqua', 'sign-out'),
                array('Missing checkout', 'missing_checkout', 'bg-yellow', 'clock-o'),
                array('Open exceptions', 'open_exceptions', 'bg-red', 'warning'),
            );
            foreach ($stats as $stat) { ?>
                <div class="col-lg-3 col-sm-6 col-xs-12"><div class="small-box bio-stat <?php echo $stat[2]; ?>"><div class="inner"><h3><?php echo (int) (isset($dashboard[$stat[1]]) ? $dashboard[$stat[1]] : 0); ?></h3><p><?php echo $stat[0]; ?></p></div><div class="icon"><i class="fa fa-<?php echo $stat[3]; ?>"></i></div></div></div>
            <?php } ?>
        </div>

        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs" role="tablist">
                <li class="active"><a href="#bio-overview" data-toggle="tab"><i class="fa fa-dashboard"></i> Overview</a></li>
                <?php if ($mode !== 'live') { ?><li><a href="#bio-terminal" data-toggle="tab"><i class="fa fa-desktop"></i> Test Terminal</a></li><?php } ?>
                <li><a href="#bio-setup" data-toggle="tab"><i class="fa fa-cogs"></i> Setup & Devices</a></li>
                <li><a href="#bio-mappings" data-toggle="tab"><i class="fa fa-exchange"></i> Identity Mapping</a></li>
                <li><a href="#bio-events" data-toggle="tab"><i class="fa fa-list-alt"></i> Events</a></li>
                <li><a href="#bio-days" data-toggle="tab"><i class="fa fa-calendar-check-o"></i> Daily Sessions</a></li>
                <li><a href="#bio-exceptions" data-toggle="tab"><i class="fa fa-warning"></i> Reconciliation</a></li>
                <li><a href="#bio-scanner" data-toggle="tab"><i class="fa fa-qrcode"></i> QR Scanner</a></li>
                <li><a href="#bio-audit" data-toggle="tab"><i class="fa fa-history"></i> Audit</a></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane active" id="bio-overview">
                    <div class="row">
                        <div class="col-md-6"><div class="box box-info"><div class="box-header with-border"><h3 class="box-title">System health</h3></div><div class="box-body">
                            <dl class="dl-horizontal">
                                <dt>Gateway last seen</dt><dd><?php echo html_escape(isset($dashboard['last_gateway_seen']) && $dashboard['last_gateway_seen'] ? $dashboard['last_gateway_seen'] : 'Never'); ?></dd>
                                <dt>Last committed cursor</dt><dd class="bio-code"><?php echo html_escape(isset($dashboard['last_cursor']) && $dashboard['last_cursor'] ? $dashboard['last_cursor'] : 'None'); ?></dd>
                                <dt>Active devices</dt><dd><?php echo (int) (isset($dashboard['active_devices']) ? $dashboard['active_devices'] : count($devicesList)); ?></dd>
                                <dt>Simulated events</dt><dd><?php echo (int) (isset($dashboard['simulated_events']) ? $dashboard['simulated_events'] : 0); ?></dd>
                            </dl>
                        </div></div></div>
                        <div class="col-md-6"><div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Production sequence</h3></div><div class="box-body"><ol><li>Map the active student and staff codes.</li><li>Submit individual IN and OUT events in Simulation.</li><li>Connect ZKBio Time and run physical events in Shadow.</li><li>Resolve every exception and confirm checkout behaviour.</li><li>Enable Live with administrator password confirmation.</li></ol><a class="btn btn-default btn-sm" href="<?php echo site_url('report/biometric_attlog'); ?>"><i class="fa fa-archive"></i> Legacy biometric history</a></div></div></div>
                    </div>
                </div>

                <?php if ($mode !== 'live') { ?><div class="tab-pane" id="bio-terminal">
                    <p class="bio-tab-help">This submits one deliberate punch through the real processing service. Select IN or OUT exactly as a user will on the single physical terminal.</p>
                    <?php if (!empty($terminal_result)) { ?><div class="alert alert-info"><strong>Last result</strong><pre><?php echo html_escape(json_encode($terminal_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre></div><?php } ?>
                    <?php if ($canAddBiometric) { ?><form action="<?php echo site_url('admin/biometricattendance/simulate'); ?>" method="post" class="form-horizontal">
                        <?php echo $csrfField; ?>
                        <div class="form-group"><label class="col-sm-3 control-label">Person code <span class="bio-required">*</span></label><div class="col-sm-6"><input type="text" name="person_code" maxlength="100" required class="form-control" placeholder="Admission number or employee ID"><p class="help-block">An unmapped code will be quarantined, not silently linked.</p></div></div>
                        <div class="form-group"><label class="col-sm-3 control-label">Direction <span class="bio-required">*</span></label><div class="col-sm-6 bio-direction"><label><input type="radio" name="direction" value="IN" checked> <i class="fa fa-sign-in text-green"></i> Check In</label><label><input type="radio" name="direction" value="OUT"> <i class="fa fa-sign-out text-aqua"></i> Check Out</label></div></div>
                        <div class="form-group"><label class="col-sm-3 control-label">Verification method</label><div class="col-sm-4"><select name="verification_method" class="form-control"><option value="face">Face</option><option value="fingerprint">Fingerprint</option><option value="card">RFID card</option><option value="pin">PIN</option><option value="qr">Trusted QR</option></select></div></div>
                        <div class="form-group"><label class="col-sm-3 control-label">Occurred at</label><div class="col-sm-4"><input type="datetime-local" name="occurred_at" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>"></div></div>
                        <div class="form-group"><label class="col-sm-3 control-label">External event ID</label><div class="col-sm-6"><input type="text" name="external_event_id" maxlength="191" class="form-control bio-code" placeholder="Leave blank to generate one"><p class="help-block">Reuse a previous ID intentionally to verify duplicate handling.</p></div></div>
                        <div class="form-group"><div class="col-sm-offset-3 col-sm-6"><button type="submit" class="btn btn-primary" <?php echo $mode !== 'simulation' ? 'disabled' : ''; ?>><i class="fa fa-hand-pointer-o"></i> Submit one punch</button><?php if ($mode !== 'simulation') { ?><span class="help-block">Switch to Simulation mode before using the Test Terminal.</span><?php } ?></div></div>
                    </form>
                    <?php if (!empty($last_simulation) && !empty($last_simulation['external_event_id'])) { ?>
                        <form action="<?php echo site_url('admin/biometricattendance/resendsimulation'); ?>" method="post" class="text-center">
                            <?php echo $csrfField; ?>
                            <button class="btn btn-default" <?php echo $mode !== 'simulation' ? 'disabled' : ''; ?>><i class="fa fa-repeat"></i> Resend event <?php echo html_escape($last_simulation['external_event_id']); ?></button>
                            <p class="help-block">This sends the identical stored event again and must return <code>duplicate</code>.</p>
                        </form>
                    <?php } } else { ?><div class="alert alert-warning">Your role can view biometric records but cannot submit Test Terminal events.</div><?php } ?>
                </div><?php } ?>

                <div class="tab-pane" id="bio-setup">
                    <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Go-live checklist</h3></div><div class="box-body"><p>SchoolLift blocks Live mode until every safety check below passes. Run the physical terminal in Shadow mode before enabling official writes.</p><ul class="bio-checklist"><?php foreach (array('integration' => 'Active gateway integration and credential', 'physical_device' => 'Enabled physical bidirectional terminal', 'punch_states' => 'Distinct IN and OUT punch states', 'identity_mapping' => 'At least one active identity mapping', 'shadow_event' => 'A physical event received in Shadow mode', 'exceptions_clear' => 'No open biometric exceptions') as $check => $label) { $ok = !empty($liveReadiness['checks'][$check]); ?><li><i class="fa <?php echo $ok ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i> <?php echo html_escape($label); ?></li><?php } ?></ul><?php if (!empty($liveReadiness['ready'])) { ?><div class="alert alert-success">Technical preflight passed. Complete the recommended five-day Shadow pilot and confirm your password before Live.</div><?php } else { ?><div class="alert alert-warning">Live is locked until this checklist is complete.</div><?php } ?></div></div>
                    <div class="row"><div class="col-md-6"><div class="box box-warning"><div class="box-header with-border"><h3 class="box-title">Operating mode and rules</h3></div><div class="box-body">
                        <?php if ($canEditBiometric) { ?><form method="post" action="<?php echo site_url('admin/biometricattendance/settings'); ?>">
                            <?php echo $csrfField; ?>
                            <div class="form-group"><label>Mode</label><select name="mode" class="form-control"><option value="disabled" <?php echo $mode === 'disabled' ? 'selected' : ''; ?>>Disabled</option><option value="simulation" <?php echo $mode === 'simulation' ? 'selected' : ''; ?>>Simulation</option><option value="shadow" <?php echo $mode === 'shadow' ? 'selected' : ''; ?>>Shadow</option><option value="live" <?php echo $mode === 'live' ? 'selected' : ''; ?>>Live</option></select></div>
                            <div class="row"><div class="col-sm-6"><div class="form-group"><label>Student late after</label><input name="student_late_after" type="time" step="1" class="form-control" value="<?php echo html_escape(isset($settings['student_late_after']) ? $settings['student_late_after'] : '08:00:00'); ?>"></div></div><div class="col-sm-6"><div class="form-group"><label>Staff late after</label><input name="staff_late_after" type="time" step="1" class="form-control" value="<?php echo html_escape(isset($settings['staff_late_after']) ? $settings['staff_late_after'] : '08:00:00'); ?>"></div></div></div>
                            <div class="form-group"><label>Timezone</label><input name="timezone" class="form-control" value="<?php echo html_escape(isset($settings['timezone']) ? $settings['timezone'] : 'Africa/Lagos'); ?>"></div>
                            <div class="row">
                                <div class="col-sm-6"><div class="form-group"><label>Student Present type</label><select name="student_present_type_id" class="form-control"><?php foreach ($student_attendance_types as $type) { ?><option value="<?php echo (int) $type['id']; ?>" <?php echo (int) $settings['student_present_type_id'] === (int) $type['id'] ? 'selected' : ''; ?>><?php echo html_escape($type['type']); ?></option><?php } ?></select></div></div>
                                <div class="col-sm-6"><div class="form-group"><label>Student Late type</label><select name="student_late_type_id" class="form-control"><?php foreach ($student_attendance_types as $type) { ?><option value="<?php echo (int) $type['id']; ?>" <?php echo (int) $settings['student_late_type_id'] === (int) $type['id'] ? 'selected' : ''; ?>><?php echo html_escape($type['type']); ?></option><?php } ?></select></div></div>
                                <div class="col-sm-6"><div class="form-group"><label>Staff Present type</label><select name="staff_present_type_id" class="form-control"><?php foreach ($staff_attendance_types as $type) { ?><option value="<?php echo (int) $type['id']; ?>" <?php echo (int) $settings['staff_present_type_id'] === (int) $type['id'] ? 'selected' : ''; ?>><?php echo html_escape($type['type']); ?></option><?php } ?></select></div></div>
                                <div class="col-sm-6"><div class="form-group"><label>Staff Late type</label><select name="staff_late_type_id" class="form-control"><?php foreach ($staff_attendance_types as $type) { ?><option value="<?php echo (int) $type['id']; ?>" <?php echo (int) $settings['staff_late_type_id'] === (int) $type['id'] ? 'selected' : ''; ?>><?php echo html_escape($type['type']); ?></option><?php } ?></select></div></div>
                            </div>
                            <div class="row"><div class="col-sm-6"><div class="form-group"><label>Retention (days)</label><input type="number" min="30" max="3650" name="retention_days" class="form-control" value="<?php echo (int) $settings['retention_days']; ?>"></div></div><div class="col-sm-6"><div class="form-group"><label>Maximum event age (days)</label><input type="number" min="1" max="365" name="max_event_age_days" class="form-control" value="<?php echo (int) $settings['max_event_age_days']; ?>"></div></div></div>
                            <div class="checkbox"><input type="hidden" name="project_students" value="0"><label><input type="checkbox" name="project_students" value="1" <?php echo !empty($settings['project_students']) ? 'checked' : ''; ?>> Project valid student days in Live mode</label></div>
                            <div class="checkbox"><input type="hidden" name="project_staff" value="0"><label><input type="checkbox" name="project_staff" value="1" <?php echo !empty($settings['project_staff']) ? 'checked' : ''; ?>> Project valid staff days in Live mode</label></div>
                            <div class="form-group"><label>Current password <small>(required to enter Live)</small></label><input name="current_password" type="password" autocomplete="current-password" class="form-control"></div>
                            <button class="btn btn-warning"><i class="fa fa-save"></i> Save mode and rules</button>
                        </form><?php } else { ?><p>Your role can view configuration but cannot change it.</p><?php } ?>
                    </div></div></div>
                    <div class="col-md-6"><div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">ZKBio integration credential</h3></div><div class="box-body">
                        <?php if (!empty($issued_token)) { ?><p><strong>Copy this token now. It will not be shown again.</strong></p><div class="bio-token"><?php echo html_escape($issued_token); ?></div><hr><?php } ?>
                        <?php if ($canEditBiometric) { ?><form method="post" action="<?php echo site_url('admin/biometricattendance/integration'); ?>"><?php echo $csrfField; ?><div class="form-group"><label>Name</label><input name="name" required maxlength="100" class="form-control" value="School Gate ZKBio Time"></div><div class="form-group"><label>Provider</label><select name="provider" class="form-control"><option value="zkbio_time">ZKBio Time / BioTime</option></select></div><button class="btn btn-primary"><i class="fa fa-key"></i> Create integration and token</button></form><?php } ?>
                        <?php if ($integrationList) { ?><hr><?php foreach ($integrationList as $integration) {
                            $integrationId = (int) $integration['id'];
                            $inState = '0';
                            $outState = '1';
                            foreach (isset($punchStateMappings[$integrationId]) ? $punchStateMappings[$integrationId] : array() as $stateRow) {
                                if ($stateRow['direction'] === 'IN') { $inState = $stateRow['raw_punch_state']; }
                                if ($stateRow['direction'] === 'OUT') { $outState = $stateRow['raw_punch_state']; }
                            }
                        ?>
                            <div class="well well-sm">
                                <p><span class="bio-health-dot <?php echo !empty($integration['is_active']) ? 'ok' : 'bad'; ?>"></span><strong><?php echo html_escape($integration['name']); ?></strong> <small class="bio-code"><?php echo html_escape($integration['token_prefix']); ?></small></p>
                                <?php if ($canEditBiometric) { ?><form method="post" action="<?php echo site_url('admin/biometricattendance/punchstates'); ?>" class="form-inline">
                                    <?php echo $csrfField; ?><input type="hidden" name="integration_id" value="<?php echo $integrationId; ?>">
                                    <label>IN state <input name="in_state" required maxlength="32" class="form-control input-sm bio-code" value="<?php echo html_escape($inState); ?>"></label>
                                    <label>OUT state <input name="out_state" required maxlength="32" class="form-control input-sm bio-code" value="<?php echo html_escape($outState); ?>"></label>
                                    <button class="btn btn-sm btn-default">Save states</button>
                                </form>
                                <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">
                                    <form method="post" action="<?php echo site_url('admin/biometricattendance/rotateintegration'); ?>"><?php echo $csrfField; ?><input type="hidden" name="integration_id" value="<?php echo $integrationId; ?>"><button class="btn btn-xs btn-warning" onclick="return confirm('Rotate this token? The gateway stops authenticating until its configuration is updated.');"><i class="fa fa-refresh"></i> Rotate token</button></form>
                                    <form method="post" action="<?php echo site_url('admin/biometricattendance/toggleintegration'); ?>"><?php echo $csrfField; ?><input type="hidden" name="integration_id" value="<?php echo $integrationId; ?>"><input type="hidden" name="is_active" value="<?php echo !empty($integration['is_active']) ? 0 : 1; ?>"><button class="btn btn-xs <?php echo !empty($integration['is_active']) ? 'btn-danger' : 'btn-success'; ?>"><?php echo !empty($integration['is_active']) ? 'Disable' : 'Enable'; ?></button></form>
                                </div><?php } ?>
                            </div>
                        <?php } } ?>
                    </div></div></div></div>
                    <div class="box box-info"><div class="box-header with-border"><h3 class="box-title">Single bidirectional terminal</h3></div><div class="box-body">
                        <?php if ($canEditBiometric) { ?><form class="form-inline" method="post" action="<?php echo site_url('admin/biometricattendance/device'); ?>"><?php echo $csrfField; ?><div class="form-group"><label class="sr-only">Serial</label><input name="serial_number" required maxlength="100" class="form-control" placeholder="Terminal serial"></div> <div class="form-group"><input name="name" required maxlength="100" class="form-control" placeholder="Terminal name"></div> <div class="form-group"><input name="location" maxlength="191" class="form-control" placeholder="Location"></div> <div class="form-group"><select name="integration_id" class="form-control" required><option value="">Choose integration</option><?php foreach ($integrationList as $integration) { ?><option value="<?php echo (int) $integration['id']; ?>"><?php echo html_escape($integration['name']); ?></option><?php } ?></select></div> <button class="btn btn-info"><i class="fa fa-plus"></i> Register device</button></form><hr><?php } ?>
                        <div class="bio-table-wrap"><table class="table table-striped"><thead><tr><th>Serial</th><th>Name</th><th>Location</th><th>Direction</th><th>Virtual</th><th>Status</th><th>Last seen</th><th></th></tr></thead><tbody><?php foreach ($devicesList as $device) { ?><tr><td class="bio-code"><?php echo html_escape($device['serial_number']); ?></td><td><?php echo html_escape($device['name']); ?></td><td><?php echo html_escape($device['location']); ?></td><td><span class="label label-primary">bidirectional</span></td><td><?php echo !empty($device['is_virtual']) ? 'Yes' : 'No'; ?></td><td><?php echo !empty($device['is_active']) ? 'Enabled' : 'Disabled'; ?></td><td><?php echo html_escape($device['last_seen_at']); ?></td><td><?php if ($canEditBiometric) { ?><form method="post" action="<?php echo site_url('admin/biometricattendance/toggledevice'); ?>"><?php echo $csrfField; ?><input type="hidden" name="device_id" value="<?php echo (int) $device['id']; ?>"><input type="hidden" name="is_active" value="<?php echo !empty($device['is_active']) ? 0 : 1; ?>"><button class="btn btn-xs <?php echo !empty($device['is_active']) ? 'btn-danger' : 'btn-success'; ?>"><?php echo !empty($device['is_active']) ? 'Disable' : 'Enable'; ?></button></form><?php } ?></td></tr><?php } ?></tbody></table></div>
                    </div></div>
                    <?php if ($canEditBiometric) { ?><div class="box box-danger"><div class="box-header with-border"><h3 class="box-title">Simulation data cleanup</h3></div><div class="box-body"><p>Use this only after demonstration evidence has been exported. It never removes configuration, mappings, credentials, or audit logs.</p><form method="post" action="<?php echo site_url('admin/biometricattendance/purgesimulation'); ?>" class="form-inline"><?php echo $csrfField; ?><input name="confirmation" required class="form-control bio-code" placeholder="PURGE_SIMULATION_DATA"> <button class="btn btn-danger" onclick="return confirm('Permanently remove simulation events, sessions, and their exceptions?');">Purge simulation data</button></form></div></div><?php } ?>
                </div>

                <div class="tab-pane" id="bio-mappings">
                    <p class="bio-tab-help">External device codes must map explicitly. Student subject IDs are current student-session IDs; staff subject IDs are staff IDs.</p>
                    <?php if ($canEditBiometric) { ?><form class="form-inline" method="post" action="<?php echo site_url('admin/biometricattendance/mapping'); ?>"><?php echo $csrfField; ?><div class="form-group"><select id="bio-mapping-type" name="subject_type" class="form-control"><option value="student">Student</option><option value="staff">Staff</option></select></div> <div class="form-group" style="min-width:320px"><select id="bio-mapping-subject" name="subject_id" required class="form-control" style="width:100%"></select></div> <div class="form-group"><input name="external_person_code" maxlength="100" required class="form-control" placeholder="Device person code"></div> <button class="btn btn-primary"><i class="fa fa-link"></i> Save mapping</button></form>
                    <form class="pull-right form-inline" method="post" action="<?php echo site_url('admin/biometricattendance/bulkpreview'); ?>" style="margin-top:-34px"><?php echo $csrfField; ?><select name="subject_type" class="form-control"><option value="student">Students</option><option value="staff">Staff</option></select> <button class="btn btn-default"><i class="fa fa-users"></i> Preview roster codes</button></form><div class="clearfix"></div><?php } ?><hr>
                    <?php if (!empty($mapping_preview) && !empty($mapping_preview['success'])) { ?>
                        <div class="box box-warning"><div class="box-header with-border"><h3 class="box-title"><?php echo ucfirst(html_escape($mapping_preview['subject_type'])); ?> mapping preview</h3></div><div class="box-body">
                            <p><span class="label label-success"><?php echo (int) $mapping_preview['totals']['create']; ?> create</span> <span class="label label-default"><?php echo (int) $mapping_preview['totals']['skip']; ?> already mapped</span> <span class="label label-danger"><?php echo (int) $mapping_preview['totals']['conflict']; ?> conflicts</span></p>
                            <div class="bio-table-wrap" style="max-height:360px;overflow-y:auto"><table class="table table-condensed"><thead><tr><th>Action</th><th>Person</th><th>Code</th><th>Reason</th></tr></thead><tbody><?php foreach ($mapping_preview['items'] as $item) { ?><tr><td><span class="label label-<?php echo $item['action'] === 'create' ? 'success' : ($item['action'] === 'conflict' ? 'danger' : 'default'); ?>"><?php echo html_escape($item['action']); ?></span></td><td><?php echo html_escape($item['subject_name']); ?> <small>#<?php echo (int) $item['subject_id']; ?></small></td><td class="bio-code"><?php echo html_escape($item['external_person_code']); ?></td><td><?php echo html_escape($item['reason']); ?></td></tr><?php } ?></tbody></table></div>
                            <?php if ($canEditBiometric && (int) $mapping_preview['totals']['create'] > 0) { ?><form method="post" action="<?php echo site_url('admin/biometricattendance/bulkseed'); ?>"><?php echo $csrfField; ?><input type="hidden" name="subject_type" value="<?php echo html_escape($mapping_preview['subject_type']); ?>"><input type="hidden" name="preview_hash" value="<?php echo html_escape($mapping_preview['preview_hash']); ?>"><button class="btn btn-warning" onclick="return confirm('Create only the unambiguous mappings shown in this preview?');"><i class="fa fa-check"></i> Confirm <?php echo (int) $mapping_preview['totals']['create']; ?> mappings</button></form><?php } ?>
                        </div></div>
                    <?php } ?>
                    <div class="bio-table-wrap"><table class="table table-hover"><thead><tr><th>Type</th><th>Subject ID</th><th>External code</th><th>Valid from</th><th>Valid until</th><th>Active</th><th></th></tr></thead><tbody><?php foreach ($mappingList as $mapping) { ?><tr><td><?php echo html_escape($mapping['subject_type']); ?></td><td><?php echo (int) $mapping['subject_id']; ?></td><td class="bio-code"><?php echo html_escape($mapping['external_person_code']); ?></td><td><?php echo html_escape($mapping['valid_from']); ?></td><td><?php echo html_escape($mapping['valid_until']); ?></td><td><?php echo !empty($mapping['is_active']) ? 'Yes' : 'No'; ?></td><td><?php if ($canEditBiometric) { ?><form method="post" action="<?php echo site_url('admin/biometricattendance/togglemapping'); ?>"><?php echo $csrfField; ?><input type="hidden" name="mapping_id" value="<?php echo (int) $mapping['id']; ?>"><input type="hidden" name="is_active" value="<?php echo !empty($mapping['is_active']) ? 0 : 1; ?>"><button class="btn btn-xs <?php echo !empty($mapping['is_active']) ? 'btn-danger' : 'btn-success'; ?>"><?php echo !empty($mapping['is_active']) ? 'Disable' : 'Enable'; ?></button></form><?php } ?></td></tr><?php } ?><?php if (!$mappingList) { ?><tr><td colspan="7" class="bio-empty">No identity mappings yet.</td></tr><?php } ?></tbody></table></div>
                </div>

                <div class="tab-pane" id="bio-events"><div class="bio-table-wrap"><table class="table table-condensed table-striped"><thead><tr><th>ID</th><th>Time</th><th>Code</th><th>Serial</th><th>Direction</th><th>Method</th><th>Source/mode</th><th>Status</th><th>Reason</th></tr></thead><tbody><?php foreach ($eventList as $event) { ?><tr><td><?php echo (int) $event['id']; ?></td><td><?php echo html_escape($event['occurred_at_local']); ?></td><td class="bio-code"><?php echo html_escape($event['person_code']); ?></td><td class="bio-code"><?php echo html_escape($event['device_serial']); ?></td><td><?php echo html_escape($event['direction']); ?></td><td><?php echo html_escape($event['verification_method']); ?></td><td><?php echo html_escape($event['source'] . ' / ' . $event['operating_mode']); ?></td><td><?php echo html_escape($event['processing_status']); ?></td><td><?php echo html_escape($event['failure_code']); ?></td></tr><?php } ?><?php if (!$eventList) { ?><tr><td colspan="9" class="bio-empty">No events have been received.</td></tr><?php } ?></tbody></table></div></div>

                <div class="tab-pane" id="bio-days"><div class="bio-table-wrap"><table class="table table-striped"><thead><tr><th>Date</th><th>Type</th><th>Subject</th><th>Scope</th><th>First IN</th><th>Last OUT</th><th>Duration</th><th>Status</th><th>Projection</th></tr></thead><tbody><?php foreach ($dayList as $day) { ?><tr><td><?php echo html_escape($day['attendance_date']); ?></td><td><?php echo html_escape($day['subject_type']); ?></td><td><?php echo (int) $day['subject_id']; ?></td><td><?php echo html_escape($day['record_scope']); ?></td><td><?php echo html_escape($day['first_in_at']); ?></td><td><?php echo html_escape($day['last_out_at']); ?></td><td><?php echo $day['duration_minutes'] === null ? '—' : (int) $day['duration_minutes'] . ' min'; ?></td><td><?php echo html_escape($day['attendance_status']); ?><?php echo !empty($day['missing_checkout']) ? ' / missing checkout' : ''; ?></td><td><?php echo html_escape($day['projection_status']); ?></td></tr><?php } ?><?php if (!$dayList) { ?><tr><td colspan="9" class="bio-empty">No daily attendance sessions yet.</td></tr><?php } ?></tbody></table></div></div>

                <div class="tab-pane" id="bio-exceptions"><div class="bio-table-wrap"><table class="table table-hover"><thead><tr><th>ID</th><th>Created</th><th>Code</th><th>Message</th><th>Status</th><th>Resolution</th></tr></thead><tbody><?php foreach ($exceptionList as $exception) { ?><tr><td><?php echo (int) $exception['id']; ?></td><td><?php echo html_escape($exception['created_at']); ?></td><td class="bio-code"><?php echo html_escape($exception['exception_code']); ?></td><td><?php echo html_escape($exception['message']); ?></td><td><?php echo html_escape($exception['status']); ?></td><td><?php if ($exception['status'] === 'open') { ?><form method="post" action="<?php echo site_url('admin/biometricattendance/resolveexception'); ?>" class="form-inline"><?php echo $csrfField; ?><input type="hidden" name="exception_id" value="<?php echo (int) $exception['id']; ?>"><select name="action" class="form-control input-sm"><option value="retry">Retry processing</option><option value="resolve">Resolve</option><option value="ignore">Ignore</option></select><input name="note" required maxlength="500" class="form-control input-sm" placeholder="Reason"><button class="btn btn-xs btn-warning">Resolve</button></form><?php } else { echo html_escape($exception['resolution_action']); } ?></td></tr><?php } ?><?php if (!$exceptionList) { ?><tr><td colspan="6" class="bio-empty">No exceptions.</td></tr><?php } ?></tbody></table></div></div>

                <div class="tab-pane" id="bio-scanner">
                    <?php if (empty($qr_encryption_ready)) { ?><div class="alert alert-danger"><strong>QR credential issuance is not ready.</strong> Configure a protected <code>BIOMETRIC_QR_ENCRYPTION_KEY</code> of at least 32 random characters on the server, then restart PHP. Credentials are never issued without encrypted reprint storage.</div><?php } ?>
                    <?php if (!empty($scan_result)) {
                        $scanStatus = isset($scan_result['status']) ? strtolower($scan_result['status']) : 'rejected';
                        $scanSubject = isset($scan_result['subject']) && is_array($scan_result['subject']) ? $scan_result['subject'] : array();
                    ?><div class="bio-scan-result <?php echo html_escape($scanStatus); ?>">
                        <?php if (!empty($scanSubject['subject_type']) && !empty($scanSubject['subject_id'])) { ?><img src="<?php echo site_url('admin/biometricattendance/subjectphoto/' . rawurlencode($scanSubject['subject_type']) . '/' . (int) $scanSubject['subject_id']); ?>" alt="Cardholder photograph"><?php } ?>
                        <div><h3 style="margin-top:0"><?php echo strtoupper(html_escape($scanStatus)); ?> — <?php echo html_escape(isset($scan_result['direction']) ? $scan_result['direction'] : ''); ?></h3>
                            <p><strong><?php echo html_escape(isset($scanSubject['name']) ? $scanSubject['name'] : 'Credential not resolved'); ?></strong><?php if (!empty($scanSubject['code'])) { ?> <span class="bio-code">(<?php echo html_escape($scanSubject['code']); ?>)</span><?php } ?></p>
                            <p><?php echo html_escape(isset($scanSubject['subject_type']) ? ucfirst($scanSubject['subject_type']) : ''); ?><?php if (!empty($scanSubject['class']) || !empty($scanSubject['section'])) { ?> — <?php echo html_escape(trim((isset($scanSubject['class']) ? $scanSubject['class'] : '') . ' ' . (isset($scanSubject['section']) ? $scanSubject['section'] : ''))); ?><?php } ?></p>
                            <p><?php echo html_escape(isset($scan_result['message']) ? $scan_result['message'] : ''); ?></p><strong>Gate operator: visually compare this photograph with the cardholder before admitting them.</strong>
                        </div>
                    </div><?php } ?>
                    <?php if (!in_array($mode, array('simulation', 'live'), true)) { ?><div class="alert alert-warning">QR scanning is disabled in <?php echo html_escape($mode); ?> mode. Use Simulation for safe tests or Live for official gate attendance.</div><?php } ?>
                    <div class="row"><div class="col-md-6"><div class="box box-success"><div class="box-header with-border"><h3 class="box-title">Trusted gate scanner</h3></div><div class="box-body"><p>Select direction before every scan. The credential is submitted over the authenticated staff session; opening a QR as a URL cannot mark attendance.</p>
                        <?php if (!$stationList) { ?><div class="alert alert-warning">Register this browser or gate phone as a trusted scanner station before scanning.</div><?php } elseif (empty($assigned_station)) { ?><div class="alert alert-warning">Assign this signed-in browser session to one trusted station before scanning.</div><?php } else { ?><div class="alert alert-success"><i class="fa fa-shield"></i> Assigned station: <strong><?php echo html_escape($assigned_station['name']); ?></strong> — <?php echo html_escape($assigned_station['location']); ?></div><?php } ?>
                        <?php if ($canEditBiometric && $stationList) { ?><form method="post" action="<?php echo site_url('admin/biometricattendance/assignstation'); ?>" class="form-inline"><?php echo $csrfField; ?><select name="station_uuid" class="form-control" required><?php foreach ($stationList as $station) { ?><option value="<?php echo html_escape($station['station_uuid']); ?>" <?php echo !empty($assigned_station) && $assigned_station['station_uuid'] === $station['station_uuid'] ? 'selected' : ''; ?>><?php echo html_escape($station['name']); ?> — <?php echo html_escape($station['location']); ?></option><?php } ?></select> <button class="btn btn-default"><i class="fa fa-link"></i> Assign this browser</button></form><hr><?php } ?>
                        <?php if ($canAddBiometric) { ?><form id="bio-scan-form" method="post" action="<?php echo site_url('admin/biometricattendance/scan'); ?>"><?php echo $csrfField; ?><div class="form-group bio-direction"><label><input type="radio" name="direction" value="IN" checked> Check In</label><label><input type="radio" name="direction" value="OUT"> Check Out</label></div><div class="form-group"><label>Credential</label><input id="bio-qr-token" name="credential" required autocomplete="off" class="form-control bio-code" placeholder="Scan or paste SLQR credential"></div><button class="btn btn-success" <?php echo empty($assigned_station) || !in_array($mode, array('simulation', 'live'), true) ? 'disabled' : ''; ?>><i class="fa fa-qrcode"></i> Submit scan</button> <button type="button" id="bio-camera" class="btn btn-default" <?php echo !in_array($mode, array('simulation', 'live'), true) ? 'disabled' : ''; ?>><i class="fa fa-camera"></i> Use camera</button><p id="bio-camera-status" class="help-block"></p></form><?php } ?>
                        <?php if ($canEditBiometric) { ?><hr><form class="form-inline" method="post" action="<?php echo site_url('admin/biometricattendance/station'); ?>"><?php echo $csrfField; ?><input name="name" required maxlength="100" class="form-control" placeholder="Scanner name"> <input name="location" maxlength="191" class="form-control" placeholder="Gate/location"> <button class="btn btn-default"><i class="fa fa-mobile"></i> Register scanner</button></form><?php } ?>
                    </div></div></div>
                    <div class="col-md-6"><div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Issue or revoke card credentials</h3></div><div class="box-body">
                        <?php if ($canEditBiometric) { ?><form method="post" action="<?php echo site_url('admin/biometricattendance/issuecredential'); ?>"><?php echo $csrfField; ?><div class="row"><div class="col-xs-4"><select id="bio-credential-type" name="subject_type" class="form-control"><option value="student">Student</option><option value="staff">Staff</option></select></div><div class="col-xs-6"><select id="bio-credential-subject" name="subject_id" required class="form-control" style="width:100%"></select></div><div class="col-xs-2"><button class="btn btn-primary" <?php echo empty($qr_encryption_ready) ? 'disabled' : ''; ?>>Issue</button></div></div></form><?php } ?>
                        <?php if (!empty($issued_credential)) { ?><hr><p><strong>Credential issued. Print this through the ID Design Studio.</strong></p><div class="bio-token"><?php echo html_escape(isset($issued_credential['token']) ? $issued_credential['token'] : ''); ?></div><?php } ?>
                        <hr><div class="bio-table-wrap"><table class="table table-condensed"><thead><tr><th>UUID</th><th>Type</th><th>Subject</th><th>Issued</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($credentialList as $credential) { ?><tr><td class="bio-code"><?php echo html_escape($credential['credential_uuid']); ?></td><td><?php echo html_escape($credential['subject_type']); ?></td><td><?php echo (int) $credential['subject_id']; ?></td><td><?php echo html_escape($credential['issued_at']); ?></td><td><?php echo !empty($credential['is_active']) ? 'Active' : 'Revoked'; ?></td><td><?php if ($canEditBiometric && !empty($credential['is_active'])) { ?><form method="post" action="<?php echo site_url('admin/biometricattendance/revokecredential'); ?>"><?php echo $csrfField; ?><input type="hidden" name="credential_uuid" value="<?php echo html_escape($credential['credential_uuid']); ?>"><input name="reason" required maxlength="255" class="form-control input-sm" placeholder="Reason"><button class="btn btn-xs btn-danger">Revoke</button></form><?php } ?></td></tr><?php } ?><?php if (!$credentialList) { ?><tr><td colspan="6" class="bio-empty">No card credentials issued.</td></tr><?php } ?></tbody></table></div>
                    </div></div></div></div>
                </div>

                <div class="tab-pane" id="bio-audit"><div class="bio-table-wrap"><table class="table table-condensed"><thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Entity</th><th>ID</th><th>IP</th></tr></thead><tbody><?php foreach ($auditList as $row) { ?><tr><td><?php echo html_escape($row['created_at']); ?></td><td><?php echo html_escape($row['actor_id']); ?></td><td><?php echo html_escape($row['action']); ?></td><td><?php echo html_escape($row['entity_type']); ?></td><td><?php echo html_escape($row['entity_id']); ?></td><td><?php echo html_escape($row['ip_address']); ?></td></tr><?php } ?><?php if (!$auditList) { ?><tr><td colspan="6" class="bio-empty">No audit records.</td></tr><?php } ?></tbody></table></div></div>
            </div>
        </div>
    </section>
</div>
<script>
(function ($) {
    $(function () {
    function configureSubjectSelect(typeSelector, subjectSelector) {
        var type = $(typeSelector), subject = $(subjectSelector);
        if (!type.length || !subject.length || !$.fn.select2) return;
        function activate() {
            if (subject.hasClass('select2-hidden-accessible')) subject.select2('destroy');
            subject.empty().select2({
                width: '100%', minimumInputLength: 1, placeholder: 'Search name or code',
                ajax: {url: function(){ return '<?php echo site_url('admin/biometricattendance/subjectsearch'); ?>/' + type.val(); }, dataType:'json', delay:250, data:function(params){return {q:params.term||''};}, processResults:function(data){return data;}, cache:false}
            });
        }
        type.on('change', activate); activate();
    }
    configureSubjectSelect('#bio-mapping-type', '#bio-mapping-subject');
    configureSubjectSelect('#bio-credential-type', '#bio-credential-subject');
    var hash = window.location.hash;
    if (hash && $('.nav-tabs a[href="' + hash + '"]').length) $('.nav-tabs a[href="' + hash + '"]').tab('show');
    $('.nav-tabs a').on('shown.bs.tab', function (event) { if (history.replaceState) history.replaceState(null, '', event.target.hash); });
    $('#bio-camera').on('click', async function () {
        var status = $('#bio-camera-status');
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices) { status.text('Camera QR detection is not available in this browser. Use the credential field or a supported HTTPS browser.'); return; }
        try {
            var detector = new BarcodeDetector({formats:['qr_code']});
            var stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}});
            var video = document.createElement('video'); video.srcObject = stream; video.setAttribute('playsinline',''); await video.play(); status.text('Camera active — point it at the card QR.');
            var tries = 0; var timer = setInterval(async function () { tries++; try { var codes = await detector.detect(video); if (codes.length) { $('#bio-qr-token').val(codes[0].rawValue); clearInterval(timer); stream.getTracks().forEach(function(t){t.stop();}); status.text('Credential captured. Confirm direction, then submit.'); } else if (tries > 200) { clearInterval(timer); stream.getTracks().forEach(function(t){t.stop();}); status.text('No QR detected. Try again or enter it manually.'); } } catch (ignore) {} }, 150);
        } catch (error) { status.text('Camera could not start: ' + error.message); }
    });
    });
})(jQuery);
</script>
