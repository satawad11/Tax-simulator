# MySQL backup and restore

Run backups before every deployment and migration. Store the resulting SQL file outside the host and protect it as Member financial data.

```powershell
docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --single-transaction --routines --triggers --set-gtid-purged=OFF "$MYSQL_DATABASE"' > tax-simulator-backup.sql
```

Verify that the command succeeded and the file is non-empty. A restore drill must use a unique,
empty database whose name is recorded before creation, for example
`tax_simulator_restore_YYYYMMDDHHMMSS`. Confirm the name does not already exist, create it, and
restore into that exact name:

```powershell
$restoreDatabase = 'tax_simulator_restore_YYYYMMDDHHMMSS'
if ($restoreDatabase -notmatch '^tax_simulator_restore_[0-9]{14}$') { throw 'Unsafe restore database name' }
$existing = docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -N -uroot -e "SHOW DATABASES LIKE ''$1''"' sh $restoreDatabase
if ($existing) { throw 'Restore database already exists' }
docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot -e "CREATE DATABASE $1 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"' sh $restoreDatabase
Get-Content tax-simulator-backup.sql -Raw | docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot "$1"' sh $restoreDatabase
docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot "$1" -e "SELECT COUNT(*) FROM migrations; SELECT version,status FROM tax_rule_versions ORDER BY id"' sh $restoreDatabase
```

Drop only the exact drill database after its results are recorded; validate that its name starts
with `tax_simulator_restore_` before dropping it. Never point a restore drill at the active
database. After a real restore, verify `/api/v1/health`, the published `2568.1` rule version, an
authorized Member history record, and an admin read-only page. Do not use `migrate:fresh` as a
restore method.
