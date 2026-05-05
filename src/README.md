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
   - **LLM integrations**: set at least one of `GEMINI_API_KEY`, `AZURE_API_KEY`, `OPENAI_API_KEY`, or `MISTRAL_API_KEY` for report summarization. Set `LLM_TIMEOUT_SECONDS` to tune provider request timeouts; it defaults to 120 seconds.
4. Store secrets in secure environment variables for production deployments. Avoid committing `.env` files to version control.

## 3. Code structure

- `app/Http/Controllers/` — request controllers; `DashboardController.php` powers the dashboard views and actions.
- `app/Models/` — Eloquent models such as `User` and report-related entities.
- `app/Services/` — domain services, including prompt loading, LLM summarization (`SummarizerService.php`), and integrations (e.g., Azure DevOps).
- `app/View/Components/` & `resources/views/` — Blade templates and UI components.
- `routes/` — HTTP routes (`web.php`, `api.php`, etc.).
- `database/` — migrations, seeders, and factories.
- `storage/app/private/prompts/` — Markdown templates used for daily and weekly summaries.

## 4. Customizing report prompts

- The prompt templates that steer the tone and structure of generated reports live in `storage/app/private/prompts/`.
- Edit the Markdown files directly (for example, `weekly.md` or `daily.md`) to adjust wording, emphasis, or guidance for the LLM.
- Keep the YAML front matter (if present) intact; only modify the Markdown body unless you know the consuming code expects new metadata.
- After saving changes, regenerate a report to confirm the updated tone matches expectations.
- `public/` — public assets and Vite build output.

## 5. Prompt pipeline

Prompts live at `reportgen/storage/app/prompts/` and contain the placeholder `{concatenated_report_here}`.

- Daily summary: concatenates all entries for the selected date and runs a 2-step pipeline:
    1) `daily1.md` with `{concatenated_report_here}` replaced
    2) The result of step 1 is injected as `{concatenated_report_here}` into `daily2.md`

- Weekly summary: concatenates all entries in the date range and applies `weekly.md` with the placeholder replaced.

Refer to the repository root README for deployment strategies, contribution guidelines, and any additional project-wide documentation.
