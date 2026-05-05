# BluStar Transcription for FreePBX — Technical Wiki

A FreePBX 17 module that automatically uploads call recordings to a BluStar transcription server immediately after each call ends.

> **Audience:** PBX administrators, support engineers, and developers maintaining or extending this module.
> For end-user setup, see the project [README](README.md).

---

## 1. Overview

When FreePBX finishes writing a call recording to disk, this module hooks into Asterisk's `MixMonitor` post-recording mechanism, picks up the file, and POSTs it as `multipart/form-data` to a configured BluStar transcription server. The server authenticates the request with an API key and queues the file for transcription.

Every transfer is logged to a MySQL/MariaDB table so administrators can audit which recordings succeeded, which failed, and why.

**Module identifier:** `blustartranscription`
**Display name:** BluStar Transcription
**Publisher:** The Collabetive
**License:** Proprietary

### Compatibility

| Requirement | Version |
| --- | --- |
| FreePBX | 17.0 or newer |
| PHP | 8.1+ |
| Core module | `core ge 17.0` |

---

## 2. Architecture

```
 ┌────────────────────────────┐
 │ FreePBX recording finishes │
 │  (MixMonitor closes file)  │
 └──────────────┬─────────────┘
                │ MIXMON_POST in globals_custom.conf
                ▼
 ┌──────────────────────────────────────┐
 │ /var/lib/asterisk/agi-bin/           │
 │   transcription-upload.sh <file>     │
 │  - Reads /etc/asterisk/              │
 │      transcription.conf              │
 │  - curl POST to transcription server │
 └──────────────┬───────────────────────┘
                │ multipart/form-data + X-API-KEY header
                ▼
 ┌──────────────────────────────────────┐
 │ BluStar transcription server         │
 │   POST  /upload     (file upload)    │
 │   GET   /health     (liveness)       │
 │   POST  /webhook    (auth check)     │
 └──────────────┬───────────────────────┘
                │ result code
                ▼
 ┌──────────────────────────────────────┐
 │ transcription-log.php                │
 │  → INSERT INTO transcription_log     │
 └──────────────────────────────────────┘
```

The module ships three execution surfaces:

1. **Admin UI** — PHP pages rendered inside FreePBX for settings, connection testing, and viewing transfer history.
2. **Bash uploader** — invoked by Asterisk after every recording, decoupled from PHP so it stays fast and lightweight.
3. **CLI logger** — a small PHP helper run by the bash script to record results into the FreePBX database without inlining PHP into shell.

---

## 3. Repository layout

```
Blustar-Transcribe-PBX-Module/
├── .github/workflows/main.yml          # GitHub Actions: build, GPG-sign, release
├── README.md                           # End-user / installer docs
├── WIKI.md                             # (this file)
└── src/                                # Everything packaged into the module tarball
    ├── module.xml                      # FreePBX module manifest
    ├── install.php                     # Runs at module install — DB + scripts + Asterisk hook
    ├── uninstall.php                   # Reverses install.php
    ├── Transcription.class.php         # BMO class — module backend
    ├── page.transcription.php          # Top-level admin page (tab router)
    ├── views/
    │   ├── settings.php                # Settings form
    │   └── logs.php                    # Transfer log viewer (paginated)
    ├── assets/js/
    │   └── transcription.js            # AJAX, key visibility toggle, purge confirm
    └── agi-bin/
        ├── transcription-upload.sh     # Post-recording uploader (Asterisk runs this)
        └── transcription-log.php       # CLI helper called by the uploader
```

---

## 4. Installation flow

`install.php` runs once, the first time FreePBX installs or upgrades the module. It:

1. **Creates the log table** `transcription_log` (InnoDB, utf8mb4) with columns:
   - `id` (PK, auto-increment)
   - `timestamp` (DATETIME, defaults to `CURRENT_TIMESTAMP`)
   - `filename` (VARCHAR 512)
   - `status` (ENUM: `success`, `error`, `scp_failed`, `webhook_failed`)
   - `error_msg` (TEXT, nullable)
   - Indexes on `timestamp` and `status`.
2. **Installs the agi scripts** into `/var/lib/asterisk/agi-bin/` with mode `0755` and `asterisk:asterisk` ownership:
   - `transcription-upload.sh`
   - `transcription-log.php`
