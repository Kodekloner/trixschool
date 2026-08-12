# Production biometric attendance and Nigeria deployment guide

Last reviewed: 12 August 2026 (Africa/Lagos)

This guide explains how to demonstrate, test, procure, install, and operate SchoolLift biometric attendance with **one physical terminal per school**. The same terminal handles IN and OUT. Before identifying, the student or staff member deliberately selects the appropriate state on the terminal; ZKBio Time exposes that choice as `punch_state`.

SchoolLift never infers direction from the time of day, never alternates a person's punches automatically, and never treats a terminal serial as an API password.

## Executive recommendation

For most Nigerian primary and secondary schools below 3,000 students, procure:

- one **ZKTeco SenseFace 3A** with face, fingerprint, 125 kHz RFID, and PIN options;
- a regulated 12V/3A supply, surge/backup power, weather/sun protection, and wired CAT6;
- ZKBio Time 9.0.6 or newer on a protected Windows gateway PC;
- the repository's `tools/biometric-gateway` synchronizer;
- a supervised RFID/QR/manual alternative for anyone who cannot or should not use biometrics.

Use a SenseFace 7A only where capacity, screen size, or measured queue throughput justifies its higher formal quote. A second device is an optional future capacity/resilience upgrade, not a requirement for correct IN/OUT processing.

The software path is:

```text
Person selects IN or OUT and identifies
                    |
                    v
     one bidirectional SenseFace terminal
                    |
                    v
             ZKBio Time/BioTime
                    |
                    v
       outbound Windows PHP gateway
                    |
                    v
       POST /api/biometric/v2/events
                    |
                    v
 validation -> immutable event -> daily IN/OUT session
                              -> exception/reconciliation
                              -> official attendance only in live mode
```

Before paying, the supplier must prove that the exact model, firmware, and licence return a stable transaction ID, person code, timestamp, terminal serial, verification type, and reliable IN/OUT `punch_state` values. The proposed starting map is `0 = IN`, `1 = OUT`; it is not approved until tested against the purchased release.

## Three test tools that serve different purposes

### 1. Website Test Terminal

**Attendance > Biometric Attendance > Test Terminal** is the normal no-device demonstration for school staff. It submits one manual event at a time through the real validation, ledger, session, exception, and reporting workflow.

It is not an automatic animation. The operator must choose a person/code, choose IN or OUT, choose a simulated verification method, and submit each punch separately. In `simulation` mode, events are marked simulated and cannot update official attendance, payroll, totals, or notifications.

### 2. ZKBio mock provider

`tools/biometric-sandbox` is a loopback developer service resembling a common ZKBio Time transaction API. It tests polling, pagination, delayed/out-of-order data, duplicates, authentication, `429`, and `503`. It never connects to the school database.

### 3. Windows gateway

`tools/biometric-gateway` is the actual outbound synchronizer intended for the school PC. It has a durable SQLite queue/cursor, safe overlap polling, event allowlisting, authenticated batching, retry/backoff, a process lock, diagnostics, and Windows Task Scheduler scripts.

Use the website Test Terminal to explain the school workflow. Use the mock plus gateway to prove the machine-to-machine integration. Neither replaces the physical recognition, liveness, capacity, lighting, power, or firmware pilot.

## Operating modes and safety boundaries

| Mode | Accepted source | Event/session records | Official attendance/payroll |
| --- | --- | --- | --- |
| `disabled` | None | Rejected | Never changed |
| `simulation` | Website Test Terminal and trusted QR scanner | Stored and visibly marked simulated | Never changed |
| `shadow` | Real gateway/device events | Stored and calculated | Never changed |
| `live` | Real gateway/device and trusted QR events | Stored and calculated | Valid IN events project to official attendance |

Mode is read from server configuration, not request data. A sender cannot add `"mode":"live"` to bypass it. Moving to `live` requires biometric edit permission, General Settings edit permission, password confirmation, and an audit entry.

Keep a separate disposable school/tenant for sales demonstrations where possible. A production school may test in `simulation`, but simulated records must remain clearly labelled and filtered out of official figures.

## Part 1: demonstrate without a physical device

### Prerequisites

1. Deploy the database migration and biometric V2 application code to a disposable or backed-up school database.
2. Sign in as a staff user whose role has the biometric attendance permissions needed for setup, mapping, test-terminal operation, and event viewing.
3. Open **Attendance > Biometric Attendance**.
4. Set the mode to `simulation`. Do not use `live` for a demonstration.
5. Confirm the page visibly says simulated events do not affect official attendance.
6. Confirm the migration-created virtual bidirectional terminal `SIM-GATE-001` exists and is enabled.
7. Confirm the school timezone is `Africa/Lagos` and the current academic session/term is correct.

