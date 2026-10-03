# MILESTONE_01_PROMPT.md

## Codex Task: Milestone 01 — Infrastructure Foundation

You are working on the project:

**Thai Personal Income Tax Simulation Platform**

Before changing any file, you MUST read:

```text
PROJECT_REQUIREMENTS.md
CODING_RULES.md
```

Treat those files as the source of truth for this project.

Also check for this UI reference:

```text
docs/ui-reference/tax-simulator-mockup.png
```

The UI reference is already approved. Do not redesign the product.

---

# Objective

Create the initial Docker-based Laravel development environment and minimum application skeleton only.

This milestone is infrastructure-focused.

Do NOT implement tax calculation business rules yet.

---

# Required Stack

Use exactly:

- Laravel
- PHP 8.4
- MySQL 8.4
- Nginx
- Laravel Sanctum
- Blade
- Tailwind CSS
- JavaScript ES Modules
- Docker Compose

Do not replace the stack with:

- React
- Vue
- Svelte
- Next.js
- Nuxt
- PostgreSQL
- SQLite for the main application
- Apache

unless explicitly approved.

---

# Architecture

The application is API-first.

API base path:

```text
/api/v1
```

The same Laravel backend will serve:

1. Web Application
2. Future Mobile Application

Do not place tax business logic in JavaScript.

Do not place tax business logic in Controllers.

---

# Deliverables

## 1. Laravel Project

If the repository is empty, create/install a Laravel application in the current project directory without creating an unnecessary nested project directory.

Confirm the Laravel version and PHP compatibility.

---

## 2. Docker Configuration

Create:

```text
Dockerfile
docker-compose.yml
docker/nginx/default.conf
.dockerignore
```

Services:

```text
app
nginx
mysql
```

### app

Must support:

- PHP 8.4
- Composer
- required Laravel PHP extensions
- Artisan
- development workflow

### nginx

Must:

- serve Laravel from `/public`
- forward PHP requests to app container
- expose the web app on host port:

```text
8080
```

unless port 8080 is already occupied. If occupied, report it before choosing an alternative.

### mysql

Use:

- MySQL 8.4
- persistent named volume
- database name: `tax_simulator`
- development username: `tax_user`
- development password through environment variables

Do not hard-code production secrets.

---

# 3. Environment Files

Create/update:

```text
.env.example
```

with Docker-friendly defaults.

Required variables should include at least:

```env
APP_NAME="Tax Simulator"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=tax_simulator
DB_USERNAME=tax_user
DB_PASSWORD=tax_password
```

Do not commit `.env`.

If `.env` does not exist for local execution, create it from `.env.example` during setup only.

Generate application key.

---

# 4. Laravel Sanctum

Install/configure Laravel Sanctum if required by the installed Laravel version.

Do not build the complete authentication module in this milestone.

Only prepare the application so Sanctum can be used in later milestones.

---

# 5. API Route Foundation

Ensure Laravel API routing exists.

Create:

```text
GET /api/v1/health
```

Expected HTTP status:

```text
200
```

Expected response:

```json
{
  "success": true,
  "message": null,
  "data": {
    "status": "ok",
    "service": "tax-simulator-api"
  }
}
```

Do not return Laravel debug details.

---

# 6. Web Route Foundation

Create a minimal public home page at:

```text
GET /
```

The page is temporary infrastructure UI, but must already follow the approved visual direction:

- blue and white
- clean layout
- rounded cards
- modern spacing
- Tailwind CSS
- responsive
- not an admin-dashboard visual style

Display:

```text
ระบบจำลองการยื่นภาษีเงินได้บุคคลธรรมดา
```

and a short message:

```text
ระบบกำลังอยู่ระหว่างการพัฒนา
```

Include navigation placeholders:

```text
หน้าแรก
ทดลองยื่นภาษี
ความรู้ภาษี
ข่าวสาร
เกี่ยวกับเรา
เข้าสู่ระบบ
```

Do not implement the full approved mockup in Milestone 01.

This page only verifies Blade + Tailwind + JavaScript pipeline while preserving the visual direction.

---

# 7. Tailwind CSS

Set up Tailwind CSS for Laravel.

Ensure production build works.

Use project-local Node dependencies.

Do not use CDN Tailwind for the final project setup.

---

# 8. JavaScript

Configure JavaScript using ES Modules / Vite.

Create a basic module structure if useful:

