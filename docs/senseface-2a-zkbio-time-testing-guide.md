# SchoolLift SenseFace 2A Testing Guide

## ZKBio Time trial, one bidirectional terminal, and SchoolLift Shadow mode

**Prepared for:** SchoolLift administrators and installation technicians

**Environment:** Nigeria (`Africa/Lagos`)

**Guide date:** 22 September 2026

**ZKBio Time address used in this guide:** `http://127.0.0.1:8080/`

---

## Purpose of this guide

This guide explains how to test one ZKTeco SenseFace 2A as both the Check In and Check Out terminal for GIS SchoolLift. It starts with the terminal and ZKBio Time alone, then connects them to the GIS SchoolLift website through the SchoolLift Windows gateway.

The first physical tests must use **Shadow mode**. Shadow mode receives real device punches and calculates daily IN/OUT sessions, but it does not change official student attendance, staff attendance, payroll, totals, or notifications.

Do **not** start in Live mode.

## How the parts communicate

```text
Person selects Check In or Check Out
                 |
                 v
        One SenseFace 2A terminal
                 |
          T&A PUSH / ADMS
                 v
  ZKBio Time on the protected Windows PC
                 |
   SchoolLift Windows gateway (once per minute)
                 |
          Outbound HTTPS connection
                 v
        gis.schoollift.com.ng
                 |
                 v
   Event ledger and daily attendance session
         (no official write in Shadow)
```

The SenseFace does not communicate directly with the public SchoolLift website. ZKBio Time first receives the terminal's attendance transactions. The SchoolLift Windows gateway then securely carries only the required transaction details from ZKBio Time to the correct SchoolLift school.

## Before starting

Prepare the following:

- One SenseFace 2A and its correct power supply.
- One protected Windows 10/11 computer running the ZKBio Time trial.
- ZKBio Time opening successfully at `http://127.0.0.1:8080/`.
- A wired Ethernet connection where practical.
- The terminal and Windows computer on the same trusted school network.
- Administrator access to `https://gis.schoollift.com.ng`.
- The latest SchoolLift biometric migrations already installed on the GIS school database.
- PHP 8.2 or newer for the SchoolLift Windows gateway.
- One consenting adult test person. Do not begin by enrolling many pupils.
- A notebook or test sheet for recording serial numbers, IP addresses, raw punch states, times, and results.

## Important safety rules

1. Keep SchoolLift in **Shadow** while testing the physical terminal.
2. Do not expose ZKBio Time port `8080` directly to the public internet.
3. Do not put biometric images or templates into SchoolLift.
4. Use a consenting adult for the first test.
5. Use one explicit Check In selection and one explicit Check Out selection. Do not infer direction from time.
6. Do not assume `0 = IN` and `1 = OUT` until the actual terminal transactions prove it.
7. Back up terminal information before switching its PUSH protocol. Some firmware may restart or clear information during the change.
8. Do not enable Live after only one successful test. Complete the Shadow pilot first.

---

# Part 1 — Confirm the ZKBio Time trial

## Step 1: Sign in and record the installed version

On the Windows computer running ZKBio Time:

1. Open a current browser.
2. Visit `http://127.0.0.1:8080/`.
3. Sign in to ZKBio Time.
4. Find **About**, **System Information**, or **Licence Information**. The name can differ between releases.
5. Record:
   - the complete ZKBio Time version and build number;
   - trial status and any expiry shown;
   - permitted number of devices and people;
   - whether API access is enabled.

The SchoolLift gateway expects the common ZKBio Time API authentication and transaction interfaces. If the later connection check says the API is unavailable, the ZKBio user may lack API permission or the trial may not include the required API entitlement.

## Step 2: Confirm the ZKBio Time services

Open the ZKBio Time Service/Platform Console supplied with the installation and confirm that all required services are running. Also record the port used for terminal ADMS/device communication.

The web page opening on port `8080` does not automatically prove that the terminal must use port `8080`. Confirm the device/ADMS port shown by the installed ZKBio release.

---

# Part 2 — Put the terminal and computer on the same network

## Step 3: Find the Windows LAN address

The address `127.0.0.1` means “this same computer.” It is correct for the SchoolLift gateway running on the ZKBio computer, but it is not an address the SenseFace can use to find that computer.