### Map one student and one staff member

The mapping connects a vendor `person_code` to one SchoolLift identity. It is deliberately separate from the person's name.

1. Open the **Identity Mapping** tab.
2. Use **Preview/seed active roster codes** to create unambiguous admission-number and employee-ID mappings in a disposable tenant, or add them manually.
3. For a manual student mapping, choose **Student**, enter the current `student_session.id` (not the general student ID), set a synthetic external code such as `DEMO-STUDENT-001`, and save.
4. For a manual staff mapping, choose **Staff**, enter the active `staff.id`, and set `DEMO-STAFF-001`.
5. Verify both mappings are active and have no duplicate external codes.

For a live school, use the immutable admission/employee number or a documented dedicated code. Never use only the person's name.

### Submit a student check-in

1. Open the **Test Terminal** tab.
2. Confirm the selected virtual device is `SIM-GATE-001`.
3. Enter the mapped code `DEMO-STUDENT-001`.
4. Select **Check In**.
5. Select a simulated method such as **Face**.
6. Click the single-event submit button once.
7. Record the displayed external event ID.
8. Verify the result is `accepted` and the daily session shows an IN time but no OUT time.
9. Verify the session is marked `simulation` and official projection says it was not attempted.
10. Open the ordinary student attendance report and confirm no official row/count changed.

### Submit the separate checkout

1. Stay on or reopen **Test Terminal**.
2. Select the same virtual terminal and person code.
3. Select **Check Out** explicitly.
4. Choose the verification method and submit exactly one event.
5. Verify the result is `accepted`.
6. Open **Daily Sessions** and confirm earliest IN, latest OUT, and duration are populated.
7. Again confirm official attendance and payroll/totals are unchanged in simulation mode.

The software must not create checkout in the background after check-in. A missing OUT remains a visible missing checkout.

### Demonstrate deduplication

1. Use **Resend same event ID** for the preceding punch; do not create a new ID.
2. Verify the response is `duplicate`.
3. Verify event/session counts and IN/OUT timestamps have not changed.
4. Explain that a gateway can safely replay after an internet failure because the stable external ID protects attendance.

### Demonstrate quarantine and reconciliation

1. Enter the explicitly marked unknown code `DEMO-UNKNOWN-001`.
2. Submit one IN punch.
3. Verify the event is `quarantined`, not silently assigned to another person.
4. Open **Reconciliation** and inspect the reason and raw safe metadata.
5. Open **Identity Mapping** and create/correct the code-to-subject mapping.
6. Return to **Reconciliation**, choose **Retry processing**, enter an operator note, and verify it resolves to the intended person.
7. Confirm the original event remains immutable and the reconciliation actor, time, reason, and result are audited.

### Additional manual cases

| Case | Action | Required result |
| --- | --- | --- |
| Repeated IN | Submit two IN events with different IDs/times | Earliest valid IN remains the session IN |
| Repeated OUT | Submit two OUT events after IN | Latest valid OUT remains the session OUT |
| OUT without IN | Submit OUT first | Exception; no invented IN |
| Invalid direction | Submit an unsupported punch state through the API fixture | Quarantined/rejected; no time-based guess |
| Unknown device | Use an unregistered serial in an API test | Quarantined/rejected |
| Delayed event | Add an older occurrence time after a newer event | Session recomputes using occurrence time |
| Out of order | Deliver OUT before an earlier IN | Exception initially, then corrected session after IN arrives |
| Student and staff | Repeat full IN/OUT for each type | Separate correct identity/session handling |
| Manual conflict | Manually edit biometric-owned official attendance in a controlled live test | Session locks/conflict is audited; no silent overwrite |

### Demonstration acceptance evidence

Capture screenshots or an exported test record showing:

- one serial for both directions;
- distinct explicit punch states;
- accepted IN and OUT events;
- one complete simulated daily session;
- duplicate handling;
- exception plus reconciliation;
- no official attendance/payroll change;
- staff user and timestamps in the audit trail.

Do not use a real student's biometric identifier, production token, or live attendance date in a public sales demonstration.

## Part 2: test the actual gateway without hardware

### Run automated tests

From the repository root:

```bash
php tools/biometric-sandbox/tests/run.php
php tools/biometric-gateway/tests/run.php
```

The gateway suite verifies one-serial IN/OUT, stripped template/image fields, durable SQLite restart, cursor overlap, deduplication, `401`, `429`, `503`, provider outage, and repaired-credential recovery.

