# SchoolLift biometric gateway

This is the on-premises, PHP 8.2-compatible synchronizer between one school's ZKBio Time installation and the SchoolLift biometric V2 API. It is designed for **one bidirectional terminal**: the same serial sends an explicit ZKBio `punch_state` for both IN and OUT.

The gateway does not guess direction, alternate punch direction, receive face/fingerprint templates, or call the legacy `/biometric` endpoint.

## What it does

Each `once` run performs this sequence:

1. Acquire a non-blocking file lock so overlapping Task Scheduler runs cannot race.
2. Send a safe status heartbeat and claim at most one website-requested action.
3. Deliver any previously queued events.
4. Every five minutes, or on a privileged website request, download and validate a complete SchoolLift directory snapshot before making any ZKBio change.
5. Reconcile SchoolLift-owned departments, positions, active current-session students, and active non-Super-Admin staff through supported ZKBio APIs.
6. Authenticate to ZKBio Time and poll the configured terminal with an overlap window.
7. Copy only the stable event ID, person code, occurrence time, terminal serial, punch state, and verification-method code.
8. Atomically save new events and the polling cursor in SQLite.
9. POST batches to `/api/biometric/v2/events` with the SchoolLift bearer token.
10. Mark `accepted`, `duplicate`, `quarantined`, and `rejected` outcomes as durably acknowledged.
11. Save and report safe directory/attendance results, then send a final heartbeat.
12. Defer network errors, `401`, `403`, `408`, `429`, and `5xx` responses with bounded exponential backoff.

If the process crashes after SchoolLift accepts a batch but before SQLite is updated, the next run repeats the same external event IDs. SchoolLift's event-level idempotency returns `duplicate`; it must not create another attendance event.

## Requirements

- Windows 10/11 or Windows Server supported by the school's ZKBio Time release.
- 64-bit PHP 8.2 CLI with `curl`, `json`, `openssl`, `pdo_sqlite`, and `sqlite3` enabled.
- ZKBio Time/BioTime API credentials licensed for transaction reads plus employee, department, position, area, employee-delete, and device-resync APIs.
- A SchoolLift integration token created by **Attendance > Biometric Attendance > Setup**.
- HTTPS with a valid certificate for SchoolLift. Prefer HTTPS for ZKBio; when the vendor exposes only local HTTP, bind/use `127.0.0.1` on the same protected PC and enable only the narrow loopback exception.
- Correct `Africa/Lagos` time, NTP, and a terminal configured to let the user explicitly choose IN or OUT.

Run this from the gateway directory to verify extensions:

```powershell
& "C:\PHP82\php.exe" -v
& "C:\PHP82\php.exe" -m | Select-String "curl|json|openssl|PDO|pdo_sqlite|sqlite3"
```

## Recommended no-terminal Windows setup

An ordinary school operator should not type PHP or PowerShell commands. On the
protected Windows computer that runs ZKBio Time:

1. Put this gateway folder in a permanent location outside every IIS, Apache,
   Nginx, XAMPP, WAMP, or Laragon web directory. Do not run it from a temporary
   download folder that may later be deleted.
2. Install PHP 8.2 with the required extensions, or use a production installer
   that bundles the approved PHP runtime.
3. In SchoolLift, create the ZKBio integration token and set the attendance mode
   to **Shadow** before testing the physical connection.
4. Double-click
   `windows\SchoolLift-Gateway-Manager.cmd` and approve the Windows UAC prompt.
5. Enter the SchoolLift HTTPS address, local ZKBio address, one terminal serial,
   least-privileged ZKBio API credentials, the area IDs, and the one-time SchoolLift integration
   token.
6. Select **Save protected configuration**, then **Test connections**.
7. Select **Install / repair automatic sync**. The manager installs the existing
   once-per-minute task under Windows `LOCAL SERVICE`, starts it, and verifies
   that the task contains only the fixed gateway action and fixed configuration
   path. It also makes the gateway code read-only to ordinary local users so
   they cannot replace code that the service identity will execute.

