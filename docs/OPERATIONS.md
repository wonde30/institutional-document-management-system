# Operations Guide

**System:** Wollo University Administrative and Document Management System (WU-DMS)

This document covers the operational arrangements that the application
depends on and that the application itself cannot provide: the scheduler, the
continuous integration pipeline, and the lifecycle of the temporary dataset
files.

---

## 1. The Laravel scheduler

### 1.1 Why it matters

Two behaviours of the system are driven by the scheduler. Neither happens
merely because the scheduler event exists.

| Scheduled command | Schedule | Purpose |
|---|---|---|
| `dms:check-due-dates` | every minute | Detects overdue assignments, workflow tasks and document movements, and dispatches the reminders and escalation audit events. |
| `dms:cleanup-import-uploads` | daily at 02:30 | Removes the temporary dataset files of import operations that were inspected and never executed. |

**If the scheduler is not running, neither behaviour occurs.** A deployment that
omits the scheduler will accumulate overdue notifications and stale import
files without any error being raised.

### 1.2 The production requirement

A single cron entry on the host is sufficient. It must run every minute.

```cron
* * * * * cd /var/www/wu-dms/server && php artisan schedule:run >> /dev/null 2>&1
```

> The `schedule:run` entry is required. The single-purpose alternative
> `php artisan dms:cleanup-import-uploads` may be scheduled directly, but the
> due-date command must then be scheduled separately, and the scheduler's
> `withoutOverlapping` protection is lost.

Verify that the scheduler is live:

```bash
cd server
php artisan schedule:list
```

### 1.3 The schedule is not a backup

The backup is a separate, explicit command (§3). It is deliberately **not**
scheduled by the application, because the retention window, the destination and
the credential of the backup destination are a deployment decision. The
application refuses to claim a backup it cannot prove.

---

## 2. Continuous integration

### 2.1 The workflow

The workflow is a GitHub Actions definition at `.github/workflows/ci.yml`.

| Aspect | Value |
|---|---|
| Repository platform | GitHub (the remote is `https://github.com/ATPLC/DMS.git`) |
| Triggers | `push` and `pull_request` on `main` and `develop` |
| Backend runtime | PHP 8.4 with the extensions required by the framework and the PDF and cryptography services |
| Front end runtime | Node.js 24, npm from the committed `package-lock.json` |
| Database service | MySQL 8.4 in a service container, with a health check and a disposable `wollo_dms_test` schema |
| Caching | The Composer vendor directory and the npm cache, both keyed on the committed lockfiles |
| Concurrency | One run per branch. A second run cancels the first. |
| Secrets | None. The MySQL service is created inside the runner, so no credential is stored in the repository. |
| Deployment | None. The pipeline verifies only; it never deploys. |

### 2.2 The commands the pipeline executes

Backend, in order:

```bash
composer validate --no-check-publish
find app bootstrap config database routes tests -name '*.php' -print0 | xargs -0 -n1 -P4 php -l
composer install --no-interaction --no-progress --prefer-dist
composer audit
php artisan route:list --except-vendor
php artisan migrate --force
php artisan test
```

Frontend, in order:

```bash
npm ci
node ../scripts/validate-ci-workflow.cjs
npx vue-tsc -b --noEmit
npm run test
npm run build
```

### 2.3 Why the suites are not run concurrently

