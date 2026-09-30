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
$can_delete = !empty($can_delete);
$table_column_count = $can_delete ? 8 : 7;
?>
<style>
    .shared-email-heading { display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .shared-email-heading__address { overflow-wrap:anywhere; word-break:break-word; text-align:right; }
    .shared-email-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:15px; }
    .shared-email-unread td { font-weight:600; }
    .shared-email-contact { overflow-wrap:anywhere; word-break:break-word; }
    .shared-email-bulk-actions { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
    .shared-email-select { width:36px; text-align:center; }
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

                        <?php if ($can_delete) { ?>
                            <form id="shared-email-delete-form" method="post" action="<?php echo site_url('admin/emailinbox/delete_selected'); ?>">
                                <input type="hidden" name="shared_email_action_csrf" value="<?php echo html_escape($shared_email_action_csrf); ?>">
                                <input type="hidden" name="return_folder" value="<?php echo html_escape($filters['folder']); ?>">
                                <input type="hidden" name="return_q" value="<?php echo html_escape($filters['q']); ?>">
                                <div class="shared-email-bulk-actions">
                                    <button type="submit" id="shared-email-delete-selected" class="btn btn-danger btn-sm"<?php echo empty($conversations) ? ' disabled="disabled"' : ''; ?>>
                                        <i class="fa fa-trash"></i> Delete Selected
                                    </button>
                                    <span class="text-muted">Deleted conversations cannot be restored.</span>
                                </div>
                        <?php } ?>
                        <div class="table-responsive mailbox-messages">
                            <table class="table table-hover table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <?php if ($can_delete) { ?>
                                            <th class="shared-email-select">
                                                <input type="checkbox" id="shared-email-select-all" aria-label="Select all email conversations">
                                            </th>
                                        <?php } ?>
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
                                        <tr><td colspan="<?php echo (int) $table_column_count; ?>" class="text-center text-muted">No email conversations found.</td></tr>
                                    <?php } ?>
                                    <?php foreach ($conversations as $conversation) { ?>
                                        <tr class="<?php echo (int) $conversation['unread_count'] > 0 ? 'shared-email-unread' : ''; ?>">
                                            <?php if ($can_delete) { ?>
                                                <td class="shared-email-select">
                                                    <input type="checkbox" class="shared-email-conversation-checkbox" name="conversation_ids[]" value="<?php echo (int) $conversation['id']; ?>" aria-label="Select <?php echo html_escape($conversation['conversation_number']); ?>">
                                                </td>
                                            <?php } ?>
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
                                                <?php if ($can_delete) { ?>
                                                    <button type="submit" class="btn btn-danger btn-xs" formaction="<?php echo site_url('admin/emailinbox/delete/' . (int) $conversation['id']); ?>" formmethod="post" title="Delete conversation" onclick="return confirm('Permanently delete this email conversation and its message history?');">
                                                        <i class="fa fa-trash"></i> Delete
                                                    </button>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($can_delete) { ?>
                            </form>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php if ($can_delete) { ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var selectAll = document.getElementById('shared-email-select-all');
    var deleteSelected = document.getElementById('shared-email-delete-selected');
    var checkboxes = document.querySelectorAll('.shared-email-conversation-checkbox');

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = selectAll.checked;
            }
        });
    }

    if (deleteSelected) {
        deleteSelected.addEventListener('click', function (event) {
            var selected = 0;
            for (var i = 0; i < checkboxes.length; i++) {
                if (checkboxes[i].checked) {
                    selected++;
                }
            }
            if (selected === 0) {
                event.preventDefault();
                alert('Select at least one email conversation to delete.');
                return;
            }
            if (!confirm('Permanently delete ' + selected + ' selected email conversation' + (selected === 1 ? '' : 's') + ' and their message history?')) {
                event.preventDefault();
            }
        });
    }
});
</script>
<?php } ?>