3. **Hooks Asterisk's recording subsystem** by editing `/etc/asterisk/globals_custom.conf` and appending:
   ```
   MIXMON_POST = /var/lib/asterisk/agi-bin/transcription-upload.sh ^{MIXMONITOR_FILENAME} ; transcription-module
   ```
   The `; transcription-module` comment is a marker the installer uses to find and replace its own line idempotently. `^{MIXMONITOR_FILENAME}` is `MixMonitor`'s deferred variable syntax — it is expanded by Asterisk at recording-stop time, not at parse time.

`uninstall.php` reverses each of the above:

- Removes the marker line from `globals_custom.conf`
- Drops `transcription_log`
- Deletes both agi scripts
- Deletes `/etc/asterisk/transcription.conf`

> The settings table managed by FreePBX's `FreePBX_Helpers` is **not** dropped on uninstall; FreePBX cleans those rows itself when the module is removed.

---

## 5. Configuration

### 5.1. Settings storage

Settings are persisted via the FreePBX BMO `getConfig()` / `setConfig()` API, backed by the FreePBX `kvstore`/`module_xml` schema. The backing keys are defined in `Transcription::DEFAULTS`:

| Key | Default | Notes |
| --- | --- | --- |
| `enabled` | `true` | Normalized to literal strings `"true"` / `"false"` on save. |
| `server_host` | *(empty)* | IP or hostname of the transcription server. Required. |
| `server_port` | `8600` | TCP port for HTTP API. |
| `api_key` | *(empty)* | Required. Sent as `X-API-KEY` header. **Never logged or echoed.** |
| `log_retention_days` | `30` | Used by the manual purge action. Min 1, max 365 in the UI. |

### 5.2. The generated config file

Whenever the admin saves settings, `Transcription::writeConfFile()` materializes a shell-sourceable file at `/etc/asterisk/transcription.conf`:

```
# Auto-generated by FreePBX Transcription module
# Do not edit manually — changes will be overwritten

SERVER_HOST='...'
SERVER_PORT='8600'
API_KEY='...'
ENABLED='true'
LOG_RETENTION_DAYS='30'
```

- Permissions: `0640`, owner `asterisk:asterisk`. This restricts who can read the API key to the `asterisk` user/group plus root.
- Single-quote escaping uses the standard `'\''` pattern so values containing apostrophes round-trip safely through `source`.
- After writing, `needreload()` is called so FreePBX flags the dialplan reload banner.

The bash uploader sources this file directly — there is no other authoritative copy of the runtime config.

---

## 6. Recording upload pipeline

### 6.1. How the script is invoked

Asterisk evaluates the `MIXMON_POST` global when a `MixMonitor` instance closes its recording. FreePBX's `sub-record-check` macro consumes that variable and supplies it as the post-process command. The deferred `^{MIXMONITOR_FILENAME}` expands to the recording's path (relative to `/var/spool/asterisk/monitor` or absolute, depending on the dialplan).

### 6.2. `transcription-upload.sh`

`/var/lib/asterisk/agi-bin/transcription-upload.sh` runs under `set -uo pipefail` and:

1. **Loads config** by sourcing `/etc/asterisk/transcription.conf`. Aborts with `logger -t transcription-upload` if missing.
2. **Honors the kill switch** — exits 0 silently when `ENABLED != "true"`.
3. **Resolves the recording path** — if the argument is relative (e.g. `2026/04/15/file.wav`), prefixes `/var/spool/asterisk/monitor/`.
4. **Validates** the file exists and that `SERVER_HOST` and `API_KEY` are non-empty.
5. **POSTs the file** with `curl`:
   - URL: `http://$SERVER_HOST:$SERVER_PORT/upload`
   - Header: `X-API-KEY: $API_KEY`
   - Body: `multipart/form-data` with field name `file`
   - `--connect-timeout 10`, `--max-time 300`
6. **Logs every outcome** via `transcription-log.php`:
   - `success` on HTTP 2xx
   - `error` on curl failure (exit code recorded) or HTTP non-2xx (status + truncated body recorded)
7. **Mirrors everything** to syslog with the tag `transcription-upload` so it shows up in `journalctl -t transcription-upload`.

### 6.3. `transcription-log.php`

A tiny CLI bootstrap that includes `/etc/freepbx.conf` and calls `\FreePBX::Transcription()->addLog($filename, $status, $errorMsg)`. It exists purely to keep PHP out of inline `php -r` calls, which would expose values to shell quoting bugs.

