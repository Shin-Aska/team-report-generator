# Source code directory

## 1. Project setup

1. **Install prerequisites**
   - PHP 8.2+
   - Composer
   - Node.js & npm
   - A database engine supported by Laravel (e.g., MySQL/MariaDB, SQLite)
2. **Install PHP dependencies**
   ```bash
   composer install
   ```
3. **Install front-end dependencies and build assets**
   ```bash
   npm install
   npm run build    # or `npm run dev` while developing
   ```
4. **Run database migrations and seeders (if applicable)**
   ```bash
   php artisan migrate --seed
   ```
5. **Serve the application**
   ```bash
   php artisan serve
   ```
   The dashboard will be available at the host/port printed in the console.

## 2. Environment configuration (.env)

1. Copy the template:
   ```bash
   cp .env.example .env
   ```
2. Generate your Laravel application key:
   ```bash
   php artisan key:generate
   ```
3. Update the values in `.env` using the placeholders in `.env.example` as a guide. Key sections to review:
   - **App**: `APP_NAME`, `APP_URL`, and debug settings.
   - **Database**: set the connection of your choice (SQLite by default) or supply host, port, database, username, and password for MySQL/MariaDB.
   - **Queue/Cache/Sessions**: choose drivers that match your environment (`database`, `redis`, etc.).
   - **Mail**: provide SMTP credentials if you plan to send emails.
   - **AWS / File storage**: required only if you integrate S3-compatible storage.
   - **LLM integrations**: set `GEMINI_API_KEY` (and swap models if desired); configure `LLM_ALLOWED_ORIGINS` for allowed frontend origins consuming the `/llm` endpoint.
4. Store secrets in secure environment variables for production deployments. Avoid committing `.env` files to version control.

## 3. Code structure

- `app/Http/Controllers/` — request controllers; `DashboardController.php` powers the dashboard views and actions.
- `app/Models/` — Eloquent models such as `User` and report-related entities.
- `app/Services/` — domain services, including prompt loading, LLM summarization (`SummarizerService.php`), and integrations (e.g., Azure DevOps).
- `app/View/Components/` & `resources/views/` — Blade templates and UI components.
- `routes/` — HTTP routes (`web.php`, `api.php`, etc.).
- `database/` — migrations, seeders, and factories.
- `storage/app/prompts/` — Markdown templates used for daily and weekly summaries.
- `public/` — public assets and Vite build output.

Refer to the repository root README for deployment strategies, contribution guidelines, and any additional project-wide documentation.