On Windows:

1. Open **Settings**.
2. Select **Network & Internet**.
3. Select the connected Ethernet or Wi-Fi network.
4. Open **Hardware properties**.
5. Record the **IPv4 address**.

Example:

```text
Windows LAN address: 192.168.1.50
ZKBio local browser:  http://127.0.0.1:8080/
```

Reserve the Windows address in the router or configure it according to the school's network policy so it does not unexpectedly change.

## Step 4: Configure the SenseFace network

On the SenseFace 2A, open the communication/network settings and enter:

- an unused terminal IP address on the same network;
- the correct subnet mask, commonly `255.255.255.0`;
- the router/default gateway address;
- the approved DNS address when required.

Example only:

| Item | Example value |
|---|---|
| Windows/ZKBio computer | `192.168.1.50` |
| SenseFace terminal | `192.168.1.60` |
| Subnet mask | `255.255.255.0` |
| Router/gateway | `192.168.1.1` |

Use values supplied by the school's network administrator. Do not copy the examples blindly.

---

# Part 3 — Configure the SenseFace for attendance

## Step 5: Switch the protocol to T&A PUSH

The menu wording can vary by firmware, but look for a path similar to:

```text
Menu
  > System
  > Device Type Setting
  > PUSH Protocol
  > T&A PUSH
```

The SenseFace 2A must use the attendance PUSH protocol for ZKBio Time. AC PUSH is for the access-control workflow and is not the mode required by this SchoolLift attendance test.

Restart the terminal if requested.

## Step 6: Point the terminal to ZKBio Time

Look for a menu similar to:

```text
Menu
  > Communication
  > Cloud Server Settings
```

Configure:

| Setting | Required value |
|---|---|
| Server mode | `ADMS` |
| Server address | Windows LAN address, for example `192.168.1.50` |
| Server port | Confirmed ZKBio Time ADMS/device port |

Do not enter `127.0.0.1` on the terminal. Do not enter `gis.schoollift.com.ng` on the terminal.

## Step 7: Configure manual Check In and Check Out

Look for:

```text
Menu
  > Personalize
  > Punch States Options
```

Configure:

- **Punch State Mode:** Manual Mode
- **Punch State Required:** ON
- **Punch State Timeout:** approximately 5–10 seconds

Then open **Shortcut Key Mappings** and create clearly labelled actions. A suggested starting arrangement is:

- Up key: Check In
- Down key: Check Out

Firmware behaviour can differ. The selection may be made immediately before biometric identification or requested immediately afterward. The essential requirement is that the person deliberately selects IN or OUT for every punch and that ZKBio records that explicit choice.

Place a sign next to the terminal:

> SELECT CHECK IN OR CHECK OUT BEFORE COMPLETING YOUR SCAN.

## Step 8: Synchronize time

Confirm the correct date, time, and timezone on:

- Windows;
- ZKBio Time;
- the SenseFace terminal;
- SchoolLift.

Use `Africa/Lagos`. A wrong clock can place a valid punch on the wrong attendance day or cause it to be quarantined as too old or in the future.

---

# Part 4 — Prove the device works inside ZKBio Time

## Step 9: Confirm the terminal is online

In ZKBio Time, open the device list and locate the SenseFace. Confirm:

- the exact serial number;
- Online/Connected status;
- the expected IP address;
- the correct device time.

If the terminal is not present or online, stop here and resolve the terminal-to-ZKBio connection before configuring SchoolLift.

## Step 10: Create one adult test person

Use a simple device person code such as:

```text
900001
```

The device person code is the identifier ZKBio puts on every transaction. It is not the person's name and it is not a school subject such as English or Mathematics.

Create the person in ZKBio Time and send the record to the device, or enroll the person directly on the terminal according to the supported ZKBio workflow. In either case, confirm that ZKBio Time and the terminal show the same code.

Enroll only one method for the first proof:

- face;
- fingerprint; or
- card.

## Step 11: Produce two transactions

1. Deliberately select **Check In**.
2. Authenticate as device person `900001`.
3. Wait briefly.
4. Deliberately select **Check Out**.
5. Authenticate again as `900001`.

Open the transaction/attendance-record page in ZKBio Time. Confirm that both rows contain:

