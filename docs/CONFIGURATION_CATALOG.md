# Configuration Catalog

**System:** Wollo University Administrative and Document Management System (WU-DMS)

This catalog is the authoritative registry of the configuration values that
determine how the system behaves. It separates three categories that must not
be conflated:

* **Deployment configuration** — release-controlled values read from the
  environment. Changing them requires a deployment.
* **Secrets** — never editable through any interface; environment or vault only.
* **Administrator-managed runtime settings** — values an authorized
  administrator must be able to adjust after deployment without a release.

> **Current state:** the system is configured entirely through the environment.
> There is **no** database-backed settings table, and the `System Settings`
> administration screen is a static placeholder with no persistence. This is
> recorded as an open item in `docs/BUILD_STATUS.md`. Until that item is
> closed, the administrator-managed category below is empty by design rather
> than by omission.

---

## 1. Deployment Configuration

These values are read from the environment at boot. They take effect on
application restart (or on `php artisan config:clear` when the configuration
cache is in use). No audit trail is produced, because they are not editable
through the application.

### 1.1 Application identity

| Identifier | Purpose | Type | Default | Permitted values | Effect |
|---|---|---|---|---|---|
| `APP_NAME` | Institutional display name shown in the interface and in outbound mail. | string | `Wollo University DMS` | Any non-empty string | `config('app.name')`; used as the mail sender name and by the front end |
| `APP_ENV` | Execution environment. | string | `local` | `local`, `production`, `testing` | Determines debug output and error disclosure |
| `APP_DEBUG` | Whether framework diagnostics are rendered. **Must be `false` in production.** | boolean | `true` | `true`, `false` | Stack traces are exposed in responses when `true` |
| `APP_URL` | Public base URL of the API. | string | `http://localhost:8000` | Absolute URL | Generated URLs, mail links |
| `APP_LOCALE` | Default locale. | string | `en` | Installed locale | Translation resolution |
| `APP_FALLBACK_LOCALE` | Locale used when a key is missing. | string | `en` | Installed locale | Translation resolution |
| `APP_MAINTENANCE_DRIVER` | Backing store for maintenance mode. | string | `file` | `file`, `database`, `cache` | Maintenance state storage |

### 1.2 Session, cookie and transport security

| Identifier | Purpose | Type | Default | Permitted values | Effect |
|---|---|---|---|---|---|
| `SESSION_DRIVER` | Session persistence. | string | `database` | `database`, `file`, `redis`, `cookie` | Session storage |
| `SESSION_LIFETIME` | Idle session lifetime in minutes. | integer | `120` | `1`–`1440` | Session expiry |
| `SESSION_ENCRYPT` | Whether session payloads are encrypted at rest. | boolean | `false` | `true`, `false` | Session payload protection |
| `SESSION_PATH` | Cookie path. | string | `/` | Absolute path | Cookie scope |
| `SESSION_DOMAIN` | Cookie domain. | string | `localhost` | Host name | Cookie scope |
| `SANCTUM_STATEFUL_DOMAINS` | Hosts permitted to use the stateful SPA session. | csv string | `localhost:5173,localhost:8000` | Comma-separated host list | **Security boundary**: a host outside this list cannot use the session guard |
| `FRONTEND_URL` | Base URL of the single page application. | string | `http://localhost:5173` | Absolute URL | Builds the account recovery and address confirmation links delivered by mail |
| `CORS_ALLOWED_ORIGINS` | Origins permitted to call the API. | csv string | Local development origins | Comma-separated origin list | Cross-origin access. Falls back to `FRONTEND_URL` when unset |
| `BCRYPT_ROUNDS` | Password hashing work factor. | integer | `12` | `10`–`15` | Password hashing cost |

### 1.3 Database

