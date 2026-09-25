<?php
$format_date = function ($value) {
    if (empty($value)) {
        return '';
    }
    $time = strtotime($value);
    return $time ? date('Y-m-d H:i', $time) : '';
};
$folder_labels = array(
    'all' => 'All',
    'inbox' => 'Inbox',
    'sent' => 'Sent',
    'unread' => 'Unread',
);
?>
<style>
    .shared-email-heading { display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .shared-email-heading__address { overflow-wrap:anywhere; word-break:break-word; text-align:right; }
    .shared-email-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:15px; }
    .shared-email-unread td { font-weight:600; }
    .shared-email-contact { overflow-wrap:anywhere; word-break:break-word; }
    @media (max-width:767px) {
        .shared-email-heading, .shared-email-toolbar { align-items:stretch; flex-direction:column; }
        .shared-email-heading__address { text-align:left; }
        .shared-email-toolbar .btn { margin-bottom:4px; }
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <h1><i class="fa fa-envelope"></i> Shared Email</h1>
    </section>
    <section class="content">
        <?php echo $this->session->flashdata('msg'); ?>
        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border shared-email-heading">
                        <h3 class="box-title">Email Conversations</h3>
                        <?php if (!empty($inbound_email_address)) { ?>
                            <div class="shared-email-heading__address text-muted">
                                <i class="fa fa-reply"></i> Reply address: <?php echo html_escape($inbound_email_address); ?>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="box-body">
                        <?php if (empty($inbound_email_address)) { ?>
                            <div class="alert alert-warning">
                                <i class="fa fa-warning"></i> The shared reply address could not be determined for this school domain.
                            </div>
                        <?php } ?>

                        <div class="shared-email-toolbar">
                            <div>
                                <?php foreach ($folder_labels as $folder_key => $folder_label) { ?>
                                    <a href="<?php echo site_url('admin/emailinbox?folder=' . $folder_key); ?>" class="btn btn-sm <?php echo $filters['folder'] === $folder_key ? 'btn-primary' : 'btn-default'; ?>">
                                        <?php echo html_escape($folder_label); ?> (<?php echo isset($counts[$folder_key]) ? (int) $counts[$folder_key] : 0; ?>)
                                    </a>
                                <?php } ?>
                                <?php if (!empty($can_send)) { ?>
                                    <a href="<?php echo site_url('admin/mailsms/compose?tab=external'); ?>" class="btn btn-success btn-sm">
                                        <i class="fa fa-paper-plane"></i> New External Email
                                    </a>
                                <?php } ?>
                            </div>
                            <form method="get" action="<?php echo site_url('admin/emailinbox'); ?>">
                                <input type="hidden" name="folder" value="<?php echo html_escape($filters['folder']); ?>">
                                <div class="input-group input-group-sm">
                                    <input type="text" name="q" class="form-control" value="<?php echo html_escape($filters['q']); ?>" placeholder="Search sender, recipient or subject">
                                    <span class="input-group-btn">
                                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i></button>
                                    </span>
                                </div>
                            </form>
                        </div>

                        <div class="table-responsive mailbox-messages">
                            <table class="table table-hover table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Conversation</th>
                                        <th>External Contact</th>
                                        <th>Subject</th>
                                        <th>Latest</th>
                                        <th>Messages</th>
                                        <th>Last Activity</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($conversations)) { ?>
                                        <tr><td colspan="7" class="text-center text-muted">No email conversations found.</td></tr>
                                    <?php } ?>
                                    <?php foreach ($conversations as $conversation) { ?>
                                        <tr class="<?php echo (int) $conversation['unread_count'] > 0 ? 'shared-email-unread' : ''; ?>">
                                            <td>
                                                <?php echo html_escape($conversation['conversation_number']); ?>
                                                <?php if ((int) $conversation['unread_count'] > 0) { ?>
                                                    <span class="label label-danger"><?php echo (int) $conversation['unread_count']; ?> new</span>
                                                <?php } ?>
                                            </td>
                                            <td class="shared-email-contact">
                                                <?php echo html_escape($conversation['participant_name']); ?><br>
                                                <span class="text-muted"><?php echo html_escape($conversation['participant_email']); ?></span>
                                            </td>
                                            <td><?php echo html_escape($conversation['subject']); ?></td>
                                            <td>
                                                <?php if ($conversation['last_message_direction'] === 'incoming') { ?>
                                                    <span class="label label-success"><i class="fa fa-inbox"></i> Received</span>
                                                <?php } else { ?>
                                                    <span class="label label-info"><i class="fa fa-paper-plane"></i> Sent</span>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <span title="Received"><i class="fa fa-inbox"></i> <?php echo (int) $conversation['incoming_count']; ?></span>
                                                &nbsp;
                                                <span title="Sent"><i class="fa fa-paper-plane"></i> <?php echo (int) $conversation['outgoing_count']; ?></span>
                                            </td>
                                            <td><?php echo $format_date($conversation['last_message_at']); ?></td>
                                            <td class="text-right">
                                                <a href="<?php echo site_url('admin/emailinbox/view/' . (int) $conversation['id']); ?>" class="btn btn-default btn-xs" title="Open conversation">
                                                    <i class="fa fa-reorder"></i> Open
                                                </a>
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