### Start the mock ZKBio provider

Terminal A:

```bash
cd tools/biometric-sandbox
php bin/serve.php
```

It binds only to `127.0.0.1:8787`. Keep it loopback-only.

Terminal B injects a school day from one terminal:

```bash
cd tools/biometric-sandbox
php bin/simulate.php \
  --scenario=normal \
  --employee=DEMO-STUDENT-001 \
  --terminal-serial=SIM-GATE-001
```

The mock creates both events for `SIM-GATE-001`; direction comes from `punch_state`.

### Configure the gateway for the mock

Follow [`../tools/biometric-gateway/README.md`](../tools/biometric-gateway/README.md). For a fully local test only:

```php
'verify_tls' => true,
'allow_insecure_localhost' => true,
'provider' => [
    'base_url' => 'http://127.0.0.1:8787',
    'username' => 'sandbox_admin',
    'password' => 'sandbox_password',
    'terminal_serial' => 'SIM-GATE-001',
    // retain the other values from config.example.php
],
```

Point `schoollift.base_url` to a disposable staging tenant in `shadow`. A mock event arriving through the gateway intentionally has source `gateway`, so the API accepts it in `shadow` or `live`; `simulation` accepts only the browser Test Terminal and trusted QR demonstration. Use the staging tenant's one-time integration token.

The local mock can use loopback HTTP while `verify_tls` remains enabled; that setting still validates the staging tenant's HTTPS certificate. Disable verification only when **both** endpoints are loopback in a disposable local test, never when SchoolLift is remote.

```bash
php tools/biometric-gateway/bin/gateway.php doctor --config=/secure/path/config.php
php tools/biometric-gateway/bin/gateway.php once --config=/secure/path/config.php
php tools/biometric-gateway/bin/gateway.php status --config=/secure/path/config.php
```

Expected result:

- ZKBio authentication succeeds;
- two transactions are read from `SIM-GATE-001`;
- punch state `0` and `1` are forwarded unchanged;
- SchoolLift returns a result for each event;
- the local queue shows zero pending/dead;
- website **Events** and **Daily Sessions** tabs show the shadow IN/OUT;
- official attendance remains unchanged.

### Test retries

The mock supports:

```bash
php bin/simulate.php --scenario=duplicate --terminal-serial=SIM-GATE-001
php bin/simulate.php --scenario=out-of-order --terminal-serial=SIM-GATE-001
php bin/simulate.php --scenario=delayed --delay-seconds=10 --terminal-serial=SIM-GATE-001
php bin/simulate.php --scenario=rate-limit
php bin/simulate.php --scenario=server-error
php bin/simulate.php --scenario=auth-error
```

After a recoverable failure, `status` must show a pending record or provider backoff—not data loss. After the delay and another `once`, pending should return to zero. Do not use `retry-dead` until an operator understands and fixes the permanent rejection.

### Gateway durability facts

- The cursor and fetched events commit in one SQLite transaction.
- Every queue row retains the cursor from that transaction, so retries send a stable batch request even after later polls.
- A provider failure cannot advance the cursor.
- The overlap window safely rediscovers recent events; the unique external ID prevents duplicates.
- The gateway uses a file lock and the Windows task uses `IgnoreNew`.
- No arbitrary ZKBio fields leave the gateway; biometric images/templates are stripped by an allowlist.
- SchoolLift tenant resolution comes from the configured HTTPS hostname, not request JSON.
- Plain HTTP is accepted only for explicitly enabled loopback tests.

Stop the scheduled task before an SQLite backup and use an SQLite-consistent backup method. Copying only `gateway.sqlite` while WAL files are active may be incomplete.

## Part 3: install the physical system

### Procure with an API proof, not only a product name

