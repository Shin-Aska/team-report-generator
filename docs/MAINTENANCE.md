# Maintenance & Operations Guide

This guide complements the developer-focused instructions in `src/README.md` and captures the day-to-day upkeep expected for deployed instances of the Report Generator Server. Follow these practices for each team-specific deployment described in [docs/MULTI_TEAM_SETUP.md](./MULTI_TEAM_SETUP.md).

---

## Routine operations

### Configuration caches
- After adjusting `.env` values or config files, rebuild caches per instance:
  ```bash
  php artisan config:clear
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  ```
- Run the commands inside each team’s `src/` directory so cached data reflects the correct database and LLM credentials.

### Database migrations & seeds
- Apply schema changes with production flags to avoid interactive prompts:
  ```bash
  php artisan migrate --force
  php artisan db:seed --force
  ```
- For multi-team deployments, repeat the commands for every duplicate `src/` folder to keep schemas aligned.
- If a migration fails because tables already exist, prefer corrective migrations over resetting data. As a last resort, `php artisan migrate:fresh --seed` will rebuild the schema but wipes data—use only when explicitly approved.

### Scheduler & queues
- Ensure a cron entry triggers Laravel’s scheduler every minute:
  ```cron
  * * * * * php /path/to/team-alpha/src/artisan schedule:run >> /dev/null 2>&1
  ```
- Run queue workers per team instance. On shared hosts without daemons, schedule `php artisan queue:work --once` via cron; on VPS/cloud environments, supervise long-lived workers with `supervisor`, `systemd`, or similar.

### Logs & storage
- Monitor `storage/logs/laravel.log` for each deployment and rotate or archive logs to avoid disk exhaustion.
- Keep `storage/` and `bootstrap/cache/` writable (`chmod -R 775 storage bootstrap/cache`) but avoid world-writable permissions.
- Back up database snapshots and any persistent storage (e.g., uploaded assets) on a cadence that matches your recovery objectives.

---

## Prompt pipeline upkeep

Prompts live in `storage/app/prompts/` and rely on the placeholder `{concatenated_report_here}`.

- **Daily summary:** Runs a two-step pipeline—`daily1.md` receives the concatenated entries, then its output becomes the input for `daily2.md`.
- **Weekly summary:** Uses `weekly.md` with the same placeholder.
- When editing prompts, keep the placeholder intact and maintain Markdown formatting for optimal LLM responses.
- Store alternative prompt drafts under version control or a `prompts/archive/` directory so you can roll back revisions if summaries regress.
- After changing prompt files, smoke-test both daily and weekly reports via the dashboard to confirm the LLM output still reads correctly.

---

## Admin model & user management

Each deployment seeds an `admin` boolean column on `users` to differentiate administrators from regular teammates.

- Seed data includes three accounts (`test@example.com`, `ronald@example.com`, `terrence@example.com`) with the password `password`; change these credentials post-install.
- Promote additional admins by updating the user record through the in-app UI or via Tinker:
  ```bash
  php artisan tinker
  >>> \App\Models\User::where('email', 'lead@example.com')->update(['admin' => true]);
  ```
- Non-admins can edit only their own profile; they cannot manage other users. Verify permissions after role changes to ensure the UI reflects the expected capabilities.

---

## Troubleshooting reference

- **Immediate 500 errors** usually indicate the web root is not pointing at `public/` or `vendor/` is missing. Confirm the deployment layout and rerun `composer install --no-dev --optimize-autoloader` if necessary.
- **Blank pages or 404s** often mean URL rewriting (`mod_rewrite`, IIS URL Rewrite, Nginx `try_files`) is disabled. Align web server settings with the guidance in [docs/HOSTING.md](./HOSTING.md).
- **Environment changes not taking effect** happen when config caches are stale—run `php artisan config:clear` on the affected instance.
- **Slow performance** can improve by enabling caches, verifying PHP OPcache is active, and ensuring the database server is reachable with low latency.
- **Queue or scheduler drift** manifests as missing summary emails or outdated reports. Confirm cron is firing and queue workers are running for every team deployment.

---

## Upgrades & dependency management

- Stage upgrades in a non-production clone first. Run automated tests (if present) and manual smoke tests against representative data.
- Update PHP dependencies with `composer update` (or targeted `composer update vendor/package`) and rebuild assets via `npm install` + `npm run build` if frontend changes are involved.
- After validating the release, deploy updates to each team’s `src/` copy, preserving team-specific `.env` files and user-generated `storage/` contents.
- Document upgrade history per instance so you can trace which teams received a given release—pair this with the CHANGELOG in the repository root.

---

## When to seek architectural changes

If the volume of deployments grows or cross-team features emerge, re-evaluate the duplicate-`src` model described in [docs/MULTI_TEAM_SETUP.md](./MULTI_TEAM_SETUP.md). Laravel multi-tenant packages or a SaaS-oriented refactor may provide better economies of scale once maintenance overhead or reporting requirements exceed the boundaries set here.
