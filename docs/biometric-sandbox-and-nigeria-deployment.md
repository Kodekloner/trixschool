# Biometric attendance sandbox and Nigeria deployment guide

Last reviewed: 10 August 2026 (Africa/Lagos)

This guide covers two different stages:

1. Testing biometric attendance without buying a physical terminal.
2. Procuring and deploying a real student entry/checkout system in Nigeria.

The repository now contains a safe, local ZKBio Time-style simulator. It is useful for developing and testing the integration boundary, but it does not pretend to scan a real face or fingerprint. Real biometric accuracy, liveness detection, enrollment quality, queue speed, device storage, power recovery, and firmware behaviour still require a physical pilot before production approval.

## Executive decision

For a normal Nigerian primary or secondary school with fewer than 3,000 students, the best value deployment is:

- two **ZKTeco SenseFace 3A** terminals, one permanently assigned to **IN** and one to **OUT**;
- face as the normal touch-free method, with fingerprint and 125 kHz RFID card as fallbacks;
- wired Ethernet to ZKBio Time 9.0.6 on a small Windows gateway, or BioTime Africa only after its API access is confirmed in writing;
- a versioned SchoolLift synchronizer between ZKBio Time and SchoolLift;
- UPS/surge protection, terminal power supplies, and mobile-data failover.

At the time of research, a current Nigerian SenseFace 3A listing was **₦299,000 per unit**. It supports 3,000 faces, 6,000 fingerprints, 6,000 cards, 150,000 transactions, TA Push switching, and IP65 protection. The listing is a marketplace price, so the unit, firmware, warranty, VAT, and delivery must be verified before payment.

For a large school, multi-school gateway, or expected enrollment above 3,000, use two **SenseFace 7A** terminals instead. Its official specifications include 10,000 standard face and fingerprint templates, 50,000 users/cards, 300,000 transactions, face verification under 0.35 seconds, TA Push, TCP/IP, optional Wi-Fi, IP65, and 12V/3A power. There is no trustworthy public Nigerian list price for the 7A, so the correct procurement price is **formal quote required**. A **₦600,000–₦900,000 per-unit planning allowance** is used in this document only to reserve budget; it is not a seller quotation.

Do not use one shared terminal on which students choose IN or OUT. Two fixed terminals make direction deterministic and reduce mistaken checkout events. Clear signs and different terminal serial numbers should identify each direction.

## What was added to this repository

The no-device sandbox is under `tools/biometric-sandbox/`. It provides:

- mock `POST /api-token-auth/` and `POST /jwt-api-token-auth/` token endpoints;
- mock `GET /iclock/api/transactions/` transaction polling;
- filters and pagination similar to a common ZKBio Time transaction API;
- isolated JSON state with file locking;
- normal IN/OUT, duplicate, delayed, out-of-order, unknown student, unknown device, malformed event, bad authentication, HTTP 429, and HTTP 503 scenarios;
- loopback-only operation by default;
- dependency-free unit and HTTP integration tests.

The repository also has `tests/legacy_biometric_endpoint_test.php`. That test loads the real current `Biometric` controller and the real `Stuattendence_model::onlineattendence()` method behind in-memory CodeIgniter/database doubles. It proves the current entry behaviour without opening a MySQL connection.

An authenticated website demonstration is available at **Attendance > Biometric Demo** (`/admin/biometricdemo`). It uses the same synthetic scenario generator directly inside CodeIgniter, so school staff can demonstrate the event flow without starting a command-line server. The page is restricted to logged-in staff with `student_attendance.can_view`, uses a separate enforced session token, and performs no student lookup, device connection, attendance insert, file-state update, or other database write.

The website demonstration adds only a controller, a stateless demonstration library, a view, and an Attendance navigation link. It does not change the production biometric receiver, attendance model, configuration, or database schema. Direct web access to files under `tools/` is denied for Apache deployments by `tools/.htaccess`; other web servers should also exclude or deny that directory.

## What is not implemented yet

The sandbox is a test provider, not the production integration itself. These production items remain a separate implementation phase:

- a ZKBio Time polling/webhook synchronizer;
- an immutable biometric event table with a unique vendor event ID;
- device and student identity mappings;
- an attendance session model that stores first entry and final checkout;
- retry/dead-letter handling and an operator reconciliation screen;
- a secured, versioned SchoolLift ingestion API.

That distinction matters because the existing `/biometric` endpoint is not sufficient for proper checkout. It accepts a small vendor-shaped JSON body, allowlists only the serial number, maps `user_id` to admission number, always records attendance type `Present`, and inserts at most one row per student per date. A second same-day event—including checkout—is rejected by the current `onlineattendence()` implementation. The safe regression test deliberately preserves and exposes that limitation.

The device serial is an identifier, not a secret. The legacy endpoint has no independent API credential, signature, nonce, or replay protection; it also trusts the supplied timestamp, has no direction field, performs limited schema validation, and returns no explicit error body for several invalid requests. Do not expose it as the final internet-facing hardware integration. Put the production adapter behind authenticated HTTPS, server-side tenant/device ownership, strict validation, idempotency, rate limiting, and an immutable audit trail.

## Recommended architecture

```text
Student
   |
   +--> fixed IN SenseFace terminal ----+
   |                                    |
   +--> fixed OUT SenseFace terminal ---+--> wired LAN --> ZKBio Time 9.0.6
                                                            |
                                                            | REST API/webhook
                                                            v
                                                    SchoolLift sync worker
                                                            |
                                                            v
                                             immutable biometric event ledger
                                                            |
                                                            v
                                             attendance entry/checkout summary
```

The terminals should communicate with ZKBio Time, not directly with the current legacy endpoint. The sync worker should retrieve vendor events, normalize them, enforce device/student mappings, deduplicate them, and then update the SchoolLift attendance view. Raw biometric templates should remain on the terminals/ZKTeco platform; SchoolLift needs transaction metadata, not face images or fingerprint templates.

## Part 1: no-device sandbox testing

### Browser demonstration for school staff

The website demonstration is the recommended interface for school owners, administrators, teachers, sales demonstrations, and staff training. It needs no terminal, face, fingerprint, RFID card, ZKBio Time installation, separate sandbox server, or command line.

The demonstration is available at:

```text
https://YOUR-SCHOOL-DOMAIN/admin/biometricdemo
```

It also appears in the staff navigation as **Attendance > Biometric Demo**.

#### Access requirements

Before demonstrating, confirm that:

1. The latest `dev` deployment containing commit `2f12302` is installed.
2. The server runs PHP 7.4 or newer.
3. The staff user is authenticated.
4. The `student_attendance` module is active for the school.
5. The user's role has **Student Attendance > View** (`student_attendance.can_view`).
6. `tools/biometric-sandbox/src/ScenarioFactory.php` exists in the deployed release because the website service reuses that safe scenario generator.

No database migration or biometric setting is required. Biometric attendance may remain disabled in General Settings because the page does not use the live receiver.

Do not make this page anonymous. “Anyone can demonstrate it” means any authenticated staff member whose role has been deliberately granted Student Attendance view permission. For a public sales demonstration, use a dedicated demo school account rather than exposing an unrestricted route.

#### Safety rules before starting