```text
resources/js/
├── app.js
├── api/
└── components/
```

Do not add a frontend framework.

---

# 9. Initial Code Structure

Create empty/placeholder folders if needed to establish architecture:

```text
app/DTO/Tax
app/Services/Tax
```

Do not create fake tax calculation implementation.

You may create README placeholders in empty architecture directories only if required to keep them in version control.

---

# 10. Database

Run default Laravel migrations successfully against MySQL.

Do not create the tax domain migrations yet.

Those belong to Milestone 02.

---

# 11. Health Test

Add a Feature Test for:

```text
GET /api/v1/health
```

The test must verify:

- HTTP 200
- `success === true`
- `data.status === "ok"`

---

# 12. Home Page Test

Add a basic Feature Test for:

```text
GET /
```

Verify:

- HTTP 200
- page contains Thai project title

---

# 13. Docker Verification

Run:

```bash
docker compose config
```

Then build:

```bash
docker compose up -d --build
```

Wait for MySQL to become ready.

Then run inside the app container:

```bash
php artisan migrate
php artisan test
```

Use the correct Docker Compose command form for this project, e.g.:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan test
```

---

# 14. Frontend Verification

Run:

```bash
npm install
npm run build
```

If Node runs inside a container, document the exact command used.

The milestone is not complete if the frontend build fails.

---

# 15. Verify URLs

Verify:

```text
http://localhost:8080/
```

and:

```text
http://localhost:8080/api/v1/health
```

If command-line HTTP tooling is available, test the health endpoint.

---

# 16. Do Not Implement Yet

Do NOT implement these in Milestone 01:

- PND90 calculation
- PND91 calculation
- tax brackets
- tax years domain
- tax forms domain
- income rules
- expense rules
- allowance rules
- donation rules
- recommendation rules
- tax planning
- refund engine
- member Tax Return CRUD
- complete authentication flow
- admin CMS
- news CMS
- article CMS
- full public UI

These belong to later milestones.

---

# 17. Do Not Redesign the UI

The approved design direction is represented by:

```text
docs/ui-reference/tax-simulator-mockup.png
```

Rules:

- do not replace blue/white theme
- do not use dark theme
- do not turn the public site into an admin dashboard
- do not replace the approved navigation structure
- do not introduce a completely different visual identity

If the reference image is missing, continue only with the limited temporary M1 page described above and report:

```text
UI reference file missing from repository.
```

Do not invent a replacement design.

---

# 18. Error Handling

If Docker Desktop is not running or Docker daemon is unavailable:

1. Do not pretend tests succeeded.
2. Report the exact command that failed.
3. Explain the required user action briefly.
4. Continue with file creation/static validation only where safe.

If a port is occupied, report it.

If Composer/npm installation fails, report the actual error.

Never claim success for commands that were not executed successfully.

---

# 19. Git

Do not delete existing user files.

Before major changes, inspect:

```bash
git status
```

Do not force-reset the repository.

Do not run destructive Git commands.

Do not commit unless explicitly requested.

---

# 20. Completion Criteria

Milestone 01 is complete only if all applicable checks pass:

```text
[ ] Laravel boots
[ ] Docker config validates
[ ] app container runs
[ ] nginx container runs
[ ] mysql container runs
[ ] Laravel connects to MySQL
[ ] migrations pass
[ ] GET / returns 200
[ ] GET /api/v1/health returns 200
[ ] php artisan test passes
[ ] npm build passes
[ ] Tailwind renders correctly
```

If Docker cannot be executed due to the user's environment, clearly distinguish:

```text
FILES PREPARED
```

from:

```text
RUNTIME VERIFIED
```

---

# 21. Required Final Report

At the end, provide a concise report with exactly these sections:

## Summary

What was completed.

## Files Created

List files.

## Files Modified

List files.

## Docker Services

Show status of:

- app
- nginx
- mysql

## Routes

Show:

```text
GET /
GET /api/v1/health
```

## Commands Executed

List actual commands that were run.

## Test Results

Show:

- Laravel tests
- frontend build
- migration result
- health endpoint result

## Known Issues

List unresolved issues, or state:

```text
None
```

## UI Reference Status

State whether:

```text
docs/ui-reference/tax-simulator-mockup.png
```

was found.

## Next Step

Recommend:

```text
Milestone 02 — Database Foundation:
migrations + Eloquent models + relationships + seeders
```

Do not begin Milestone 02 until approved.
