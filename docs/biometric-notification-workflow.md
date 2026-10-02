# Biometric guardian notification workflow

Applies to SchoolLift biometric workflow through database migration 143.

## What the platform does

SchoolLift creates guardian notifications only after a student biometric event has passed every attendance safety check. The event must be accepted in **Live** mode, linked to an active student identity, and successfully projected into official attendance. Simulation, Shadow, quarantined, duplicate, unprojected, staff, and rejected events do not create guardian messages.

An eligible Check In or Check Out event can create two separate message types:

1. an attendance message saying the student checked in or checked out; and
2. an outstanding-fee message, but only when the Fees module currently reports an unpaid balance for that student's active `student_session`.

Messages are written to `biometric_notification_queue`; attendance processing never waits for an email, SMS, or WhatsApp provider. A provider failure therefore cannot undo or delay official attendance.

## Recipient and channel mapping

| Channel | Student field used | Required provider |
|---|---|---|
| Email | `students.guardian_email` | SchoolLift email configuration |
| SMS | `students.guardian_phone` | Active SMS gateway |
| WhatsApp | `students.guardian_phone` | Enabled WhatsApp gateway |

The current student schema has one guardian phone field. SchoolLift therefore treats the guardian phone as the guardian's WhatsApp number when WhatsApp is enabled. Store it in a format accepted by the configured gateway, preferably including the country code. A missing recipient is recorded as a failed/skipped queue item; the system does not fall back from one channel to another.

## The two levels of switches

A notification is queued only when **both** levels permit its channel:

1. On **Biometric Attendance > Setup**, the attendance direction and biometric master channel must be enabled:
   - Notify on student Check In;
   - Notify on student Check Out;
   - Allow biometric Email notifications;
   - Allow biometric SMS notifications; and/or
   - Allow biometric WhatsApp notifications.
2. On **System Settings > Notification Setting**, the Email, SMS, or WhatsApp checkbox must be enabled on the individual template.

The three editable templates are:

- **Biometric Student Check In** (`biometric_attendance_in`)
- **Biometric Student Check Out** (`biometric_attendance_out`)
- **Biometric Outstanding Fees Reminder** (`biometric_fees_due`)

Migration 143 copies the existing biometric Email/SMS/WhatsApp master values into both attendance templates. The new outstanding-fee template starts with every channel disabled so a school cannot incur unexpected messaging charges. A school must review its wording and explicitly enable each required fee-notification channel.

The fee template is independent of the two attendance-direction switches. It can run after any eligible projected student Check In or Check Out as long as its template channel and the corresponding biometric master channel are enabled.

## Editable template variables

Attendance templates support:

- `{{guardian_name}}`
- `{{student_name}}`
- `{{admission_no}}`
- `{{attendance_direction}}`
- `{{attendance_action}}`
- `{{attendance_time}}`
- `{{attendance_date}}`
- `{{school_name}}`

The outstanding-fee template supports:

- `{{guardian_name}}`
- `{{student_name}}`
- `{{admission_no}}`
- `{{outstanding_amount}}`
- `{{currency_symbol}}`
- `{{fee_item_count}}`
- `{{session_name}}`
- `{{school_name}}`

The subject and message body are both rendered from the saved template. The SMS template ID remains available for gateways that require a registered message-template identifier.

## How the fee check works

The reminder reads the Fees module through `Studentfeemaster_model::getStudentFees()` for the student's current session. For every assigned fee item it uses the same balance rule as the existing fee reports:

```text
outstanding = assigned fee - payments - discounts
```

Only positive item balances are added. Payment fines are not added to this base unpaid-fee balance, matching the application's established outstanding-balance report.

The balance is checked twice:

1. before queueing, so students with no unpaid fee do not get fee queue rows; and
2. immediately before delivery, so a payment or discount posted after the clock event cancels a stale reminder.

An outstanding-fee notification is deduplicated to **one per student attendance day per channel**. Repeated scans and a later Check Out do not create repeated paid reminders on the same day. Check In and Check Out attendance messages remain separate, with at most one of each per day and channel.

## Queue processing and retries

The biometric maintenance cron calls `processNotificationQueue(20)` and administrators can also use **Process notification queue now** on the biometric Setup tab.

The worker claims eligible pending/retry rows, sends through the configured provider, and records the result. A transient provider failure is retried at increasing intervals, starting at five minutes, up to five attempts. Work left in `processing` by an interrupted worker is recovered after fifteen minutes. Permanently failed items stay visible with a redacted error and can be deliberately requeued after the provider configuration or guardian contact is corrected.

Turning off a master or template channel before delivery prevents already queued messages for that channel from being sent. Editing a template before delivery makes the queued item use the latest saved wording.

## GIS snapshot finding

The supplied `trixschool_gis.sql` snapshot was in Shadow mode and had Check In, Check Out, Email, SMS, and WhatsApp biometric switches set to `0`. Its original biometric workflow therefore could technically deliver all three channels, but GIS would not send them while those switches remained disabled. All 87 active students in that snapshot had blank guardian email and guardian phone fields, and the snapshot had no `student_fees_master` assignments. Consequently, the snapshot itself cannot deliver guardian alerts or create an outstanding-fee reminder until current guardian contacts and fee assignments exist. The live database may have changed since the snapshot was taken, so verify it again before enablement.

The GIS go-live operation intentionally left notification switches off. Going Live and enabling paid/external notifications are separate operational decisions.

## Deployment and enablement

1. Back up the school database.
2. Deploy the matching application code.
3. Import `docs/biometric-notification-templates-migration-143.sql` into that school database, or run the CodeIgniter migration to version 143.
4. Confirm both verification queries at the end of the SQL return zero rows.
5. Configure and test the school's Email, SMS, and/or WhatsApp provider.
6. Complete `guardian_email` and `guardian_phone` for intended recipients.
7. Review the three messages under **System Settings > Notification Setting** and enable only the required channel checkboxes.
8. Enable the corresponding master channels and Check In/Check Out switches under **Biometric Attendance > Setup**.
9. Perform one controlled Live student Check In and Check Out, process the queue, and confirm provider delivery and fee behavior.

Never enable a channel only to test whether provider credentials are correct on a full school population. Use a controlled student record with approved guardian contacts first.

## Relevant implementation files

- `application/libraries/Biometric_attendance_service.php` — eligibility, queueing, fee checks, rendering, delivery, and retries
- `application/models/Biometric_attendance_model.php` — migration readiness checks
- `application/controllers/admin/Notification.php` — template channel settings and editing
- `application/views/admin/notification/setting.php` — Email/SMS/WhatsApp toggles
- `application/views/admin/biometricattendance/index.php` — biometric master switches and queue status
- `application/migrations/143_add_biometric_notification_templates.php` — application migration
- `docs/biometric-notification-templates-migration-143.sql` — direct per-school import file