The backend suite uses a single MySQL schema (`wollo_dms_test`) that is
refreshed per suite. Two database-backed suites running against the same
schema interfere with each other: one suite's schema refresh destroys the
other's tables mid-run, producing `table doesn't exist`, `1412 Table definition
has changed` and deadlock errors that are not real defects.

The pipeline therefore runs `php artisan test` as a single command, which
executes the suites sequentially, and the workflow is serialised per branch.
**Do not parallelise the suites**, and do not add a second database-backed job
that targets the same schema.

### 2.4 The integration check is a test

The compatibility between the front end and the back end is verified by
`server/tests/Feature/FrontendApiContractTest.php`. It reads every call that
`client/src/api/client.ts` declares and asserts that each one resolves to a
registered route, treating a template literal segment and a route parameter as
the same placeholder.

Because it runs inside the ordinary backend suite, a contract break fails the
same run that catches a behavioural regression. A contract break was found this
way in this phase: the OCR operations of the document detail screen called four
endpoints that did not exist.

### 2.5 Validating the workflow definition

```bash
node scripts/validate-ci-workflow.cjs
```

The validator asserts that every step declares a command or an action, that
PHP and Node are pinned, that the MySQL service uses the 8.4 image and has a
health check, and that the lockfile installs and the dependency audit are
present. It exits non-zero on a violation.

### 2.6 Required repository configuration

| Configuration | Why | Where |
|---|---|---|
| Repository actions enabled | The workflow does not run otherwise | Repository settings |
| The `main` branch protected | A direct push bypasses the pull-request run | Branch protection rules |
| `develop` created, or the trigger edited | The workflow triggers on it | Repository settings |
| Read access to the repository | Every step checks out the repository | Automatic for the default token |
| A MySQL 8.4 image available | The service container is pulled from Docker Hub | Docker Hub availability, not a repository secret |

No secret, key or credential is required by the workflow.

### 2.7 Failure behaviour

Any failed step fails its job and marks the workflow red. A failing pull
request run blocks a merge only if branch protection requires the check to
pass. The workflow reports the failing command's output verbatim; no step
suppresses an error.

---

## 3. Temporary dataset file lifecycle

### 3.1 The expiry policy

A dataset file that was uploaded for an import is **temporary only while the
operation that owns it is still waiting to be applied**.

| Operation state | The file | Reason |
|---|---|---|
| `uploaded`, `validated` | **Removed** once the retention window has passed | The batch was inspected and never applied, so the file is no longer needed and occupies private storage. |
| `importing` | Never touched | The operation is being executed. |
| `completed`, `completed_with_errors`, `failed` | Never touched | The source file is the only record of what was submitted, and it supports the audit trail. |
| `discarded` | The stale reference is cleared | The file was removed when the operation was discarded. The operation history is always preserved. |

The retention window defaults to **168 hours (7 days)** and is a runtime
setting: `data_imports.temporary_file_retention_hours`. It is adjustable
through the configuration catalogue in the administration interface and
overridable per run with `--hours`.

### 3.2 The command

```bash
cd server

# Report what would be removed.
php artisan dms:cleanup-import-uploads --dry-run

# Perform the cleanup.
php artisan dms:cleanup-import-uploads

# Use a different window for one run.
php artisan dms:cleanup-import-uploads --hours=24
```

The command reports the retention window, the number of eligible operations,
the number of files removed (or that would be removed), the number of expired
references cleared, the number of paths skipped and the number of failures. It
exits non-zero when a file could not be removed.

### 3.3 Safety properties

| Property | Behaviour |
|---|---|
| Scope | Only a stored path inside the `imports/` area of the private `local` disk may ever be removed. Any other path is reported and left untouched, whatever the operation claims. |
| Original documents | A registered university document is stored outside the import area and is never a candidate. |
| Idempotence | Running the command twice is safe; the second run reports zero removals. |
| Missing files | A reference to a file that has already gone is cleared, and the operation history is kept. |
| Failure isolation | A file that cannot be removed is reported, the run continues, and the command exits non-zero. |
| Audit | A run that removed at least one file records a `data.import.cleanup_completed` audit event with the window and the counts. |

---

## 4. Backup, restoration and the disaster recovery drill

The application provides the commands but not the schedule. The backup
destination, the retention window and the credential belong to the deployment.

```bash
php artisan dms:backup              # execute a real backup
php artisan dms:backup-status       # the recovery health, the recent backups, the drill status
php artisan dms:restore --help      # the restoration options
php artisan dms:dr-drill            # an isolated restoration drill with a SHA-256 integrity check
php artisan dms:backup-cleanup      # enforce the retention policy with the safety preservation locks
php artisan dms:storage-status      # the storage footprint and the disk health
```

The restoration drill restores into an isolated location and verifies the
integrity of the restored copy, so it is safe to run against a live system.

---

## 5. Environment configuration

The environment variables, their purpose and the values that must never be
exposed through an administrative interface are documented in
`docs/CONFIGURATION_CATALOG.md`. The rules that matter operationally:

- `APP_KEY` is generated once per environment with `php artisan key:generate`
  and is never stored in the database.
- The database credentials and the mail transport are environment values, never
  runtime settings.
- `MAIL_MAILER=log` is the default in `.env.example`. It writes the recovery
  and confirmation links to the log instead of delivering them. **Set a real
  transport for any deployment where account recovery must work.**

---

## 6. Verification of the operational arrangements

| Check | Command |
|---|---|
| The scheduler is registered | `php artisan schedule:list` |
| The cleanup is safe | `php artisan dms:cleanup-import-uploads --dry-run` |
| The workflow definition is valid | `node scripts/validate-ci-workflow.cjs` |
| The route table is intact | `php artisan route:list --except-vendor` |
| The migrations apply | `php artisan migrate --status` |
| The dependencies are clean | `composer audit` |
| The operational queries use indexes | `php artisan dms:profile-queries` |
