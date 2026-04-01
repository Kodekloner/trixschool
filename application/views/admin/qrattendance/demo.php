<div class="content-wrapper">
    <section class="content-header">
        <h1><i class="fa fa-qrcode"></i> QR Attendance Logs</h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Saved CSV Logs</h3>
                        <?php if (!empty($today_log_exists)) { ?>
                            <a href="<?php echo site_url('admin/qrattendancedemo/download/' . $attendance_on); ?>" class="btn btn-success btn-sm pull-right">
                                <i class="fa fa-download"></i> Download Today's CSV
                            </a>
                        <?php } ?>
                    </div>
                    <div class="box-body">
                        <p class="text-muted">
                            Every successful phone or scanner QR attendance mark is written into a CSV file with full datetime.
                            Refresh this page after a scan to see the latest downloadable files.
                        </p>
                        <p class="text-muted">
                            <strong>Log folder:</strong> <?php echo html_escape($log_directory); ?>
                        </p>

                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Filename</th>
                                        <th>Size</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($log_files)) { ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">
                                                No QR attendance CSV file has been created yet. Scan a card again after this update, then refresh this page.
                                            </td>
                                        </tr>
                                    <?php } else { ?>
                                        <?php foreach ($log_files as $log_file) { ?>
                                            <tr>
                                                <td><?php echo html_escape($log_file['date']); ?></td>
                                                <td><?php echo html_escape($log_file['name']); ?></td>
                                                <td><?php echo number_format($log_file['size'] / 1024, 2); ?> KB</td>
                                                <td class="text-right">
                                                    <a href="<?php echo site_url('admin/qrattendancedemo/download/' . $log_file['date']); ?>" class="btn btn-success btn-xs">
                                                        <i class="fa fa-download"></i> Download
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php } ?>
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
