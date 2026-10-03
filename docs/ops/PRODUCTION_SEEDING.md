# Production seeding

`DatabaseSeeder` runs `ReferenceDataSeeder` and the read-only `TaxBaselineSeeder` outside `local`. Reference data includes tax year, forms, mappings, allowance masters, source-backed rule rows, and source registry records. `TaxBaselineSeeder` verifies published version `2568.1`; it does not replace it.

```sh
php artisan migrate --force
php artisan db:seed --force
```

`CmsInitialDataSeeder` is optional and must be invoked explicitly if the initial public content is approved. Never run `DevelopmentAccountSeeder` or `DevelopmentDemoDataSeeder` in production. Never seed test factories, synthetic Members, or demo returns. Take a backup first and verify version `2568.1` remains published and unchanged afterward.

Run `php artisan ops:production-readiness --repository-only` after seeding. Draft `2568.2`, when
present, must remain `draft` and must not replace the sole published baseline. Provision the first
administrator as a named, individually owned account through the normal registration/account
process, then have an authorized database operator assign the existing admin role in a recorded
change. Require a unique password reset and revoke any bootstrap credential immediately. Do not
create a shared or demo admin and do not place a password in a seeder, command history, or this
document.
