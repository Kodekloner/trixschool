<div class="content-wrapper">
    <section class="content-header">
        <h1><i class="fa fa-whatsapp"></i> WhatsApp Settings</h1>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <?php echo $this->session->flashdata('msg'); ?>
                <?php if (!$table_exists) { ?>
                    <div class="alert alert-warning">
                        The <strong>whatsapp_config</strong> table does not exist yet. Run the WhatsApp config SQL migration in this school database before saving.
                    </div>
                <?php } ?>
                <?php if (validation_errors()) { ?>
                    <div class="alert alert-danger"><?php echo validation_errors(); ?></div>
                <?php } ?>
                <div class="box box-primary">
                    <div class="box-header ptbnull">
                        <h3 class="box-title titlefix">Provider Configuration</h3>
                    </div>
                    <div class="box-body">
                        <p>Connect Meta Cloud API, a managed 360dialog account, or a WhatsApp delivery webhook, then use <a href="<?php echo site_url('admin/mailsms/compose_whatsapp'); ?>">Communicate &gt; Send WhatsApp</a>.</p>
                        <p>Meta and 360dialog require an approved WhatsApp template to start a conversation. Text replies are available for 24 hours after the recipient messages the school. A provider acceptance does not confirm delivery to the phone.</p>
                    </div>
                    <form method="post" action="<?php echo site_url('admin/whatsappsettings'); ?>">
                        <div class="box-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Enable WhatsApp Sending</label>
                                        <select name="enabled" class="form-control">
                                            <option value="0" <?php echo empty($config['enabled']) ? 'selected' : ''; ?>>No</option>
                                            <option value="1" <?php echo !empty($config['enabled']) ? 'selected' : ''; ?>>Yes</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Provider</label>
                                        <select name="provider" id="whatsapp_provider" class="form-control">
                                            <option value="meta_cloud" <?php echo $config['provider'] == 'meta_cloud' ? 'selected' : ''; ?>>Meta Cloud API</option>
                                            <option value="360dialog" <?php echo $config['provider'] == '360dialog' ? 'selected' : ''; ?>>360dialog (managed provider)</option>
                                            <option value="custom_webhook" <?php echo $config['provider'] == 'custom_webhook' ? 'selected' : ''; ?>>Custom Webhook</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Default Country Code</label>
                                        <input type="text" name="default_country_code" class="form-control" value="<?php echo html_escape($config['default_country_code']); ?>" placeholder="234">
                                        <span class="help-block">Used to normalize local phone numbers such as 080...</span>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Auto-send Login Credentials</label>
                                        <select name="auto_send_login_credentials" class="form-control">
                                            <option value="0" <?php echo empty($config['auto_send_login_credentials']) ? 'selected' : ''; ?>>No</option>
                                            <option value="1" <?php echo !empty($config['auto_send_login_credentials']) ? 'selected' : ''; ?>>Yes</option>
                                        </select>
                                        <span class="help-block">Requires an approved WhatsApp template from your provider.</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <?php $auto_for = array_filter(array_map('trim', explode(',', $config['auto_credential_for']))); ?>
                                    <div class="form-group">
                                        <label>Credential Recipients</label><br>
                                        <label class="checkbox-inline"><input type="checkbox" name="auto_credential_for[]" value="parent" <?php echo in_array('parent', $auto_for) ? 'checked' : ''; ?>> Parents</label>
                                        <label class="checkbox-inline"><input type="checkbox" name="auto_credential_for[]" value="student" <?php echo in_array('student', $auto_for) ? 'checked' : ''; ?>> Students</label>
                                        <label class="checkbox-inline"><input type="checkbox" name="auto_credential_for[]" value="staff" <?php echo in_array('staff', $auto_for) ? 'checked' : ''; ?>> Staff</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Template Parameter Order</label>
                                        <input type="text" name="login_template_parameters" class="form-control" value="<?php echo html_escape($config['login_template_parameters']); ?>">
                                        <span class="help-block">Comma separated. Default: display_name,school_name,url,username,password</span>
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <h4 class="platform-fields">WhatsApp Platform Templates</h4>
                            <div class="row">
                                <div class="col-md-3 meta-only">
                                    <div class="form-group">
                                        <label>Graph API Version</label>
                                        <input type="text" name="meta_graph_api_version" class="form-control" value="<?php echo html_escape($config['meta_graph_api_version']); ?>" placeholder="v20.0">
                                    </div>
                                </div>
                                <div class="col-md-3 meta-only">
                                    <div class="form-group">
                                        <label>Phone Number ID</label>
                                        <input type="text" name="meta_phone_number_id" class="form-control" value="<?php echo html_escape($config['meta_phone_number_id']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3 platform-fields">
                                    <div class="form-group">
                                        <label>Login Credential Template Name</label>
                                        <input type="text" name="meta_template_name" class="form-control" value="<?php echo html_escape($config['meta_template_name']); ?>" placeholder="parent_login_credentials">
                                    </div>
                                </div>
                                <div class="col-md-3 platform-fields">
                                    <div class="form-group">
                                        <label>Template Language</label>
                                        <input type="text" name="meta_template_language" class="form-control" value="<?php echo html_escape($config['meta_template_language']); ?>" placeholder="en">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group meta-only">
                                <label>Access Token</label>
                                <textarea name="meta_access_token" class="form-control" rows="3"><?php echo html_escape($config['meta_access_token']); ?></textarea>
                                <span class="help-block"><?php echo !empty($credential_saved) && $config['provider'] === 'meta_cloud' ? 'A token is saved. Leave blank to keep it, or paste a replacement.' : 'Paste the token for the selected Meta sender.'; ?></span>
                            </div>

                            <div class="dialog360-only">
                                <h4>360dialog Managed Provider</h4>
                                <div class="form-group">
                                    <label>Channel API Key</label>
                                    <input type="password" name="dialog360_api_key" class="form-control" value="" autocomplete="new-password">
                                    <span class="help-block"><?php echo !empty($credential_saved) && $config['provider'] === '360dialog' ? 'An API key is saved. Leave blank to keep it, or paste a replacement.' : 'Copy the Messaging API key for this school\'s connected number from 360dialog Hub.'; ?></span>
                                </div>
                                <p>Complete 360dialog's coexistence onboarding to keep an existing WhatsApp Business app number. Provider subscription and WhatsApp messaging charges apply. Use templates approved for that connected account.</p>
                                <p><a href="https://docs.360dialog.com/docs/hub/embedded-signup/coexistence-onboarding" target="_blank" rel="noopener">360dialog coexistence setup guide</a></p>
                            </div>

                            <hr>
                            <h4 class="webhook-only">Custom Webhook</h4>
                            <div class="row webhook-only">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Webhook URL</label>
                                        <input type="text" name="webhook_url" class="form-control" value="<?php echo html_escape($config['webhook_url']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Bearer Token</label>
                                        <input type="text" name="webhook_bearer_token" class="form-control" value="<?php echo html_escape($config['webhook_bearer_token']); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="box-footer">
                            <button type="submit" class="btn btn-primary pull-right" <?php echo !$table_exists ? 'disabled' : ''; ?>>Save WhatsApp Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>
<script>
    $(function () {
        function showWhatsAppProviderFields() {
            var provider = $('#whatsapp_provider').val();
            $('.meta-only').toggle(provider === 'meta_cloud');
            $('.dialog360-only').toggle(provider === '360dialog');
            $('.webhook-only').toggle(provider === 'custom_webhook');
            $('.platform-fields').toggle(provider === 'meta_cloud' || provider === '360dialog');
        }
        $('#whatsapp_provider').on('change', showWhatsAppProviderFields);
        showWhatsAppProviderFields();
    });
</script>
