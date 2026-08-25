# Application code architecture

This document covers project-owned Laravel classes under `src/app`. Laravel framework classes, vendor internals, bootstrap files, and migrations are intentionally out of scope.

## Runtime baseline

The supported runtime is **PHP 8.5 or newer** with Laravel 12. Composer enforces this baseline, and the application container defaults to PHP 8.5 CLI/FPM images. Run `composer update` on PHP 8.5 after changing the baseline so transitive dependencies in the lock file remain compatible.

## Request and report flow

1. Authenticated routes dispatch dashboard requests to `DashboardController`.
2. The controller loads `Entry`, `User`, and existing report records through Eloquent.
3. `PromptService` loads version-controlled Markdown templates.
4. `SummarizerService` builds prompts, calls the selected provider, tries configured fallbacks, and creates deterministic local output if every provider fails.
5. Canonical model output is cached in `GeneratedReport`. Optional greetings are applied at response time, so changing a team or header date neither duplicates nor invalidates an expensive LLM result.
6. Refinement rewrites only the summary and stores reusable variants in `RefinedReport`.

## Controllers

### `AuthController`

Owns browser login and logout. It uses Laravel's authentication guard, regenerates the session after authentication, and invalidates the session and CSRF token during logout. Password hashing remains a model/framework concern.

### `BusProjectController`

Validates bus-project create, update, and delete requests. It delegates mutation to `BusProjectService`, then produces dashboard redirects and status messages.

### `DashboardController`

The primary HTTP orchestration layer:

- Assembles the selected day's entry, previous entry, team state, bus projects, ticket state, and available engines.
- Publishes entries and supports administrator posting on behalf of another user.
- Determines working-day windows for daily reports and ranges for weekly reports.
- Reads and updates report caches, compares source signatures, and exposes stale state.
- Validates optional daily greeting inputs and prepends greetings to cached and fresh responses.
- Validates refinement modes and serializes saved variants for the browser.

### `Controller`

Project-level base controller. It currently adds no behavior to Laravel's controller but provides a stable extension point for shared web concerns.

## Models

### `User`

Authenticated team member. Model casts hash passwords, hidden fields protect authentication data during serialization, and `admin` is enforced by `UserManager`. A user owns many generated reports.

### `Entry`

One Markdown update from one user for one `entry_date`. The date is cast to Carbon, and `user()` supplies contributor identity during report assembly.

### `BusProject`

A project name and optional description. Its explicit table name preserves compatibility with the existing `BusProject` schema.

### `GeneratedReport`

Canonical output for a report type, date/range, user, and engine. Its `signature` hashes source state; a mismatch marks cached content stale. It owns `RefinedReport` variants.

### `RefinedReport`

Saved rewrite of a canonical report. `mode` and `prompt_hash` identify the transformation, while `source_signature` identifies the canonical source version.

### `App\\Models\\Models\\Entry`

Deprecated namespace placeholder retained for compatibility. New code must use `App\\Models\\Entry`.

## Services

### `PromptService`

Loads the two daily stages and weekly prompt from private local storage. Keeping templates outside PHP allows model instructions to evolve independently of orchestration code.

### `SummarizerService`

Owns report and provider logic:

- Daily generation first produces initiative-oriented notes and then a spoken summary.
- Weekly generation uses one template and range.
- Refinement isolates the summary, preserving details and tickets.
- Engine selection tries the requested engine before other configured engines.
- Azure, OpenAI, Gemini, and Mistral protocols stay in provider-specific methods.
- Deterministic Markdown fallbacks preserve useful output during outages.

Prompts use XML-style boundaries to distinguish report data from instructions. Strict output contracts reduce formatting variance across Gemini 2.5 Pro and Mistral.

### `DevopsWorkItemsService`

Builds Azure DevOps WIQL, batches detail requests, filters configured states, and groups counts by area. It returns structured errors so API failures do not break dashboard rendering.

### `BusProjectService`

Centralizes CRUD and prompt formatting. `summarizeForPrompt()` creates concise context; `getPreparedTemplate()` creates the status/risk checklist used by daily generation.

### `UserManager`

Centralizes mutation authorization. Administrators manage all users; regular users can update only themselves and cannot grant administrator access.

## Provider configuration

| Engine | Required settings | Default model |
|:--|:--|:--|
| Gemini | `GEMINI_API_KEY` | `GEMINI_MODEL=gemini-2.5-pro` |
| Mistral | `MISTRAL_API_KEY` | `MISTRAL_MODEL=mistral-large-latest` |
| OpenAI | `OPENAI_API_KEY` | `OPENAI_MODEL=gpt-4.1` |
| Azure AI | `AZURE_ENDPOINT`, `AZURE_API_KEY` | `AZURE_MODEL=gpt-5-nano` |

## Documentation convention

Project classes use PHPDoc/Doxygen-style class comments stating responsibility, invariants, and collaborators. Eloquent models also document dynamic properties with `@property`. Method comments should explain non-obvious behavior—date selection, caching, authorization, external requests, or shaped arrays—instead of restating PHP syntax.

## Live LLM acceptance test

The normal test suite never spends provider quota. A separate `live-llm` PHPUnit
group sends the same deterministic standup input through both daily prompt stages
and calls each provider directly, bypassing the production fallback sequence. This
ensures a Gemini result cannot accidentally make the Mistral case pass or vice versa.

Run both providers from `src/` with real credentials:

```bash
RUN_LIVE_LLM_TESTS=1 \
GEMINI_API_KEY='...' \
MISTRAL_API_KEY='...' \
php artisan test --group=live-llm
```

The acceptance checks require the structured section contract, one merged
initiative, no Markdown fences, a header-free spoken update, and preserved blocker
signal. A missing opt-in flag or provider credential reports a skipped test rather
than silently using another provider.
