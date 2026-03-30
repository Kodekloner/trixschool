<div class="content-wrapper">
    <section class="content-header">
        <h1><i class="fa fa-qrcode"></i> QR Attendance Demo</h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-5">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Scan Station</h3>
                        <a href="<?php echo site_url('admin/qrattendancedemo/download/' . $attendance_on); ?>" class="btn btn-success btn-sm pull-right">
                            <i class="fa fa-download"></i> Download Demo CSV
                        </a>
                    </div>
                    <div class="box-body">
                        <p class="text-muted">Use the camera scanner for a fast demo, or paste the QR URL below if you want a manual fallback.</p>

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
                            Scan a student ID card to see the result here.
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Admission No</th>
                                        <th>Student</th>
                                        <th>Class</th>
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
                    (result.student_name ? '<br><strong>Student:</strong> ' + escapeHtml(result.student_name) : '') +
                    (result.admission_no ? '<br><strong>Admission No:</strong> ' + escapeHtml(result.admission_no) : '')
                );
        }

        function appendResultRow(result) {
            var row = '' +
                '<tr>' +
                    '<td>' + escapeHtml(result.admission_no) + '</td>' +
                    '<td>' + escapeHtml(result.student_name) + '</td>' +
                    '<td>' + escapeHtml(result.class_section) + '</td>' +
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
            $('#qr-reader-message').text('Grant camera access, then present each student ID card to the scanner.');
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