---

## 7. Server-side API contract

The module assumes the BluStar transcription server exposes three HTTP endpoints. Schemas are defined by the server, but the PBX module relies on this contract:

| Method | Path | Auth | Purpose | Module behavior |
| --- | --- | --- | --- | --- |
| `GET` | `/health` | none | Liveness probe | `Test Connection` requires HTTP 200 |
| `POST` | `/webhook` | `X-API-KEY` | Auth probe | `Test Connection` interprets `401` as a key mismatch and `400` as auth-OK-but-bad-payload (expected). |
| `POST` | `/upload` | `X-API-KEY` | File ingestion | Bash uploader sends multipart `file` field. Any 2xx is treated as success. |

**Transport is plain HTTP** today — deployments that need encryption should run the module-to-server traffic over a private VLAN or VPN tunnel.

---

## 8. Admin UI

`page.transcription.php` is the entry point registered by the `<menuitems>` block in `module.xml`. It renders a two-tab Bootstrap shell and dispatches to `views/settings.php` or `views/logs.php` based on the `?tab=` query parameter (whitelisted to `settings` / `logs`).

### 8.1. Settings tab (`views/settings.php`)

Three sections:

- **General** — radio toggle for `enabled`.
- **Transcription Server** — `server_host` (required), `server_port` (numeric 1–65535), `api_key` (password field with show/hide eye toggle).
- **Maintenance** — `log_retention_days` (numeric 1–365).

Two buttons:

- **Save Settings** — standard form POST. Handled by `doConfigPageInit()` which routes to `Transcription::saveSettings()`.
- **Test Connection** — fires the `test_api` AJAX command without saving. Performs client-side validation that host and key are non-empty before issuing the request.

### 8.2. Transfer Logs tab (`views/logs.php`)

- Paginated table (50 rows per page) over `transcription_log`, ordered by `timestamp DESC`.
- Status filter dropdown (`All` / `Success` / `Error`) submitted via `GET`.
- **Purge Old Logs** button → AJAX `purge_logs` command → confirms with the user, then deletes rows older than `log_retention_days` (capped to 30 if the setting is non-positive).
- Counts and pagination links are rendered server-side; no client-side state.

### 8.3. AJAX surface

`Transcription::ajaxRequest()` whitelists two commands; `ajaxHandler()` dispatches them.

| Command | URL | Returns |
| --- | --- | --- |
| `test_api` | `ajax.php?module=transcription&command=test_api` | `{ success: bool, message: string }` |
| `purge_logs` | `ajax.php?module=transcription&command=purge_logs` | `{ success: true, message: "Purged N old log entries" }` |

Both responses are consumed by `assets/js/transcription.js`.

### 8.4. Connection test logic

`Transcription::testApiConnection()` runs two sequential probes against the configured server:

1. `GET /health` with a 5-second connect/total timeout.
   - Translates common curl errno values into actionable diagnostics:
     - `7` → "Connection refused — is the transcription service running on host:port?"
     - `6` → "Could not resolve host"
     - `28` → "Connection timed out after 5s — check firewall rules"
   - Non-200 responses surface `HTTP <code>` plus the first 200 chars of the body.
2. `POST /webhook` with `{}` and the configured `X-API-KEY`.
   - `401` → key rejected (test fails)
   - `400` → auth passed, payload was rejected as expected (test passes)
   - Any other code is reported verbatim.

The test does **not** modify settings; it always reads the current persisted values.

---

## 9. Database schema

