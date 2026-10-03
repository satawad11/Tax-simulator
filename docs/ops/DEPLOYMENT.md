# Controlled deployment

The repository Compose file is a local development and verification stack; Docker Desktop and the Vite development process are not production infrastructure. Production needs an orchestrator or host configuration that runs immutable app and Nginx images, persistent MySQL, TLS termination, restricted network access, durable logs, and monitored backups.

1. Build from the lockfiles and run the full SQLite/MySQL/Pint/frontend/browser checks.
2. Create `.env` from `.env.example`; use unique secrets, `APP_ENV=production`, `APP_DEBUG=false`, an HTTPS `APP_URL`, secure database credentials, `SESSION_SECURE_COOKIE=true`, and the deployed domains.
3. Back up MySQL and verify the backup.
4. Build frontend assets with `npm ci && npm run build` and immutable application images.
5. Run `php artisan ops:production-readiness --repository-only`. Put the service in maintenance mode if the migration requires it, then run `php artisan migrate --force` and the production-safe seeding procedure.
6. Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache` in the final environment.
7. Start PHP-FPM and Nginx behind HTTPS. Trust forwarded headers only from the deployment's known reverse proxy addresses.
8. Run `php artisan ops:production-readiness` in the final environment. Verify `/api/v1/health`, security headers, the published `2568.1` baseline, public PND90/PND91 calculations, Member ownership, admin denial/authorization, and frontend assets.
9. Monitor 5xx/429 rates, authentication failures, calculation exceptions, publish actions, DB health, latency and disk/backup failures.

The health route is liveness only and exposes no database diagnostics. Use the MySQL
container/orchestrator health check as readiness. Do not enable traffic until the full readiness
command and smoke checks pass. Follow `ROLLBACK.md` if any gate fails.
