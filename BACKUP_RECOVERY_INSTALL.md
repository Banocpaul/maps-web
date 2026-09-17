# M.A.P.S. Backup & Recovery Installation

This module is restricted to active users with the `administrator` role.

## 1. Install the S3-compatible filesystem adapter

Run this once from the Laravel project directory:

```powershell
composer require league/flysystem-aws-s3-v3 --with-all-dependencies
```

## 2. Generate the backup encryption key

Run:

```powershell
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Copy the printed value. Keep it in a password manager. If this key is lost,
encrypted backups cannot be restored.

For local testing, add these values to `.env`:

```dotenv
BACKUP_DISK=backups
BACKUP_FILESYSTEM_DRIVER=local
BACKUP_ENCRYPTION_KEY=PASTE_THE_GENERATED_VALUE
BACKUP_DIRECTORY=database-backups
BACKUP_TIMEOUT_SECONDS=600
BACKUP_RETENTION_DAILY=7
BACKUP_RETENTION_WEEKLY=4
BACKUP_RETENTION_MONTHLY=6
```

Local backups are stored under `storage/app/backups`. Local storage is only
for development and is not safe as the only Render backup destination.

The TiDB dump deliberately uses `--skip-lock-tables` and `--skip-add-locks`
without MariaDB's `--single-transaction` option. MariaDB's dump client issues
savepoint commands for that option, and TiDB rejects its savepoint rollback.
Schedule production backups during the lowest-write period (02:00 Manila).

## 3. Run the migration and tests

```powershell
php artisan optimize:clear
php artisan migrate
php artisan test --filter=DatabaseBackupAuthorizationTest
php artisan test --filter=BackupFileCipherTest
```

## 4. Test a local encrypted backup

```powershell
php artisan maps:backup:create --type=manual
php artisan maps:backup:verify
```

Sign in as an Administrator and open **Administration > Backup & Recovery**.

## 5. Configure Cloudflare R2

Create a private R2 bucket named `maps-database-backups`, then create an R2 API
token with object read/write permission for only that bucket.

Add the following environment variables to the Render web service and Render
Cron Job:

```dotenv
BACKUP_DISK=backups
BACKUP_FILESYSTEM_DRIVER=s3
BACKUP_ENCRYPTION_KEY=THE_SAME_BASE64_KEY_USED_LOCALLY
BACKUP_DIRECTORY=database-backups
BACKUP_TIMEOUT_SECONDS=600
BACKUP_RETENTION_DAILY=7
BACKUP_RETENTION_WEEKLY=4
BACKUP_RETENTION_MONTHLY=6

BACKUP_AWS_ACCESS_KEY_ID=YOUR_R2_ACCESS_KEY
BACKUP_AWS_SECRET_ACCESS_KEY=YOUR_R2_SECRET_KEY
BACKUP_AWS_DEFAULT_REGION=auto
BACKUP_AWS_BUCKET=maps-database-backups
BACKUP_AWS_ENDPOINT=https://YOUR_ACCOUNT_ID.r2.cloudflarestorage.com
BACKUP_AWS_USE_PATH_STYLE_ENDPOINT=false
```

Do not add an R2 public domain. The bucket must stay private.

## 6. Create the Render Cron Job

Use the same GitHub repository and branch as the web service.

- Schedule: `0 18 * * *`
- Time: 18:00 UTC equals 02:00 Asia/Manila the following day
- Command:

```bash
php artisan maps:backup:create --type=scheduled && php artisan maps:backup:prune
```

Copy the database, backup, `APP_KEY`, and other required Laravel environment
variables from the web service into the Cron Job through a Render Environment
Group.

## 7. Restore procedure

First list the backup UUIDs in the administrator page. Open a Render Shell on
the web service and run:

```bash
php artisan maps:backup:restore BACKUP_UUID
```

Type `RESTORE-MAPS` when prompted. The command will:

1. create a pre-restore safety backup;
2. verify the requested backup checksum and encryption;
3. put Laravel into maintenance mode;
4. restore the SQL dump into TiDB;
5. return Laravel to normal operation; and
6. preserve both backup history records.

For non-interactive emergency use:

```bash
php artisan maps:backup:restore BACKUP_UUID --confirm=RESTORE-MAPS
```

Do not use `--skip-safety-backup` in production unless the current database is
already unavailable and a safety backup cannot be created.

## 8. Commit and deploy

```powershell
git add composer.json composer.lock app config database resources routes tests Dockerfile .env.example BACKUP_RECOVERY_INSTALL.md
git commit -m "Add administrator backup and recovery module"
git push origin main
```