The manager saves non-executable JSON at
`C:\ProgramData\SchoolLift\Biometric\gateway-config.json`. Its SQLite queue,
lock, and redacted log live in the protected `runtime` directory beside it. The
configuration is ACL-restricted to Administrators, SYSTEM, and read-only access
for the gateway service identity. Secrets are never placed in Task Scheduler
arguments.

The manager provides only these fixed local controls:

- test ZKBio and SchoolLift connections (`doctor`);
- inspect the local queue (`status`);
- request one synchronization (`once`);
- show the last 100 redacted log lines;
- requeue permanently failed records after a separate warning and confirmation;
- install, verify, or remove only the named SchoolLift scheduled task.

It has no command box, file picker, configurable program path, inbound listener,
or remote shell. The hosted website may enqueue only the gateway's named,
short-lived `connection_test`, `sync_now`, `directory_sync`, or confirmed
`retry_failed` request.
The already-running gateway asks for one such request over its authenticated
outbound HTTPS connection and maps it to fixed internal code; it never receives
command text, arguments, executable names, or filesystem paths.

These repository `.cmd` and PowerShell files are an auditable setup aid, but
they are not code-signed. Production distribution should be an Authenticode-
signed MSI/EXE that bundles and verifies the approved PHP runtime, installs the
gateway in Program Files, creates Start Menu shortcuts, and supports controlled
upgrades. Do not train schools to ignore Windows publisher warnings.

After installation, the same background task is used for both **Shadow** and
**Live**. Change the operating mode only in the protected SchoolLift website.
Moving from Shadow to Live does not require a command, reinstall, or different
gateway configuration.

## Configuration

Keep configuration and runtime data outside the website directory. A typical layout is:

```text
C:\SchoolLift\biometric-gateway\       gateway code (read-only for service account)
C:\ProgramData\SchoolLift\Biometric\  gateway-config.json
C:\ProgramData\SchoolLift\Biometric\runtime\  SQLite queue, log and lock
```

The repository's `tools/.htaccess` denies browser access in Apache deployments. Configure an equivalent explicit deny rule in Nginx/IIS, and preferably copy the gateway code outside the web document root as shown above.

The Windows manager creates a protected JSON configuration automatically. For a
manual maintainer installation, copy either `config.example.json` or
`config.example.php` to the protected location and edit it. Existing PHP
configuration files remain supported. The important settings are:

- `gateway_id`: stable name for this installation; do not change it casually.
- `provider.base_url`: local ZKBio Time URL.
- `provider.terminal_serial`: the **one** approved physical device serial.
- `provider.area_ids`: ZKBio area IDs assigned to synchronized people; Active roster mode refuses to run when this is empty.
- `provider.employees_path`, `departments_path`, `positions_path`, `areas_path`, and `resync_path`: supported endpoints for the installed licensed release. Never replace these with database access.
- `provider.resync_to_device`: asks ZKBio to deliver created/updated personnel to configured device areas. It does not create biometric templates.
- SchoolLift's integration setup owns the authoritative punch-state map (initially `0 => IN`, `1 => OUT`). Confirm it using the licensed release/firmware before shadow/live mode. The gateway deliberately forwards the raw state unchanged so unknown values reach reconciliation instead of being guessed or discarded locally.
- `provider.overlap_seconds`: default two-day replay window. Make it longer than the longest expected terminal/ZKBio outage.
- `schoollift.base_url`: exact HTTPS tenant hostname; the tenant is resolved from this host, never from payload data.
- `schoollift.bearer_token`: high-entropy token shown once by SchoolLift.
- `directory.default_interval_seconds`: local fallback interval, normally 300 seconds.
- `directory.maximum_delete_count` and `maximum_delete_percent`: safety thresholds. A batch exceeding either one is held until a user with biometric delete privilege approves that exact snapshot.

