# Report Generator Server

![GitHub](https://img.shields.io/github/license/Shin-Aska/team-report-generator)
![GitHub release (latest SemVer)](https://img.shields.io/github/v/release/Shin-Aska/team-report-generator)

![Report Generator dashboard preview](docs/preview.png)

A Laravel-based team status reporting application. It helps teams capture daily updates, compile weekly summaries, and share consolidated reports quickly. The core web app lives in `reportgen/` and provides:

- Post daily status entries (Markdown) per user and date
- Post “as another user” from the dashboard (for PMs/team leads)
- Daily and weekly reports rendered in Bootstrap modals (AJAX)
- View team status entries by date or by date range
- LLM summarization pipeline with templated prompts (Gemini, OpenAI, Azure, Mistral)
- In-app user management (admin-only): add, edit, and remove team members

## Repository layout

- `src/` — the Laravel 12 application (PHP 8.2) containing app code, Composer dependencies, and public assets
- `src/README.md` — detailed developer guide with setup steps, environment configuration, and directory structure explanations
- `docs/` — supplementary documentation, including `HOSTING.md` (shared hosting guide), `TEAM_STRUCTURE.md` (operational cadence defaults), `MULTI_TEAM_SETUP.md` (multi-team deployment model), and `prototype.epgz` (Pencil wireframes; open with [Pencil](https://pencil.evolus.vn/))

## Requirements

- PHP 8.2+
- Composer 2+
- MySQL 8+ (or compatible) — configure via `.env`
- Node.js 18+ and npm (optional, for Vite/dev UX)

Typical PHP extensions for Laravel should be installed (openssl, pdo, mbstring, tokenizer, xml, ctype, json, fileinfo).

## Additional operational docs

- [Team Structure & Reporting Cadence](docs/TEAM_STRUCTURE.md) — documents the monthly "bus" project rhythm, daily standup expectations, and Azure DevOps variables required to enrich reports, including the `ORGANIZATION_URL`, `PERSONAL_ACCESS_TOKEN`, `ADO_PROJECT`, and `ADO_API_VERSION` settings now found in `.env.example`.
- [Multi-Team Deployment Guide](docs/MULTI_TEAM_SETUP.md) — explains the duplicated-`src` architecture used to isolate each team with its own database, directory layout, and maintenance workflow.

## Quick start

1) Clone and enter the app folder

 ```bash
 git clone <your-repo-url> report-generator-server
 cd report-generator-server/reportgen
 ```

2) Install PHP dependencies

 ```bash
 composer install
 ```

3) Configure environment

Copy `.env.example` to `.env` (if needed) and set the following:

 ```ini
 APP_NAME="Report Generator"
 APP_ENV=local
 APP_KEY=
 APP_DEBUG=true
 APP_URL=http://127.0.0.1:8000
 
 DB_CONNECTION=mysql
 DB_HOST=127.0.0.1
 DB_PORT=3306
 DB_DATABASE=reportgen
 DB_USERNAME=root
 DB_PASSWORD=
 
 # LLM configuration (at least one required; if more than one provided, a dropdown will appear)
 LLM_TIMEOUT_SECONDS=120

 # Gemini
 GEMINI_API_KEY=your_gemini_key
 GEMINI_MODEL=gemini-2.5-flash-preview-05-20

 # OpenAI
 OPENAI_API_KEY=
 OPENAI_MODEL=gpt-4.1 # Defaults to 4.1

 # Azure
 AZURE_ENDPOINT=your_endpoint
 AZURE_API_KEY=your_key_here
 AZURE_AI_MODEL=gpt-4.1 # Defaults to 4.1

 # Mistral
 MISTRAL_API_KEY=your_key_here

 SESSION_DRIVER=file
 ```

Then generate the app key:

 ```bash
 php artisan key:generate
 ```

4) Run database migrations and seeders

 ```bash
 php artisan migrate --force
 php artisan db:seed --force
 ```

Seeder creates three users (password: `password`):
- test@example.com (admin)
- ronald@example.com
- terrence@example.com

5) (Optional) Frontend build/dev server

 ```bash
 npm install
 npm run dev
 ```

Or you can use the convenience Composer script (requires Node):

 ```bash
 composer run dev
 ```

6) Run the app

 ```bash
 php artisan serve
 ```

Visit http://127.0.0.1:8000 and log in with a seeded user.

## Running with containers (Docker or Podman)

Use this when you don't want PHP/Node on your host. Requires Docker or Podman.

1) Create your environment file (host-side, in `src/`)

```bash
cp src/.env.example src/.env
# Edit API keys if you use LLM features; DB defaults already match compose (host=db, user=app, pass=app)
```

2) Generate the app key (no PHP or Laravel needed on the host)

```bash
# Linux / macOS / WSL
sh generate-key.sh

# Windows (PowerShell)
.\generate-key.ps1
```

3) Build images

```bash
podman compose build   # or: docker compose build
```

4) Start the stack

```bash
podman compose up -d   # or: docker compose up -d
```

5) Run migrations and seed demo data

```bash
podman compose exec app sh -lc "cd /var/www/html && php artisan migrate --force && php artisan db:seed --force"
```

What happens:
- `app` (PHP-FPM) uses the bind-mounted `.env`.
- `web` serves the built assets via Nginx on `http://localhost:8080`.
- `db` is MySQL 8 with credentials `app`/`app` and database `app`.
- `scheduler` and `queue` reuse the app image for cron and queue workers.

Common commands:
- Logs: `podman compose logs -f app`
- Rebuild after code changes: `podman compose build && podman compose up -d`

6) Rebuilding every code update
- Every code update you have to rebuild the web app
```bash
podman compose build app web #or docker compose build app web
podman compose up -d --force-recreate  #or docker compose up -d --force-recreate
```

## Key features and endpoints

- Dashboard: `GET /dashboard`
    - Click date picker to change working date
    - Click a user avatar to “post as” that user
    - If a status exists for the selected user/date, it auto-loads into the editor
    - Admin-only actions: Add, Edit, Remove team members

- Reports
    - Daily report (modal): `GET /reports/daily?date=YYYY-MM-DD`
    - Weekly report (modal): `GET /reports/weekly?start=YYYY-MM-DD&end=YYYY-MM-DD`

- Statuses
    - By date: `GET /statuses?date=YYYY-MM-DD`
    - By range: `GET /statuses/range?start=YYYY-MM-DD&end=YYYY-MM-DD`

- Entries (AJAX helpers)
    - Fetch a user’s entry for a date: `GET /entries/fetch?user_id=ID&date=YYYY-MM-DD`
    - Publish: `POST /entries/publish`

## Maintenance & operations

For day-to-day upkeep, refer to [docs/MAINTENANCE.md](docs/MAINTENANCE.md). It consolidates configuration cache management, migrations, scheduler/queue supervision, prompt upkeep, admin role practices, troubleshooting checklists, and upgrade workflows originally captured here and in `src/README.md`.

## License

MIT (see repository license if provided).
