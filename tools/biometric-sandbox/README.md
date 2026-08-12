# ZKBio Time no-device mock

This isolated PHP service simulates the transaction API that the production gateway reads. It is a developer/integration fixture: it does not load CodeIgniter, connect to a school database, scan biometrics, or write attendance.

The school-facing demonstration is now the manual **Attendance > Biometric Attendance > Test Terminal** workflow. That interface submits one intentional IN or OUT event through the real event ledger. There is no automatic “Run demonstration” flow.

For the complete procedure and Nigerian deployment guidance, see [`../../docs/biometric-sandbox-and-nigeria-deployment.md`](../../docs/biometric-sandbox-and-nigeria-deployment.md). For gateway setup, see [`../biometric-gateway/README.md`](../biometric-gateway/README.md).

Requirements: PHP 7.4 or newer. Start the loopback-only mock:

```bash
php bin/serve.php
```

Inject and read a normal day using one bidirectional virtual terminal:

```bash
php bin/simulate.php \
  --scenario=normal \
  --employee=DEMO-STUDENT-001 \
  --terminal-serial=SIM-GATE-001
```

Both source events have the same terminal serial. The explicit `punch_state` differs: `0` for IN and `1` for OUT.

Other deterministic fixtures:

```bash
php bin/simulate.php --scenario=duplicate --terminal-serial=SIM-GATE-001
php bin/simulate.php --scenario=delayed --delay-seconds=10 --terminal-serial=SIM-GATE-001
php bin/simulate.php --scenario=out-of-order --terminal-serial=SIM-GATE-001
php bin/simulate.php --scenario=server-error
php bin/simulate.php --scenario=rate-limit
php bin/simulate.php --list
```

Use `--keep-state` to accumulate fixtures. Runtime state stays under ignored `var/state.json` unless `BIOMETRIC_SANDBOX_STATE` overrides it.

Default local-only credentials:

- API username/password: `sandbox_admin` / `sandbox_password`
- API token: `sandbox-token`
- control key: `sandbox-control-key`

Override them with `BIOMETRIC_SANDBOX_USERNAME`, `BIOMETRIC_SANDBOX_PASSWORD`, `BIOMETRIC_SANDBOX_TOKEN`, and `BIOMETRIC_SANDBOX_CONTROL_KEY` before starting the server.

Endpoints:

- `POST /api-token-auth/` and `POST /jwt-api-token-auth/`
- `GET /iclock/api/transactions/`
- `GET /health`
- protected `/sandbox/*` control endpoints using `X-Sandbox-Key`

The launcher and router reject non-loopback traffic by default. `--allow-network` is only for an isolated, firewalled laboratory LAN. Never expose this mock to the public internet or put production credentials/data into it.

Run its own tests with:

```bash
php tests/run.php
```

The payload resembles a common ZKBio Time API, not a warranty for every edition. Compare it with the licensed API documentation for the exact production release.
