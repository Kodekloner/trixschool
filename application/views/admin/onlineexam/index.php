<?php $this->load->view('admin/onlineexam/_assessment_styles'); ?>
<div class="content-wrapper onlineexam-ui onlineexam-list-page">
    <section class="content">
        <?php echo $this->session->flashdata('msg'); ?>
        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary" id="route">
                    <div class="box-header ptbnull assessment-box-header">
                        <h3 class="box-title titlefix pt5">Online assessment list</h3>
                        <a class="btn btn-default btn-sm" href="<?php echo site_url('admin/onlineexam/review'); ?>"><i class="fa fa-table"></i> Review students and scores</a>
                        <?php if ($this->rbac->hasPrivilege('online_examination', 'can_add')) { ?>
                            <a class="btn btn-primary btn-sm" href="<?php echo site_url('admin/onlineexam/workflow'); ?>"><i class="fa fa-plus"></i> Add academic assessment</a>
                        <?php } ?>
                    </div>
                    <div class="box-body">
                        <div class="mailbox-messages">
                            <div class="table-responsive onlineexam-scroll" role="region" aria-label="Online assessment list" tabindex="0">
                                <table class="table table-striped table-bordered table-hover exam-list onlineexam-list-table" data-export-title="Online assessment list">
                                    <caption class="sr-only">Online assessments and workflow actions</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Assessment</th>
                                            <th scope="col" class="text-center">Purpose</th>
                                            <th scope="col" class="text-center">Papers / questions</th>
                                            <th scope="col">Opens</th>
                                            <th scope="col">Closes</th>
                                            <th scope="col">Duration</th>
                                            <th scope="col" class="text-center">Status</th>
                                            <th scope="col" class="text-center">Feedback</th>
                                            <th scope="col" class="text-right noExport onlineexam-action-cell">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
(function ($) {
    'use strict';

    function initializeDescriptionPopovers() {
        $('.detail_popover').not('[data-onlineexam-popover-ready]').each(function () {
            $(this).attr('data-onlineexam-popover-ready', '1').popover({
                placement: 'right',
                trigger: 'hover focus',
                container: 'body',
                html: true,
                content: function () {
                    return $(this).closest('td').find('.fee_detail_popover').html();
                }
            });
        });
    }

    $(document).ready(function () {
        $('.exam-list').on('draw.dt', initializeDescriptionPopovers);
        initDatatable('exam-list', 'admin/onlineexam/getexamlist', [], [], 100);
        initializeDescriptionPopovers();
    });
})(jQuery);
</script>