- Use the supplied code `DEMO/STUDENT/0001`, not a real admission number.
- Use `SIM-IN-001` and `SIM-OUT-001`, not production serial numbers.
- Do not open or post data to `/biometric` during the demonstration.
- Do not enable a physical terminal merely to use this page.
- Confirm the blue **Synthetic demonstration — no attendance is saved** banner is visible.
- Remember that the displayed entry/checkout is an expected preview, not a saved attendance record.

The form accepts other synthetic labels for presentation purposes, but identifiers are limited to letters, numbers, dots, slashes, underscores, and dashes. This validation prevents arbitrary HTML or script input from being displayed.

#### First demonstration: a normal school day

1. Sign in to the school staff portal.
2. Open **Attendance** in the left navigation.
3. Select **Biometric Demo**.
4. Confirm the safety banner states that no attendance is saved.
5. Select **Normal school day**.
6. Leave these safe defaults in place:

   ```text
   Synthetic student code: DEMO/STUDENT/0001
   IN terminal serial:      SIM-IN-001
   OUT terminal serial:     SIM-OUT-001
   ```

7. Select the date for the demonstration. Times are interpreted in `Africa/Lagos`.
8. Click **Run demonstration**.
9. Confirm the result shows:

   - provider HTTP status `200`;
   - events received `2`;
   - accepted `2`;
   - duplicates, quarantined, and rejected all `0`;
   - first entry `07:30:00`;
   - final checkout `15:10:00`;
   - outcome **Complete entry and checkout**.

10. Review the event table. The first event should be an accepted `IN` event from `SIM-IN-001`; the second should be an accepted `OUT` event from `SIM-OUT-001`.
11. Expand **Show complete synthetic JSON** when demonstrating the raw event contract to a technical audience.
12. Point out the final confirmation: `persisted = false` and `database writes = 0`.

#### Understanding the results screen

The results page contains five useful areas:

1. **Provider status** shows the simulated response from a ZKBio Time-style service. A `200` means events were returned; `401`, `429`, and `503` demonstrate failure handling.
2. **Decision counters** show how many events were received, accepted, ignored as duplicates, quarantined, or rejected.
3. **Expected attendance preview** derives the first valid IN and final valid OUT by occurrence time.
4. **Provider delivery sequence** shows whether events arrive together, later, or only after a retry.
5. **Synthetic device events and decisions** explains the validation result for every event.

The complete JSON is useful for developers and integrators. It includes:

- `sandbox: true`;
- `persisted: false`;
- `database_writes: 0`;
- the simulated provider response;
- delivery batches;
- raw events;
- normalized accepted events;
- the expected daily attendance preview.

#### Expected result for every website scenario

| Website selection | Provider and counters | Expected preview or operator action | Pass condition |
|---|---|---|---|
| **Normal school day** | HTTP 200; received 2; accepted 2 | Entry 07:30, checkout 15:10 | Complete entry and checkout |
| **Duplicate event delivery** | HTTP 200; received 3; accepted 2; duplicate 1 | Same entry and checkout as normal | Repeated vendor ID is ignored once and does not create a second attendance event |
| **Delayed checkout event** | HTTP 200; two polling batches; accepted 2 eventually | First poll contains entry; later poll exposes checkout | Eventual preview is entry 07:30 and checkout 15:10 |
| **Out-of-order delivery** | HTTP 200; received 2; accepted 2, even though OUT arrives first | Summary is reordered by `punch_time` | Preview still shows entry 07:30 and checkout 15:10 |
| **Unknown student identity** | HTTP 200; received 1; quarantined 1 | No student attendance is created | Outcome is No attendance change |
| **Unknown terminal** | HTTP 200; received 1; quarantined 1 | Event waits for device investigation/mapping | Outcome is No attendance change |
| **Malformed event** | HTTP 200; received 1; rejected 1 | Invalid payload is not accepted | Outcome is No attendance change |
| **Vendor server error (503)** | First poll HTTP 503; received 0; retry is shown | Preserve cursor and retry safely | No attendance change and retry guidance is displayed |
| **API rate limit (429)** | First poll HTTP 429; received 0; retry is shown | Back off before retrying | No busy-loop and no attendance change |
| **Authentication failure (401)** | HTTP 401; received 0; not blindly retryable | Stop polling and repair credentials | No attendance change and administrator guidance is displayed |

Run all ten scenarios when performing acceptance testing. For a short non-technical demonstration, the four most useful scenarios are Normal, Duplicate, Delayed checkout, and Unknown student.

#### Suggested ten-minute presentation script

Use this sequence when presenting the system to a school:

1. **Explain the architecture:** a student uses a fixed IN or OUT terminal; ZKBio Time supplies events; SchoolLift validates identity, device, timestamp, direction, and event ID.
2. **Run Normal school day:** show the expected 07:30 entry and 15:10 checkout.
3. **Run Duplicate event delivery:** explain why unstable internet may resend an event and why a stable vendor event ID must prevent double attendance.
4. **Run Delayed checkout event:** explain that the student's checkout can arrive during a later poll without losing the morning entry.
5. **Run Out-of-order delivery:** show why occurrence time, rather than arrival order, determines the daily summary.
6. **Run Unknown student identity:** explain quarantine and operator mapping instead of silently creating a person.
7. **Run Vendor server error or API rate limit:** explain safe retry, cursor preservation, and backoff.
8. **Finish with the isolation notice:** show `persisted = false` and `database writes = 0`, then explain that a physical pilot is still required before procurement approval or production use.

#### How to prove that no school attendance was changed

The page is designed to prove its own isolation:

- The controller loads the stateless `Biometricdemo_lib`; it does not load `Stuattendence_model`.
- The service uses only the synthetic `ScenarioFactory` and in-memory arrays.
- The form never calls `/biometric`.
- Every result reports `persisted = false` and `database_writes = 0`.
- Opening the page again with a fresh GET request shows no previous result because demonstration state is not stored.

For a supervised acceptance demonstration, an administrator can also:

1. Open the real Student Attendance or Biometric Attendance Log in another tab.
2. Note the current row count or capture a screenshot.
3. Run several website scenarios.
4. Refresh the real report and confirm no new attendance or biometric-log row appeared.

Do not insert a real student code to perform this proof; the supplied synthetic value is sufficient.

#### Permission and security test

Test both sides of the access rule:

1. Sign in with a staff role that has Student Attendance view permission. Confirm **Attendance > Biometric Demo** is visible and the page loads.
2. Sign in with a test role without that permission. Confirm the menu entry is hidden.
3. While using the unprivileged role, visit `/admin/biometricdemo` directly. Confirm the application denies access.
4. With an authorized account, leave the page open, sign out in another tab or let the session expire, then submit the old form. Confirm the request does not run normally.
5. If the page reports that its demonstration token expired, refresh it and retry. The separate token is enforced even though global CodeIgniter CSRF protection is currently disabled.

#### Website demonstration troubleshooting

