# Multi-Team Deployment Guide

The application ships with a **single-team assumption**: one Laravel codebase, one database schema, and one tenant of users generating reports. For simplicity and transparency in this open-source project, multi-team support is implemented by deploying **one copy of `src/` per team**, each wired to its own database and environment file. This document outlines the layout, configuration, and maintenance workflow baked into that design.

---

## Why duplicate `src/`?

- **Data isolation:** Each team instance owns its own schema (`reportgen_team_a`, `reportgen_team_b`, …), preventing cross-team data access by design.
- **Operational simplicity:** The project deliberately avoids additional tenancy middleware. Maintaining separate copies keeps Laravel behavior predictable and easy to audit.
- **Blast radius control:** Upgrades, outages, or configuration changes in one instance leave other teams untouched.

> ⚠️ This design favors isolation over hosting efficiency. Account for disk usage and database connections per team when sizing infrastructure.

---

## Directory layout

Provision a parent directory (on the same server or across separate hosts) and duplicate the repository’s `src/` folder for each team. Keep the vendor build artifacts with each copy so deployments remain self-contained.

```
/var/www/report-generator/
├─ team-alpha/
│  ├─ src/                    # Clone/copy of repo's src
│  │  ├─ .env                 # Points at team Alpha DB
│  │  └─ ...
│  └─ public/ (optional)      # If host forces a dedicated docroot
├─ team-beta/
│  └─ src/                    # Clone/copy of repo's src
│     ├─ .env                 # Points at team Beta DB
│     └─ ...
└─ shared-assets/             # Optional: storage for deployments/backups
```

For shared hosting that requires a `public_html/` directory, mirror the pattern shown in `docs/HOSTING.md` for each team (e.g., `public_html/team-alpha/` pointing at `team-alpha/src/public`).

---

## Step-by-step setup

1. **Duplicate the application folder**
   - From the repository root run a fresh `composer install --no-dev --optimize-autoloader` and `npm run build` inside `src/`.
   - Copy the entire `src/` directory to a new team folder (`cp -a src/ /var/www/report-generator/team-alpha/src`).
   - Repeat for every team.

2. **Create team-specific environment files**
   - Copy `.env.example` to `.env` inside each duplicated `src/`.
   - Update database settings to point at the team’s schema/user:
     ```ini
     APP_NAME="Report Generator – Team Alpha"
     APP_URL=https://reports.alpha.example.com

     DB_DATABASE=reportgen_alpha
     DB_USERNAME=reportgen_alpha
     DB_PASSWORD=strong_password_here
     ```
   - Generate an app key per instance: `php artisan key:generate` (run inside each `src/`).
   - Adjust LLM keys or other secrets if teams require separate credentials.

3. **Provision databases**
   - Create one schema/user pair per team (e.g., `CREATE DATABASE reportgen_alpha CHARACTER SET utf8mb4;`).
   - Grant least-privilege credentials to each team’s database user.
   - Run migrations and seeders for every instance:
     ```bash
     php artisan migrate --force
     php artisan db:seed --force
     ```

4. **Expose each instance**
   - **Separate domains/subdomains:** Map `team-alpha` folder to `reports.alpha.example.com`, `team-beta` to `reports.beta.example.com`, etc.
   - **Document root:** Point the virtual host or shared-hosting web root to `team-*/src/public`.
   - Configure session cookies (`SESSION_DOMAIN`) per team so browsers never mix credentials across domains.

5. **Isolation checklist**
   - Log into each team instance and confirm seeded users cannot access other teams’ domains.
   - Check that asset builds, storage directories, and queues are scoped per team.

---

## Maintenance and updates

- **Deployments:** Apply code updates to one team at a time. Use the workflow below:
  1. Pull new release into a staging copy of the repository.
  2. Test migrations and smoke tests against a non-production clone of a team database.
  3. Rsync/replace each team’s `src/` with the validated build, preserving the team-specific `.env` and storage folder.

- **Shared storage:** When teams upload files, keep `storage/app` isolated. Local disks already separate directories per instance; for cloud storage drivers (S3), use unique buckets or prefixes per team and configure via `.env`.

- **Queues & schedulers:** Configure cron (`php artisan schedule:run`) and queue workers per instance. Use distinct supervisor service names to avoid collisions.

- **Monitoring:** Track logs and metrics separately. Centralized logging solutions should tag entries by team to simplify triage.

- **Security patches:** Track every team instance and roll out critical patches everywhere so versions stay aligned.

---

## Automation tips

Because duplication is the core model, automation helps manage repetitive steps:

1. **Provisioning script:** Maintain a shell script or Ansible playbook that:
   - Creates the directory structure.
   - Copies the baseline `src/` build.
   - Generates a random app key.
   - Substitutes database credentials and domains in `.env` via templating.

2. **CI/CD pipelines:** Parameterize deployments by team so a single pipeline job deploys to multiple targets, swapping `.env` values per environment.

3. **Secrets management:** Store team-specific secrets in a vault or parameter store instead of git.

---

## When to reconsider this approach

- You anticipate dozens of teams and the operational overhead becomes high.
- You need cross-team analytics or shared logins.
- You require runtime tenancy (e.g., switching team context from a dropdown).

At that scale we evaluate Laravel multi-tenant packages (e.g., `stancl/tenancy`) or a SaaS architecture with database-per-tenant or shared schema plus tenant IDs. Until then, duplicating `src/` with unique `.env` files is the baseline approach baked into the project to preserve data isolation.