| Identifier | Purpose | Type | Default | Permitted values | Effect |
|---|---|---|---|---|---|
| `DB_CONNECTION` | Database driver. | string | `mysql` | `mysql` | Connection driver |
| `DB_HOST` | Database host. | string | `127.0.0.1` | Host name | Connection target |
| `DB_PORT` | Database port. | integer | `3306` | `1`–`65535` | Connection target |
| `DB_DATABASE` | Schema name. | string | `wollo_dms` | Schema name | Connection target |
| `DB_USERNAME` | Database user. | string | `root` | Account name | Connection credential |
| `DB_PASSWORD` | Database password. | string | *(empty)* | — | Connection credential |

### 1.4 Storage

| Identifier | Purpose | Type | Default | Permitted values | Effect |
|---|---|---|---|---|---|
| `FILESYSTEM_DISK` | Default disk for document storage. | string | `local` | `local`, `s3` | Selects the storage driver |
| `AWS_ACCESS_KEY_ID` | Object storage access key. | string | *(empty)* | — | Required when `FILESYSTEM_DISK=s3` |
| `AWS_SECRET_ACCESS_KEY` | Object storage secret. | string | *(empty)* | — | Required when `FILESYSTEM_DISK=s3` |
| `AWS_DEFAULT_REGION` | Object storage region. | string | *(empty)* | AWS region | Required when `FILESYSTEM_DISK=s3` |
| `AWS_BUCKET` | Object storage bucket. | string | *(empty)* | Bucket name | Required when `FILESYSTEM_DISK=s3` |
| `AWS_URL` | Public base URL for objects. | string | *(empty)* | Absolute URL | Object URL construction |
| `AWS_ENDPOINT` | Custom endpoint for compatible object stores. | string | *(empty)* | Absolute URL | S3-compatible storage |
| `AWS_USE_PATH_STYLE_ENDPOINT` | Path-style addressing. | boolean | `false` | `true`, `false` | Required by some S3-compatible stores |

### 1.5 Queue, cache and logging

| Identifier | Purpose | Type | Default | Permitted values | Effect |
|---|---|---|---|---|---|
| `QUEUE_CONNECTION` | Queue backend. | string | `database` | `database`, `sync`, `redis` | Background job delivery. The database driver **requires a running worker** (`php artisan queue:work`) |
| `CACHE_STORE` | Cache backend. | string | `database` | `database`, `file`, `redis`, `array` | Cache and rate-limit backing store |
| `LOG_CHANNEL` | Log channel. | string | `stack` | `stack`, `single`, `daily`, `stderr` | Log destination |
| `LOG_STACK` | Channels in the stack. | csv string | `single` | Comma-separated channel list | Log fan-out |
| `LOG_LEVEL` | Minimum recorded level. | string | `debug` | `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency` | Log verbosity |
| `LOG_DEPRECATIONS_CHANNEL` | Channel for deprecation notices. | string | `null` | Channel name or `null` | Deprecation reporting |

### 1.6 Mail

| Identifier | Purpose | Type | Default | Permitted values | Effect |
|---|---|---|---|---|---|
| `MAIL_MAILER` | Mail transport. | string | `log` | `log`, `smtp`, `sendmail` | **Account recovery and address confirmation are only delivered when this is a real transport.** `log` writes the message to the log file instead of sending it |
| `MAIL_SCHEME` | Transport security scheme. | string | `null` | `smtp`, `smtps`, `null` | Transport encryption |
| `MAIL_HOST` | Mail server host. | string | `127.0.0.1` | Host name | Mail server |
| `MAIL_PORT` | Mail server port. | integer | `2525` | `1`–`65535` | Mail server |
| `MAIL_USERNAME` | Mail account. | string | `null` | Account name | Mail authentication |
| `MAIL_PASSWORD` | Mail credential. | string | `null` | — | Mail authentication |
| `MAIL_FROM_ADDRESS` | Sender address. | string | `noreply@wollo.edu.et` | Email address | Sender of recovery notices |
| `MAIL_FROM_NAME` | Sender name. | string | `${APP_NAME}` | Any string | Sender display name |