- device person code `900001`;
- the same SenseFace serial number;
- correct times;
- two different punch states;
- the expected verification method.

Record the actual raw punch-state values. The planned starting map is:

```text
Check In  = 0
Check Out = 1
```

If the device produces other values, those observed values must be used in SchoolLift. Never guess or use a time-of-day rule.

---

# Part 5 — Prepare SchoolLift Shadow mode

## Step 12: Open Biometric Attendance

Sign in to:

`https://gis.schoollift.com.ng/admin/biometricattendance`

Open **Setup & Devices**.

## Step 13: Select Shadow mode

Under **Operating mode and rules**:

1. Select **Shadow**.
2. Enter timezone `Africa/Lagos`.
3. Review student and staff late times.
4. Select the intended Present and Late attendance types.
5. Leave projection choices as required for the future Live rollout; they do not write official attendance while the system remains in Shadow.
6. Save the mode and rules.

Shadow mode means real punches are accepted, validated, stored, and calculated without changing official attendance.

## Step 14: Create the ZKBio integration token

Under **ZKBio integration credential**:

1. Use a clear name such as `GIS School SenseFace 2A`.
2. Keep the provider as **ZKBio Time / BioTime**.
3. Select **Create integration and token**.
4. Copy the complete token immediately and store it securely for the gateway setup.

SchoolLift displays the complete token only once. SchoolLift stores only its secure hash afterward.

Do not create multiple active integrations for the same gateway test.

## Step 15: Save the observed punch-state mapping

For the new integration, enter the values proved by the physical test. If the device produced the expected values:

```text
IN state:  0
OUT state: 1
```

The values must be distinct.

## Step 16: Register the physical terminal

Under **Single bidirectional terminal**, enter:

| Field | Example |
|---|---|
| Terminal serial | Exact serial shown by ZKBio Time |
| Terminal name | `Main School Gate` |
| Location | `Front Gate` |
| Integration | The integration created above |

The serial is case- and punctuation-sensitive for reliable matching. Copy it rather than typing from memory.

## Step 17: Create the identity mapping

Open **Identity Mapping**:

1. Choose **Student** or **Staff**.
2. Search for the test person's active SchoolLift record.
3. Select the exact person.
4. Enter `900001` in **Device person code**.
5. Save the mapping.

The mapping means:

```text
ZKBio person 900001 = this exact SchoolLift student or staff record
```

Creating the mapping does not enroll a face or fingerprint. Biometric enrollment remains inside the terminal/ZKBio environment.

---

# Part 6 — Install the SchoolLift Windows gateway

## Step 18: Put the gateway in a permanent location

On the same protected Windows computer that runs ZKBio Time, copy the complete gateway folder to:

```text
C:\SchoolLift\biometric-gateway
```

Do not place it inside an IIS, XAMPP, WAMP, Laragon, Apache, or other public web directory.

## Step 19: Confirm the PHP runtime

Install PHP 8.2 or newer with these extensions enabled:

- cURL;
- JSON;
- OpenSSL;
- PDO SQLite;
- SQLite3.

The graphical Gateway Manager searches controlled locations such as `C:\PHP82\php.exe` and `C:\php\php.exe`. A packaged production installer may include the approved runtime automatically.

## Step 20: Open the graphical Gateway Manager

Double-click:

```text
C:\SchoolLift\biometric-gateway\windows\SchoolLift-Gateway-Manager.cmd
```

Approve the Windows administrator prompt.

Complete the fields as follows:

| Gateway Manager field | Value for this test |
|---|---|
| SchoolLift school website | `https://gis.schoollift.com.ng` |
| ZKBio API on this computer | `http://127.0.0.1:8080` |
| One terminal serial number | Exact SenseFace serial registered in SchoolLift |
| ZKBio API username | API-enabled ZKBio user |
| ZKBio API password | Password for that ZKBio user |
| SchoolLift integration token | One-time token copied from SchoolLift |

Using `127.0.0.1` here is correct because the gateway and ZKBio Time run on the same Windows computer.

## Step 21: Save and test the connection

In the Gateway Manager:

1. Select **Save protected configuration**.
2. Select **Test connections**.
3. Read the diagnostic result.
4. When both ZKBio and SchoolLift pass, select **Install / repair automatic sync**.

