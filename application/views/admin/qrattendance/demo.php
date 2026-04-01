<div class="content-wrapper">
    <section class="content-header">
        <h1><i class="fa fa-qrcode"></i> QR Attendance Logs</h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-5">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Scan Station</h3>
                        <a href="<?php echo site_url('admin/qrattendancedemo/download/' . $attendance_on); ?>" class="btn btn-success btn-sm pull-right">
                            <i class="fa fa-download"></i> Download Today's CSV
                        </a>
                    </div>
                    <div class="box-body">
                        <p class="text-muted">Use the camera scanner for a fast scan station, or paste the QR URL below if you want a manual fallback. Each successful scan is written into a CSV file on the server.</p>

                        <div id="qr-reader" style="width: 100%; min-height: 280px; border: 1px dashed #d2d6de; border-radius: 4px; padding: 10px;"></div>
                        <div id="qr-reader-message" class="help-block" style="margin-top: 10px;">Camera scanner is loading.</div>

                        <hr>

                        <div class="form-group">
                            <label for="manual_scan_value">Manual QR Value</label>
                            <textarea id="manual_scan_value" class="form-control" rows="3" placeholder="Paste the full QR URL here, then click Process Scan."></textarea>
                        </div>
                        <button type="button" id="process_manual_scan" class="btn btn-primary">
                            <i class="fa fa-check"></i> Process Scan
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Live Scan Results</h3>
                    </div>
                    <div class="box-body">
                        <div id="scan_feedback" class="alert alert-info">
                            Scan a student or staff ID card to see the result here.
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Details</th>
                                        <th>Status</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody id="scan_results_body">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Saved CSV Logs</h3>
                    </div>
                    <div class="box-body table-responsive">
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
                                        <td colspan="4" class="text-center text-muted">No QR attendance CSV file has been created yet. Scan a card first, then refresh this page.</td>
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
    </section>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script type="text/javascript">
    (function () {
        var lastScanValue = '';
        var lastScanAt = 0;

        function escapeHtml(text) {
            return $('<div>').text(text || '').html();
        }

        function renderFeedback(result) {
            var alertClass = 'alert-info';
            if (result.status === 'success') {
                alertClass = 'alert-success';
            } else if (result.status === 'danger') {
                alertClass = 'alert-danger';
            } else if (result.status === 'warning') {
                alertClass = 'alert-warning';
            }

            $('#scan_feedback')
                .removeClass('alert-info alert-success alert-danger alert-warning')
                .addClass(alertClass)
                .html(
                    '<strong>' + escapeHtml(result.status) + ':</strong> ' +
                    escapeHtml(result.message) +
                    (result.full_name ? '<br><strong>Name:</strong> ' + escapeHtml(result.full_name) : '') +
                    (result.identity_no ? '<br><strong>ID:</strong> ' + escapeHtml(result.identity_no) : '')
                );
        }

        function appendResultRow(result) {
            var row = '' +
                '<tr>' +
                    '<td>' + escapeHtml(result.entity_type) + '</td>' +
                    '<td>' + escapeHtml(result.identity_no) + '</td>' +
                    '<td>' + escapeHtml(result.full_name) + '</td>' +
                    '<td>' + escapeHtml(result.details_value) + '</td>' +
                    '<td><span class="label label-default">' + escapeHtml(result.status) + '</span></td>' +
                    '<td>' + escapeHtml(result.scanned_at) + '</td>' +
                '</tr>';

            $('#scan_results_body').prepend(row);
        }

        function submitScan(scanValue) {
            $.ajax({
                url: '<?php echo site_url('qrattendance/api_mark'); ?>',
                type: 'POST',
                dataType: 'json',
                data: {
                    scan_value: scanValue
                },
                success: function (response) {
                    renderFeedback(response);
                    appendResultRow(response);
                    $('#manual_scan_value').val('');
                },
                error: function () {
                    renderFeedback({
                        status: 'danger',
                        message: 'The scan request failed. Please try again.'
                    });
                }
            });
        }

        function handleDetectedCode(decodedText) {
            var now = Date.now();
            if (decodedText === lastScanValue && (now - lastScanAt) < 3000) {
                return;
            }

            lastScanValue = decodedText;
            lastScanAt = now;
            submitScan(decodedText);
        }

        $('#process_manual_scan').on('click', function () {
            var scanValue = $('#manual_scan_value').val().trim();
            if (!scanValue) {
                renderFeedback({
                    status: 'warning',
                    message: 'Paste a QR value or URL before processing.'
                });
                return;
            }

            submitScan(scanValue);
        });

        if (typeof Html5QrcodeScanner !== 'undefined') {
            $('#qr-reader-message').text('Grant camera access, then present each student or staff ID card to the scanner.');
            var scanner = new Html5QrcodeScanner('qr-reader', {
                fps: 10,
                qrbox: 220
            }, false);

            scanner.render(handleDetectedCode, function () {
                $('#qr-reader-message').text('Camera scanner is ready. If scanning is slow, use the manual QR field below.');
            });
        } else {
            $('#qr-reader-message').text('Camera scanner could not load. Use the manual QR field below.');
        }
    })();
</script>