| Problem | Likely reason | Resolution |
|---|---|---|
| Biometric Demo is missing from Attendance | Student Attendance module inactive or role lacks `student_attendance.can_view` | Enable the module and grant only the required role permission, then sign in again |
| Direct URL says access denied | Current role is not authorized | Use an authorized demo-school staff account or update the role deliberately |
| “Session token is missing or expired” | Old page, expired session, back-button submission, or reused form | Refresh the page and run the scenario again |
| Form rejects the student or serial label | Unsupported character or more than 64 characters | Use the safe defaults or letters, numbers, `.`, `/`, `_`, and `-` only |
| Form rejects the date | Date is not a real `YYYY-MM-DD` value | Select a valid date with the browser date control |
| Delay is rejected | Value is outside 0–300 seconds | Enter a whole number between 0 and 300 |
| Page reports it cannot generate the demo | Missing scenario file, unsupported PHP, or deployment error | Confirm PHP 7.4+, deploy `tools/biometric-sandbox/src/ScenarioFactory.php`, and inspect the PHP/CodeIgniter error log |
| Menu exists but old code still loads | Stale PHP OPcache or incomplete release | Deploy the complete commit and restart/clear PHP OPcache using the server's normal release procedure |
| Real attendance changes after a demonstration | Someone used the live `/biometric` receiver or another attendance screen | Stop the test, preserve logs, identify the writer, and do not treat it as expected demo behaviour |

#### Browser-demo completion record

For an internal sign-off, record:

- school/demo tenant hostname;
- date and Africa/Lagos time;
- application commit deployed;
- tester and staff role;
- browser and version;
- each scenario run and its pass/fail result;
- confirmation that the live attendance and biometric log were unchanged;
- screenshots of Normal, Duplicate, Delayed, and one rejection/failure scenario;
- defects or questions requiring production-integration work.

The browser page is for demonstrations and staff training. Keep the standalone HTTP mock and command-line tests below for developers who need to test a future synchronizer against token and transaction endpoints.

### Requirements

The website demonstration only requires the normal SchoolLift application and PHP 7.4 or newer. It does not require a separate port, `allow_url_fopen`, `proc_open`, MySQL changes, ZKBio Time, or a device.

The standalone developer sandbox additionally requires:

- PHP `allow_url_fopen=On` for the simulator HTTP client.
- PHP `proc_open` for the automated HTTP integration test.
- An unused loopback port; the default is `8787`.

Composer, MySQL, Docker, internet access, ZKBio Time, and a biometric device are not required.

Check the local runtime from the repository root:

```bash
php --version
php -r "echo 'allow_url_fopen=' . ini_get('allow_url_fopen') . PHP_EOL;"
```

### Safety boundary

Both interfaces use synthetic data. The website page loads CodeIgniter only to enforce login, permission, session-token, and normal layout controls; its demonstration service remains stateless. The standalone sandbox does not load CodeIgniter. Its server binds to `127.0.0.1` by default, and its HTTP router independently rejects non-loopback clients. Runtime state is written only to `tools/biometric-sandbox/var/state.json`; that file is ignored by Git.

Do not point the sandbox or manual HTTP tests at a real SchoolLift hostname. `application/config/database.php` selects tenant database configuration from `HTTP_HOST` and does not provide a safe disposable local database boundary. Any future full-stack HTTP test must use an explicit test-only hostname and a cloned/synthetic database with credentials that cannot reach production.

### Quick automated test

From the repository root:

```bash
php tests/biometric_web_demo_test.php
php tools/biometric-sandbox/tests/run.php
php tests/legacy_biometric_endpoint_test.php
```

Expected final lines:

```text
biometric web demo tests passed (56 assertions)
Biometric sandbox tests passed (40 assertions).
legacy biometric endpoint tests passed
```

The first command tests every website scenario and verifies expected event decisions, entry/checkout previews, validation, and the explicit zero-write safety result without loading a database.

The second command starts a temporary local server on a random free port, tests authentication, authorization, event injection, filtering, pagination, an outage/recovery cycle, state reset, and core scenario logic, then removes its temporary state.

The third command verifies the real legacy controller/model contract entirely in memory. Its successful checkout-related assertion is that the current code rejects a second same-day punch. That is a regression description, not the desired production checkout behaviour.

The current legacy receiver expects a different shape from the mock ZKBio Time API:

```json
{
  "serial_number": "SIM-IN-001",
  "user_id": "TBSW/ST/0002",
  "t": "2026-08-03 07:30:00",
  "ip": "127.0.0.1"
}
```

Do not post the sandbox's ZKBio Time transaction object directly to `/biometric`. The future adapter must deliberately map `terminal_sn` to `serial_number`, `emp_code` to the student identity, and `punch_time` to the event time while retaining `id` and `punch_state` for idempotency and direction. The current receiver has nowhere reliable to store those last two values as an entry/checkout event stream, which is why the V2 ledger is required.

### Start the interactive sandbox

Open a terminal at the repository root:

```bash
cd tools/biometric-sandbox
php bin/serve.php
```

Expected startup output:

```text
SchoolLift biometric sandbox: http://127.0.0.1:8787
Synthetic data only; press Ctrl+C to stop.
```

Leave that terminal running. Open a second terminal in the same directory and run:

```bash
php bin/simulate.php --scenario=normal --employee=TBSW/ST/0002 --date=2026-08-03
```

The response should contain:

- scenario `normal`;
- an injection response with HTTP `201` and two created events;
- a transactions response with HTTP `200` and `count: 2`;
- one event from `SIM-IN-001` with punch state `0`;
- one event from `SIM-OUT-001` with punch state `1`.

The simulator resets sandbox state before each scenario unless `--keep-state` is supplied.

### Available scenarios and pass criteria

List the scenarios with:

```bash
php bin/simulate.php --list
```

| Scenario | What the sandbox produces | What the future SchoolLift synchronizer must do |
|---|---|---|
| `normal` | IN at 07:30, OUT at 15:10 | Store two immutable events; derive first entry and final checkout |
| `duplicate` | Same vendor transaction ID delivered twice | Insert the event once and acknowledge/replay safely |
| `delayed` | OUT is invisible until the requested delay expires | Move the cursor only after committed work; collect the event on a later poll |
| `out-of-order` | OUT is returned before IN | Recompute the daily session by event time, not arrival order |
| `unknown-student` | `emp_code` has no SchoolLift mapping | Quarantine it for operator mapping; do not create a student silently |
| `unknown-device` | Unregistered serial number | Reject/quarantine and raise an alert |
| `invalid-event` | Missing employee, invalid time, unsupported state | Fail validation and retain a redacted diagnostic record |
| `server-error` | First transaction poll returns HTTP 503 | Retry with bounded exponential backoff and no cursor loss |
| `rate-limit` | First transaction poll returns HTTP 429 | Respect backoff/`Retry-After` when supplied; do not busy-loop |
| `auth-error` | Token request uses a wrong password | Stop polling, mark credentials unhealthy, and alert an administrator |

Example commands:

```bash
php bin/simulate.php --scenario=duplicate
php bin/simulate.php --scenario=delayed --delay-seconds=10
php bin/simulate.php --scenario=out-of-order
php bin/simulate.php --scenario=unknown-student
php bin/simulate.php --scenario=unknown-device
php bin/simulate.php --scenario=invalid-event
php bin/simulate.php --scenario=server-error
php bin/simulate.php --scenario=rate-limit
php bin/simulate.php --scenario=auth-error
```

For `delayed`, run the transaction query immediately and confirm only entry is visible, wait longer than `--delay-seconds`, then query again and confirm checkout is visible. No long wait is needed in the automated test because its clock-controlled unit test checks both sides deterministically.

### Inspect the mock API manually with curl

Health check:

```bash
curl -s http://127.0.0.1:8787/health
```

Request a token:

