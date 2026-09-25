<?php
$format_date = function ($value) {
    if (empty($value)) {
        return '';
    }
    $time = strtotime($value);
    return $time ? date('Y-m-d H:i', $time) : '';
};
?>
<style>
    .shared-thread-heading { display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .shared-thread-heading .box-title { min-width:0; overflow-wrap:anywhere; word-break:break-word; }
    .shared-thread-meta { overflow-wrap:anywhere; word-break:break-word; }
    .shared-thread-message .panel-heading { display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .shared-thread-message__body { white-space:normal; overflow-wrap:anywhere; word-break:break-word; }
    .shared-thread-attachments { margin:10px 0 0; padding-left:20px; }
    @media (max-width:767px) {
        .shared-thread-heading, .shared-thread-message .panel-heading { align-items:flex-start; flex-direction:column; }
        .shared-thread-heading .btn { width:100%; }
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <h1><i class="fa fa-envelope"></i> Shared Email <?php echo html_escape($conversation['conversation_number']); ?></h1>
    </section>
    <section class="content">
        <?php echo $this->session->flashdata('msg'); ?>
        <div class="row">
            <div class="col-md-8">
                <div class="box box-primary">
                    <div class="box-header with-border shared-thread-heading">
                        <h3 class="box-title"><?php echo html_escape($conversation['subject']); ?></h3>
                        <a href="<?php echo site_url('admin/emailinbox'); ?>" class="btn btn-default btn-xs"><i class="fa fa-arrow-left"></i> Back to Shared Email</a>
                    </div>
                    <div class="box-body">
                        <?php foreach ($messages as $message) { ?>
                            <div class="panel panel-<?php echo $message['direction'] === 'incoming' ? 'success' : 'info'; ?> shared-thread-message">
                                <div class="panel-heading">
                                    <div>
                                        <strong><?php echo html_escape(!empty($message['sender_name']) ? $message['sender_name'] : $message['sender_email']); ?></strong>
                                        <span class="text-muted">
                                            &middot; <?php echo html_escape($message['direction']); ?>
                                            &middot; <?php echo $format_date($message['created_at']); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <?php if ($message['delivery_status'] === 'failed') { ?>
                                            <span class="label label-danger">Failed</span>
                                        <?php } elseif ($message['direction'] === 'outgoing') { ?>
                                            <span class="label label-info">Sent</span>
                                        <?php } else { ?>
                                            <span class="label label-success">Received</span>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="panel-body shared-thread-message__body">
                                    <p><strong>Subject:</strong> <?php echo html_escape($message['subject']); ?></p>
                                    <hr>
                                    <?php
                                    $body = !empty($message['body_text'])
                                        ? $message['body_text']
                                        : strip_tags((string) $message['body_html']);
                                    echo nl2br(html_escape($body));
                                    ?>
                                    <?php if (!empty($message['attachment_names'])) { ?>
                                        <hr>
                                        <strong><i class="fa fa-paperclip"></i> Attachments</strong>
                                        <ul class="shared-thread-attachments">
                                            <?php foreach ($message['attachment_names'] as $attachment_name) { ?>
                                                <li><?php echo html_escape($attachment_name); ?></li>
                                            <?php } ?>
                                        </ul>
                                    <?php } ?>
                                    <?php if (!empty($message['error_message'])) { ?>
                                        <hr>
                                        <span class="text-danger"><?php echo html_escape($message['error_message']); ?></span>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <?php if ($this->rbac->hasPrivilege('shared_email', 'can_add')) { ?>
                        <div class="box-footer">
                            <?php if (empty($inbound_email_address)) { ?>
                                <div class="alert alert-warning">Replying is disabled because the shared inbound address is not configured.</div>
                            <?php } ?>
                            <form action="<?php echo site_url('admin/emailinbox/reply/' . (int) $conversation['id']); ?>" method="post" enctype="multipart/form-data">
                                <?php echo $this->customlib->getCSRF(); ?>
                                <input type="hidden" name="shared_email_action_csrf" value="<?php echo html_escape($shared_email_action_csrf); ?>">
                                <div class="form-group">
                                    <label for="shared_email_reply">Reply</label><small class="req"> *</small>
                                    <textarea id="shared_email_reply" name="message" class="form-control" rows="7" required></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="pr20" for="shared_email_attachment">Attachment</label>
                                    <input type="file" id="shared_email_attachment" class="filestyle form-control" name="email_attachment[]" multiple="multiple">
                                </div>
                                <button type="submit" class="btn btn-primary pull-right" <?php echo empty($inbound_email_address) ? 'disabled' : ''; ?>>
                                    <i class="fa fa-reply"></i> Send Reply
                                </button>
                                <div class="clearfix"></div>
                            </form>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="box box-primary">
                    <div class="box-header with-border"><h3 class="box-title">Conversation Details</h3></div>
                    <div class="box-body shared-thread-meta">
                        <table class="table table-striped">
                            <tr><th>Contact</th><td><?php echo html_escape($conversation['participant_name']); ?></td></tr>
                            <tr><th>Email</th><td><a href="mailto:<?php echo html_escape($conversation['participant_email']); ?>"><?php echo html_escape($conversation['participant_email']); ?></a></td></tr>
                            <tr><th>Started by</th><td><?php echo html_escape(!empty($conversation['created_by_name']) ? $conversation['created_by_name'] : 'External sender'); ?></td></tr>
                            <tr><th>Reply address</th><td><?php echo html_escape($conversation['inbound_address']); ?></td></tr>
                            <tr><th>Started</th><td><?php echo $format_date($conversation['created_at']); ?></td></tr>
                            <tr><th>Last activity</th><td><?php echo $format_date($conversation['last_message_at']); ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
