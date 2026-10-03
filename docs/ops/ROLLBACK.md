# Rollback plan

Before release, record the application image/version and digest, migration batch, previous
frontend assets, backup path/checksum, restore-drill result, decision owner and change window.

For an application or asset regression, stop the cutover or route traffic away, restore the
previous immutable app/Nginx images and their matching built assets, clear and rebuild Laravel
caches, then run the readiness command and smoke-test health, public calculation, Member history
and admin authorization. Record the trigger, operator, timestamps and verification result.

Database rollback requires review of the exact migration. Prefer a forward corrective migration. Run `php artisan migrate:rollback --step=1 --force` only when the migration's `down()` is proven safe and no later code or data depends on it. The M10 migration removes only its audit query index. Never casually delete or rewrite published rule versions, Member returns, calculations, scenarios, or historical snapshots. Use the verified backup restore procedure when a safe schema rollback is impossible.

The rollback decision gate is failed readiness, failed supported-path smoke, data-integrity
evidence, or an operator-approved availability incident. A guarded unsupported tax input is not a
rollback trigger when it returns its documented controlled response.
