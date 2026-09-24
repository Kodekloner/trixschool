# SchoolLift Windows Gateway Manager

This folder provides a no-terminal setup and support interface for the local
SchoolLift biometric gateway. It does **not** expose a remote shell or inbound
web server on the school computer. The separately secured outbound control
channel can accept only named, argument-free gateway requests.

## What a school operator does

1. Create a ZKBio integration token on the school's SchoolLift website.
2. Set SchoolLift to **Shadow** mode.
3. On the protected computer running ZKBio Time, double-click
   `SchoolLift-Gateway-Manager.cmd`.
4. Approve the Windows administrator prompt.
5. Complete the five connection fields and save.
6. Use **Test connections**.
7. Use **Install / repair automatic sync**.
8. Make one Check In and one Check Out punch on the same physical terminal and
   confirm both events on the SchoolLift website.

After that, Windows runs the gateway once every minute. There is no fixed
waiting period: once the same terminal has produced a supervised physical IN
and OUT, every enabled person type has passed its Shadow check, the queue is
clear, and all exceptions are resolved, an authorized SchoolLift administrator
may complete the website's Live preflight and password confirmation. Nothing
is reinstalled and no command is run on Windows.

## Fields in the manager

- **SchoolLift school website:** the exact tenant HTTPS address, for example
  `https://demo.schoollift.com.ng`. HTTP is rejected.
- **ZKBio API on this computer:** normally the vendor's loopback address, such
  as `http://127.0.0.1:8098`. Plain HTTP is accepted only for `127.0.0.1`,
  `localhost`, or `::1`; a LAN or internet address must use HTTPS.
- **One terminal serial number:** the exact serial registered in SchoolLift.
  One bidirectional device supplies both IN and OUT punch states.
- **ZKBio read-only API username/password:** a least-privileged account that can
  read attendance transactions but cannot manage templates or devices.
- **SchoolLift integration token:** the high-entropy token shown once by the
  SchoolLift setup page. The token is masked and is never placed in task
  arguments.

The PHP executable path and all local file locations are detected or fixed by
the manager. There is deliberately no Browse button, arbitrary executable box,
command entry, or user-selected configuration path.

## Buttons and safety rules

- **Save protected configuration** validates the fields and writes
  `C:\ProgramData\SchoolLift\Biometric\gateway-config.json` as data, not
  executable PHP. Windows ACLs protect it.
- **Test connections** runs the fixed `doctor` action. SchoolLift must be in
  Shadow or Live because those are the modes that accept physical gateway
  traffic.
- **Install / repair automatic sync** first requires a successful connection
  test, installs the named task under restricted `LOCAL SERVICE`, starts it,
  and shows whether its fixed action matches this manager.
- **View queue status** reads the local SQLite counts without a network call.
- **Synchronize now** performs one normal gateway cycle. It cannot change the
  SchoolLift mode or bypass device, identity, direction, or attendance rules.
- **Retry failed (advanced)** is enabled as an action only after status shows
  dead records and the operator confirms that the cause has already been
  corrected. It cannot alter an event payload.
- **Show recent log** displays at most 100 lines from the fixed redacted log.
- **Remove task** removes only the named scheduled task after confirmation. It
  deliberately keeps configuration, SQLite state, and logs for recovery.

## Fixed storage and permissions

```text
C:\ProgramData\SchoolLift\Biometric\
    gateway-config.json       Administrators/SYSTEM full; LOCAL SERVICE read
    runtime\
        gateway.sqlite       LOCAL SERVICE may update
        gateway.lock         LOCAL SERVICE may update
        gateway.log          LOCAL SERVICE may update
```

The gateway code itself must remain in its installed location. The manager
removes ordinary-user write access, grants Administrators and SYSTEM full
control, and grants `LOCAL SERVICE` read/execute access throughout that gateway
directory. It refuses symbolic links/reparse points and known
IIS/XAMPP/WAMP/Laragon web document directories. Put no unrelated files in the
gateway folder; updates must run with administrator approval.

## PHP discovery

The manager accepts PHP 8.2 or newer only from these controlled candidates, in
order:

1. `runtime\php.exe` bundled inside the gateway directory;
2. `C:\SchoolLift\PHP82\php.exe`;
3. `C:\PHP82\php.exe`;
4. `C:\php\php.exe`;
5. the installed `php.exe` application on the machine PATH.

The gateway's own validation then requires `curl`, `json`, `openssl`, and
`pdo_sqlite`. There is no UI for selecting a different executable. A production
installer should bundle the approved runtime so discovery is deterministic.

## Web requests and hard safety boundaries

The website may request `connection_test`, `sync_now`, or a separately
confirmed `retry_failed`. Those are short-lived database records, not shell
commands. The gateway claims at most one through outbound authenticated HTTPS,
checks its exact allowlist, saves the claim/result durably, runs fixed internal
code, and returns a capped/redacted result. It does not accept request arguments.

The following must never become a web button or command payload:

- submitting PHP, PowerShell, CMD, executable names, shell text, or process arguments;
- choosing an executable or configuration filesystem path;
- starting the developer mock or injecting synthetic events into Live mode;
- displaying or downloading local ZKBio passwords or bearer tokens;
- installing/removing Windows tasks from the hosted server;
- resetting the queue or retrying permanent failures without local review;
- running the repository test suites on a production school website.

The public website may create/revoke an integration credential, show received
events and gateway heartbeat information, control the protected operating mode,
and enqueue the three fixed requests above. Local installation, configuration,
and scheduled-task ownership remain Windows responsibilities.

## Production packaging requirement

The included `.cmd` and `.ps1` scripts are unsigned source-tree aids. Before
school-wide distribution, package the gateway as an Authenticode-signed MSI or
EXE, bundle and hash-verify PHP plus extensions, install code read-only under
Program Files, retain protected state under ProgramData, create a signed Start
Menu shortcut, and provide a tested upgrade/rollback path. Schools should not
be instructed to ignore an unknown-publisher warning.