Send an itemized request to [ZKTeco West Africa](https://www.zkteco-wa.com/website/page/store-locator/) or an authorized Nigerian installer. Ask for:

1. One exact SenseFace 3A (or justified SenseFace 7A), serial and module configuration shown.
2. Face, fingerprint, standard 125 kHz RFID, and PIN capability required by the school.
3. Regulated 12V/3A PSU, mounting plate, protected junctions, canopy/shade, and cabling items.
4. Exact firmware version and TA Push/ADMS/ZKBio Time compatibility.
5. ZKBio Time 9.0.6+ licence and separate API entitlement.
6. Stable ID, employee code, timestamp, punch state, verification method, terminal serial, filters, and pagination in the raw transaction API.
7. A pre-purchase demonstration where one terminal produces one IN and one OUT and the API shows different punch states.
8. Nigerian warranty, dead-on-arrival procedure, firmware/security updates, replacement SLA, installation, commissioning, training, VAT, delivery, and support as separate lines.
9. Written confirmation that SchoolLift does not need or receive face/fingerprint templates.

Do not buy solely from a marketplace screenshot. Verify sealed condition, model/serial, firmware, modules, licence, API access, warranty issuer, and seller authorization before payment.

### Site survey

1. Count arrivals per minute at the real peak. One device is affordable but can become a queue bottleneck; measure rather than guess.
2. Choose a supervised point where the user can stop, select IN/OUT, identify, and see the result without blocking emergency egress.
3. Test camera light at morning/afternoon angles and provide shade despite IP65 rating.
4. Check accessibility for younger/shorter students and wheelchair users.
5. Measure one pure-copper CAT6 route and keep outdoor/lightning-prone runs protected; use fibre where appropriate.
6. Measure terminal, PC, switch/router, and monitor load before sizing UPS/inverter runtime.
7. Test primary and failover internet at the gate and gateway location.
8. Isolate device/gateway traffic from student Wi-Fi using firewall/VLAN rules.
9. Approve privacy impact, notices, fallback, retention, correction, and incident processes before enrollment.

### Configure the one terminal

1. Record model, serial, MAC, firmware, location, installer, warranty, and power supply.
2. Mount at the vendor-approved height/angle and protect direct sunlight, rain, cable joints, and tampering.
3. Use wired Ethernet and reserved DHCP/static addressing.
4. Configure NTP and `Africa/Lagos` on terminal, ZKBio, Windows, and SchoolLift.
5. Enable the terminal's user-facing state/function-key selection for **Check In** and **Check Out**.
6. Place simple `SELECT CHECK IN OR CHECK OUT BEFORE SCANNING` signage next to the screen.
7. Configure `0 = IN` and `1 = OUT` only after observing the actual API proof. If the release uses other values, change the server integration map and gateway documentation together.
8. Reboot, disconnect/reconnect LAN, and verify time plus buffered uploads.

Do not use time windows such as “before noon means IN.” Do not toggle each person's next punch. A missing/unknown state is an exception.

### Configure ZKBio Time and the Windows gateway

1. Use the vendor-supported 9.0.6 or newer fixed build on a patched 64-bit Windows host.
2. Use a dedicated database/service identity and change all defaults.
3. Restrict ZKBio administration to LAN/VPN; never port-forward it publicly.
4. Add the one terminal and verify its serial exactly.
5. Create a least-privileged API identity able to read users/transactions but not templates/device administration.
6. Install PHP 8.2 CLI and enable `curl`, `json`, `openssl`, `pdo_sqlite`, and `sqlite3`.
7. Place the gateway configuration/runtime outside the website tree and protect it with Windows ACLs.
8. Create the SchoolLift integration token, record it once, and store only its hash in SchoolLift.
9. Run gateway `doctor`, `once`, and `status` manually.
10. Install the once-per-minute restricted-account task using `windows/install-task.ps1`.
11. Reboot and confirm ZKBio, gateway task, cursor, queue, and SchoolLift health recover.

### Enroll and map students/staff

1. Use admission number/employee ID or another immutable code as ZKBio `emp_code`.
2. Obtain the approved guardian/student/staff notices and process before collecting biometrics.
3. Capture face/fingerprint under realistic conditions only in the vendor platform.
4. Provide a supervised QR, RFID, PIN, or manual path where appropriate.
5. Create/preview SchoolLift mappings and resolve duplicates before enabling them.
6. Test one explicit IN and OUT from the same serial for each pilot person.
7. Disable mappings and revoke cards/QR credentials promptly for leavers, lost cards, or compromised codes.

## Trusted QR fallback

SchoolLift ID cards encode a random revocable credential, not an attendance-writing URL. The QR scanner is an authenticated staff page assigned to the one gate station.

### Required server encryption key

Before issuing the first credential, configure `BIOMETRIC_QR_ENCRYPTION_KEY` in the SchoolLift PHP/web-server environment. Use a cryptographically random value of at least 32 characters, for example a 48-byte base64 value generated on a trusted administration machine:

```bash
php -r "echo base64_encode(random_bytes(48)), PHP_EOL;"
```

Store the value in the hosting platform's secret manager or protected server/service environment—not in Git, the database, a browser, or any web-accessible file. Ensure the PHP-FPM/Apache/IIS application identity receives it, restart the relevant PHP/web service, then open **Attendance > Biometric Attendance > QR Scanner**. The red “QR credential issuance is not ready” warning must disappear before issuance. SchoolLift fails closed when the key or OpenSSL support is unavailable.

Back up this key in the school's encrypted recovery vault separately from the database. The database contains encrypted reprint values; without the matching key, existing QR credentials can still be hash-verified at the scanner but cannot be decrypted for ID-card reprinting. Do not casually rotate or delete the key. A safe rotation needs a planned application procedure that decrypts every active credential with the old key and re-encrypts it with the new key, plus a tested rollback. Until such a re-encryption procedure is provided, either retain the original key or revoke and reissue all affected cards after changing it.

Correct operation:

1. Gate staff sign in over HTTPS.
2. Staff select visible IN or OUT for the station.
3. They scan the card.
4. SchoolLift shows the person's name, photograph, role/class, chosen direction, and result.
5. Staff visually compare the photograph/person and accept the result.
6. The scan enters the same immutable event/session workflow.

A photographed QR can still be copied, so visual supervision is a control. Lost cards must be revoked/reissued immediately. Direct public GET requests must never mark attendance, and QR images must be generated locally rather than sent to an external image service.

See [ID Design Studio](id-design-studio.md) for design, issuance, printing, and revocation.

## Part 4: Nigerian equipment and costs

### Price method

Prices below were researched on 3 August 2026 and rechecked for this single-device plan on 12 August 2026. Nigerian electronics, exchange rates, delivery, and stock change quickly. Marketplace prices are advertised evidence, not institutional quotes. Reconfirm model, firmware, warranty, VAT, delivery, installation, and API licence on the purchase date.

Do not rely on a fixed exchange rate in this guide. Recalculate any USD invoice on its payment date using the [Central Bank of Nigeria NFEM rate page](https://www.cbn.gov.ng/rates/ExchRateByCurrency.html) and the bank/vendor's disclosed settlement terms.

### Terminal choices

| Device | Capacity/fit | Nigeria price used | Decision |
| --- | --- | ---: | --- |
| **ZKTeco SenseFace 3A** | 3,000 faces; 6,000 fingerprints/cards/users; 150,000 logs; IP65; TA Push | **₦299,000 for one**, [Jiji listing](https://jiji.ng/gwarinpa/security-and-surveillance/zkteco-senseface-3a-compact-access-control-time-attendance-terminal-8REpAHvyN7kURPvMDqea5HCs.html) | Recommended value choice below 3,000 students, subject to API proof/warranty |
| **ZKTeco SenseFace 7A** | Standard 10,000 faces/fingerprints; 50,000 users/cards; 300,000 logs; IP65; TA Push | **Formal quote required**; reserve **₦600,000–₦900,000 for one** only for planning | Larger capacity/screen; measure one-device peak throughput |
| **ZKTeco SpeedFace V5L TD** | 6,000 faces; fingerprint/RFID/face; 5-inch screen | **₦507,840**, [SecureTech Nigeria](https://www.securetech.com.ng/product/speedface-v5l-td/?wmc-currency=NGN) | Alternative only after exact TA Push/API proof |

Official product references: [SenseFace 3 Series](https://zkteco.com/en/SenseFaceSeries/SenseFace_3_Series), [SenseFace 7 Series](https://www.zkteco.com/en/HybridBiometric4ZM/SenseFace_7_Series), and [ZKBio Time](https://www.zkteco.com/en/ZKBio_Time/ZKBioTime).

### Supporting equipment

| Item | Quantity for this design | Price reference |
| --- | ---: | ---: |
| Windows gateway PC | 1 | **₦250,000 used**, **₦550,000 new**, or **₦571,500 refurbished**, based on [Dell OptiPlex listings](https://www.parkwaynigeria.com/product/dell-optiplex-5060-micro-desktop) |
| PC/network UPS | 1 | **₦117,200–₦140,000**, [Kara UPS reference](https://kara.com.ng/blue-gate-offline-1-570kva-ups) |
| Longer lithium backup | Optional 1 | **₦548,800**, [1.2kVA/1.3kWh reference](https://www.jumia.com.ng/cworth-energy-1.3kwh-lithium-lifepo4-battery-100ah-and-1.2kva-luxsun-hybrid-inverter-418986924.html) |
| Regulated terminal 12V/3A PSU | **1** | **₦11,000**, [Ikeja listing](https://jiji.ng/ikeja/power-equipments/mini-12v-3a-power-supply-unit-for-access-controlt-hzlHNjTxGL57DHWMsYwMx9Zf.html) |
| Gigabit switch | 1 | **₦14,600–₦29,000**, [Kara comparison](https://kara.com.ng/gigabit-ethernet-switch) |
| Pure-copper CAT6 | **One surveyed run** | About **₦10,000 for a 20m lead** or **₦156,600/305m**, [Kara cable reference](https://kara.com.ng/hikvision-ds-1ln6uzc0-o-std-grey-305m) |
| 4G/5G failover router | 1 | **₦20,000 / ₦40,000 / ₦80,000**, [MTN broadband](https://www.mtn.ng/broadband/) |
| Failover data | 1 SIM/month | **₦9,000 30GB** or **₦14,500 60GB**, [MTN router plans](https://www.mtn.ng/broadband/router-plans/) |
| RFID fallback cards | Users plus 5–10% spares | **₦45,000/100** or **₦350,000/1,000**, [market reference](https://www.jumia.com.ng/slp/custom-metal-rfid-card) |
| Managed QR scanner screen | Reuse 1 HTTPS-capable staff phone/tablet/laptop with camera, or quote a dedicated device | **Use existing managed equipment or formal quote**; do not use an unsupervised personal phone |
| PVC ID-card printer | Optional 1 when cards are printed in school | **Formal Nigerian quote required** for 300-DPI printer, warranty, ribbon, cleaning kit, and driver support |
| Blank CR80 cards and colour ribbon | Optional consumables matched to printer | **Formal quote required**; obtain cost per finished front/back card and spare/wastage allowance |
| Surge strip | At least 1 | **₦18,500**, [Kara surge reference](https://kara.com.ng/surge-protectors) |
| Voltage stabilizer | 1 where needed | **₦57,000**, [Kara stabilizer reference](https://kara.com.ng/duravolt-dv-2000va-stabilizer) |
| Canopy/mount/conduit/connectors | **One terminal installation** | Formal site quote; pilot allowance below is **₦75,000** |
| Configuration/commissioning/training | One school | **₦100,000 pilot / ₦250,000 standard / ₦350,000 large** internal allowance |

Attendance alone does not require a lock or turnstile. Physical access control introduces fire-egress and safeguarding obligations and needs a qualified design.

### Software

| Software | Cost/status |
| --- | --- |
| SchoolLift biometric module/API | Included in this implementation; hosting/support remains the project's normal cost |
| PHP 8.2 gateway and mock | **₦0 licence cost**; local operation/support still has a cost |
| Windows | Prefer a PC with genuine included Windows 11 Pro; verify activation |
| ZKBio Time 9.0.6+ | **Formal Nigerian quote required** for users, devices, and raw transaction API; **₦650,000** is only a provisional 500-user reserve |
| BioTime Africa | Published USD subscription tiers exist, but third-party raw-event API entitlement must be confirmed in writing |
| Backup/endpoint protection | Existing managed service or formal IT-provider quote |

Do not deploy ZKBio Time 9.0.1–9.0.4. ZKTeco's [security bulletin](https://www.zkteco.com/en/Security_Bulletinsibs/24) recommends 9.0.6 for the cited vulnerability.

### One-device planning budgets

These are not quotes. Add VAT/delivery and 10–15% contingency.

#### Pilot: up to 100 users

| Component | Amount |
| --- | ---: |
| 1 SenseFace 3A | ₦299,000 |
| Used gateway PC | ₦250,000 |
| UPS | ₦117,200 |
| Standard 4G router | ₦20,000 |
| Five-port switch | ₦14,600 |
| **One** 20m CAT6 run | ₦10,000 |
| 100 RFID cards | ₦45,000 |
| **One** terminal PSU | ₦11,000 |
| Surge protection | ₦18,500 |
| One mount/canopy/conduit allowance | ₦75,000 |
| Configuration, commissioning, training | ₦100,000 |
| ZKBio licence | ₦0 only if a usable free/API entitlement is confirmed |
| **Planning total** | **₦960,300** |

#### Standard production: up to 500 users

| Component | Amount |
| --- | ---: |
| 1 SenseFace 3A | ₦299,000 |
| New gateway PC | ₦550,000 |
| Line-interactive UPS | ₦140,000 |
| 5G failover router | ₦80,000 |
| Gigabit switch | ₦29,000 |
| 305m pure-copper CAT6 roll | ₦156,600 |
| 500 RFID cards at bulk reference | ₦175,000 |
| **One** terminal PSU | ₦11,000 |
| Voltage stabilizer | ₦57,000 |
| Site work, commissioning, training allowance | ₦250,000 |
| Provisional 500-user on-prem software reserve | ₦650,000 |
| **Planning total** | **₦2,397,600** |

#### Larger school: up to 1,000 users

| Component | Amount |
| --- | ---: |
| 1 SenseFace 7A planning allowance | ₦600,000–₦900,000 |
| Refurbished gateway PC with warranty | ₦571,500 |
| UPS plus lithium inverter backup | ₦688,800 |
| Branded 4G+ failover router | ₦165,555 |
| Gigabit switch | ₦29,000 |
| Pure-copper CAT6 roll | ₦156,600 |
| 1,000 RFID cards | ₦350,000 |
| **One** terminal PSU | ₦11,000 |
| Voltage stabilizer | ₦57,000 |
| Installation/support allowance | ₦350,000 |
| **Hardware/infrastructure total** | **₦2,979,455–₦3,279,455** |
| ZKBio on-prem licence/API | Add formal quote |

At this size, the five-day shadow pilot must measure peak persons/minute and queue length. Add capacity only when evidence justifies it.

Costs excluded: VAT not shown, delivery/insurance outside Lagos, civil work beyond allowances, primary internet, SMS/WhatsApp, DPCO/legal work, ongoing support, replacement batteries, backup storage, spare hardware, locks/turnstiles, and exact software/API licences.

## Privacy, safeguarding, and security

Biometrics used for identification are sensitive personal data, especially for children. Obtain Nigerian privacy advice/DPCO support before enrollment. Start from the [Nigeria Data Protection Act 2023](https://ndpc.gov.ng/wp-content/uploads/2024/03/Nigeria_Data_Protection_Act_2023.pdf) and current [NDPC guidance](https://www.ndpc.gov.ng/).

At minimum:

- document necessity, lawful basis, alternatives, retention, recipients, hosting/transfers, and rights in a DPIA;
- issue guardian and age-appropriate student notices;
- provide an effective non-biometric path without penalizing the person;
- keep templates/images inside the vendor platform and transmit only transaction metadata to SchoolLift;
- restrict setup, events, reconciliation, exports, tokens, and mode changes by role;
- encrypt transport, hosts, backups, and credentials; rotate/revoke integration tokens;
- retain immutable event/audit records only for an approved period and document deletion from devices, vendor systems, SchoolLift, and backups;
- train staff not to photograph, coerce, share, or casually export biometric/QR data;
- test false rejection across age, height, lighting, disability, and appearance changes;
- provide correction/appeal, incident response, breach escalation, and vendor contractual controls.

## Backup and recovery

- Back up every tenant database before migrations, mode changes, and bulk credential issuance.
- Export and protect the ZKBio configuration/licence, terminal enrollment backup where legally approved, gateway `config.php`, and the gateway SQLite database.
- Stop the scheduled gateway task before an SQLite file copy, or use an SQLite-consistent backup method; test restoring it on a separate PC.
- Keep the SchoolLift integration token and `BIOMETRIC_QR_ENCRYPTION_KEY` in the secret/recovery vault, with access logged and limited. The integration token can be rotated from Setup; update the gateway immediately. The QR key cannot be replaced like a normal token without re-encrypting or reissuing cards.
- Record RTO/RPO, restore owner, vendor licence recovery, and the supervised manual attendance process used during an outage.
- Restore into a non-production tenant first, keep mode `disabled`, verify counts/credentials/audit, then test `simulation` and gateway `shadow` before returning to live.

## Shadow pilot and go-live

### Stage 1: disabled

- Back up every tenant database and gateway/ZKBio configuration.
- Apply migrations and verify the new tables/permissions.
- Create integration/device/mappings while ingestion remains disabled.

### Stage 2: simulation

- Complete all website manual cases for students and staff.
- Prove no official attendance/payroll changes.
- Exercise trusted QR issuance, scan, revoke, and reissue.
- Run mock/gateway retry tests.

### Stage 3: physical shadow mode

Run at least five school days:

1. A pilot person selects IN, then identifies on the one terminal.
2. Confirm the API's exact punch state and SchoolLift IN.
3. Later the same person selects OUT and identifies on the same serial.
4. Confirm the different punch state and SchoolLift OUT.
5. Repeat for students/staff and face/fingerprint/card fallback.
6. Measure incorrect direction selections, recognition retries, peak queue, event delay, missing checkout, mapping errors, and outages.
7. Disconnect internet/LAN in controlled tests, produce permitted events, reconnect, and confirm buffered recovery/deduplication.
8. Reboot terminal, gateway, switch/router, ZKBio service, and scheduled task independently.

### Stage 4: limited live pilot

- Password-confirm the audited switch to `live`.
- Enable only the approved pilot group.
- Reconcile official student session/term and staff attendance types daily.
- Confirm manual records are not silently overwritten.
- Hide/reject Test Terminal actions and revoke simulator credentials in live mode.

### Stage 5: full school

- Expand mappings only after pilot sign-off.
- Monitor last sync, queue pending/dead, terminal heartbeat, exceptions, missing checkout, and manual conflicts every school day.
- Keep support/warranty contacts and a non-biometric outage process at the gate.

## Production acceptance checklist

Functional:

- One registered serial reliably produces explicit IN and OUT states.
- Earliest IN and latest OUT remain correct with repeat, delay, and out-of-order delivery.
- Duplicate IDs never duplicate events, attendance, or notifications.
- Unknown person/device/state becomes a visible exception.
- Student projection uses the current session and term; staff uses the configured type.
- Missing checkout remains visible and is never fabricated.
- Trusted QR requires authenticated gate staff, direction selection, and visual identity confirmation.
- QR issuance is disabled without a strong server encryption key, and key backup/recovery is proven.
- Manual corrections record actor, reason, old/new state, and do not silently lose data.

Reliability/security:

- Gateway restart preserves SQLite queue/cursor.
- `429`, `503`, bad token, internet outage, and ZKBio outage recover without loss.
- Task overlap is prevented and logs contain no secrets/templates.
- TLS certificates validate in production.
- ZKBio/device administration is not internet/student-LAN accessible.
- Backup and restore are demonstrated in staging.
- DPIA, notices, fallback, retention, DPCO/legal review, staff training, and incident contacts are approved.

Commissioning evidence should include invoice/quote, model/serial, firmware/software versions, API entitlement, punch-state proof, topology/IP/cable results, power runtime, gateway diagnostics, outage/recovery evidence, sample reconciliation, privacy sign-off, training attendance, open defects, and warranty/SLA.

## Troubleshooting

| Symptom | Likely cause | Safe action |
| --- | --- | --- |
| Test Terminal is hidden | Wrong mode/module/role permission | Use an authorized account and `simulation`; never expose it anonymously |
| Simulation changed official attendance | Mode/projection defect | Stop testing, return to `disabled`, preserve audit evidence, repair before rollout |
| ZKBio returns no event | Terminal offline, wrong TA Push/API/filter/time | Check exact firmware manual, serial, clock, transaction UI, API account |
| Both actions appear IN | Wrong function-key status or punch map | Return to `shadow`; capture raw safe events and correct verified mapping |
| Punch state missing/unsupported | Firmware/API does not expose explicit state | Quarantine; do not infer; require vendor fix/proof |
| Gateway `doctor` fails provider | URL/TLS/credentials/API entitlement | Fix locally; do not expose ZKBio publicly |
| SchoolLift returns 401/403 | Revoked/wrong token or integration disabled | Rotate/fix token and run `retry-dead` only if rows reached dead state |
| Queue pending grows | SchoolLift/network errors or backoff | Read JSON log/status, repair endpoint, allow safe replay |
| Queue dead grows | Permanent body/API error or exhausted attempts | Preserve rows, fix root cause, then deliberately `retry-dead` |
| Student/staff unknown | Missing/inactive/duplicate mapping | Reconcile explicitly; never guess by name |
| OUT without IN | User selected wrong state or IN absent/delayed | Keep exception; wait/reconcile with evidence, never invent IN |
| Duplicate after retry | Server external-ID uniqueness missing/broken | Stop live projection and repair idempotency |
| Wrong displayed time | Timezone/NTP mismatch | Align terminal, ZKBio, Windows, gateway, and school to `Africa/Lagos` |
| Face fails in sunlight | Placement/angle/light/enrollment quality | Shade/reposition/re-enroll and retain fallback |
| QR issuance warning remains | `BIOMETRIC_QR_ENCRYPTION_KEY` missing/short or OpenSSL unavailable to PHP | Install OpenSSL support, configure a random 32+ character server environment secret, restart PHP/web service; never bypass encryption |
| Existing QR prints blank after server move | Encryption key was not restored or does not match | Restore the exact protected key; otherwise revoke/reissue affected cards—do not weaken decryption checks |

## Final boundary

The production software, manual simulator, mock provider, and durable gateway can be tested without buying hardware. Only a physical pilot can approve recognition accuracy, liveness, usability, queue capacity, explicit state selection, buffered uploads, environmental behavior, and power recovery. Keep the school in `simulation` or `shadow` until those facts are recorded and accepted.
