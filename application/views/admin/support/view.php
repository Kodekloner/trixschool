<?php
$format_date = function ($value) {
    if (empty($value)) {
        return '';
    }

    $time = strtotime($value);
    return $time ? date('Y-m-d H:i', $time) : '';
};
?>

<div class="content-wrapper">
    <section class="content-header">
        <h1><i class="fa fa-life-ring"></i> Support Ticket <?php echo html_escape($ticket['ticket_number']); ?></h1>
    </section>
    <section class="content">
        <?php echo $this->session->flashdata('msg'); ?>
        <div class="row">
            <div class="col-md-8">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><?php echo html_escape($ticket['subject']); ?></h3>
                        <div class="box-tools pull-right">
                            <a href="<?php echo site_url('admin/support'); ?>" class="btn btn-default btn-xs"><i class="fa fa-arrow-left"></i> Back</a>
                        </div>
                    </div>
                    <div class="box-body">
                        <?php foreach ($messages as $message) { ?>
                            <div class="panel panel-<?php echo $message['direction'] === 'incoming' ? 'default' : 'info'; ?>">
                                <div class="panel-heading">
                                    <strong><?php echo html_escape(!empty($message['sender_name']) ? $message['sender_name'] : $message['sender_email']); ?></strong>
                                    <span class="text-muted">
                                        <?php echo html_escape($message['direction']); ?> &middot; <?php echo $format_date($message['created_at']); ?>
                                    </span>
                                    <?php if ($message['delivery_status'] === 'failed') { ?>
                                        <span class="label label-danger pull-right">Failed</span>
                                    <?php } ?>
                                </div>
                                <div class="panel-body">
                                    <?php
                                    $body = !empty($message['body_text']) ? $message['body_text'] : strip_tags((string) $message['body_html']);
                                    echo nl2br(html_escape($body));
                                    ?>
                                    <?php if (!empty($message['attachment_count'])) { ?>
                                        <hr>
                                        <span class="text-muted"><i class="fa fa-paperclip"></i> <?php echo (int) $message['attachment_count']; ?> attachment(s)</span>
                                    <?php } ?>
                                    <?php if (!empty($message['error_message'])) { ?>
                                        <hr>
                                        <span class="text-danger"><?php echo html_escape($message['error_message']); ?></span>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <?php if ($this->rbac->hasPrivilege('support_ticket', 'can_add')) { ?>
                        <div class="box-footer">
                            <form action="<?php echo site_url('admin/support/reply/' . $ticket['id']); ?>" method="post">
                                <?php echo $this->customlib->getCSRF(); ?>
                                <div class="form-group">
                                    <textarea name="message" class="form-control" rows="6" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary pull-right"><i class="fa fa-reply"></i> Send Reply</button>
                            </form>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Details</h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-striped">
                            <tr>
                                <th>Requester</th>
                                <td>
                                    <?php echo html_escape($ticket['requester_name']); ?><br>
                                    <a href="mailto:<?php echo html_escape($ticket['requester_email']); ?>"><?php echo html_escape($ticket['requester_email']); ?></a>
                                </td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td><?php echo html_escape(ucfirst($ticket['status'])); ?></td>
                            </tr>
                            <tr>
                                <th>Priority</th>
                                <td><?php echo html_escape(ucfirst($ticket['priority'])); ?></td>
                            </tr>
                            <tr>
                                <th>Assigned</th>
                                <td><?php echo html_escape($ticket['assigned_staff_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Opened</th>
                                <td><?php echo $format_date($ticket['opened_at']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php if ($this->rbac->hasPrivilege('support_ticket', 'can_edit')) { ?>
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">Update Ticket</h3>
                        </div>
                        <form action="<?php echo site_url('admin/support/update/' . $ticket['id']); ?>" method="post">
                            <?php echo $this->customlib->getCSRF(); ?>
                            <div class="box-body">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status" class="form-control">
                                        <?php foreach ($status_options as $status_key => $status_label) { ?>
                                            <option value="<?php echo html_escape($status_key); ?>" <?php echo $ticket['status'] === $status_key ? 'selected' : ''; ?>><?php echo html_escape($status_label); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Priority</label>
                                    <select name="priority" class="form-control">
                                        <?php foreach ($priority_options as $priority_key => $priority_label) { ?>
                                            <option value="<?php echo html_escape($priority_key); ?>" <?php echo $ticket['priority'] === $priority_key ? 'selected' : ''; ?>><?php echo html_escape($priority_label); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Assigned Staff</label>
                                    <select name="assigned_staff_id" class="form-control">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($staff_list as $staff) { ?>
                                            <option value="<?php echo (int) $staff['id']; ?>" <?php echo (int) $ticket['assigned_staff_id'] === (int) $staff['id'] ? 'selected' : ''; ?>>
                                                <?php echo html_escape(trim($staff['name'] . ' ' . $staff['surname'])); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="box-footer">
                                <button type="submit" class="btn btn-info pull-right"><i class="fa fa-save"></i> Save</button>
                            </div>
                        </form>
                    </div>
                <?php } ?>
            </div>
        </div>
    </section>
</div>