### 1.7 Front end

| Identifier | Purpose | Type | Default | Permitted values | Effect |
|---|---|---|---|---|---|
| `VITE_APP_NAME` | Application name compiled into the front end. | string | `${APP_NAME}` | Any string | Interface title |

---

## 2. Secrets

The following values must never be stored in an editable table, returned by an
API endpoint, or displayed in an administration screen.

| Identifier | Purpose | Storage requirement |
|---|---|---|
| `APP_KEY` | Application encryption key. | Environment or secret vault. Rotating it invalidates every encrypted value and every session cookie. |
| `DB_PASSWORD` | Database credential. | Environment or secret vault. |
| `MAIL_PASSWORD` | Mail credential. | Environment or secret vault. |
| `AWS_SECRET_ACCESS_KEY` | Object storage credential. | Environment or secret vault. |
| `AWS_ACCESS_KEY_ID` | Object storage credential. | Environment or secret vault. |
| Sanctum session identifiers | Authenticates the SPA session. | Server-side session store only. Never issued to the browser as a token. |
| Password reset tokens | Authorize a single credential recovery. | `password_reset_tokens` table, single use, deleted on completion. Never returned by an API. |

The demo credential file produced by the provisioning command
(`server/storage/demo_credentials.json`) is written with restricted permissions
and is excluded from version control by `.gitignore`.

---

## 3. Administrator-Managed Runtime Settings

**Empty.** No value is currently adjustable by an administrator at runtime
without a deployment.

The intended category, once the `System Settings` screen is given a backing
API, is:

| Intended identifier | Purpose | Type | Default | Minimum role | Persistence | Audit |
|---|---|---|---|---|---|---|
| `institution.display_name` | Official institutional name shown across the interface. | string | `Wollo University` | `system.configure` | `system_settings` table | Required |
| `institution.acronym` | Short institutional identifier. | string | `WU` | `system.configure` | `system_settings` table | Required |
| `institution.academic_year` | Current academic year pairing. | string | — | `system.configure` | `system_settings` table | Required |
| `retention.default_policy_code` | Retention policy applied when a document is registered without an explicit policy. | string | `INST_PERM` | `system.configure` | `system_settings` table | Required |
| `storage.default_driver` | Storage driver used for new documents. | enum | `local` | `system.configure` | `system_settings` table | Required |
| `security.session_lifetime_minutes` | Idle session lifetime. | integer | `120` | `system.configure` | `system_settings` table | Required |
| `security.password_minimum_length` | Minimum accepted credential length. | integer | `8` | `system.configure` | `system_settings` table | Required |

Design constraints that apply to the category when it is implemented:

* Every value is persisted in the database and survives a restart or redeploy.
* Every write is performed inside a transaction and produces an audit event
  recording the actor, the previous value, the new value and the source
  address.
* A "reset to default" operation is available per setting.
* Secrets are never part of this category.
* The front end fetches the effective values at bootstrap and applies them
  without a rebuild.

---

## 4. Configuration Drift Risks

| Risk | Consequence | Required production value |
|---|---|---|
| `APP_DEBUG=true` | Stack traces and environment disclosure in API responses | `false` |
| `MAIL_MAILER=log` | Account recovery and address confirmation are never delivered; users cannot recover access | A real transport (`smtp`) |
| `QUEUE_CONNECTION=database` with no worker running | Queued jobs, including notifications, are never processed | A supervised `php artisan queue:work` process |
| `SANCTUM_STATEFUL_DOMAINS` not matching the deployed front-end host | The SPA cannot authenticate at all | The exact deployed front-end host |
| `CORS_ALLOWED_ORIGINS` left at development values | The deployed front end is refused by the browser | The exact deployed front-end origin |
| `SESSION_ENCRYPT=false` | Session payloads are stored in clear text in the session store | `true` in production |
| `FILESYSTEM_DISK=s3` with empty AWS values | Document storage fails at runtime | All five AWS values populated |
