<?php
$compose_notifications = isset($compose_notifications) ? $compose_notifications : array(
    'custom' => array(
        'label'     => 'Custom',
        'subject'   => '',
        'template'  => '',
        'variables' => '',
        'audience'  => array('student', 'parent', 'roles'),
    ),
);
$supports_templates = !empty($whatsapp_supports_templates) || (isset($whatsapp_provider) && in_array($whatsapp_provider, array('meta_cloud', '360dialog'), true));
$whatsapp_errors = isset($whatsapp_errors) ? $whatsapp_errors : array();
?>
<div class="content-wrapper">
    <section class="content-header">
        <h1><i class="fa fa-whatsapp"></i> Send WhatsApp</h1>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <?php if (!empty($whatsapp_errors)) { ?>
                    <div class="alert alert-warning">
                        <?php echo html_escape(implode(' ', $whatsapp_errors)); ?>
                        <a href="<?php echo site_url('admin/whatsappsettings'); ?>">Open WhatsApp Settings</a>
                    </div>
                <?php } ?>
                <div class="box box-primary">
                    <form action="<?php echo site_url('admin/mailsms/send_group_whatsapp') ?>" method="post" id="whatsapp_group_form">
                        <div class="box-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <?php if ($supports_templates) { ?>
                                        <div class="form-group">
                                            <label>Send using</label>
                                            <select class="form-control" name="group_delivery_type" id="group_delivery_type">
                                                <option value="template">Approved WhatsApp template</option>
                                                <option value="text">Text reply within 24 hours</option>
                                            </select>
                                            <span class="help-block">Use an approved template when the school starts a conversation. Text replies require a message from the recipient within the past 24 hours.</span>
                                        </div>
                                        <div id="whatsapp_approved_template_fields">
                                            <div class="form-group">
                                                <label>Approved Template Name</label><small class="req"> *</small>
                                                <input type="text" name="meta_template_name" class="form-control" placeholder="school_notice">
                                                <span class="help-block">Copy the exact approved name from WhatsApp Manager or your provider dashboard. The recipient receives that template's approved wording.</span>
                                            </div>
                                            <div class="form-group">
                                                <label>Template Language</label><small class="req"> *</small>
                                                <input type="text" name="meta_template_language" class="form-control" value="en" placeholder="en or en_US">
                                            </div>
                                            <div class="form-group">
                                                <label>Template Body Values</label>
                                                <textarea name="meta_template_parameters" class="form-control" rows="4" placeholder="{{display_name}}&#10;{{school_name}}"></textarea>
                                                <span class="help-block">For templates with numbered text values in the body, enter one value per line in the approved order. You can use {{display_name}}, {{student_name}} and {{school_name}}. Leave blank for templates without values.</span>
                                            </div>
                                        </div>
                                    <?php } else { ?>
                                        <input type="hidden" name="group_delivery_type" value="text">
                                    <?php } ?>
                                    <div class="form-group whatsapp-text-fields">
                                        <label>School Message Template</label><small class="req"> *</small>
                                        <select class="form-control" name="group_template_type" id="group_template_type">
                                            <?php foreach ($compose_notifications as $notification_key => $notification_value) { ?>
                                                <option value="<?php echo $notification_key; ?>"><?php echo $notification_value['label']; ?></option>
                                            <?php } ?>
                                        </select>
                                        <span class="help-block">Choose a notification template, or select Custom to write a new WhatsApp message.</span>
                                    </div>
                                    <div class="alert alert-info whatsapp-text-fields" id="notification_template_notice" style="display:none;">
                                        <strong>Template copied from Notification Setting.</strong> You can edit this message before sending; it will not update the saved template.
                                        <div id="notification_template_variables" class="mt5"></div>
                                    </div>
                                    <div class="form-group">
                                        <label><?php echo $this->lang->line('title'); ?></label><small class="req"> *</small>
                                        <input autofocus="" class="form-control" name="group_title">
                                    </div>
                                    <div class="form-group whatsapp-text-fields">
                                        <label><?php echo $this->lang->line('message'); ?></label><small class="req"> *</small>
                                        <textarea id="group_msg_text" name="group_message" class="form-control compose-textarea" rows="12"><?php echo set_value('message'); ?></textarea>
                                        <span class="text-muted tot_count_group_msg_text pull-right word_counter"><?php echo $this->lang->line('character') . " " . $this->lang->line('count') ?>: 0</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label><?php echo $this->lang->line('message_to'); ?></label><small class="req"> *</small>
                                        <div class="well minheight303">
                                            <div class="checkbox mt0 compose-audience compose-audience-student">
                                                <label><input type="checkbox" name="user[]" value="student"> <b><?php echo $this->lang->line('students'); ?></b></label>
                                            </div>
                                            <div class="checkbox compose-audience compose-audience-parent">
                                                <label><input type="checkbox" name="user[]" value="parent"> <b><?php echo $this->lang->line('guardians'); ?></b></label>
                                            </div>
                                            <?php foreach ($roles as $role_key => $role_value) { ?>
                                                <div class="checkbox compose-audience compose-audience-role">
                                                    <label><input type="checkbox" name="user[]" value="<?php echo $role_value['id']; ?>"> <b><?php echo $role_value['name']; ?></b></label>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="box-footer">
                            <div class="pull-right">
                                <button type="submit" class="btn btn-primary submit_whatsapp_group" data-loading-text="<i class='fa fa-spinner fa-spin '></i> Sending" <?php echo !empty($whatsapp_errors) ? 'disabled' : ''; ?>><i class="fa fa-whatsapp"></i> <?php echo $this->lang->line('send'); ?></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<script type="text/javascript">
    var composeNotifications = <?php echo json_encode($compose_notifications, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var lastGroupTemplateType = 'custom';

    function updateWhatsAppDelivery() {
        var useTemplate = $('#group_delivery_type').val() === 'template';
        if (useTemplate) {
            $('#group_template_type').val('custom');
            applyGroupNotificationTemplate(false);
        }
        $('.whatsapp-text-fields').toggle(!useTemplate);
        $('#notification_template_notice').toggle(!useTemplate && $('#group_template_type').val() !== 'custom');
        $('.whatsapp-text-fields :input').prop('disabled', useTemplate);
        $('#whatsapp_approved_template_fields').toggle(useTemplate).find(':input').prop('disabled', !useTemplate);
    }

    $(document).on('change', '#group_delivery_type', updateWhatsAppDelivery);

    function updateGroupWhatsAppCounter() {
        var message = $('#group_msg_text').val() || '';
        $('.tot_count_group_msg_text').html("<?php echo $this->lang->line('character') . " " . $this->lang->line('count') ?>: " + message.length);
    }

    function toggleComposeAudience(config) {
        var audience = config.audience || [];

        $('.compose-audience').hide();
        $('.compose-audience input[type="checkbox"]').prop('checked', false);

        if ($.inArray('student', audience) !== -1) {
            $('.compose-audience-student').show();
        }
        if ($.inArray('parent', audience) !== -1) {
            $('.compose-audience-parent').show();
        }
        if ($.inArray('roles', audience) !== -1) {
            $('.compose-audience-role').show();
        }
    }

    function applyGroupNotificationTemplate(clearPreviousTemplate) {
        var selectedTemplate = $('#group_template_type').val() || 'custom';
        var config = composeNotifications[selectedTemplate] || composeNotifications.custom;

        toggleComposeAudience(config);

        if (selectedTemplate === 'custom') {
            $('#notification_template_notice').hide();
            $('#notification_template_variables').html('');
            $('.compose-audience').show();

            if (clearPreviousTemplate && lastGroupTemplateType !== 'custom') {
                $('input[name="group_title"]').val('');
                $('#group_msg_text').val('');
            }
        } else {
            $('input[name="group_title"]').val(config.subject || config.label || '');
            $('#group_msg_text').val(config.template || '');
            $('#notification_template_notice').show();
            if (config.variables) {
                $('#notification_template_variables').html('<small><strong>Available variables:</strong> <span></span></small>');
                $('#notification_template_variables span').text(config.variables);
            } else {
                $('#notification_template_variables').html('');
            }
        }

        lastGroupTemplateType = selectedTemplate;
        updateGroupWhatsAppCounter();
    }

    $(document).on('change', '#group_template_type', function () {
        applyGroupNotificationTemplate(true);
    });

    $(document).on('keypress keyup keydown paste change focus blur', '#group_msg_text', function () {
        updateGroupWhatsAppCounter();
    });

    $(document).ready(function () {
        applyGroupNotificationTemplate(false);
        updateWhatsAppDelivery();
    });

    $("#whatsapp_group_form").submit(function (event) {
        event.preventDefault();

        var formData = new FormData();
        var other_data = $(this).serializeArray();
        $.each(other_data, function (key, input) {
            formData.append(input.name, input.value);
        });

        var $form = $(this),
            url = $form.attr('action');
        var $this = $('.submit_whatsapp_group');
        $this.button('loading');

        $.ajax({
            type: "POST",
            url: url,
            data: formData,
            dataType: "JSON",
            contentType: false,
            processData: false,
            cache: false,
            success: function (data) {
                if (data.status == 1) {
                    var message = "";
                    $.each(data.msg, function (index, value) {
                        message += value;
                    });
                    errorMsg(message);
                } else {
                    $('#whatsapp_group_form')[0].reset();
                    $('#group_template_type').val('custom');
                    lastGroupTemplateType = 'custom';
                    applyGroupNotificationTemplate(false);
                    updateWhatsAppDelivery();
                    successMsg(data.msg);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                errorMsg("Unable to send WhatsApp message. Please try again.");
            },
            complete: function (data) {
                $this.button('reset');
            }
        });
    });
</script>