The automatic Windows task runs the connector approximately once per minute. No routine command prompt is required.

The configuration and gateway queue are protected under:

```text
C:\ProgramData\SchoolLift\Biometric\
```

Do not copy passwords or bearer tokens into batch files, shortcuts, email, or public support messages.

### Where to find the School Computer Connector

After the automatic sync is installed, sign in to `https://gis.schoollift.com.ng` and open:

**Attendance > Biometric Attendance > School Computer Connector**

The connector is a tab inside the Biometric Attendance page; it is not a separate item in the main Attendance menu. On a narrow phone screen, the tabs are stacked vertically, so scroll below **Setup & Devices** if necessary.

This tab displays the Windows connector's heartbeat, ZKBio connection, last synchronization, queue counts, and safe **Check connections** and **Synchronize now** actions.

---

# Part 7 — Run the complete physical Shadow test

## Step 22: Confirm the connector heartbeat

In SchoolLift, open **School computer connector**. Within approximately one minute, confirm that the gateway appears and reports:

- a recent heartbeat;
- ZKBio connection status;
- synchronization status;
- queue counts;
- provider cursor.

The website's **Check connections** button creates a safe request that the already-installed Windows gateway collects. The website cannot start an uninstalled or stopped program on the school computer.

## Step 23: Submit a real Check In

1. On the SenseFace, select **Check In**.
2. Authenticate as the test person.
3. Confirm the transaction appears in ZKBio Time.
4. Wait up to two minutes for the automatic connector.
5. In SchoolLift, open **Events**.
6. Confirm an accepted Shadow IN event appears.

## Step 24: Submit a separate real Check Out

1. On the same SenseFace, select **Check Out**.
2. Authenticate as the same person.
3. Confirm the transaction appears in ZKBio Time.
4. Wait for synchronization.
5. Confirm an accepted Shadow OUT event appears in SchoolLift.
6. Open **Daily Sessions**.
7. Confirm the session has both its first IN and latest OUT.

The same terminal serial must appear on both transactions. Direction must come from the raw punch state, not from different terminals.

## Step 25: Confirm that Shadow did not change official attendance

Open the ordinary student or staff attendance page and confirm that this physical Shadow test did not create or alter an official attendance record.

That is expected. Shadow is the safety rehearsal.

---

# Part 8 — Required test cases

Perform and record these tests before considering Live mode:

| Test | Action | Expected SchoolLift result |
|---|---|---|
| Normal day | One IN followed by one OUT | Accepted events and completed daily session |
| Missing checkout | Submit IN only | Session remains visibly missing OUT |
| OUT without IN | Submit OUT first | Exception; SchoolLift does not invent an IN |
| Repeated IN | Submit IN twice | Earliest valid IN remains authoritative |
| Repeated OUT | Submit OUT twice | Latest valid OUT remains authoritative |
| Duplicate replay | Synchronize the same ZKBio transaction again | Duplicate result; no second attendance event |
| Unknown person | Punch with an unmapped device code | Quarantined event and mapping exception |
| Wrong/unknown state | Produce an unsupported punch state if safely possible | Quarantined event; direction is not guessed |
| Network outage | Disconnect internet after ZKBio receives a punch, then restore it | Gateway queues/retries without losing the event |
| Reboot | Restart terminal and Windows services | Connections and synchronization recover |
| Late arrival | Punch after the configured late threshold | Daily session calculates Late in Shadow |
| Time check | Compare terminal, ZKBio, Windows, and SchoolLift | Times represent the same `Africa/Lagos` moment |

Use **Exceptions** and the gateway's redacted logs to investigate failures. Do not “resolve” an exception until its real cause is understood.

---

# Part 9 — Troubleshooting

## Terminal does not appear in ZKBio Time

Check:

- T&A PUSH is selected;
- terminal and Windows computer are on the same reachable network;
- the terminal uses the Windows LAN address, not `127.0.0.1`;
- the ADMS/device port is correct;
- Windows Firewall allows the ZKBio service;
- the ZKBio services are running;
- date and time are correct.

## Punch appears on the terminal but not in ZKBio Time