```bash
curl -s -X POST http://127.0.0.1:8787/api-token-auth/ \
  -H 'Content-Type: application/json' \
  -d '{"username":"sandbox_admin","password":"sandbox_password"}'
```

Read transactions:

```bash
curl -s 'http://127.0.0.1:8787/iclock/api/transactions/?page_size=100&ordering=punch_time' \
  -H 'Authorization: Token sandbox-token'
```

Filter one direction/device:

```bash
curl -s 'http://127.0.0.1:8787/iclock/api/transactions/?terminal_sn=SIM-OUT-001' \
  -H 'Authorization: Token sandbox-token'
```

Test pagination:

```bash
curl -s 'http://127.0.0.1:8787/iclock/api/transactions/?page=1&page_size=1' \
  -H 'Authorization: Token sandbox-token'
```

The response should report the total in `count`, return one item in `data`, and provide `next` when another page exists.

Inspect state using the separate control credential:

```bash
curl -s http://127.0.0.1:8787/sandbox/state \
  -H 'X-Sandbox-Key: sandbox-control-key'
```

Reset all synthetic state:

```bash
curl -s -X POST http://127.0.0.1:8787/sandbox/reset \
  -H 'Content-Type: application/json' \
  -H 'X-Sandbox-Key: sandbox-control-key' \
  -d '{}'
```

The API token cannot reset or inject data; sandbox control operations require the independent `X-Sandbox-Key`.

### PowerShell equivalents

On Windows PowerShell, start the server in one window:

```powershell
Set-Location tools/biometric-sandbox
php bin/serve.php
```

In a second window:

```powershell
Set-Location tools/biometric-sandbox
php bin/simulate.php --scenario=normal --employee=TBSW/ST/0002 --date=2026-08-03
Invoke-RestMethod -Uri http://127.0.0.1:8787/health
```

Retrieve and use a token:

```powershell
$body = @{ username = 'sandbox_admin'; password = 'sandbox_password' } | ConvertTo-Json
$auth = Invoke-RestMethod -Method Post -Uri http://127.0.0.1:8787/api-token-auth/ -ContentType 'application/json' -Body $body
$headers = @{ Authorization = "Token $($auth.token)" }
Invoke-RestMethod -Uri 'http://127.0.0.1:8787/iclock/api/transactions/?page_size=100' -Headers $headers
```

### Change the default sandbox credentials

The checked-in defaults are intentionally obvious test credentials. Do not reuse them anywhere else. For a shared test workstation, set all four values before starting the server.

Bash/WSL:

```bash
export BIOMETRIC_SANDBOX_USERNAME='local_test_user'
export BIOMETRIC_SANDBOX_PASSWORD='replace-with-a-long-test-password'
export BIOMETRIC_SANDBOX_TOKEN='replace-with-a-random-test-token'
export BIOMETRIC_SANDBOX_CONTROL_KEY='replace-with-a-different-random-key'
php bin/serve.php
```

PowerShell:

```powershell
$env:BIOMETRIC_SANDBOX_USERNAME = 'local_test_user'
$env:BIOMETRIC_SANDBOX_PASSWORD = 'replace-with-a-long-test-password'
$env:BIOMETRIC_SANDBOX_TOKEN = 'replace-with-a-random-test-token'
$env:BIOMETRIC_SANDBOX_CONTROL_KEY = 'replace-with-a-different-random-key'
php bin/serve.php
```

### Trusted-LAN testing

Loopback is the correct default. If another test machine must connect on an isolated, trusted lab network, start explicitly with both a non-loopback address and the override:

```bash
php bin/serve.php --host=192.0.2.10 --port=8787 --allow-network
```

Replace `192.0.2.10` with the test workstation's actual private address. Use a host firewall, rotate all sandbox credentials, and never port-forward this service to the internet. `--allow-network` sets `BIOMETRIC_SANDBOX_ALLOW_REMOTE=1` for the child server; without it, both the launcher and router reject network exposure.

### Testing a future sync worker against the sandbox

Configure the worker with:

```text
Vendor base URL: http://127.0.0.1:8787
Token endpoint:  /api-token-auth/
Transactions:    /iclock/api/transactions/
Entry serial:    SIM-IN-001
Exit serial:     SIM-OUT-001
Timezone:        Africa/Lagos
```

Use only synthetic students in a disposable database. A minimum worker acceptance run is:

1. Run `normal`; verify two source events and one daily session with entry and checkout.
2. Run `duplicate`; verify the unique event count does not increase on replay.
3. Run `out-of-order`; verify the session still shows 07:30 entry and 15:10 checkout.
4. Run `delayed`; verify the later poll adds checkout without overwriting entry.
5. Run unknown identity/device cases; verify both are quarantined and visible to an operator.
6. Run invalid data; verify it cannot mutate attendance.
7. Run 429 and 503 cases; verify retry/backoff and unchanged cursor.
8. Restart the worker and replay the same page; verify idempotency.
9. Stop the sandbox during a poll, restart it, and verify recovery without duplicate attendance.
10. Compare the worker's last cursor, raw event count, accepted count, duplicate count, and quarantine count.

### Sandbox limitations

The payload represents a common ZKBio Time transaction contract. Exact endpoint paths, authentication headers, punch-state values, pagination, date filters, and license permissions can differ by edition and firmware. Before production, compare the adapter with the API document delivered for the exact licensed ZKBio Time release.

