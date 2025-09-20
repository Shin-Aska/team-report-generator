# ReportGen (Laravel app)

ReportGen is a Laravel-based team status reporting application. It enables teammates to post daily updates, managers to compile weekly summaries, and everyone to view consolidated statuses by date or range. This folder contains the full Laravel app.

## Features

- Post daily status entries (Markdown) per user and date
- Post “as another user” from the dashboard (for PMs/team leads)
- Daily and weekly reports displayed in a modal via AJAX
- View team statuses by date or date range
- Templated LLM summarization (Gemini-first, OpenAI fallback)
- User management in-app (admin only): add, edit, remove users

## Where things live

- `app/Http/Controllers/DashboardController.php` — all dashboard actions, reports, statuses, user mgmt
- `app/Services/PromptService.php` — loads prompt templates
- `app/Services/SummarizerService.php` — LLM summarization pipeline
- `resources/views/dashboard/index.blade.php` — main dashboard UI
- `storage/app/prompts/` — `daily1.md`, `daily2.md`, `weekly.md` with `{concatenated_report_here}` placeholder

## Notes

- The app expects environment variables defined in `../README.md` (root). See that file for install/run steps.
- Admin role is enforced through a boolean `admin` column on `users`.

## License

MIT (see repository license if provided).
