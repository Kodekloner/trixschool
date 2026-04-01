<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR Attendance</title>
    <style type="text/css">
        body {
            margin: 0;
            background: #f5f7fb;
            color: #1f2937;
            font-family: Arial, sans-serif;
        }
        .attendance-card {
            max-width: 540px;
            margin: 48px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.10);
            padding: 28px;
        }
        .attendance-status {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 18px;
        }
        .attendance-status.success {
            background: #dcfce7;
            color: #166534;
        }
        .attendance-status.info {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .attendance-status.warning {
            background: #fef3c7;
            color: #92400e;
        }
        .attendance-status.danger {
            background: #fee2e2;
            color: #b91c1c;
        }
        h1 {
            margin: 0 0 10px;
            font-size: 28px;
        }
        p {
            margin: 0 0 18px;
            line-height: 1.5;
        }
        .attendance-meta {
            border-top: 1px solid #e5e7eb;
            margin-top: 22px;
            padding-top: 18px;
        }
        .attendance-meta div {
            margin-bottom: 10px;
        }
        .attendance-meta strong {
            display: inline-block;
            min-width: 120px;
        }
    </style>
</head>
<body>
    <div class="attendance-card">
        <div class="attendance-status <?php echo html_escape($status); ?>">
            <?php echo html_escape($status); ?>
        </div>
        <h1>QR Attendance</h1>
        <p><?php echo html_escape($message); ?></p>

        <?php if ($full_name !== '') { ?>
            <div class="attendance-meta">
                <div><strong><?php echo html_escape($entity_label); ?></strong><?php echo html_escape($full_name); ?></div>
                <div><strong>ID</strong><?php echo html_escape($identity_no); ?></div>
                <div><strong><?php echo html_escape($details_label); ?></strong><?php echo html_escape($details_value); ?></div>
                <div><strong>Date</strong><?php echo html_escape($attendance_on); ?></div>
                <div><strong>Scanned At</strong><?php echo html_escape($scanned_at); ?></div>
            </div>
        <?php } ?>
    </div>
</body>
</html>