The committed example uses local `http://127.0.0.1` for ZKBio because many on-prem editions expose their API only on the same Windows host; `allow_insecure_localhost` cannot authorize an HTTP LAN/public host. If the licensed edition supports HTTPS, use it and turn the exception off. SchoolLift must always use HTTPS outside a fully local disposable test.

The PHP example reads passwords from `ZKBIO_GATEWAY_USERNAME`, `ZKBIO_GATEWAY_PASSWORD`, and `SCHOOLLIFT_BIOMETRIC_TOKEN`. A Task Scheduler service identity must have those variables in its own environment. Alternatively, use the manager-generated JSON or an external `config.php` protected with Windows ACLs; never put secrets into the repository, a shortcut, a batch file, or Task Scheduler arguments.

Example ACL setup, run in an elevated PowerShell window and substitute the actual service identity:

```powershell
New-Item -ItemType Directory -Force "C:\ProgramData\SchoolLift\Biometric" | Out-Null
icacls "C:\ProgramData\SchoolLift\Biometric" /inheritance:r
icacls "C:\ProgramData\SchoolLift\Biometric" /grant:r "Administrators:(OI)(CI)F" "SCHOOL\schoollift-gateway:(OI)(CI)M"
```

## Maintainer CLI fallback

The following commands remain available for installation engineers, automated
testing, and recovery. They are not routine Shadow/Live operating instructions;
use the Windows manager for normal setup and support.

```powershell
$Php = "C:\PHP82\php.exe"
$Gateway = "C:\SchoolLift\biometric-gateway\bin\gateway.php"
$Config = "C:\ProgramData\SchoolLift\Biometric\gateway-config.json"

& $Php $Gateway doctor --config=$Config
& $Php $Gateway once --config=$Config
& $Php $Gateway status --config=$Config
& $Php $Gateway retry-dead --config=$Config
```

- `doctor` verifies the local runtime, authenticates to ZKBio Time, performs a five-minute read-only poll, and checks that SchoolLift is healthy and currently accepts gateway events (`shadow` or `live`).
- `once` safely runs one synchronization cycle.
- `status` makes no network request and reports cursor plus pending/delivered/dead counts.
- `retry-dead` requeues terminally failed batches only after an operator corrects their cause.

Exit code `0` means success (or another process already owns the lock). Exit code `1` means an endpoint, configuration, or delivery needs attention. Task Scheduler should retain the last result and the JSON-lines gateway log should be monitored.

## Routine website controls

After the one-time Windows installation, authorized school staff use the
Biometric Attendance page instead of these maintainer commands. Each automatic
run posts a heartbeat with only connection state, last-sync state, queue counts,
cursor, and a bounded error message. The website can show whether the gateway is
healthy even when nobody has punched recently.

The gateway stores a claimed website action in SQLite before running it and
stores its safe result before reporting it. If Windows, the internet, or PHP
stops between those steps, the same identifier can be replayed safely. Failure
of this control channel is logged but does not stop the normal durable attendance
synchronization.

### Automatic roster ownership

SchoolLift is authoritative for identity and organization; ZKBio remains
authoritative for face/fingerprint templates and raw punches. Roster mode is
configured per integration:

- `off`: no directory reads or writes;
- `preview`: calculate and report create/adopt/update/delete/conflict results without writing ZKBio;
- `active`: apply the complete desired state through the ZKBio API.

Students use Admission No. as `emp_code`, class as department, and class arm as
position. Position codes contain both class and arm IDs, so repeated names such
as “A” remain unique. Staff use Employee ID, staff department, and designation.
Parents/guardians and staff with the Super Admin role are excluded.

A unique existing `emp_code` is adopted and SchoolLift-managed identity fields
are overwritten. Duplicate or missing codes are conflicts and are never guessed
by name. Updates preserve ZKBio-owned biometric data because the gateway sends
only identity/organization fields. A new person still needs one supervised
face/fingerprint enrollment in ZKBio or on the terminal.

