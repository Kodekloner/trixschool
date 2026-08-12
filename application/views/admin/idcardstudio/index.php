<div class="content-wrapper">
    <section class="content-header">
        <h1>ID Card Design Studio <small><?php echo $subject_type === 'staff' ? 'Staff cards' : 'Student cards'; ?></small></h1>
    </section>
    <section class="content">
        <?php echo $this->session->flashdata('msg'); ?>
        <?php if (!$studio_ready) { ?>
            <div class="alert alert-warning">
                <strong>Migration required.</strong> Install database migration 131 before converting a template. Existing ID cards remain available in the legacy builder.
            </div>
        <?php } ?>

        <div class="box box-primary">
            <div class="box-header with-border">
                <div class="btn-group pull-right" role="group" aria-label="Card type">
                    <a class="btn btn-sm <?php echo $subject_type === 'student' ? 'btn-primary' : 'btn-default'; ?>" href="<?php echo site_url('admin/idcardstudio/index/student'); ?>">Students</a>
                    <a class="btn btn-sm <?php echo $subject_type === 'staff' ? 'btn-primary' : 'btn-default'; ?>" href="<?php echo site_url('admin/idcardstudio/index/staff'); ?>">Staff</a>
                </div>
                <h3 class="box-title">Choose a legacy template</h3>
            </div>
            <div class="box-body">
                <p class="text-muted">
                    Conversion creates a separate, versioned studio design. It does not edit or remove the legacy template, so existing printing continues to work until you publish and adopt the studio design.
                </p>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Template</th>
                                <th>Legacy layout</th>
                                <th>Studio status</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($templates)) { ?>
                            <tr><td colspan="4" class="text-center text-muted">No <?php echo html_escape($subject_type); ?> ID card templates exist yet.</td></tr>
                        <?php } ?>
                        <?php foreach ($templates as $template) { ?>
                            <tr>
                                <td>
                                    <strong><?php echo html_escape($template->title); ?></strong><br>
                                    <small class="text-muted"><?php echo html_escape($template->school_name); ?></small>
                                </td>
                                <td><?php echo !empty($template->enable_vertical_card) ? 'Portrait' : 'Landscape'; ?></td>
                                <td>
                                    <?php if (!empty($template->studio_design_id)) { ?>
                                        <span class="label label-success">Converted</span>
                                        <?php if (!empty($template->published_version_id)) { ?>
                                            <span class="label label-primary">Published</span>
                                        <?php } else { ?>
                                            <span class="label label-warning">Draft only</span>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <span class="label label-default">Legacy only</span>
                                    <?php } ?>
                                </td>
                                <td class="text-right">
                                    <?php if (!empty($template->studio_design_id)) { ?>
                                        <a class="btn btn-primary btn-sm" href="<?php echo site_url('admin/idcardstudio/editor/' . $subject_type . '/' . (int) $template->id); ?>">
                                            <i class="fa fa-pencil"></i> Open Studio
                                        </a>
                                    <?php } elseif ($studio_ready && $this->rbac->hasPrivilege($subject_type === 'staff' ? 'staff_id_card' : 'student_id_card', 'can_edit')) { ?>
                                        <form method="post" action="<?php echo site_url('admin/idcardstudio/convert/' . $subject_type . '/' . (int) $template->id); ?>" style="display:inline">
                                            <input type="hidden" name="studio_csrf" value="<?php echo html_escape($studio_csrf); ?>">
                                            <button type="submit" class="btn btn-default btn-sm" onclick="return confirm('Create a separate Design Studio copy of this template? The legacy template will remain unchanged.');">
                                                <i class="fa fa-magic"></i> Convert to Design Studio
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
    </section>
</div>
