# Production Backup Service V1

## Decision and scope

KKK OS creates one full database snapshot every day and keeps rolling restore points for 30 days. A backup is successful only after the same artifact and manifest have been written to, read back from, and SHA-256 verified on exactly two distinct Laravel filesystem disks.

Database snapshots and application files are separate artifacts so either layer can be restored independently. Application files include the Laravel `local` and `public` disk roots under `storage/app/private` and `storage/app/public`.

The primary destination is a protected VPS directory. The physically separate secondary destination is the private Cloudflare R2 bucket `kkk-os-v1-backup`, accessed with bucket-restricted S3 credentials stored only in the permission-restricted production environment file. Until both database and application-file artifacts pass a supervised restore test, `BACKUP_ENABLED` must remain `false`.

## Components

- `backup:database` creates a native full snapshot, compresses it, replicates it to two disks, verifies byte length and SHA-256, writes a versioned manifest, and then applies retention.
- `backup:verify [backupId]` verifies the requested backup, or the latest backup present in both locations.
- `backup:files` archives private uploads and generated/public files, replicates the archive to both locations, verifies byte length and SHA-256, writes a versioned manifest, applies retention, and logs the outcome.
- The scheduler runs `backup:database --isolated` daily at `BACKUP_DAILY_AT` in the production environment. Laravel overlap and one-server locks are also enabled.
- The scheduler runs `backup:files --isolated` at `BACKUP_FILES_DAILY_AT`, after the database backup window.
- MySQL and MariaDB use `mysqldump`; PostgreSQL uses `pg_dump`; SQLite uses a consistent `VACUUM INTO` snapshot. Unsupported database drivers fail closed.

The MySQL dump includes the selected database, routines, triggers, events, and binary-safe data. PostgreSQL produces a plain SQL dump with clean statements and without restoring object ownership or privileges. Temporary database credentials are written to a mode `0600` client file and deleted after the native process finishes; passwords are not placed in process arguments.

## Configuration

Set these environment values through the production secret/configuration system. Do not commit populated production environment files.

```dotenv
BACKUP_ENABLED=false
BACKUP_DB_CONNECTION=mysql
BACKUP_DATABASE_DISKS=backup_primary,backup_secondary
BACKUP_PRIMARY_ROOT=/var/backups/kkk-os
BACKUP_SECONDARY_ACCESS_KEY_ID=
BACKUP_SECONDARY_SECRET_ACCESS_KEY=
BACKUP_SECONDARY_REGION=
BACKUP_SECONDARY_BUCKET=
BACKUP_SECONDARY_ENDPOINT=
BACKUP_SECONDARY_USE_PATH_STYLE_ENDPOINT=false
BACKUP_SECONDARY_ROOT=kkk-os
BACKUP_DAILY_AT=02:00
BACKUP_RETENTION_DAYS=30
BACKUP_PROCESS_TIMEOUT_SECONDS=3600
BACKUP_MYSQLDUMP_BINARY=mysqldump
BACKUP_PG_DUMP_BINARY=pg_dump
BACKUP_FILES_DAILY_AT=02:30
BACKUP_FILES_RETENTION_DAYS=30
BACKUP_FILES_TEMPORARY_DIRECTORY=
```

`backup_primary` is a private local disk rooted at `/var/backups/kkk-os`. The directory must be owned by the application service account and must not be under the web root. `backup_secondary` is private S3-compatible object storage using dedicated least-privilege credentials and the `kkk-os` object prefix. Both disks throw write/read errors. Confirm provider-side encryption at rest, restricted service credentials, access logging, object visibility, and recovery access before enabling the schedule.

`BACKUP_DAILY_AT` is interpreted in the Laravel application timezone. Confirm the production timezone is `Asia/Kuala_Lumpur` before treating `02:00` as 2:00 AM Malaysia time.

The server must execute Laravel's scheduler every minute. If more than one application server runs the scheduler, all nodes must share a cache store that supports atomic locks. The current database cache store can satisfy this when all nodes use the same production database.

After configuration is cached, validate the production contract without creating a backup:

```text
php artisan config:show backup
php artisan schedule:list
```

