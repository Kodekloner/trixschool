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
                                        <select name="provider" class="form-control">
                                            <option value="meta_cloud" <?php echo $config['provider'] == 'meta_cloud' ? 'selected' : ''; ?>>Meta Cloud API</option>
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
                            <h4>Meta Cloud API</h4>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Graph API Version</label>
                                        <input type="text" name="meta_graph_api_version" class="form-control" value="<?php echo html_escape($config['meta_graph_api_version']); ?>" placeholder="v20.0">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Phone Number ID</label>
                                        <input type="text" name="meta_phone_number_id" class="form-control" value="<?php echo html_escape($config['meta_phone_number_id']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Template Name</label>
                                        <input type="text" name="meta_template_name" class="form-control" value="<?php echo html_escape($config['meta_template_name']); ?>" placeholder="parent_login_credentials">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Template Language</label>
                                        <input type="text" name="meta_template_language" class="form-control" value="<?php echo html_escape($config['meta_template_language']); ?>" placeholder="en">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Access Token</label>
                                <textarea name="meta_access_token" class="form-control" rows="3"><?php echo html_escape($config['meta_access_token']); ?></textarea>
                            </div>

                            <hr>
                            <h4>Custom Webhook</h4>
                            <div class="row">
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
