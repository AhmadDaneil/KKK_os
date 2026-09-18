# Production Database Backup Service V1

## Decision and scope

KKK OS creates one full database snapshot every day and keeps rolling restore points for 30 days. A backup is successful only after the same artifact and manifest have been written to, read back from, and SHA-256 verified on exactly two distinct Laravel filesystem disks.

This service backs up the application database only. Private customer uploads, artwork, generated PSD/JPEG files, and other private storage require a separate backup process with its own schedule, retention, verification, and restore drill. They are intentionally not included in the database archive.

No backup provider has been selected. The Project Owner must approve vendor, cost, credentials, regions, and the two physical locations. Until then, `BACKUP_ENABLED` must remain `false`. The application contract accepts any two Laravel filesystem disks, but production must not point both disk names to the same physical storage account, server, volume, or failure domain.

## Components

- `backup:database` creates a native full snapshot, compresses it, replicates it to two disks, verifies byte length and SHA-256, writes a versioned manifest, and then applies retention.
- `backup:verify [backupId]` verifies the requested backup, or the latest backup present in both locations.
- The scheduler runs `backup:database --isolated` daily at `BACKUP_DAILY_AT` in the production environment. Laravel overlap and one-server locks are also enabled.
- MySQL and MariaDB use `mysqldump`; PostgreSQL uses `pg_dump`; SQLite uses a consistent `VACUUM INTO` snapshot. Unsupported database drivers fail closed.

The MySQL dump includes the selected database, routines, triggers, events, and binary-safe data. PostgreSQL produces a plain SQL dump with clean statements and without restoring object ownership or privileges. Temporary database credentials are written to a mode `0600` client file and deleted after the native process finishes; passwords are not placed in process arguments.

## Configuration

Set these environment values through the production secret/configuration system. Do not commit populated production environment files.

```dotenv
BACKUP_ENABLED=false
BACKUP_DB_CONNECTION=mysql
BACKUP_DATABASE_DISKS=backup_primary,backup_secondary
BACKUP_DAILY_AT=02:00
BACKUP_RETENTION_DAYS=30
BACKUP_PROCESS_TIMEOUT_SECONDS=3600
BACKUP_MYSQLDUMP_BINARY=mysqldump
BACKUP_PG_DUMP_BINARY=pg_dump
```

`backup_primary` and `backup_secondary` are logical names. Define both in `config/filesystems.php` only after the providers are approved. Both disks must be private and configured to throw write/read errors. Confirm provider-side encryption at rest, restricted service credentials, access logging, object visibility, and recovery access before enabling the schedule.

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
2. Copy the artifact and its manifest from one verified private location into a restricted recovery workspace. Recalculate SHA-256 and compare it with the manifest after transfer.
3. Restore into an isolated staging database first. Do not restore directly over production. Use credentials supplied through a protected client configuration or secret store, never a password in command history.
4. Decompress the `.gz` artifact. For MySQL or MariaDB, import the SQL with the matching `mysql` client. For PostgreSQL, import it with `psql` using `ON_ERROR_STOP=1`. For SQLite, stop all writers and restore the decompressed database file to a staging copy.
5. Run migrations only if the application release being tested requires them. Prefer restoring with the application release that produced the backup before attempting an upgrade.
6. Validate table counts, recent known orders, order relationships, staff-independent customer access behavior, one-package and two-package orders, and a Photoshop CSV export from representative restored data.
7. Record the backup ID, source location, checksum, database client versions, validation evidence, duration, and any errors in the restore-drill record.
8. Only after staging validation and explicit incident approval may the production database be placed in maintenance mode and replaced using the approved incident runbook. Preserve the pre-restore production database as a rollback snapshot.
9. After production recovery, run application smoke tests and start a new supervised backup. Do not remove the incident restore point as part of normal cleanup until the incident is closed.

## Known limitations and pending decisions

- Backup vendors, cost, credentials, regions, storage classes, and the second failure domain are pending Project Owner approval. V1 does not invent these decisions.
- The service does not back up private uploads or artwork. A separate private-storage backup service remains required before go-live.
- Integrity verification proves that stored bytes match the manifest; it does not prove that SQL is logically restorable. A supervised restore drill is required before go-live and should be repeated on a defined operational schedule.
- Application-level archive encryption is not implemented. Production enablement is blocked until both approved destinations provide verified encryption at rest and access controls, or the Project Owner approves a separate application-level encryption design and key-recovery procedure.
- Native database client binaries must be installed and compatible with the production database server. Their paths are configuration values, not hardcoded assumptions.
- MySQL `--single-transaction` consistency applies to transactional tables. If production introduces non-transactional tables, the snapshot strategy must be reviewed.
- Retention intentionally keeps orphaned or inconsistent backups instead of deleting the only surviving copy. Operators must investigate and reconcile those warnings manually.
- Provider object-lock, immutability, cross-account recovery, and lifecycle policies are not configured until providers are selected.

## Test coverage

Automated tests cover successful replication and verification in two locations, rolling 30-day retention, retention safety when the second destination fails, rejection of duplicate disk configuration, checksum corruption detection, command wiring, and the daily production schedule. Native `mysqldump` and `pg_dump` execution must additionally be exercised in the production-like restore drill because the test suite does not depend on external database client binaries.