Then perform the first supervised run:

```text
php artisan backup:database --force --isolated
php artisan backup:verify
php artisan backup:files --force --isolated
```

Remove `--force` after `BACKUP_ENABLED=true` is deployed. A successful command identifies the backup ID, checksum, size, and both logical disk names without printing credentials.

## Failure behavior

Snapshot creation failure writes nothing to either destination and does not run retention.

If either destination cannot write or read back the artifact or manifest, the run fails, retention does not run, and the service attempts to remove the new partial backup from every destination touched by that run. Existing restore points are not deleted.

Retention begins only after the new backup has been verified in both locations. It deletes backups older than the exact 30-day cutoff only when matching V1 manifests exist in both locations and agree on the artifact path and checksum. An orphaned copy, malformed manifest, or cross-location mismatch is retained for operator investigation. A retention error returns a failed command status for monitoring, but the newly verified backup remains available.

The scheduled command is isolated for up to six hours and uses a six-hour scheduler overlap lock. Failed runs must alert an operator. Do not treat a scheduler invocation alone as proof of a usable backup; monitor the command exit status and run `backup:verify` regularly.

## Restore procedure

Restoration is deliberately not automated because it is destructive and must be supervised.

1. Identify the required restore point and run `php artisan backup:verify BACKUP_ID`. Stop if either location fails verification.
2. Copy the database artifact, application-files artifact, and their manifests from one verified private location into a restricted recovery workspace. Recalculate SHA-256 and compare each artifact with its manifest after transfer.
3. Restore into an isolated staging database first. Do not restore directly over production. Use credentials supplied through a protected client configuration or secret store, never a password in command history.
4. Decompress the `.gz` artifact. For MySQL or MariaDB, import the SQL with the matching `mysql` client. For PostgreSQL, import it with `psql` using `ON_ERROR_STOP=1`. For SQLite, stop all writers and restore the decompressed database file to a staging copy.
5. Run migrations only if the application release being tested requires them. Prefer restoring with the application release that produced the backup before attempting an upgrade.
6. Extract the application-files `.tar.gz` only into an isolated recovery directory. Confirm archive entries remain under `private/` or `public/`, compare representative restored files with their source checksums, and never extract directly over production during a drill.
7. Validate table counts, recent known orders, order relationships, staff-independent customer access behavior, one-package and two-package orders, and a Photoshop CSV export from representative restored data.
8. Record both backup IDs, source location, checksums, database client versions, validation evidence, duration, and any errors in the restore-drill record.
9. Only after staging validation and explicit incident approval may production data be replaced using the approved incident runbook. Preserve the pre-restore production state as a rollback snapshot.
10. After production recovery, run application smoke tests and start a new supervised backup. Do not remove the incident restore points as part of normal cleanup until the incident is closed.

## Known limitations and pending decisions

- Cloudflare R2 connectivity and bucket-scoped credentials are configured, but automated production activation remains blocked until the restore drill succeeds.
- Integrity verification proves that stored bytes match the manifest; it does not prove that SQL is logically restorable. A supervised restore drill is required before go-live and should be repeated on a defined operational schedule.
- Application-level archive encryption is not implemented. Production enablement is blocked until both approved destinations provide verified encryption at rest and access controls, or the Project Owner approves a separate application-level encryption design and key-recovery procedure.
- Native database client binaries must be installed and compatible with the production database server. Their paths are configuration values, not hardcoded assumptions.
- MySQL `--single-transaction` consistency applies to transactional tables. If production introduces non-transactional tables, the snapshot strategy must be reviewed.
- Retention intentionally keeps orphaned or inconsistent backups instead of deleting the only surviving copy. Operators must investigate and reconcile those warnings manually.
- Provider object-lock, immutability, cross-account recovery, and lifecycle policies remain pending until the S3-compatible provider is selected.

## Test coverage

Automated tests cover database replication and verification, application-file archiving of both private and public roots, rolling 30-day retention, retention safety when the second destination fails, rejection of duplicate disk configuration, checksum corruption detection, command wiring, and both daily production schedules. Native database clients and archive extraction must additionally be exercised in the production-like restore drill.
