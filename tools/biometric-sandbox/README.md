# No-device biometric sandbox

This isolated PHP tool simulates the part of ZKBio Time that an attendance synchronizer consumes. It does not load CodeIgniter, connect to the SchoolLift database, store biometric templates, or modify production attendance records.

For non-technical demonstrations inside the school application, sign in with a role that has Student Attendance view access and open **Attendance > Biometric Demo**. That page runs the scenario generator in memory and never writes attendance. The standalone server documented here remains useful for automated tests and for developing the future ZKBio Time synchronizer.

For the complete test procedure, production architecture, Nigerian device/software price references, installation steps, privacy controls, and acceptance checklist, see [`../../docs/biometric-sandbox-and-nigeria-deployment.md`](../../docs/biometric-sandbox-and-nigeria-deployment.md).

Requirements: PHP 7.4 or newer with `allow_url_fopen` enabled for the simulator client. No Composer packages are required.

From this directory, start the loopback-only server:

```bash
php bin/serve.php
```

The launcher and HTTP router both reject non-loopback use by default. `--allow-network` deliberately enables access on a trusted test LAN; never expose the sandbox to the public internet.

In another terminal, run a deterministic scenario (the mock state resets by default):

```bash
php bin/simulate.php --scenario=normal --employee=TBSW/ST/0002 --date=2026-08-03
php bin/simulate.php --scenario=duplicate
php bin/simulate.php --scenario=delayed --delay-seconds=10
php bin/simulate.php --scenario=out-of-order
php bin/simulate.php --scenario=server-error
php bin/simulate.php --list
```

Use `--keep-state` to accumulate scenarios. Runtime state is ignored by Git and remains under `var/state.json` unless `BIOMETRIC_SANDBOX_STATE` overrides it.

Default local credentials:

- API user/password: `sandbox_admin` / `sandbox_password`
- API token: `sandbox-token`
- simulator control key: `sandbox-control-key`

Override them with `BIOMETRIC_SANDBOX_USERNAME`, `BIOMETRIC_SANDBOX_PASSWORD`, `BIOMETRIC_SANDBOX_TOKEN`, and `BIOMETRIC_SANDBOX_CONTROL_KEY` before starting the server.

Implemented endpoints:

- `POST /api-token-auth/` and `POST /jwt-api-token-auth/`
- `GET /iclock/api/transactions/`
- `GET /health`
- Control endpoints under `/sandbox/*`, protected by `X-Sandbox-Key`

Run all unit and HTTP-level tests with:

```bash
php tests/run.php
```

The payload shape represents the common ZKBio Time transaction contract, not a guarantee for every firmware/API edition. Compare it with the licensed API documentation supplied with the exact ZKBio Time release before connecting production hardware.
