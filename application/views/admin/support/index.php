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
        <h1><i class="fa fa-life-ring"></i> Support Tickets</h1>
    </section>
    <section class="content">
        <?php echo $this->session->flashdata('msg'); ?>
        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Support Tickets</h3>
                    </div>
                    <div class="box-body">
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
                                                    <a href="<?php echo site_url('admin/support/delete/' . $ticket['id']); ?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="Delete" onclick="return confirm('<?php echo $this->lang->line('delete_confirm'); ?>');">
                                                        <i class="fa fa-remove"></i>
                                                    </a>
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
