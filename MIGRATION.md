# Production Migration Guide

This project includes tooling for migrating from a legacy Leantime deployment to the new target environment.

## Files and Credentials

- Preferred legacy source env vars: `LEAN_OLD_DB_HOST`, `LEAN_OLD_DB_USER`, `LEAN_OLD_DB_PASSWORD`, `LEAN_OLD_DB_DATABASE`, `LEAN_OLD_DB_PORT`
- Preferred legacy FTP env vars: `FTP_OLD_HOST`, `FTP_OLD_USER`, `FTP_OLD_PASS` (`FTP_OLD_PORT` optional)
- Target runtime env: `LEAN_DB_*`, `LEAN_USE_S3=true`, `LEAN_S3_*`
- Optional fallback files: `artifacts/.prod.env`, `artifacts/ftp`

## 1) Database Migration

### Fastest path when SQL dump file is already local

Dry run:

```bash
php bin/leantime migration:import-sql --file=artifacts/u706165963_bfil_leantime.sql --dry-run
```

Execute:

```bash
php bin/leantime migration:import-sql --file=artifacts/u706165963_bfil_leantime.sql
php bin/leantime db:migrate
```

### SQL dump stored in S3 (no local file required)

Dry run:

```bash
php bin/leantime migration:import-sql --s3-key=bkup/u706165963_bfil_leantime.sql --dry-run
```

Execute:

```bash
php bin/leantime migration:import-sql --s3-key=bkup/u706165963_bfil_leantime.sql
php bin/leantime db:migrate
```

### Linux target server (recommended)

Dry run:

```bash
bash bin/prod-cutover.sh
```

Execute:

```bash
bash bin/prod-cutover.sh --execute
```

### Windows operator machine

Dry run:

```powershell
powershell -File bin/prod-cutover.ps1
```

Execute:

```powershell
powershell -File bin/prod-cutover.ps1 -Execute
```

What this does:

1. Dumps source DB from `LEAN_OLD_DB_*` (or fallback `artifacts/.prod.env`).
2. Imports into target DB from current `LEAN_DB_*`.
3. Runs `php bin/leantime db:migrate`.

## 2) Legacy Files to S3

### Fastest path when files are already local

Dry run:

```bash
php bin/leantime migration:sync-local-to-s3 --source=artifacts/userfiles --dry-run
```

Execute:

```bash
php bin/leantime migration:sync-local-to-s3 --source=artifacts/userfiles
```

Optional safer upload:

```bash
php bin/leantime migration:sync-local-to-s3 --source=artifacts/userfiles --db-only
```

Preferred method (external runner with `lftp` + `awscli`):

Dry run:

```bash
bash bin/file-cutover.sh
```

Execute:

```bash
bash bin/file-cutover.sh --execute
```

Options:

- `--remote-path userfiles` FTP path on legacy server.
- `--local-dir ./artifacts/migration/userfiles-mirror` local staging.
- `--skip-download` re-use existing local mirror.
- `--skip-upload` download only.
- `--verify-only` run count checks only.

Command-based method (runs in app context, requires PHP `ext-ftp`):

Dry run:

```bash
php bin/leantime migration:sync-ftp-to-s3 --dry-run --remote-path=userfiles
```

Execute sync:

```bash
php bin/leantime migration:sync-ftp-to-s3 --remote-path=userfiles
```

Useful options:

- `--db-only` only uploads files referenced in `zp_file`.
- `--limit=100` canary run.
- `--preserve-path` keep remote folder structure in S3 keys.
- `--ftp-file=artifacts/ftp` fallback if env vars are not set.

## 3) Recommended Cutover Order

1. Put legacy app into a short maintenance/read-only window.
2. Run final DB migration (`bin/prod-cutover.ps1 -Execute`).
3. Run FTP to S3 sync command.
4. Smoke test target app (login, projects, tickets, file download).
5. Switch DNS for `pm.bluefission.com`.
6. Keep rollback option by preserving old environment briefly.

## 4) Rollback

- Re-point DNS to old environment.
- Keep the latest DB dump for diff/retry.