Check the terminal's server connection indicator, T&A PUSH selection, ADMS settings, serial, network route, and buffered records. Restart only after recording the current settings.

## Gateway says ZKBio authentication failed

Check:

- ZKBio address is exactly `http://127.0.0.1:8080` for this installation;
- username and password are correct;
- the user has API permission;
- the trial/licence has not exceeded its API or device limit;
- the installed release exposes the documented API routes.

Do not replace the official licence with a cracked or modified licence.

## ZKBio contains the transaction but SchoolLift does not

Check:

- SchoolLift mode is Shadow;
- the integration is enabled;
- Gateway Manager connection tests pass;
- automatic synchronization is installed and running;
- the SchoolLift token has not been rotated;
- the Windows computer can reach the SchoolLift HTTPS address;
- gateway queue/log status.

## SchoolLift reports an unknown device

Copy the serial shown on the ZKBio transaction and compare it with the enabled SchoolLift physical device. Correct the registered serial; do not create an unsafe serial bypass.

## SchoolLift reports an unknown person

Compare the transaction's device person code with the active SchoolLift mapping. The values must match exactly. Then retry the quarantined event through the documented exception workflow.

## Both actions become IN, or both become OUT

Return to Shadow. Capture the raw state produced by one deliberate IN and one deliberate OUT. Correct the integration's punch-state mapping. Do not use arrival time to guess the direction.

## Times or attendance days are wrong

Align Windows, ZKBio Time, the terminal, and SchoolLift to `Africa/Lagos`. Check automatic clock synchronization and daylight-saving settings.

---

# Part 10 — Successful-test checklist

The initial physical test is successful only when every applicable item is checked:

- [ ] ZKBio Time services are running.
- [ ] The trial/licence permits one test device.
- [ ] API permission is available for the connector user.
- [ ] The SenseFace is operating in T&A PUSH mode.
- [ ] The SenseFace is Online in ZKBio Time.
- [ ] The terminal clock is correct.
- [ ] Manual IN/OUT selection is required and understandable.
- [ ] ZKBio Time shows one real IN and one real OUT.
- [ ] Their raw punch states are different and recorded.
- [ ] The physical serial is registered exactly once in SchoolLift.
- [ ] The device person code maps to the correct SchoolLift person.
- [ ] The Windows gateway passes both connection checks.
- [ ] The automatic once-per-minute synchronization is installed.
- [ ] SchoolLift receives both accepted events in Shadow.
- [ ] Daily Sessions shows the correct IN and OUT.
- [ ] Official attendance remains unchanged in Shadow.
- [ ] There are no unexplained open exceptions.
- [ ] Network interruption and reboot recovery have been tested.

## What happens next

Continue operating in Shadow for at least five normal school days. Review incorrect user selections, missed checkouts, mapping errors, queue behaviour, network/power interruptions, duplicate transactions, and peak gate traffic.

Only after the Shadow pilot is accepted should an authorized administrator consider Live mode. Live mode requires the SchoolLift readiness checks, appropriate permissions, and administrator password confirmation. Once enabled, valid IN events can create or update official daily attendance according to the configured projection rules, so it must not be used casually for experiments.

---

# Official references

- ZKTeco SenseFace 2A product page: <https://www.zkteco.com/en/SenseFaceSeries/SenseFace_2A>
- SenseFace 2A user manual: <https://www.zkteco.co.id/wp-content/uploads/download-manager-files/ZK_SenseFace-2A_UM_EN-v1.0_20240327.pdf>
- ZKBio Time product/download page: <https://www.zkteco.com/en/ZKBioTime/ZKBioTime>
- ZKTeco FAQ: <https://www.zkteco.com/en/faq>
- SchoolLift detailed deployment guide: `docs/biometric-sandbox-and-nigeria-deployment.md`

## Information to collect if support is needed

Send screenshots with passwords, tokens, faces, fingerprints, and private student information hidden. Include:

- SenseFace model and firmware version;
- ZKBio Time version and build number;
- terminal serial privately, not in a public post;
- terminal Online/Offline status;
- the safe error text from Gateway Manager;
- whether the punch appears in ZKBio Time;
- whether the event appears in SchoolLift;
- the raw IN and OUT punch-state values;
- relevant event or exception ID.
