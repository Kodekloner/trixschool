<?php
$format_date = function ($value) {
    if (empty($value)) {
        return '';
    }

    $time = strtotime($value);
    return $time ? date('Y-m-d H:i', $time) : '';
};
$notification_enabled = !empty($notification_preference) && !empty($notification_preference['is_active']);
$notification_destination = $notification_enabled && !empty($notification_preference['email'])
    ? $notification_preference['email']
    : $notification_email;
?>

<style>
    .support-email-actions { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:15px; }
    .support-email-actions__right { margin-left:auto; text-align:right; }
    .support-email-actions__hint { display:block; margin-top:5px; color:#777; font-size:12px; }
    .support-ticket-delete { display:inline-block; margin:0; vertical-align:middle; }
    @media (max-width:767px) {
        .support-email-actions { align-items:stretch; flex-direction:column; }
        .support-email-actions__right { margin-left:0; text-align:left; }
        .support-email-actions .btn { width:100%; }
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <h1><i class="fa fa-life-ring"></i> Support Tickets</h1>
    </section>
    <section class="content">
        <?php echo $this->session->flashdata('msg'); ?>
        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Support Tickets</h3>
                        <?php if (!empty($inbound_email_address)) { ?>
                            <div class="box-tools pull-right">
                                <span class="text-muted"><i class="fa fa-envelope"></i> <?php echo html_escape($inbound_email_address); ?></span>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="box-body">
                        <div class="support-email-actions">
                            <div>
                                <?php if (!empty($can_send_external_email)) { ?>
                                    <a href="<?php echo site_url('admin/mailsms/compose?tab=external'); ?>" class="btn btn-primary btn-sm">
                                        <i class="fa fa-paper-plane"></i> New External Email
                                    </a>
                                <?php } ?>
                            </div>
                            <div class="support-email-actions__right">
                                <?php if (!empty($notification_table_ready)) { ?>
                                    <form method="post" action="<?php echo site_url('admin/support/notification'); ?>" style="display:inline-block;">
                                        <input type="hidden" name="support_notification_csrf" value="<?php echo html_escape($support_notification_csrf); ?>">
                                        <input type="hidden" name="enabled" value="<?php echo $notification_enabled ? '0' : '1'; ?>">
                                        <button type="submit" class="btn btn-sm <?php echo $notification_enabled ? 'btn-success' : 'btn-default'; ?>" <?php echo (!$notification_enabled && empty($notification_email)) ? 'disabled' : ''; ?>>
                                            <i class="fa fa-bell<?php echo $notification_enabled ? '' : '-o'; ?>"></i>
                                            <?php echo $notification_enabled ? 'Email Alerts On' : 'Enable Email Alerts'; ?>
                                        </button>
                                    </form>
                                    <?php if (!empty($notification_destination)) { ?>
                                        <span class="support-email-actions__hint">
                                            Alerts <?php echo $notification_enabled ? 'go' : 'will go'; ?> to <?php echo html_escape($notification_destination); ?>.
                                            Add that account to Gmail, Outlook, or Apple Mail on the device.
                                        </span>
                                    <?php } else { ?>
                                        <span class="support-email-actions__hint">Add an email address to your staff profile to enable device alerts.</span>
                                    <?php } ?>
                                    <?php if ($notification_enabled && !empty($notification_preference['last_error'])) { ?>
                                        <span class="support-email-actions__hint text-warning"><i class="fa fa-warning"></i> The last alert could not be delivered; check Email Settings.</span>
                                    <?php } ?>
                                <?php } else { ?>
                                    <button type="button" class="btn btn-default btn-sm" disabled><i class="fa fa-bell-o"></i> Email Alerts</button>
                                    <span class="support-email-actions__hint">Import database migration 134 to enable alerts.</span>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-8">
                                <a href="<?php echo site_url('admin/support'); ?>" class="btn btn-default btn-sm">All</a>
                                <?php foreach ($status_options as $status_key => $status_label) { ?>
                                    <a href="<?php echo site_url('admin/support?status=' . $status_key); ?>" class="btn btn-default btn-sm">
                                        <?php echo html_escape($status_label); ?> (<?php echo isset($counts[$status_key]) ? (int) $counts[$status_key] : 0; ?>)
                                    </a>
                                <?php } ?>
                            </div>
                            <div class="col-sm-4">
                                <form method="get" action="<?php echo site_url('admin/support'); ?>">
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="q" class="form-control" value="<?php echo html_escape($filters['q']); ?>" placeholder="Search">
                                        <?php if (!empty($filters['status'])) { ?>
                                            <input type="hidden" name="status" value="<?php echo html_escape($filters['status']); ?>">
                                        <?php } ?>
                                        <span class="input-group-btn">
                                            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i></button>
                                        </span>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="clearfix"></div>
                        <div class="mailbox-messages table-responsive" style="margin-top:15px;">
                            <table class="table table-hover table-striped table-bordered example">
                                <thead>
                                    <tr>
                                        <th>Ticket</th>
                                        <th>Requester</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Assigned</th>
                                        <th>Last Message</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tickets as $ticket) { ?>
                                        <tr>
                                            <td><?php echo html_escape($ticket['ticket_number']); ?></td>
                                            <td>
                                                <?php echo html_escape($ticket['requester_name']); ?><br>
                                                <a href="mailto:<?php echo html_escape($ticket['requester_email']); ?>"><?php echo html_escape($ticket['requester_email']); ?></a>
                                            </td>
                                            <td><?php echo html_escape($ticket['subject']); ?></td>
                                            <td><span class="label label-default"><?php echo html_escape(ucfirst($ticket['status'])); ?></span></td>
                                            <td><?php echo html_escape(ucfirst($ticket['priority'])); ?></td>
                                            <td><?php echo html_escape($ticket['assigned_staff_name']); ?></td>
                                            <td><?php echo $format_date($ticket['last_message_at']); ?></td>
                                            <td class="text-right white-space-nowrap">
                                                <a href="<?php echo site_url('admin/support/view/' . $ticket['id']); ?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="View">
                                                    <i class="fa fa-reorder"></i>
                                                </a>
                                                <?php if ($this->rbac->hasPrivilege('support_ticket', 'can_delete')) { ?>
                                                    <form method="post" action="<?php echo site_url('admin/support/delete/' . $ticket['id']); ?>" class="support-ticket-delete" onsubmit="return confirm(<?php echo html_escape(json_encode($this->lang->line('delete_confirm'))); ?>);">
                                                        <?php echo $this->customlib->getCSRF(); ?>
                                                        <input type="hidden" name="support_action_csrf" value="<?php echo html_escape($support_action_csrf); ?>">
                                                        <button type="submit" class="btn btn-default btn-xs" data-toggle="tooltip" title="Delete">
                                                            <i class="fa fa-remove"></i>
                                                        </button>
                                                    </form>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
