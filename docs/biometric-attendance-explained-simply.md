# SchoolLift Biometric Attendance Explained Simply

Last reviewed: 13 August 2026
Applies to: the single-terminal biometric workflow and connector controls through migration 132

This guide explains the SchoolLift biometric attendance system in very simple language. It is written for school owners, administrators, teachers, gate staff, installers, and support staff who may not be technical.

The most important lesson is this:

> **Simulation practises the website workflow. Shadow practises with the real machine without touching the official register. Live is the only mode that can change official attendance.**

Do not move to Live merely because the page opens or the terminal recognizes one face. Live should begin only after the school has tested the complete physical process carefully.

## Contents

1. [The whole system in one story](#the-whole-system-in-one-story)
2. [The three notebooks](#the-three-notebooks)
3. [The four operating modes](#the-four-operating-modes)
4. [Mode 1: Disabled](#mode-1-disabled)
5. [Mode 2: Simulation](#mode-2-simulation)
6. [Mode 3: Shadow](#mode-3-shadow)
7. [Mode 4: Live](#mode-4-live)
8. [How one terminal handles both IN and OUT](#how-one-terminal-handles-both-in-and-out)
9. [What happens to every punch](#what-happens-to-every-punch)
10. [How SchoolLift calculates a day](#how-schoollift-calculates-a-day)
11. [Event, daily-session, and projection statuses](#event-daily-session-and-projection-statuses)
12. [Identity mapping](#identity-mapping)
13. [Exceptions and reconciliation](#exceptions-and-reconciliation)
14. [Manual attendance protection](#manual-attendance-protection)
15. [Trusted QR attendance](#trusted-qr-attendance)
16. [Website pages and permissions](#website-pages-and-permissions)
17. [Moving safely from Disabled to Live](#moving-safely-from-disabled-to-live)
18. [Daily operation in Live mode](#daily-operation-in-live-mode)
19. [Emergency actions](#emergency-actions)
20. [What SchoolLift stores and does not store](#what-schoollift-stores-and-does-not-store)
21. [Frequently asked questions](#frequently-asked-questions)
22. [A short checklist for the school owner](#a-short-checklist-for-the-school-owner)

## The whole system in one story

Imagine a student named Amina.

At 7:42 in the morning:

1. Amina walks to the school's one biometric terminal.
2. She chooses **Check In** on the terminal.
3. She presents her face, fingerprint, card, or PIN.
4. The terminal recognizes her and sends a transaction to ZKBio Time.
5. The SchoolLift Windows gateway reads that transaction.
6. The gateway sends attendance information securely to the correct SchoolLift school website.
7. SchoolLift checks the terminal, Amina's code, the time, and the selected IN state.
8. SchoolLift records Amina's first IN as 7:42.

At 2:15 in the afternoon:

1. Amina returns to the same terminal.
2. She chooses **Check Out**.
3. She identifies herself again.
4. SchoolLift records her last OUT as 2:15.
5. SchoolLift calculates the time between 7:42 and 2:15.

SchoolLift does **not** invent the 2:15 checkout. Amina must make a separate OUT punch. If she forgets, her day remains marked as **Missing checkout**.

The physical flow is:

```text
Amina selects IN or OUT
          |
          v
One biometric terminal recognizes her
          |
          v
ZKBio Time stores the terminal transaction
          |
          v
The Windows gateway sends it out over HTTPS
          |
          v
SchoolLift validates the event
          |
          +--> Event Book
          +--> Daily Summary Book
          +--> Exception Tray, if something is wrong
          +--> Official Register, only when Live allows it
```

## The three notebooks

The easiest way to understand the system is to imagine that SchoolLift keeps three different notebooks.

### Notebook 1: the Event Book

The Event Book contains each original event that SchoolLift stores durably. A request rejected very early may never become an Event Book row, and replaying a duplicate does not create a second row.

For example:

```text
7:42 AM — Amina — IN — Face — Gate terminal
2:15 PM — Amina — OUT — Face — Gate terminal
```

This book answers questions such as:

- What exactly arrived from the terminal?
- Which device sent it?
- Was it IN or OUT?
- Was face, fingerprint, card, PIN, or QR used?
- Was the stored original event accepted or quarantined, and did a sender receive a Duplicate or Rejected outcome without creating another row?
- When did the event happen, and when did SchoolLift receive it?

### Notebook 2: the Daily Summary Book

The Daily Summary Book joins valid events for one person and one date.

For Amina it may say:

```text
Date: 12 August 2026
First IN: 7:42 AM
Last OUT: 2:15 PM
Status: Present
Duration: 393 minutes
Missing checkout: No
```

This is where SchoolLift keeps checkout and duration. It always uses:

- the **earliest valid IN**; and
- the **latest valid OUT**.

### Notebook 3: the Official Attendance Register

This is the ordinary student or staff attendance record already used by SchoolLift reports and daily attendance.

Only Live mode can copy a valid biometric day into this official register. Simulation and Shadow are never allowed to do it.

The official register mainly receives the person's daily attendance type, such as **Present** or **Late**, based on the first IN. Checkout and duration remain in the newer Daily Summary Book.

### The Exception Tray

Problems that SchoolLift can safely preserve for investigation go into an Exception Tray for a human to inspect. Completely invalid or unauthorized requests may be rejected before they are stored.

Examples include:

- an unknown student code;
- an unknown terminal;
- an unsupported IN/OUT state;
- an OUT without an IN;
- an existing manual attendance record that SchoolLift refuses to overwrite.

### The Audit Diary

The Audit Diary records important administrator actions, such as:

- changing the operating mode;
- creating or rotating an integration credential;
- adding or disabling a device;
- changing a person's identity mapping;
- resolving an exception;
- issuing or revoking a QR credential;
- purging simulation records.

## The four operating modes

Think of the operating mode as a four-position safety switch.

| Mode | Simple meaning | What it accepts | Does it change official attendance? |
| --- | --- | --- | --- |
| **Disabled** | The attendance mailbox is locked | Nothing | No |
| **Simulation** | Staff practise with a safe training copy | Website Test Terminal and trusted QR | Never |
| **Shadow** | The real machine practises beside the real register | Physical terminal through the gateway | Never |
| **Live** | The tested system may write to the real register | Physical gateway and trusted QR | Yes, when projection is enabled and safe |

The server chooses the mode. A terminal, gateway, QR code, or person sending data cannot write `mode = live` inside a request to promote itself.

The mode also controls which source is trusted:

| Source of the punch | Disabled | Simulation | Shadow | Live |
| --- | --- | --- | --- | --- |
| Website Test Terminal | Rejected | Accepted | Rejected | Rejected |
| Physical device through gateway | Rejected | Rejected | Accepted | Accepted |
| Trusted QR scanner | Rejected | Accepted | Rejected | Accepted |

Changing modes does not move old records into the new mode. A Simulation event remains Simulation. A Shadow event remains Shadow. A Shadow IN and a later Live OUT do not become one complete Live day.

The mode is attached when SchoolLift actually receives and stores an event, not when the terminal originally created it. This creates one important queue rule: before switching Shadow to Live, drain and inspect the ZKBio and gateway queues/cursor. Otherwise, an old physical transaction that was never delivered during Shadow could arrive for the first time after the switch and be stored in Live scope.

For these reasons, do not change from Shadow to Live in the middle of a school day. Drain and verify the queues, then start Live before the first arrival of a fresh pilot day.

## Mode 1: Disabled

### Child-simple explanation

Imagine a post box with a padlock.

Old letters are still safely inside the filing room, but the post box will not accept any new letters.

That is Disabled mode.

### What Disabled does

Disabled rejects new attendance events from:

- the website Test Terminal;
- the real Windows gateway;
- the physical terminal through that gateway; and
- the QR scanner.

### What Disabled does not do

Disabled does not:

- delete earlier biometric events;
- delete daily sessions;
- remove an attendance record already written in Live;
- undo a Present or Late mark;
- erase devices, mappings, integrations, credentials, or audit history.

It stops **new intake**. It is not an undo button.

### What administrators can still do

Authorized administrators can still prepare the system while intake is Disabled. They can:

- review settings;
- create the gateway integration;
- register a terminal;
- configure IN and OUT punch states;
- map students and staff;
- inspect previous events and exceptions;
- issue or revoke QR credentials where permitted.

### When to use Disabled

Use Disabled:

- immediately after installing the biometric migrations through 132;
- before the school has tested anything;
- during major maintenance;
- if the integration token may be stolen;
- if events are going to the wrong school;
- if IN and OUT appear reversed;
- if official attendance is being changed incorrectly;
- during a serious security or data-quality incident.

Disabled is the safest emergency stop when the school does not trust incoming data at all.

## Mode 2: Simulation

### Child-simple explanation

Imagine a pilot using a flight simulator.

The buttons behave like the real aeroplane, but the aeroplane never leaves the ground.

Simulation is SchoolLift's safe practice mode.

### What Simulation accepts

Simulation accepts:

- one-event-at-a-time punches from the website Test Terminal; and
- scans from an authenticated trusted QR station.

It rejects the physical Windows gateway. This separation prevents someone from accidentally connecting a real terminal while staff believe they are only practising in the browser.

### What Simulation really tests

Simulation is not an animation and it is not a fake “Run Demo” button.

Every click goes through the real processing rules for:

- event validation;
- identity mapping;
- explicit IN or OUT direction;
- event IDs and duplicate protection;
- daily-session calculation;
- late calculation;
- missing checkout;
- quarantine;
- reconciliation.

Administrative changes and reconciliation actions are audited separately. Submitting an ordinary Test Terminal punch does not by itself create an administrator-action entry in the biometric Audit Diary.

The operator must submit each punch separately. To demonstrate a full day, submit one IN and then a separate OUT.

### What Simulation saves

Simulation saves training records in the new biometric Event Book and Daily Summary Book. They are clearly marked with Simulation scope.

This allows staff to practise reading Events, Daily Sessions, Reconciliation, and Audit.

### What Simulation can never change

Simulation cannot write to:

- official student attendance;
- official staff attendance;
- payroll attendance totals;
- ordinary attendance totals;
- the old attendance tables; or
- notification workflows.

Even if **Project valid student days** or **Project valid staff days** is checked, Simulation still cannot project. Official projection requires Live scope.

### The virtual terminal

Migration 130 creates one virtual bidirectional device:

```text
SIM-GATE-001
```

The Test Terminal uses it automatically. For the browser demonstration, staff do not need to register a physical terminal or create a gateway integration.

### A safe Simulation exercise

1. Set mode to **Simulation**.
2. Map a demo student code, such as `DEMO-STUDENT-001`.
3. Open **Test Terminal**.
4. Enter the mapped code.
5. Select **Check In**.
6. Select Face, Fingerprint, Card, PIN, or QR as the test method.
7. Submit one punch.
8. Confirm the event is accepted.
9. Confirm Daily Sessions shows IN and Missing checkout.
10. Resend the same event ID and confirm it is Duplicate.
11. Submit a separate **Check Out**.
12. Confirm Daily Sessions now contains OUT and duration.
13. Confirm ordinary attendance did not change.
14. Repeat for one staff member.

### Cleaning Simulation data

Simulation data remains stored until an authorized operator deliberately purges it.

The cleanup form requires the exact confirmation:

```text
PURGE_SIMULATION_DATA
```

The purge removes Simulation events, Simulation daily sessions, and their related exceptions and reconciliation actions. It retains:

- system settings;
- devices;
- integrations;
- identity mappings;
- QR credentials; and
- audit history.

The purge also stops if Simulation data is unexpectedly linked to official attendance. That stop is a safety alarm because Simulation should never have such a link.

## Mode 3: Shadow

### Child-simple explanation

Imagine a trainee teacher marking a photocopy of the attendance register while the experienced teacher continues to use the real register.

The trainee's answers can be compared with reality, but the trainee cannot alter the real book.

That is Shadow mode.

### Why Shadow is different from Simulation

Simulation tests the website using the virtual terminal.

Shadow tests the real physical chain:

```text
Real terminal
  -> ZKBio Time
  -> Windows gateway
  -> internet and HTTPS
  -> integration token
  -> SchoolLift API
  -> registered serial number
  -> punch-state mapping
  -> identity mapping
  -> event and daily session
```

### What Shadow accepts

Shadow accepts real gateway events.

It rejects:

- the website Test Terminal; and
- the QR scanner.

This keeps the physical pilot evidence separate from browser practice.

### What Shadow saves

Shadow saves real events and calculates real daily sessions. Staff can inspect:

- recognized person codes;
- terminal serials;
- IN and OUT selections;
- timestamps;
- delays;
- duplicates;
- missing checkouts;
- unknown identities;
- network recovery; and
- exception patterns.

### What Shadow can never change

Shadow never writes official student or staff attendance. It never changes payroll attendance totals.

The physical data is real, but it stays in a separate Shadow notebook.

### Why the school should run Shadow for at least five school days

One successful face scan proves very little. A proper pilot should cover:

- a busy morning arrival;
- an afternoon departure;
- one device producing both IN and OUT;
- students and staff;
- people who forget to select a direction;
- repeated scans;
- internet interruption;
- gateway restart;
- delayed transactions;
- power recovery;
- unknown codes;
- terminal-recognition failures;
- queue length; and
- missing checkout at the end of each day.

Five school days is an operational rule, not an automatic timer in the website. SchoolLift checks technical prerequisites, but a responsible human must confirm that the pilot truly lasted long enough and included both real IN and real OUT events.

### Shadow does not become Live later

Shadow records are not promoted into Live records.

For example:

```text
7:40 AM — Shadow IN
12:00 PM — administrator changes mode to Live
2:10 PM — Live OUT
```

The Live OUT has no Live IN. The two events belong to different safety notebooks.

This is why the school must change to Live before arrivals on a fresh day, not halfway through the day.

## Mode 4: Live

### Child-simple explanation

Live is like giving a tested robot permission to help the teacher write in the real attendance register.

The robot still keeps its detailed Event Book and Daily Summary Book, but now a valid IN may also create the official Present or Late record.

Because the real register affects reports and daily school operations, Live must be treated with care.

### What Live accepts

Live accepts:

- physical terminal events delivered by the authenticated Windows gateway; and
- trusted QR scans from an authenticated and assigned scanner station.

Live rejects the website simulator. The Test Terminal is hidden in Live, and the server also rejects simulator submissions even if someone tries to call the URL directly.

### What Live means for official attendance

A valid Live IN can be projected into official attendance when all of these are true:

1. The event is accepted.
2. A valid IN exists for the person and date.
3. The event resolves to an active student-session or staff record—through an active device identity mapping for biometric events, or through a valid trusted QR credential for QR events.
4. The correct student or staff projection checkbox is enabled.
5. There is no manual lock.
6. There is no conflicting attendance row for that person and date.
7. Student attendance is day-wise when student projection is enabled.

### Live does not wait for checkout before marking attendance

This is very important.

If Amina checks in at 7:42, SchoolLift can mark her Present immediately. It does not wait until 2:15 for checkout.

Her Daily Summary will temporarily say:

```text
First IN: 7:42 AM
Last OUT: empty
Status: Present
Missing checkout: Yes
Official projection: Projected
```

When she later checks out, SchoolLift adds the OUT and duration to the Daily Summary. The ordinary official attendance record remains the daily Present or Late record.

A missing checkout is a warning to investigate. It does not automatically erase a valid arrival.

### Live and projection checkboxes are separate controls

Live means the system is allowed to process real gateway and QR events. It does not force both student and staff official registers to be updated.

The settings contain two separate controls:

- **Project valid student days in Live mode**
- **Project valid staff days in Live mode**

If Student projection is off:

- Live student events are still validated and stored;
- student Daily Sessions are still calculated;
- official student attendance is not written; and
- projection status says Disabled.

The same rule applies separately to staff.

Review these checkboxes deliberately before going Live. New installations default them to enabled, so do not assume they are off.

### What Live writes for students

For a safe valid student IN, SchoolLift can write or update a biometric-owned record in `student_attendences` containing:

- the current student-session identity;
- the local attendance date;
- the current academic session where supported;
- the current term where supported;
- the configured Present or Late attendance type;
- a biometric source marker;
- a link back to the biometric Daily Summary; and
- the first-IN time as the creation time.

### What Live writes for staff

For a safe valid staff IN, SchoolLift can write or update a biometric-owned record in `staff_attendance` containing:

- the staff identity;
- the local attendance date;
- the configured staff Present or Late type;
- a biometric source marker;
- a link back to the biometric Daily Summary; and
- the first-IN time.

Staff checkout and duration are informational in this release. Existing payroll continues to use the configured daily staff attendance type. Do not assume that hours automatically calculate salary.

### What Live does not store

SchoolLift does not store:

- fingerprint templates;
- face templates;
- biometric images;
- raw fingerprint data;
- raw face data; or
- binary biometric enrolment files.

Those remain inside the terminal/vendor platform. SchoolLift receives attendance transaction metadata only.

### The software-enforced Live safety gate

An administrator cannot jump directly from Disabled or Simulation into Live. The current mode must be Shadow.

Before allowing the move from Shadow to Live, the website checks for:

1. an active ZKBio integration;
2. an enabled, non-virtual biometric terminal attached to an active integration;
3. configured IN and OUT punch states;
4. at least one active identity mapping;
5. evidence that a physical device has delivered an accepted event and therefore has a last-seen time;
6. no open biometric exceptions;
7. biometric edit permission;
8. General Settings edit permission;
9. the administrator's correct current password; and
10. day-wise student attendance if student projection is enabled.

The switch is written to the Audit Diary.

### What the software checklist cannot prove by itself

The checklist is a safety gate, but it is not a replacement for responsible testing. Its last-seen check alone does not prove that both directions were tested or that five Shadow days were completed.

The software does not automatically prove that:

- five complete Shadow days were observed;
- the exact same physical terminal successfully sent both IN and OUT;
- `0 = IN` and `1 = OUT` are correct for the purchased firmware;
- the school tested the morning queue;
- the school tested a power failure;
- every student and staff mapping is correct;
- the device recognizes people accurately in the real lighting; or
- terminal-side offline storage works as the supplier promised.

Humans must verify and sign off these facts.

Also note that an unresolved Simulation exception can still appear in the open-exception count. Resolve it or safely purge Simulation data before attempting Live.

### Period-wise student attendance

The current biometric projection creates one daily student attendance result.

If the school uses period-by-period student attendance, SchoolLift blocks student official projection. The biometric events and Daily Sessions can remain visible, but the system will not pretend that one gate arrival proves attendance for every lesson.

The school can either:

- keep Student projection off; or
- use the school's approved day-wise attendance setup before enabling Student projection.

Staff daily projection is separate.

### Password confirmation and change control

The administrator must confirm the current password when entering or re-entering Live.

Once already in Live, ordinary permitted setting changes do not ask for that entry password again. Therefore, the school should use a written change-control rule: do not casually change punch states, late times, mappings, devices, or projection checkboxes while Live is running.

## How one terminal handles both IN and OUT

The intended affordable setup uses one physical biometric terminal per school.

That one terminal is **bidirectional**. This means it can record arrivals and departures.

### The person must choose a direction

Before identifying, the student or staff member must choose:

- **Check In**, when arriving; or
- **Check Out**, when leaving.

The terminal turns that choice into a `punch_state` value.

The proposed starting map is:

```text
0 = IN
1 = OUT
```

However, those values are not universal promises. The installer must test the exact terminal model, firmware, ZKBio Time version, and API licence. The verified values must then be saved in SchoolLift.

### What SchoolLift refuses to guess

SchoolLift does not guess direction from:

- morning versus afternoon;
- the first punch versus the second punch;
- an alternating IN/OUT pattern;
- the person's previous event; or
- the terminal serial number.

It uses the explicit punch state.

Why? Because guesses create dangerous mistakes. A student might arrive for an afternoon programme, leave early, scan twice, or forget yesterday's checkout. The clock alone cannot always explain the person's intention.

### What happens when a person chooses the wrong direction

If Amina arrives but selects OUT, SchoolLift records the supplied OUT state. If no valid IN exists, it creates an exception instead of secretly changing OUT to IN.

Gate signage and staff training are therefore important.

## What happens to every punch

Every incoming event follows a series of checks.

```text
For a gateway request, is the bearer integration credential valid?
          |
          v
Is this source allowed in the current mode?
          |
          v
Is the request properly formed and recent enough?
          |
          v
Is the terminal registered, enabled, and correctly assigned?
          |
          v
Does the punch state mean IN or OUT?
          |
          v
Does the person code map to an active student or staff member?
          |
          v
Has this exact event ID already been received?
          |
          v
Store the event and recompute the daily session
          |
          v
If Live permits it, safely project the valid IN to official attendance
```

### Time checks

Gateway timestamps must include an ISO-8601 timezone offset. SchoolLift converts them to UTC for storage and to the school's configured timezone for the local date.

An event is rejected when it is:

- more than ten minutes in the future; or
- older than the configured maximum event-age window.

The default timezone is `Africa/Lagos`.

### Duplicate safety

Each real provider event must have a stable external event ID.

If the gateway sends the same event ID with the same contents again within the same source and integration/device scope, SchoolLift returns **Duplicate**. It does not create a second arrival or attendance record.

This is useful when the internet fails. The gateway can safely retry without guessing whether the first request reached SchoolLift.

If that external event ID returns with different contents inside the same source and integration/device scope, SchoolLift rejects it as an ID conflict and creates an exception. The same scoped label must not describe two different punches.

## How SchoolLift calculates a day

### Earliest IN wins

If Amina has valid IN events at 7:50 and 7:42, the Daily Summary uses 7:42.

### Latest OUT wins

If Amina has valid OUT events at 2:00 and 2:15, the Daily Summary uses 2:15.

### Delayed events are handled by their occurrence time

The gateway may deliver an old event after a newer event. SchoolLift recomputes the whole day's valid events by the time they happened, not merely the order in which the internet delivered them.

### Present and Late

Suppose the configured late cutoff is 8:00 AM.

| First valid IN | Result |
| --- | --- |
| 7:59:59 AM | Present |
| Exactly 8:00:00 AM | Present |
| 8:00:01 AM | Late |

The first IN must be strictly later than the cutoff to become Late.

### IN without OUT

The person is Present or Late according to the IN time, and the day says **Missing checkout**.

SchoolLift does not manufacture an OUT time.

### OUT without IN

The day remains incomplete and an `OUT_WITHOUT_IN` exception is created.

### OUT earlier than IN

An `OUT_BEFORE_IN` exception is created.

If a delayed valid IN later completes the sequence correctly, SchoolLift recomputes the day and can automatically resolve the timing exception.

### Mode boundaries

Events are calculated only with other events from the same operating mode. Simulation, Shadow, and Live are separate daily scopes.

## Event, daily-session, and projection statuses

There are three different kinds of status. Do not mix them up.

### 1. Event status

This describes one punch.

#### Accepted

The punch passed validation, was stored, and was used to calculate the person's Daily Summary.

Accepted does not always mean the official register changed. For example, an accepted Shadow event never changes official attendance, and an accepted Live event may meet a manual conflict.

#### Duplicate

The exact event was already stored. SchoolLift safely avoids counting it again.

#### Quarantined

The event was stored safely, but SchoolLift did not trust it enough to use it normally.

Examples:

- unknown person;
- unknown or disabled terminal;
- wrong integration assignment;
- unsupported punch state;
- direction contradiction;
- inactive mapped person.

The operator must correct the cause and reconcile it. SchoolLift does not guess.

#### Rejected

The request was invalid or unauthorized and was not processed as a normal attendance event.

Examples:

- wrong mode for the source;
- missing required values;
- invalid timestamp;
- bad bearer token;
- oversized or malformed request;
- same external event ID reused for different data.

### 2. Daily-session status

This describes the person's calculated day.

#### Present

The earliest valid IN is at or before the configured late time.

#### Late

The earliest valid IN is after the configured late time.

#### Incomplete

No usable IN exists yet.

#### Missing checkout

A valid IN exists, but no valid OUT exists.

### 3. Official projection status

This describes whether the Daily Summary changed the old official attendance register.

#### Not applicable

The Daily Summary belongs to Simulation or Shadow, so official projection does not apply. A quarantined event normally has no valid Daily Summary to project.

#### Pending

This is a Live Daily Summary that cannot yet project, commonly because no valid IN exists. This is a state, not a background job queue. Once a valid IN exists, normal processing usually changes it immediately to Projected, Disabled, or Conflict.

#### Projected

SchoolLift successfully linked the Daily Summary to an official attendance row.

#### Disabled

The event is Live and the Daily Summary exists, but the Student or Staff projection checkbox is off.

#### Conflict

SchoolLift deliberately refused to overwrite something unsafe, such as an existing manual attendance row, a manual lock, or unsupported period-wise student attendance.

## Identity mapping

The terminal knows a label. SchoolLift knows a person.

For example:

```text
Terminal label: STU-001
SchoolLift person: Amina's current student-session record
```

The mapping is the name tag joining those two identities.

### Student mapping

A student mapping points to the student's current `student_session` record, not merely the permanent student ID. This connects attendance to the correct academic session.

### Staff mapping

A staff mapping points to the active staff record.

### Safe mapping rules

- Use a documented admission number, employee ID, or dedicated immutable code.
- Never map by a person's name alone.
- One external code must not belong to two people.
- One person must not have conflicting active mappings.
- Preview bulk mappings before confirming them.
- Resolve conflicts instead of guessing.
- Disable mappings for leavers or invalid records.

An unknown code is quarantined. SchoolLift never silently creates a new student or chooses the person with the closest name.

## Exceptions and reconciliation

Reconciliation means a responsible human examines a problem and records what was done.

### Example: unknown student

1. The terminal sends `STU-999`.
2. SchoolLift cannot find an active mapping.
3. The event is quarantined with `UNKNOWN_PERSON`.
4. An administrator checks enrolment records.
5. The administrator creates the correct mapping.
6. The administrator returns to Reconciliation.
7. They select Retry and write a note.
8. SchoolLift rechecks the stored event.
9. If it is now valid, SchoolLift accepts it and recomputes the day.

### Resolve, retry, and ignore

- **Retry** means the underlying problem was corrected and SchoolLift should validate the event again.
- **Resolve** means the operator has dealt with the issue without reprocessing that event.
- **Ignore** means evidence shows the event should not affect attendance.

Every decision should include a meaningful note.

### A direction conflict cannot be rewritten quietly

If the event itself says one direction while its configured punch state says the opposite, the contradiction is part of the original evidence. The operator must ignore that event and submit a corrected event with a new external ID. SchoolLift does not rewrite history to hide the contradiction.

### Retry in the original mode

A quarantined event must be retried while the system is back in that event's original mode. A Shadow error is not converted into a Live event merely because the school has since changed modes.

## Manual attendance protection

Imagine a teacher has already written in the official paper register.

A safe robot should raise its hand. It should not rub out the teacher's writing.

SchoolLift follows this rule.

### Existing unrelated official record

If official attendance already exists for the same person and date and it is not the row linked to this biometric Daily Summary:

- the biometric event remains stored;
- the Daily Summary remains visible;
- official projection becomes Conflict;
- an exception is created; and
- the existing official record is not silently overwritten.

### Manual edit of a biometric-owned record

If an operator manually edits an official attendance record that was created by biometrics:

- the linked biometric Daily Summary becomes manually locked;
- projection becomes Conflict;
- a manual-override exception is created;
- the action is audited; and
- later terminal punches cannot silently undo the human decision.

The school must review the evidence and use its approved correction procedure.

## Trusted QR attendance

QR is a controlled fallback, not a public attendance link.

### What is inside the QR

The QR contains a long random, revocable credential. It does not contain a URL that directly writes attendance.

Opening or photographing the code in an ordinary browser cannot call a public “mark me present” endpoint.

### What a valid QR scan requires

1. The operator is logged in to SchoolLift.
2. The browser is assigned to an active trusted scanner station.
3. The station's device is enabled.
4. The operator visibly chooses IN or OUT.
5. The credential is valid, active, unexpired, and not revoked.
6. The current mode permits QR: Simulation or Live.

QR is rejected in Disabled and Shadow.

### Human photograph check

After a scan, SchoolLift shows the person's:

- name;
- photograph;
- Student or Staff type and, for a student, class/section;
- selected direction; and
- result.

The gate operator must compare the person with the photograph. A QR image can be copied or photographed, so supervision is still necessary.

### Lost cards

If a card is lost:

1. revoke its credential;
2. issue a new credential;
3. print a new card; and
4. verify that the old credential is rejected.

Reissuing automatically deactivates the previous active credential for that person.

### QR encryption key

Issuing reprintable QR credentials requires OpenSSL and a protected server environment value named:

```text
BIOMETRIC_QR_ENCRYPTION_KEY
```

It must contain at least 32 random characters. Store and back it up outside Git and the web root. Without it, credential issuance fails safely.

## Website pages and permissions

The module is located at:

```text
Attendance > Biometric Attendance
```

### Overview

Overview shows:

- the current mode;
- today's IN events for that mode;
- today's OUT events for that mode;
- missing checkouts;
- open exceptions;
- gateway last seen;
- last committed cursor;
- active devices; and
- Simulation event count.

The counters focus on the current mode. Switching modes can make the numbers look different without deleting earlier records.

### School Computer Connector

This page replaces routine gateway command-line work. It shows whether the protected Windows/ZKBio computer is Online, Delayed, Offline, or has never connected. It also shows the last heartbeat, last synchronization, ZKBio reachability, waiting/retrying/failed queue counts, the cursor, and a safe error summary.

An authorized administrator can request only three fixed actions:

- **Check connections** — the equivalent of the old installer `doctor` check;
- **Synchronize now** — asks the connector to run its normal safe synchronization; and
- **Retry failed items** — an advanced, typed-confirmation action used only after fixing the cause.

The website does not run a command directly on the school computer. The connector asks SchoolLift whether work is waiting during its next outbound, once-per-minute contact. Therefore a request normally begins within one minute. It cannot wake a computer that is off or repair an uninstalled/stopped connector.

There is no command-entry box, remote shell, filesystem path, PowerShell button, or validation bypass. Developer test suites and the mock ZKBio server remain developer-only tools. The one-time local installation uses the Windows Gateway Setup/Manager; normal Shadow and Live operation needs no terminal.

### Test Terminal

The Test Terminal sends one deliberate browser punch at a time.

It may be visible outside Live, but its submit action works only in Simulation. It is hidden in Live.

### Setup & Devices

This page contains:

- operating mode;
- school timezone;
- student and staff late times;
- Present and Late attendance types;
- Student and Staff projection switches;
- event-age and retention policy values;
- integration credentials;
- punch-state mappings;
- physical and virtual devices;
- the go-live checklist; and
- Simulation cleanup.

The configured retention value records the school's policy. The current web service does not yet automatically prove that old SchoolLift ledger rows have been deleted merely because this number is saved. Retention must be supported by an approved operational cleanup process and verified backups.

### Identity Mapping

This connects terminal person codes to active student-session or staff records. Bulk creation always begins with a preview.

### Events

This is the Event Book: one row for each durably stored original event, including direction, source, mode, processing status, and reason. A duplicate outcome does not add another row, and an early rejection may not appear here.

### Daily Sessions

This is the Daily Summary Book: earliest IN, latest OUT, duration, Present/Late status, missing checkout, and projection status. A manual lock is reflected by Conflict status and its exception rather than a separate visible lock column.

### Reconciliation

This is the Exception Tray. Authorized staff can retry, resolve, or ignore an exception and must record a note.

### QR Scanner

This manages trusted browser stations, issues/revokes credentials, selects IN/OUT, scans credentials, and displays the person's photograph and result.

### Audit

This is the Audit Diary. It records important settings and operator actions.

### Legacy biometric history

The Overview links to a read-only history of older biometric attendance records created before this new workflow. It is kept separate from the new event ledger.

### Permission meanings

Migration 130 grants full biometric permissions to roles literally named Admin and Super Admin. Custom roles must be granted deliberately.

- **View** opens the module and its records.
- **Add** allows Test Terminal punches and QR scans.
- **Edit** allows settings, devices, integrations, mappings, reconciliation, and QR issuance/revocation.
- **Delete** is required by the server for explicit Simulation data purge. The current setup page presents that cleanup control to an administrator who can edit the biometric module, so custom roles intended to purge should be granted both Edit and Delete.

Entering Live also requires **General Settings: Edit** and the operator's current password.

## Moving safely from Disabled to Live

Use this order:

```text
Disabled -> Simulation -> Shadow -> Live
```

### Stage 1: Disabled

1. Back up the tenant database.
2. Apply the database migrations through 132.
3. Confirm mode is Disabled.
4. Configure timezone, attendance types, and late cutoffs.
5. Create the integration and securely store its one-time token.
6. Configure the terminal and punch-state mapping.
7. Map students and staff.

### Stage 2: Simulation

1. Change to Simulation.
2. Test one student IN and OUT.
3. Test one staff member IN and OUT.
4. Test Duplicate.
5. Test unknown-person quarantine.
6. Correct the mapping and test Retry.
7. Confirm official attendance and payroll totals did not change.
8. Test QR if the school will use it.
9. Resolve or purge all practice exceptions.

### Stage 3: Shadow

1. Install and configure the physical terminal, ZKBio Time, and gateway once with the Windows Setup/Manager.
2. Change to Shadow.
3. Confirm **School Computer Connector** reports a recent heartbeat, a successful connection check, and a clear queue.
4. Send a real IN and a real OUT from the same serial.
5. Verify the raw punch-state meaning against the purchased firmware.
6. Run at least five complete school days.
7. Review queues, outages, mappings, wrong direction choices, duplicates, and missing checkout.
8. Confirm official attendance remains unchanged.
9. Resolve every open exception.

### Stage 4: Live pilot

1. Choose a fresh school day.
2. Take a current backup.
3. Confirm the gateway queue is healthy.
4. Confirm the terminal clock and SchoolLift timezone.
5. Review Student and Staff projection checkboxes.
6. Review Present/Late attendance-type selections.
7. Confirm the go-live checklist is green.
8. Stop new polling briefly and confirm ZKBio/gateway queues are drained, their cursor is committed, and no older Shadow transaction is waiting to be delivered.
9. Enter the administrator's current password.
10. Change from Shadow to Live before arrivals.
11. Resume the gateway and start with a limited pilot group if operationally possible.
12. Watch Events, Daily Sessions, Reconciliation, and ordinary attendance throughout the day.

Do not change to Live halfway through a day and expect earlier Shadow events to join the Live session.

## Daily operation in Live mode

### Before arrivals

- Confirm mode is Live.
- Confirm the School Computer Connector heartbeat and last successful sync are recent.
- Confirm waiting, retrying, and failed connector counts are all zero.
- Confirm the terminal is online and its clock is correct.
- Confirm ZKBio Time is running.
- Confirm no unexpected open exceptions remain.
- Confirm IN is the clear default instruction for arrivals.

### During arrival

- Watch the queue and failed recognition attempts.
- Help users choose IN.
- Watch for repeated wrong-direction selections.
- Check that accepted IN events appear promptly.
- Investigate unknown codes instead of guessing mappings.

### During departure

- Change signage/instructions clearly to OUT.
- Remind each person to choose Check Out.
- Watch missing-checkout numbers.
- Confirm OUT events use the same terminal serial but the verified OUT punch state.

### End of day

- Review Missing checkout.
- Review open exceptions.
- Review manual conflicts.
- Compare a sample of biometric days with gate observations.
- Check gateway queue/dead-letter status and logs.
- Record incidents and resolutions.
- Never invent checkout times merely to make the report look complete.

## Emergency actions

### Use Shadow when real intake should continue but official writing must stop

Move Live to Shadow when:

- real terminal evidence is still useful;
- the gateway and terminal are trusted; but
- official projection needs to pause.

New physical events will continue entering the Shadow notebook, but they will not change official attendance.

### Use Disabled for a hard stop

Move to Disabled when:

- the token may be stolen;
- events are reaching the wrong tenant;
- serial or mapping correctness is uncertain;
- punch states appear reversed;
- data integrity is in doubt; or
- intake itself must stop.

### Other emergency controls

- Rotate or disable the integration token if gateway credentials are compromised.
- Disable a device whose serial should no longer be trusted.
- Disable an incorrect or departed person's mapping.
- Revoke a lost QR credential.
- Preserve events, audit evidence, and logs before making corrections.

None of these actions automatically removes official attendance already written. Review existing records through the approved manual process.

## What SchoolLift stores and does not store

### SchoolLift stores attendance transaction metadata

Examples include:

- stable external event ID;
- terminal serial;
- external person code;
- mapped SchoolLift person;
- IN or OUT;
- verification method name;
- UTC and local occurrence time;
- operating mode and source;
- processing status and exception reason;
- Daily Summary links;
- safe, allowlisted provider metadata;
- projection link/status; and
- audit and reconciliation actions.

### SchoolLift does not store biometric material

It does not store:

- face templates;
- fingerprint templates;
- face photographs from recognition events;
- fingerprint images;
- biometric enrolment files; or
- arbitrary binary fields from ZKBio.

The gateway sends only allowlisted attendance fields over outbound HTTPS.

### Integration credential safety

The SchoolLift bearer token is shown once. SchoolLift stores its hash, not the reusable plain token.

The gateway operator must store the one-time token outside the web root with restricted Windows permissions. If it is lost, rotate it and update the gateway.

### Gateway delivery safety

The gateway keeps a durable local SQLite queue and cursor. It retries temporary network/server failures. Stable external event IDs let SchoolLift recognize safe replays as duplicates.

The exact terminal/ZKBio offline-buffering behaviour must still be physically tested. Do not assume the terminal will remember an unlimited number of punches during a power or network outage merely because the gateway can retry its own queue.

## Frequently asked questions

### Can one terminal perform both IN and OUT?

Yes. The terminal is registered as bidirectional. The person chooses Check In or Check Out before identification.

### Why must the person choose a direction?

Because time and scan order can be ambiguous. Explicit choice records the person's actual intention.

### Can SchoolLift guess direction from the time?

No. It intentionally refuses time-window guessing and first/next-punch alternation.

### Is Simulation safe for the official register?

Yes by design. It stores practice events in Simulation scope but cannot project them into official attendance.

### Can the physical gateway send events during Simulation?

No. Gateway events are accepted only in Shadow or Live.

### What is the difference between Simulation and Shadow?

Simulation uses the website's virtual Test Terminal. Shadow uses the real physical terminal, ZKBio Time, gateway, network, credential, and serial. Neither changes official attendance.

### Why can I not jump directly to Live?

The current mode must be Shadow, and the software checks integration, physical device, punch states, mapping, physical activity, exceptions, permissions, and password.

### Does Live wait for checkout before marking attendance?

No. A valid IN can immediately project Present or Late. OUT completes checkout and duration in the Daily Summary.

### What happens when someone forgets checkout?

The day says Missing checkout. SchoolLift never invents an OUT time.

### What happens when someone scans twice?

Two different valid IN events keep the earliest IN. Two different valid OUT events keep the latest OUT. Replaying the exact same event ID produces Duplicate and is not counted again.

### What happens when the internet goes off?

The gateway keeps its durable queue and retries. Once the connection returns, delayed events can be delivered and the day recomputes by occurrence time. The terminal and ZKBio's own buffering limits must be verified during the physical pilot.

### What if someone chooses OUT in the morning?

If no valid IN exists, the Daily Summary remains incomplete and creates an OUT-without-IN exception. SchoolLift does not change it to IN automatically.

### Can Shadow attendance later become Live attendance?

No. Shadow and Live are separate scopes. Changing mode does not promote old records.

### Does turning Live off undo official attendance?

No. It changes what happens to new events. Existing official records remain until reviewed through an approved correction process.

### What happens when manual attendance already exists?

SchoolLift refuses to overwrite an unrelated existing row. It records a projection conflict for human review.

### What if a teacher edits a biometric-created attendance row?

The biometric Daily Summary becomes manually locked. Later terminal events cannot silently overwrite the teacher's decision.

### Can Student and Staff projection be enabled separately?

Yes. Each has its own Live projection checkbox.

### Can period-wise student attendance use automatic student projection?

No. One gate arrival cannot prove attendance for every lesson. Student daily projection is blocked for period-wise attendance.

### Can a QR card mark attendance without staff login?

No. The scanner must be an authenticated SchoolLift browser assigned to a trusted station, and the operator must choose IN or OUT.

### Does SchoolLift store fingerprints or face templates?

No. It stores attendance transaction metadata only.

### Why is the gateway token shown only once?

Because SchoolLift stores only its hash. Showing the reusable secret repeatedly would increase the chance of theft.

### What is Quarantined compared with Rejected?

Quarantined means SchoolLift stored the event as evidence but will not use it normally until reviewed. Rejected means the request was invalid or unauthorized and was not processed as a normal event.

### Why did dashboard totals change after switching modes?

The Overview focuses on the current mode. Previous mode records still exist but are in a different scope.

### Who can change modes or reconcile records?

Only authenticated staff with the required biometric Edit permission. Live additionally requires General Settings Edit and password confirmation on entry.

### Does the Retention setting instantly delete old records?

No. It records the chosen policy value, but the current website service does not automatically prove that old ledger rows have been purged. The school must use a documented retention and backup procedure.

### What should the school do during a power or network outage?

Keep evidence, restore power/network safely, check gateway and ZKBio queues, allow stable event IDs to replay, inspect delayed events and exceptions, and reconcile only from evidence. Never invent arrivals or departures.

## A short checklist for the school owner

Before approving Live, the owner should be able to answer **Yes** to every question:

- Has the database been backed up and the migrations through 132 verified?
- Is one physical terminal configured for explicit IN and OUT?
- Did the supplier prove the actual punch-state values?
- Is the terminal registered with the correct serial and integration?
- Are student and staff mappings reviewed?
- Did Simulation prove IN, OUT, Duplicate, quarantine, and reconciliation?
- Did Simulation leave official attendance unchanged?
- Did Shadow run for at least five complete school days?
- Did the same physical serial send both a real IN and a real OUT?
- Were outage, retry, delay, and restart behaviour tested?
- Are all open exceptions resolved?
- Are Present/Late types and cutoff times correct?
- Are Student and Staff projection choices intentional?
- Is the school using day-wise student attendance if Student projection is enabled?
- Is a fresh pilot day selected for entering Live?
- Does the responsible administrator understand that Live IN can immediately change official attendance?
- Is there a written emergency plan for returning to Shadow or Disabled?

If any answer is No, remain in Simulation or Shadow.

## Final lesson

Remember the three notebooks:

1. The **Event Book** records each punch.
2. The **Daily Summary Book** finds the earliest IN and latest OUT.
3. The **Official Attendance Register** changes only through safe Live projection.

And remember the four-position switch:

- **Disabled** stops new intake.
- **Simulation** trains people safely in the website.
- **Shadow** tests the real equipment safely beside the official register.
- **Live** may write valid attendance into the official register.

When in doubt, do not guess. Move to Shadow or Disabled, preserve the evidence, and reconcile the problem carefully.

For installation, hardware, gateway, Nigerian procurement, and no-device testing instructions, read [Biometric sandbox and Nigeria deployment](biometric-sandbox-and-nigeria-deployment.md).

For issuing QR credentials through ID cards, read [ID Card Design Studio guide](id-design-studio.md).
