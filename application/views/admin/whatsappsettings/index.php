<div class="content-wrapper">
    <section class="content-header"><h1><i class="fa fa-whatsapp"></i> WhatsApp Settings — Twilio</h1></section>
    <section class="content">
        <?php echo $this->session->flashdata('msg'); ?>
        <?php if (!empty($settings_errors)) { ?><div class="alert alert-danger"><ul><?php foreach ($settings_errors as $error) { ?><li><?php echo html_escape($error); ?></li><?php } ?></ul>Re-enter any newly supplied Auth Tokens before saving again; tokens are cleared from this page for security.</div><?php } ?>
        <div class="alert alert-info">
            Start with <strong>Sandbox</strong> to test one phone before production. Sandbox blocks group and automatic school messages.
            Twilio and applicable WhatsApp template charges still apply, including Sandbox usage beyond trial allowances.
            <a href="https://www.twilio.com/en-us/whatsapp/pricing" target="_blank" rel="noopener">View pricing</a>.
        </div>
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Twilio configuration</h3></div>
            <form method="post" action="<?php echo site_url('admin/whatsappsettings'); ?>" autocomplete="off">
                <input type="hidden" name="whatsapp_nonce" value="<?php echo html_escape($whatsapp_nonce); ?>">
                <fieldset <?php echo empty($can_edit) ? 'disabled' : ''; ?>>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-4 form-group"><label for="environment">School environment</label>
                            <select id="environment" name="environment" class="form-control">
                                <option value="sandbox" <?php echo $config['environment'] === 'sandbox' ? 'selected' : ''; ?>>Sandbox — testing only</option>
                                <option value="production" <?php echo $config['environment'] === 'production' ? 'selected' : ''; ?>>Production — real school messages</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group"><label for="enabled">Enable school sending</label>
                            <select id="enabled" name="enabled" class="form-control"><option value="0" <?php echo !$config['enabled'] ? 'selected' : ''; ?>>No</option><option value="1" <?php echo $config['enabled'] ? 'selected' : ''; ?>>Yes</option></select>
                            <span class="help-block">Keep No while setting up. The test panel works with saved credentials while school sending is off.</span>
                        </div>
                        <div class="col-md-4 form-group"><label for="default_country_code">Default country code</label><input id="default_country_code" name="default_country_code" class="form-control" value="<?php echo html_escape($config['default_country_code']); ?>" placeholder="234"></div>
                    </div>
                    <div class="row">
                    <?php foreach (array('sandbox' => 'Sandbox', 'production' => 'Production') as $environment => $label) { $profile = $config[$environment]; ?>
                        <div class="col-md-6">
                            <h4><?php echo $label; ?> account</h4>
                            <p><?php echo $environment === 'sandbox' ? 'Activate Twilio WhatsApp testing, then join it from your test phone using the code shown in Twilio Console.' : 'Use a WhatsApp sender registered and approved on this Twilio account. Registration is completed in Twilio Console.'; ?></p>
                            <?php foreach (array('account_sid' => 'Account SID', 'auth_token' => 'Auth Token', 'from' => 'WhatsApp sender', 'test_recipient' => 'Test recipient', 'login_content_sid' => 'Login credential Content SID (optional)') as $field => $field_label) { $id = $environment . '_' . $field; ?>
                            <div class="form-group"><label for="<?php echo $id; ?>"><?php echo $label . ' ' . $field_label; ?></label>
                                <input id="<?php echo $id; ?>" name="<?php echo $id; ?>" class="form-control" type="<?php echo $field === 'auth_token' ? 'password' : 'text'; ?>" autocomplete="<?php echo $field === 'auth_token' ? 'new-password' : 'off'; ?>" value="<?php echo $field === 'auth_token' ? '' : html_escape($profile[$field]); ?>" placeholder="<?php echo $field === 'account_sid' ? 'AC…' : ($field === 'login_content_sid' ? 'HX…' : ($field === 'auth_token' ? '' : '+countrycode…')); ?>">
                                <?php if ($field === 'auth_token') { ?><span class="help-block"><?php echo !empty($profile['token_saved']) ? 'A token is stored. Leave blank to keep it for the same Account SID.' : ($environment === 'sandbox' ? 'Use the account Auth Token for real WhatsApp Sandbox delivery, not Twilio simulated Test Credentials.' : 'Use the Auth Token for the Twilio account that owns this production sender.'); ?></span><?php } ?>
                                <?php if ($field === 'from') { ?><span class="help-block">Copy the sender from Twilio using +countrycode format. The whatsapp: prefix is added automatically.</span><?php } ?>
                                <?php if ($field === 'test_recipient') { ?><span class="help-block">Tests can send only to this saved number. Sandbox recipients must join your testing environment first.</span><?php } ?>
                            </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    </div>
                    <hr><h4>Automatic login credentials</h4>
                    <div class="row">
                        <div class="col-md-4 form-group"><label for="auto_send_login_credentials">Auto-send login credentials</label><select id="auto_send_login_credentials" name="auto_send_login_credentials" class="form-control"><option value="0" <?php echo !$config['auto_send_login_credentials'] ? 'selected' : ''; ?>>No</option><option value="1" <?php echo $config['auto_send_login_credentials'] ? 'selected' : ''; ?>>Yes — production only</option></select></div>
                        <div class="col-md-4 form-group"><label>Credential recipients</label><br>
                        <?php foreach (array('parent' => 'Parents', 'student' => 'Students', 'staff' => 'Staff') as $value => $label) { ?><label class="checkbox-inline"><input type="checkbox" name="auto_credential_for[]" value="<?php echo $value; ?>" <?php echo in_array($value, explode(',', $config['auto_credential_for']), true) ? 'checked' : ''; ?>> <?php echo $label; ?></label><?php } ?></div>
                        <div class="col-md-4 form-group"><label for="login_template_parameters">Login template parameter order</label><input id="login_template_parameters" name="login_template_parameters" class="form-control" value="<?php echo html_escape($config['login_template_parameters']); ?>"><span class="help-block">Available: display_name,school_name,url,username,password. Match the numbered variables in the approved Twilio login Content SID.</span></div>
                    </div>
                </div>
                <div class="box-footer"><button name="action" value="save" class="btn btn-primary" type="submit">Save Twilio settings</button></div>
                </fieldset>
            </form>
        </div>
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Connection and one-recipient test</h3></div>
            <div class="box-body">
                <p><strong>Save configuration above first.</strong> This panel uses saved credentials and the saved test recipient for the selected environment. A connection check sends no message; a test send can incur Twilio charges.</p>
                <p>After joining the Sandbox, send a message from the test phone to open the 24-hour reply window, then test text. Outside that window use a pre-approved testing Content SID from Twilio. Production templates are managed in <strong>Messaging &gt; Content Template Builder</strong>.</p>
                <p><a href="https://www.twilio.com/docs/whatsapp/sandbox" target="_blank" rel="noopener">Twilio Sandbox instructions</a> · <a href="https://www.twilio.com/console" target="_blank" rel="noopener">Open Twilio Console</a></p>
                <?php if (!empty($last_test)) { ?><p id="last_test_status">Last test: <strong><?php echo html_escape($last_test['environment'] . ' / ' . $last_test['status']); ?></strong> — <?php echo html_escape($last_test['sid']); ?>. Accepted, queued and sent do not confirm phone delivery.</p><?php } ?>
                <form method="post" action="<?php echo site_url('admin/whatsappsettings'); ?>">
                    <input type="hidden" name="whatsapp_nonce" value="<?php echo html_escape($whatsapp_nonce); ?>">
                    <fieldset <?php echo empty($can_edit) ? 'disabled' : ''; ?>>
                    <div class="row">
                        <div class="col-md-6 form-group"><label for="test_environment">Test environment</label><select id="test_environment" name="test_environment" class="form-control"><option value="sandbox" <?php echo $test_environment === 'sandbox' ? 'selected' : ''; ?>>Sandbox</option><option value="production" <?php echo $test_environment === 'production' ? 'selected' : ''; ?>>Production</option></select></div>
                        <div class="col-md-6 form-group"><label for="test_type">Send using</label><select id="test_type" name="test_type" class="form-control"><option value="text">Text reply within 24 hours</option><option value="template">Twilio Content template</option></select></div>
                    </div>
                    <div class="form-group test-text"><label for="test_body">Test message</label><textarea id="test_body" name="test_body" class="form-control" rows="2">SchoolLift WhatsApp connection test.</textarea></div>
                    <div class="test-template" style="display:none">
                        <div class="form-group"><label for="test_content_sid">Test Content SID</label><input id="test_content_sid" name="test_content_sid" class="form-control" placeholder="HX followed by 32 hex characters"></div>
                        <div class="form-group"><label for="test_variables">Template values</label><textarea id="test_variables" name="test_variables" rows="3" class="form-control" placeholder="One value per line for {{1}}, {{2}}, …"></textarea><span class="help-block">Use literal test values in numbered order; leave blank for a template without variables.</span></div>
                    </div>
                    <button type="submit" name="action" value="check_connection" class="btn btn-default">Check connection</button>
                    <button type="submit" name="action" value="send_test" class="btn btn-primary">Send one test message</button>
                    <button type="submit" name="action" value="check_status" class="btn btn-default">Check last test status</button>
                    </fieldset>
                </form>
            </div>
        </div>
    </section>
</div>
<script>
$(function () {
    $('#test_type').on('change', function () {
        var template = $(this).val() === 'template';
        $('.test-template').toggle(template);
        $('.test-text').toggle(!template);
    });
});
</script>