After a complete valid snapshot, a previously owned person who is disabled,
deleted, excluded, or no longer current is hard-deleted from ZKBio. More than
10 deletions or more than 10% of the managed roster pauses the whole deletion
set. The Biometric Attendance setup page shows the exact snapshot and requires
biometric delete privilege to approve it. An incomplete, unreachable, or
checksum-invalid snapshot never causes deletion.

## Manual scheduled-task fallback

Create a dedicated, non-administrator local or domain service account. Deny interactive login where local policy permits, grant only read/execute access to the code and modify access to the protected runtime directory, and do not use a personal administrator account.

The manager normally performs this step. For a manual installation in an
elevated PowerShell window:

```powershell
Set-ExecutionPolicy -Scope Process RemoteSigned
cd "C:\SchoolLift\biometric-gateway"
.\windows\install-task.ps1 `
  -PhpPath "C:\PHP82\php.exe" `
  -ConfigPath "C:\ProgramData\SchoolLift\Biometric\gateway-config.json" `
  -TaskUser "SCHOOL\schoollift-gateway"
```

The installer requests that account's password without placing it in the task arguments. It creates a once-per-minute task with a five-minute ceiling, `IgnoreNew` overlap protection, restart policy, and limited run level. The application lock is a second safety layer.

The manager instead uses the built-in restricted `LOCAL SERVICE` identity and
calls the same script with `-UseLocalService`. Use that identity only when it can
reach the local ZKBio API; a vendor environment that requires a domain identity
must be commissioned by an administrator with the manual credential path.

After installation:

1. Run the task manually from Task Scheduler.
2. Confirm last result `0x0`.
3. Run `status`; pending and dead should be zero after a healthy sync.
4. Inspect the JSON-lines log without copying credentials or student data into a support ticket.
5. Reboot Windows and repeat the checks.

To remove only the task:

```powershell
.\windows\uninstall-task.ps1
```

The uninstall script deliberately keeps configuration, SQLite state, logs, and credentials for controlled backup/removal.

## No-device gateway test

The separate `tools/biometric-sandbox` process behaves like a small ZKBio transaction API. It is a developer fixture, not the school-facing Test Terminal.

Ordinary demonstrations use **Attendance > Biometric Attendance > Test
Terminal** in Simulation mode and require no local command. The commands below
are retained for gateway developers and commissioning engineers. Run them only
on loopback against a disposable staging school in **Shadow** mode. Never expose
the mock on the public website, never use its synthetic events against a live
school, and never add a Live-mode simulation bypass.

Terminal 1:

```powershell
cd C:\SchoolLift\biometric-sandbox
$env:BIOMETRIC_SANDBOX_USERNAME = "sandbox_admin"
$env:BIOMETRIC_SANDBOX_PASSWORD = "sandbox_password"
$env:BIOMETRIC_SANDBOX_TOKEN = "sandbox-token"
$env:BIOMETRIC_SANDBOX_CONTROL_KEY = "sandbox-control-key"
& "C:\PHP82\php.exe" bin\serve.php
```

Terminal 2 injects one IN and one OUT from the **same serial**:

```powershell
cd C:\SchoolLift\biometric-sandbox
& "C:\PHP82\php.exe" bin\simulate.php `
  --scenario=normal `
  --employee=DEMO-STUDENT-001 `
  --terminal-serial=SIM-GATE-001
```

For this local test only, use these gateway settings:

```php
'verify_tls' => true,
'allow_insecure_localhost' => true,
'provider' => [
    'base_url' => 'http://127.0.0.1:8787',
    'terminal_serial' => 'SIM-GATE-001',
    // plus the sandbox credentials and normal provider settings
],
```

Point `schoollift.base_url` at a disposable SchoolLift staging tenant in `shadow` mode. The mock behaves as the real gateway source, and gateway events are accepted only in `shadow` or `live`; `simulation` is reserved for the website Test Terminal and trusted QR demonstration. Run `doctor`, `once`, and `status`. The application should show two shadow events, one daily session with an IN and OUT, and **no official attendance write**.