```sql
CREATE TABLE IF NOT EXISTS transcription_log (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    filename  VARCHAR(512) NOT NULL,
    status    ENUM('success','error','scp_failed','webhook_failed') NOT NULL,
    error_msg TEXT NULL,
    INDEX idx_timestamp (timestamp),
    INDEX idx_status    (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Notes:

- `scp_failed` and `webhook_failed` are reserved for legacy/future transports — the current uploader writes only `success` or `error`.
- All log inserts and reads use prepared statements via `\FreePBX::Database()`.

### Retention

- The **Purge Old Logs** UI action runs `DELETE FROM transcription_log WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)`.
- There is **no automatic cron** at present; purge is operator-driven.

---

## 10. Build, sign, release

The CI pipeline lives at `.github/workflows/main.yml` and runs on pushes to the `release` branch:

1. Reads `<version>` out of `src/module.xml`.
2. Aborts if a release tag `v<version>` already exists — forces version bumps before merging to `release`.
3. Renames `src/` to `blustartranscription/` so the tarball top-level directory matches the FreePBX module rawname.
4. Tarballs to `blustartranscription-<version>.tgz` and verifies the top-level directory before signing.
5. Imports a GPG key from repo secrets and produces a detached ASCII-armored signature `<archive>.tgz.asc`.
6. Exports the public key as `public.asc`.
7. Creates a GitHub release `v<version>` with the tarball, signature, and public key attached, and an installation snippet in the body:
   ```
   fwconsole ma downloadinstall <release-asset-url>
   fwconsole reload
   ```

> **Operational note:** The workflow consumes three repository secrets (private key, passphrase, key id). Rotate them in **GitHub → Settings → Secrets and variables → Actions**. Their values are intentionally **not** documented here.

---

## 11. Operational runbook

### 11.1. "Recordings aren't being uploaded"

1. Confirm the module's `Enabled` toggle is **Yes**.
2. Confirm calls are actually being **recorded** by FreePBX — the module only acts on files Asterisk produces. Check the relevant extension/route's recording settings.
3. Run **Test Connection**. Failure modes are described inline on the result banner.
4. Verify `/etc/asterisk/transcription.conf` exists and is readable by `asterisk`.
5. Check `journalctl -t transcription-upload` for per-call diagnostics.
6. Confirm the `MIXMON_POST` line is still present in `/etc/asterisk/globals_custom.conf` and survived the most recent `fwconsole reload`.

### 11.2. "Test Connection fails"

| Symptom | Likely cause |
| --- | --- |
| "Connection refused" | Transcription server is down, or listening on a different port. |
| "Could not resolve host" | DNS misconfiguration, or the host field is mistyped. |
| "Timed out after 5s" | Firewall is dropping packets between PBX and server. |
| `HTTP 401 — API key REJECTED` | Key on the PBX does not match the key configured on the BluStar server. |
| `HTTP 400` | **Expected on `Test Connection`.** Treated as success — the empty `{}` body is intentional. |

### 11.3. "Logs are growing without bound"

There's no automatic purge. Either:

- Click **Purge Old Logs** in the UI, or
- Schedule it from cron — e.g. `wget -qO- 'https://<pbx>/admin/ajax.php?module=transcription&command=purge_logs'` with a valid FreePBX session, or
- Run the equivalent SQL directly:
  ```sql
  DELETE FROM transcription_log
  WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY);
  ```

### 11.4. Manual reinstall of the Asterisk hook

If `globals_custom.conf` was hand-edited and the marker line is gone:

```bash
fwconsole ma uninstall blustartranscription
fwconsole ma install   blustartranscription
fwconsole reload
```

---

## 12. Security notes

- **API key handling:** Stored via FreePBX BMO config (not in plaintext on disk except in `/etc/asterisk/transcription.conf`, which is `0640 asterisk:asterisk`). Sent only as the `X-API-KEY` HTTP header, never URL-encoded into a query string. Never written to syslog or the transfer log.
- **Transport:** HTTP today. Deploy on a trusted network or front the server with a TLS-terminating proxy and update the URL prefix in `transcription-upload.sh` and `Transcription::testApiConnection()` if you need HTTPS.
- **Shell injection:** The bash uploader takes the recording path as `$1` and only ever quotes it. The PHP-side log helper takes `argv` directly and calls a parameterized DB insert — there is no shell interpolation of recording filenames into PHP.
- **SQL injection:** All queries against `transcription_log` use prepared statements. The `LIMIT`/`OFFSET` values are integer-cast (PDO doesn't accept them as bound parameters in this driver setup).
- **Direct page access:** `page.transcription.php` aborts unless `FREEPBX_IS_AUTH` is defined, which FreePBX sets after authenticating the admin session.
- **GPG-signed releases:** Every official tarball ships with a detached `.asc` signature; verify before installing on production PBXs (instructions live in the GitHub release body).

---

## 13. Versioning

Version is the single source of truth defined in `src/module.xml` under `<version>`. The release workflow refuses to publish if the corresponding `v<version>` tag already exists, so bumping `module.xml` is a hard prerequisite for cutting a new release.

Current version: **1.0.0**.

---

## 14. Support

For licensing questions or to obtain an API key, contact **The Collabetive**. Bug reports and feature requests should go to the GitHub Issues tracker on the repository.