An official shared ZKBio Time demo exists at [zkbiotime.xmzkteco.com](https://zkbiotime.xmzkteco.com/), with credentials published on the [official ZKBio Time product page](https://www.zkteco.com/en/ZKBioTime/ZKBioTime). It is useful for viewing the product UI, but it is not an isolated SchoolLift sandbox. Do not load student data, do not run destructive tests, and do not rely on being able to create API tokens because the shared account is not a private superuser environment.

## Part 2: correct production integration

### Normalized event contract

The production adapter should map the vendor response into a stable internal contract before it touches attendance:

| ZKBio Time field | SchoolLift field | Rule |
|---|---|---|
| `id` | `external_event_id` | Required; unique with provider/tenant; never reuse |
| `emp_code` | `external_student_code` | Map explicitly to the current student session/admission record |
| `punch_time` | `occurred_at` | Parse in the configured source timezone and store an unambiguous timestamp |
| `punch_state` | candidate direction | Validate against the licensed API documentation |
| `terminal_sn` | `device_serial` | Required; map to a registered device and its fixed direction |
| `terminal_alias` | source label | Audit/display only; do not use as the security identifier |
| `verify_type` | verification method | Audit value such as face/fingerprint/card when documented |
| entire source object | `raw_payload` | Retain with access controls and a documented retention period |

The registered device mapping should be the authoritative direction. If `SIM-IN-001` or the production IN serial reports an OUT state, quarantine the disagreement rather than guessing.

### Minimum data model

The production schema should include these concepts even if the final table names differ:

- `biometric_devices`: school/tenant, serial, provider, direction, location, enabled flag, last seen, firmware.
- `biometric_identity_mappings`: school/tenant, vendor employee code, student ID/session ID, validity dates, status.
- `biometric_events`: immutable source event ID, device, student mapping, event time, received time, direction, verification type, raw payload, processing status, error.
- `biometric_sync_cursors`: provider/tenant cursor or last successful time window, lease/lock and last success/error.
- `student_attendance_sessions`: attendance date, first entry, last exit, status/late rule, source, and manual override audit.
- `biometric_dead_letters`: recoverable unknown or invalid events with resolution metadata.

Required uniqueness is at least `(tenant_id, provider, external_event_id)`. If a vendor edition does not expose a stable ID, use a carefully documented fallback fingerprint over serial, employee code, punch time, state, and source—but prefer an actual vendor ID.

### Processing rules

1. Authenticate the vendor connection with a least-privileged service account.
2. Fetch pages using an overlap window so delayed records are rediscovered.
3. Validate every field before resolving the student.
4. Resolve tenant from configured connection/device ownership, never from an untrusted payload field.
5. Reject disabled or unknown devices.
6. Store the immutable event in a transaction using the unique external ID.
7. Acknowledge duplicate insertion as success.
8. Recompute the day's first valid IN and last valid OUT by event time.
9. Commit the event and attendance projection before advancing the cursor.
10. Retry transient failures with jittered exponential backoff; quarantine permanent validation errors.
11. Record who resolved, remapped, or manually overrode an event.
12. Alert when no terminal event arrives during expected school opening periods.

Do not overwrite the entry timestamp when checkout arrives. Do not infer checkout merely because the next day begins. Missing checkout should remain visible as an exception for staff review.

### Security requirements

- Run ZKBio Time **9.0.6 or a later vendor-approved fixed release**. ZKTeco's May 2026 bulletin says an unauthorized-access vulnerability affects 9.0.1–9.0.4 and is fixed in 9.0.6.
- Keep ZKBio Time and terminals off the public internet. Use a private VLAN, firewall rules, and VPN for administration.
- Allow terminal-to-gateway traffic only on documented ports; block direct inbound internet access.
- Use HTTPS for the SchoolLift connection and pin the tenant/device identity in server-side configuration.
- Store API secrets outside Git and rotate them after installer access.
- Disable default administrator credentials on every terminal and on ZKBio Time.
- Keep operating system, gateway, database, and device firmware backups; test restore procedures.
- Log authentication failures, cursor changes, device mapping changes, manual overrides, and exports.
- Restrict raw event payloads and biometric administration to named roles.

## Part 3: device and software procurement in Nigeria

### Price methodology

All prices below were checked on 3 August 2026. Nigerian electronics prices move quickly with exchange rates and stock. A retail listing is evidence of an advertised price, not a guaranteed institutional quote. Unless a source explicitly says otherwise, VAT, delivery, installation, warranty, and API licensing may be excluded.

The BioTime Africa Naira conversions use a planning exchange rate of approximately **₦1,362.21/US$**, based on the latest available CBN rate used during research (27 July 2026). Recalculate USD software prices on the invoice date using the [Central Bank of Nigeria exchange-rate page](https://www.cbn.gov.ng/rates/ExchRateByCurrency.html).

### Biometric terminal choices

| Device | Capacity and fit | Nigeria price checked | Recommendation |
|---|---|---:|---|
| **ZKTeco SenseFace 3A** | 3,000 faces; 6,000 fingerprints/cards/users; 150,000 logs; IP65; face/fingerprint/card/PIN; TA Push | **₦299,000 each**, [current Jiji listing](https://jiji.ng/gwarinpa/security-and-surveillance/zkteco-senseface-3a-compact-access-control-time-attendance-terminal-8REpAHvyN7kURPvMDqea5HCs.html) | Best value for most schools below 3,000 students; inspect before payment and obtain written firmware/API confirmation |
| **ZKTeco SenseFace 7A** | Standard 10,000 faces/fingerprints; 50,000 users/cards; 300,000 logs; 7-inch screen; IP65; TA Push | **Formal quote required**; use **₦600,000–₦900,000 each only as a planning allowance** | Best high-capacity/current-fit option; request the exact model from ZKTeco West Africa |
| **ZKTeco SpeedFace V5L TD** | 6,000 faces; fingerprint/RFID/face; 5-inch screen | **₦507,840 each**, [SecureTech Nigeria](https://www.securetech.com.ng/product/speedface-v5l-td/?wmc-currency=NGN); another [Lagos listing shows ₦498,000](https://jiji.ng/ikoyi-obalende/security-and-surveillance/zkteco-speedface-v5l-visible-light-facial-recognition-access-control-aSsG6cpGDX3ETX1y8NUwqopj.html) | Proven alternative; confirm TA Push and exact firmware before buying |
| **Hikvision DS-K1T344MBFWX-E1** | 3,000 face/fingerprint/card capacity; IP65; PoE/12V; battery backup | **₦221,400 each**, [Kara Nigeria](https://kara.com.ng/hikvision-ds-kit344mbfwx-e1-facial-terminal-with-battery-backup) | Credible lower-cost alternative only if SchoolLift builds a separate Hikvision ISAPI adapter; do not mix it into a ZKTeco-only gateway |

Official specifications are on ZKTeco's [SenseFace 3 Series](https://zkteco.com/en/SenseFaceSeries/SenseFace_3_Series) and [SenseFace 7 Series](https://www.zkteco.com/en/HybridBiometric4ZM/SenseFace_7_Series) pages.

The newer [SenseFace T3](https://www.zkteco.com/en/SenseFPSeries/SenseFace_T3) was considered but is not the primary recommendation for this codebase: its official page lists ZKBio Zlink cloud support, 1,000-user capacity, and no ZKBio Time/TA Push integration. “Newest” is less important here than a documented transaction path that the SchoolLift adapter can consume.

### Essential infrastructure and current price references

| Item | Quantity/role | Price used | Evidence and qualification |
|---|---|---:|---|
| Windows gateway PC | 1 for on-prem ZKBio Time and sync worker; prefer 16GB RAM/512GB SSD | **₦250,000 used**, **₦550,000 new**, or **₦571,500 refurbished with 180-day warranty** | [Used Dell OptiPlex 3060 Micro](https://jiji.ng/ojo/computers-and-laptops/desktop-computer-dell-optiplex-3060-16gb-intel-core-i5-ssd-256gb-q3Kv3EXfVFRR5PXJXwmBHAQx.html), [new Dell listing](https://jiji.ng/ikeja/computers-and-laptops/new-desktop-computer-dell-optiplex-3060-8gb-intel-core-i5-ssd-256gb-kAlkiuA7qBrxWkk4wzVxz681.html), [Parkway OptiPlex 5060 Micro](https://www.parkwaynigeria.com/product/dell-optiplex-5060-micro-desktop) |
| Admin display, keyboard, mouse | 1 set for local setup, or use an existing secured admin console | **Use existing equipment or obtain a quote** | A directly attached display must support at least the vendor's 1366×768 minimum; not required continuously if secure remote administration is approved |
| PC/network UPS | 1 | **₦117,200** budget or **₦140,000** line-interactive | [BlueGate 1.57kVA at Kara](https://kara.com.ng/blue-gate-offline-1-570kva-ups); [Mercury 1550VA at Jumia](https://www.jumia.com.ng/mercury-maverick-1550va-ups-223710455.html) |
| Longer backup power | Optional but recommended where outages are long | **₦548,800** | [1.2kVA inverter plus 1.3kWh LiFePO4 battery](https://www.jumia.com.ng/cworth-energy-1.3kwh-lithium-lifepo4-battery-100ah-and-1.2kva-luxsun-hybrid-inverter-418986924.html); confirm load/runtime with installer |
| Terminal 12V/3A PSU | 1 per terminal | **₦11,000 each** | [Current Ikeja listing](https://jiji.ng/ikeja/power-equipments/mini-12v-3a-power-supply-unit-for-access-controlt-hzlHNjTxGL57DHWMsYwMx9Zf.html); confirm regulated output and battery option |
| Gigabit switch | 1, at least 5 ports | **₦14,600–₦29,000** | [Kara comparison](https://kara.com.ng/gigabit-ethernet-switch); confirm branded gigabit model and warranty |
| Pure-copper CAT6 | As surveyed | **₦156,600 for 305m** or about **₦10,000 per pre-terminated 20m run** | [Hikvision pure-copper roll](https://kara.com.ng/hikvision-ds-1ln6uzc0-o-std-grey-305m); [Jumia cable listings](https://www.jumia.com.ng/cat-6-cables/?oos=1). Avoid copper-clad aluminium for long runs |
| 4G/5G failover router | 1 | **₦20,000 standard 4G**, **₦40,000 premium 4G**, or **₦80,000 5G** | [MTN broadband](https://www.mtn.ng/broadband/) and [MTN 5G router](https://shop.mtn.ng/5g-broadband-router); confirm coverage at the actual gate/server location |
| Monthly failover data | 1 SIM | **₦9,000 for 30GB/30 days** or **₦14,500 for 60GB/30 days** | [Official MTN router plans](https://www.mtn.ng/broadband/router-plans/); keep attendance traffic separate from general student Wi-Fi |
| 125 kHz RFID fallback cards | One per student plus 5–10% spares | **₦45,000/100**, **₦60,000/200**, or **₦350,000/1,000** | [Jumia 100/1,000-card listings](https://www.jumia.com.ng/slp/custom-metal-rfid-card); [Lagos 200-card listing](https://jiji.ng/ikeja/safety-equipment/pack200-rfid-id-card-for-access-control-system-or-time-clock-BsNvTmSXUu65JBXjpC7DTNKv.html). Confirm the terminal is ordered with the standard 125 kHz card module |
| Surge strip | At least 1 | **₦18,500** | [Kara surge protectors](https://kara.com.ng/surge-protectors) |
| Voltage stabilizer | 1 where mains quality is poor | **₦57,000** | [Duravolt 2kVA at Kara](https://kara.com.ng/duravolt-dv-2000va-stabilizer) |
| Encrypted backup target | At least 1 offline/immutable copy in addition to the live gateway | **₦42,020–₦54,600 for a branded 1TB portable HDD** | [Kara 2026 external-drive prices](https://kara.com.ng/external-hard-disks); encrypt it, restrict custody, test restore, and do not treat one USB disk as the entire backup strategy |
| Enclosure/canopy, trunking, connectors, mounting | Site-specific | **Formal site quote** | Required even with IP65: protect the camera from direct sun/rain and protect power/network joints |
| Installation, commissioning, training | Site-specific | **₦150,000 pilot / ₦250,000 standard / ₦350,000 large planning allowance** | Internal budget allowance, not a published installer price; replace after site survey |

Attendance recording alone does not require a magnetic lock or turnstile. Adding physical access control changes fire/egress and safeguarding requirements. Have a qualified life-safety/access-control professional design it; do not let attendance software trap students behind a failed electronic lock.

A separate USB face/fingerprint enrollment scanner is also not required when students are enrolled directly and securely at the two terminals. Buy one only if the exact terminal/software documentation requires a vendor-approved central enrollment reader. A card printer is optional; inexpensive pre-numbered 125 kHz cards can be mapped without printing.

### Required software

ZKTeco's current official minimum for on-prem ZKBio Time is a 64-bit Windows 10/11 or supported Windows Server host, dual-core 2.4GHz CPU or faster, 8GB available RAM, 100GB available storage, and a 1366×768 display. PostgreSQL is the default database; supported alternatives listed by ZKTeco are SQL Server 2016/2019/2022, MySQL 8.0, and Oracle 19c. The [official ZKBio Time page](https://www.zkteco.com/en/ZKBio_Time/ZKBioTime) is the source of those requirements. For operational headroom, use 16GB RAM and a 512GB SSD where budget permits, then size retention and backups from the measured event volume.

For a new deployment, use a supported Windows 11 Pro or Windows Server release. Do not install an ordinary end-of-support Windows 10 build merely because it appears in the vendor compatibility list.

| Software | Where it runs | Price/licensing status |
|---|---|---|
| PHP 7.4+ sandbox | Developer Windows/WSL/Linux machine | **₦0**, no Composer dependency |
| SchoolLift application | Existing hosting | Existing project cost; production V2 sync/event-ledger development is not priced in the hardware budgets |
| Windows 11 Pro | On-prem gateway | Prefer a PC with a genuine included licence; verify activation in the written PC quote |
| ZKBio Time 9.0.6 | On-prem Windows gateway | Nigerian list price is not public: **formal ZKTeco West Africa quote required** for user/device tier and API entitlement. Reserve about **₦650,000** only as a provisional 500-user planning line until replaced by a quote |
| BioTime Africa | ZKTeco-hosted cloud | Published USD subscription tiers below; API/webhook inclusion is not stated publicly and must be confirmed in writing |
| Database backup/endpoint protection | Gateway/SchoolLift infrastructure | Use the school's managed security/backup service or obtain an IT-provider quote |

ZKTeco's official product page currently publishes a ZKBio Time 9.0.6 installer and 9.0.6 API package dated May 2026, but downloads require an eligible ZKTeco account. Do not deploy 9.0.1–9.0.4; the [official security bulletin](https://www.zkteco.com/en/Security_Bulletinsibs/24) says those versions are affected by CVE-2025-15128 and recommends 9.0.6.

### BioTime Africa published subscription prices

The [ZKTeco West Africa pricing page](https://www.zkteco-wa.com/biotime.africa/pricing/) displays monthly and annual selectors. The values below use its default published USD figures as monthly amounts and convert them at ₦1,362.21/US$. The annual column is simply 12 times that monthly planning value; it does not assume any annual-payment discount. Confirm billing cadence, tax, payment currency, API access, and the current exchange rate in the final order.

| Users | Devices | Published USD/month | Approx. Naira/month | Approx. Naira/year at 12× |
|---:|---:|---:|---:|---:|
| 1–20 | 1 | Free | ₦0 | ₦0 |
| 21–50 | 3 | $7 | ₦9,535 | ₦114,426 |
| 51–100 | 4 | $13 | ₦17,709 | ₦212,505 |
| 101–200 | 5 | $21 | ₦28,606 | ₦343,277 |
| 201–300 | 10 | $29 | ₦39,504 | ₦474,049 |
| 301–400 | 15 | $35 | ₦47,677 | ₦572,128 |
| 401–500 | 20 | $52 | ₦70,835 | ₦850,019 |
| 501–700 | 50 | $58 | ₦79,008 | ₦948,098 |
| 701–900 | 80 | $83 | ₦113,063 | ₦1,356,761 |
| 901–1,000 | 100 | $104 | ₦141,670 | ₦1,700,038 |
| 1,001–2,500 | 200 | $208 | ₦283,340 | ₦3,400,076 |
| 2,501–5,000 | 500 | $347 | ₦472,687 | ₦5,672,242 |

The page also advertises the first year free for 10 devices, 200 users, and 10 apps. Treat that as a promotion requiring written eligibility confirmation. Most importantly, the page does not say whether a third-party SchoolLift REST API or webhook is included. Do not select the cloud route until ZKTeco confirms access to raw transactions, stable event ID, employee code, punch time/state, and terminal serial.

## Example deployment budgets

These are planning budgets, not purchase orders. Add quoted VAT/delivery and a 10–15% procurement contingency.

### Pilot: up to 100 students

| Component | Amount |
|---|---:|
| 2 × SenseFace 3A | ₦598,000 |
| Used Dell gateway | ₦250,000 |
| BlueGate 1.57kVA UPS | ₦117,200 |
| MTN standard 4G router | ₦20,000 |
| Five-port gigabit switch | ₦14,600 |
| Two 20m CAT6 runs | ₦20,000 |
| 100 RFID cards | ₦45,000 |
| 2 × terminal 12V/3A PSU | ₦22,000 |
| Surge protection | ₦18,500 |
| Mounting, configuration, commissioning, and training allowance | ₦150,000 |
| ZKBio Time/BioTime licence | ₦0 only if the applicable free offer and API are confirmed |
| **Planning total** | **₦1,255,300** |

If an eligible cloud/free plan with usable API access is confirmed, the gateway PC may be omitted, giving an approximate first-year total of **₦1,005,300**. A local gateway still provides operational control and can be valuable during internet outages, so remove it only after an outage design review.

### Standard production: up to 500 students

| Component | Amount |
|---|---:|
| 2 × SenseFace 3A | ₦598,000 |
| New Windows gateway PC | ₦550,000 |
| Mercury 1550VA UPS | ₦140,000 |
| MTN 5G failover router | ₦80,000 |
| Gigabit switch | ₦29,000 |
| 305m pure-copper CAT6 roll | ₦156,600 |
| 500 RFID cards at the 1,000-card bulk reference rate | ₦175,000 |
| 2 × terminal 12V/3A PSU | ₦22,000 |
| 2kVA voltage stabilizer | ₦57,000 |
| Installation, commissioning, and training allowance | ₦250,000 |
| Rounded provisional 500-user on-prem software reserve | ₦650,000 |
| **Planning total** | **₦2,707,600** |

The software figure is a rounded internal placeholder, not a Nigerian ZKTeco quote. Replace it with a written current ZKBio Time 9.0.6/API price before approval.

A cloud alternative removes the ₦550,000 PC and ₦650,000 on-prem reserve and adds approximately ₦850,019 for one year of the published 401–500-user plan. That gives approximately **₦2,357,619** for year one, only if the required SchoolLift API access is included.

Using two SpeedFace V5L TD units instead of two SenseFace 3A units raises the on-prem planning total to approximately **₦3,125,280**.

### High-capacity/reliability deployment: up to 1,000 students

| Component | Amount |
|---|---:|
| 2 × SenseFace 7A planning allowance | ₦1,200,000–₦1,800,000 |
| Refurbished Dell gateway with warranty | ₦571,500 |
| Mercury UPS plus lithium inverter backup | ₦688,800 |
| Branded 4G+ failover router | ₦165,555 |
| Gigabit switch | ₦29,000 |
| 305m pure-copper CAT6 roll | ₦156,600 |
| 1,000 RFID cards | ₦350,000 |
| 2 × terminal 12V/3A PSU | ₦22,000 |
| Voltage stabilizer | ₦57,000 |
| Installation/support allowance | ₦350,000 |
| **Hardware/infrastructure total** | **₦3,590,455–₦4,190,455** |
| On-prem 1,000-user/API licence | **Add formal quote** |

At the published 901–1,000-user BioTime Africa price, cloud software is approximately ₦1,700,038/year. Removing the gateway PC and adding that first year gives approximately **₦4.72 million–₦5.32 million**, subject to API access and all quote exclusions.

### Costs excluded from the examples

- VAT where a seller does not explicitly show it;
- delivery outside Lagos and insurance in transit;
- civil work, trenching, conduit/trunking, gate canopy, and masonry;
- primary fibre subscription if the school does not already have it;
- SMS/WhatsApp notification provider charges;
- production sync/API/database development in SchoolLift;
- DPCO, legal/privacy assessment, and documentation;
- annual support—use 10–15% of hardware value as an internal reserve until quoted;
- replacement UPS/terminal batteries;
- administrator monitor/keyboard/mouse and encrypted backup media/service when the school cannot reuse existing managed equipment;
- spare terminal or next-business-day replacement SLA;
- access locks, turnstiles, fire-alarm integration, and egress hardware.

## Procurement request to send to the Nigerian supplier

Ask [ZKTeco West Africa](https://www.zkteco-wa.com/website/page/store-locator/) for an itemized written quote. Their published Lagos contact details are 64 Adetokunbo Ademola Street, Victoria Island; sales +234 817 5555 512 / +234 817 5555 514; support +234 817 5555 513; `enquiry@zkteco-wa.com`.

The request should contain:

1. Two exact SenseFace 3A units for a school below 3,000 students, or two SenseFace 7A units for the high-capacity option.
2. One terminal configured/labelled for fixed IN and one for fixed OUT.
3. Exact firmware version and explicit TA Push/ADMS support.
4. Face, fingerprint, and standard 125 kHz RFID modules.
5. Two regulated 12V/3A power supplies, mounting plates, weather-protected junctions, and all accessories.
6. Current ZKBio Time 9.0.6 on-prem licence or BioTime Africa user/device tier.
7. Separate price and entitlement for raw transaction REST API or webhook access.
8. API fields: stable event ID, employee code, punch timestamp, punch state, verification type, terminal serial, pagination, and update cursor/filter.
9. Installer/API documentation for the exact licensed release.
10. Confirmation that biometric templates remain within ZKTeco devices/platform and are not exposed to SchoolLift.
11. Nigerian warranty length, dead-on-arrival process, replacement SLA, firmware updates, and remote/on-site support.
12. Installation, cable test results, commissioning, administrator training, and 12-month support as separate lines.
13. VAT, delivery, lead time, quote validity, and payment schedule shown separately.
14. A pre-purchase API proof: retrieve one IN and one OUT event from a demonstration terminal using the licence being quoted.

Do not pay from a marketplace listing alone. Verify serial/model labels, sealed condition, firmware, warranty issuer, and API compatibility at the seller's premises or through an authorized installer.

## Physical installation and configuration

### Before installation

1. Complete a site survey during the real arrival/departure rush.
2. Count peak students per minute and decide whether two lanes are enough.
3. Test MTN and at least one other network at the gate and gateway location.
4. Measure cable paths; keep copper Ethernet permanent links within standards and use fibre for distant buildings/lightning-prone outdoor links.
5. Decide power runtime required for short dips versus multi-hour outages.
6. Select a protected, ventilated, access-controlled gateway location.
7. Complete privacy assessment, notices, enrollment rules, retention, and a non-biometric fallback.
8. Create a synthetic staging tenant/database before any integration test.

### Install the two terminals

1. Mount each terminal at a vendor-approved height and angle tested with the school's actual age/height range, including accessibility needs.
2. Shade the camera from direct sun and rain even though the enclosure is IP65.
3. Label the lanes and terminal bodies unmistakably `ENTRY / IN` and `CHECKOUT / OUT`.
4. Record each model, serial, MAC address, firmware, direction, location, installer, and warranty.
5. Run wired CAT6 back to the protected switch; test and label both ends.
6. Connect regulated 12V/3A supplies and protected backup power. Never assume an undersized mini-UPS can supply 3A.
7. Put terminals and gateway on a dedicated VLAN with no student Wi-Fi access.
8. Reserve static DHCP addresses, configure NTP, and set the business timezone to `Africa/Lagos`.

### Configure ZKBio Time

1. Install the vendor-supported 9.0.6 or newer fixed build on a patched Windows PC.
2. Use a dedicated database and service identity; do not use a personal administrator account for synchronization.
3. Change all default passwords and enable HTTPS/VPN-restricted administration.
4. Add the two terminals and confirm their serial numbers match the procurement record.
5. Switch the device firmware/protocol to TA Push as instructed for that exact firmware.
6. Confirm both devices report online, maintain correct time after reboot, and upload buffered transactions after a network outage.
7. Create a least-privileged API account that can read users/transactions but cannot administer devices or templates.
8. Back up ZKBio Time configuration and document licence reactivation/recovery.

### Enroll students

1. Use the immutable SchoolLift admission number or a dedicated mapping code as the vendor `emp_code`; never use a student's name as the key.
2. Verify the student and guardian process before collecting biometrics.
3. Capture a high-quality face template under realistic light; add fingerprint only where appropriate and lawful.
4. Issue an RFID fallback card and record card replacement/revocation.
5. Keep a supervised non-biometric alternative for students who cannot or should not use biometrics.
6. Test each enrolled student on both IN and OUT terminals.
7. Reconcile the exported enrollment list with active SchoolLift student sessions; disable leavers promptly.

## Nigeria privacy and safeguarding checklist

Biometric identifiers used for unique identification should be treated as sensitive personal data, especially because the subjects are children. This is operational guidance, not legal advice. Engage a Nigeria-licensed Data Protection Compliance Organisation or qualified privacy counsel before rollout.

At minimum:

- document the lawful basis and necessity/proportionality of biometrics rather than assuming consent solves every issue;
- carry out and approve a data protection impact assessment before broad enrollment;
- give clear parent/guardian and age-appropriate student notices explaining purpose, controller, vendor, retention, rights, complaints, and transfers;
- offer an effective non-biometric attendance path without penalizing the child;
- collect templates in the vendor system, not in SchoolLift, and do not copy face photos/fingerprint templates into attendance payloads;
- minimize SchoolLift data to student mapping, event time, device, direction, method code, and audit metadata;
- define and automate retention for raw events, logs, templates after withdrawal/leaving, and backups;
- use role-based access, export restrictions, encryption, incident response, and breach escalation;
- contractually define ZKTeco/integrator hosting location, subprocessors, deletion, security, audit, and incident notification;
- periodically test false rejection across age/height/lighting conditions and provide human correction/appeal;
- train gate and admin staff not to coerce, disclose, photograph, or casually export biometric records.

Use the [Nigeria Data Protection Act 2023](https://ndpc.gov.ng/wp-content/uploads/2024/03/Nigeria_Data_Protection_Act_2023.pdf) and the [NDPC's school-focused guidance/report](https://www.ndpc.gov.ng/ndpc-educates-private-schools-on-protecting-sensitive-data-of-young-nigerians/) as starting references. Confirm current regulations and NDPC guidance with the school's adviser at deployment time.

## Production acceptance test

Do not mark the physical system live until evidence is recorded for every item below.

### Functional

- 20 or more consenting pilot users can check in and out on both biometric and RFID fallback.
- The correct student is resolved by immutable code, not name.
- First valid IN and last valid OUT remain correct with repeated punches.
- Duplicate vendor events do not create duplicate SchoolLift events or notifications.
- Out-of-order and delayed checkout events correct the daily session.
- Unknown students/devices and malformed events appear in a reconciliation queue.
- Manual corrections record actor, reason, old value, new value, and timestamp.
- The existing attendance reports agree with the new derived daily state.

### Reliability

- Disconnect internet while keeping the LAN up; terminals/ZKBio Time buffer and later upload events.
- Disconnect terminal LAN, generate permitted test punches, reconnect, and confirm ordered recovery.
- Reboot each terminal, gateway, switch/router, and sync worker independently.
- Simulate HTTP 429/503 with the repository sandbox and verify bounded retry/no cursor loss.
- Run at least a five-school-day pilot including morning and afternoon peaks.
- Measure queue rate and recognition failures at realistic sunlight and student heights.
- Verify backup-power runtime under the measured load and graceful gateway shutdown.
- Restore ZKBio Time and SchoolLift integration state from documented backups in staging.

### Security and privacy

- No ZKBio Time or device administration page is reachable from the public internet or student VLAN.
- Default accounts are disabled/rotated; service accounts are least-privileged.
- Installed version/firmware is recorded and checked against vendor security bulletins.
- Raw templates cannot be fetched through the SchoolLift transaction API.
- Access logs, retention/deletion, incident contacts, and DPCO sign-off are recorded.
- A student's non-biometric fallback and correction/appeal process work in practice.

### Go-live evidence

Keep a signed commissioning pack containing quote/invoice, models/serials, topology/IP plan, cable results, firmware/software versions, licence/API entitlement, administrator handover, backup/restore result, outage test, attendance reconciliation, privacy approval, training attendance, open defects, support contacts, and warranty/SLA.

## Troubleshooting

| Symptom | Check first | Safe action |
|---|---|---|
| `Simulation failed: HTTP request failed` | Is `php bin/serve.php` running on the same base URL/port? | Start the server, check `/health`, and verify firewall/port |
| Sandbox returns 401 | Username/password or API token does not match server environment | Restart with known test values; never copy production credentials into the sandbox |
| Sandbox returns 403 on reset/inject | Missing/wrong `X-Sandbox-Key` | Use the separate control key |
| Sandbox refuses remote target/client | Loopback safety is active | Keep it local, or use the documented trusted-LAN override only in an isolated lab |
| Delayed checkout not shown | Visibility delay has not elapsed | Poll again after the configured delay |
| Legacy endpoint says `Something Wrong.` on checkout | Current model allows only one student/date row | This is the known legacy limitation; implement the event-ledger/session V2 before production checkout |
| Real terminal online but no events | Wrong AC Push/TA Push mode, server address/port, clock, or firewall | Check the exact firmware manual and ZKBio Time device status/logs |
| Events have wrong direction | Terminal mapping/punch-state mismatch | Quarantine; correct server-side serial-to-direction mapping; do not rewrite raw events |
| Student not found | Vendor `emp_code` does not match an active mapping | Resolve through the reconciliation workflow; do not create a silent student |
| Duplicates after retry | Missing/incorrect unique external event constraint | Fix idempotency before continuing rollout |

## Final rollout gate

The no-device sandbox is ready for integration development now. Procurement can begin with written Nigerian quotes, but a proper student checkout rollout should wait until the production event-ledger/synchronizer work is implemented and passes the acceptance test above. The current legacy endpoint can record a first daily biometric attendance entry; it cannot represent a reliable entry/checkout lifecycle.