The loopback mock may use HTTP while `verify_tls` remains enabled; TLS verification still protects the remote SchoolLift HTTPS connection. Set `verify_tls=false` only in a completely local, disposable test where **both** endpoints are loopback. Never disable it in production. `allow_insecure_localhost` may remain enabled only when the production ZKBio API is genuinely reached through `127.0.0.1` on the same protected PC; it cannot authorize an HTTP LAN/public endpoint. SchoolLift remains HTTPS.

## API contract

The gateway sends:

```json
{
  "batch_id": "deterministic-batch-uuid",
  "gateway_version": "1.0.0",
  "cursor": "2026-08-11T08:00:00+00:00",
  "events": [
    {
      "external_event_id": "zkbio:SCHOOL-GATE-001:381002",
      "person_code": "STU-001",
      "occurred_at": "2026-08-11T06:31:00+00:00",
      "device_serial": "SCHOOL-GATE-001",
      "punch_state": "0",
      "verification_method": "face"
    }
  ]
}
```

SchoolLift replies with one outcome for every event:

```json
{
  "results": [
    {
      "external_event_id": "zkbio:SCHOOL-GATE-001:381002",
      "status": "accepted",
      "message": "IN event recorded in shadow mode"
    }
  ]
}
```

Recognized outcomes are `accepted`, `duplicate`, `quarantined`, and `rejected`. A successful response that omits an event outcome is treated as incomplete and safely retried.

Directory reconciliation uses `GET /api/biometric/v2/directory` and reports one
bounded, idempotent run to `POST /api/biometric/v2/directory/result`. The full
snapshot includes stable SchoolLift keys, desired hashes, groups, people,
tombstones, conflicts, totals, and a checksum. Successful Active results create
or refresh SchoolLift identity mappings automatically, including moving a
promoted student mapping to the current `student_session.id`.

## Retry and recovery behavior

- The SQLite cursor advances only in the same committed transaction that queues every fetched event.
- A provider failure never advances the cursor.
- Provider and SchoolLift backoff is persisted as timestamps; the process does not sleep for long periods.
- `Retry-After` is honored up to the configured maximum.
- A body-level `400`/`404`/`409`/`422` without per-event results is marked dead because resending unchanged data cannot repair it.
- Authentication and server/network errors stay pending until the attempt limit, then become dead and require operator review.
- Delivered rows remain for 30 days by default so overlap polls cannot requeue them.

Back up the protected JSON/PHP configuration securely and back up `gateway.sqlite` with an SQLite-safe method while the scheduled task is stopped. Copying only the main database while WAL files are active can produce an incomplete backup.

## Test suite

From the repository root:

```bash
php tools/biometric-gateway/tests/run.php
```

The suite starts loopback mock servers and verifies one-serial IN/OUT, template stripping, durable cursor/queue restart, overlap deduplication, `429`, `503`, provider outage, authentication repair, safe replay, directory create/adopt/update/no-op/delete behavior, enrollment preservation, and mass-deletion approval. It does not require MySQL, CodeIgniter, a physical device, or internet access.

## Production limitations

- ZKBio Time editions differ. Confirm authentication, pagination, timestamp, stable ID, `punch_state`, and verification codes against the purchased licence and firmware.
- Before Active roster mode, prove the licence permits employee, department, position, area, delete, and resync operations. A `401`, `403`, or licence denial is a vendor-entitlement blocker; do not manipulate ZKBio's database or automate its UI as a fallback.
- The default two-day overlap cannot recover an event whose punch time is older than the window after ZKBio only exposes it later. Increase it beyond the maximum expected outage, and test a real buffered-terminal recovery.
- A green gateway test does not prove face/fingerprint accuracy, liveness, user capacity, weather performance, queue speed, power recovery, or the user's correct IN/OUT selection. These require the documented physical shadow pilot.
