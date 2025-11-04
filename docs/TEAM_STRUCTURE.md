# Team Structure & Reporting Cadence

This application assumes a cross-functional team that operates on a monthly delivery rhythm, supported by daily standups, weekly summaries, and Azure DevOps work tracking. Adjust the defaults outlined below to match your organisation if needed.

## Monthly “bus project” cadence

- The roadmap is organised into monthly **bus projects**—each month the team commits to a single project bus that bundles related deliverables.
- Each bus project carries its own goal, backlog, and success metrics; at the turn of the month, a new bus begins while the previous one is retroed.
- Keep the project names and objectives updated in Azure DevOps so the reporting prompts can reference the current bus accurately.

## Daily standups

- Every workday, contributors enter their status updates in the dashboard. These entries fuel both same-day standup summaries and downstream weekly reports.
- Standup updates should answer the usual “yesterday / today / blockers” trio and reference Azure DevOps work items where possible.
- The standup summarisation logic uses the heuristics in @src/app/Http/Controllers/DashboardController.php#139-168. Review and tweak those rules if your standup cadence differs (e.g., midweek recaps or alternative weekend policies).

## Weekly summary report

- The application compiles a Friday summary that blends daily standup notes with bus-project highlights.
- Use the generated Markdown to drive leadership reports or post-week retros; the prompt templates live under `storage/app/private/prompts/` if copy tone changes are required.

## Azure DevOps integration

Set the following environment variables in `src/.env` (see @src/.env.example#69-82) to enable Azure DevOps enrichment in reports:

```ini
ORGANIZATION_URL="https://dev.azure.com/<your-organization>"
ADO_API_VERSION="7.0"
PERSONAL_ACCESS_TOKEN="<token-with-WorkItem.Read>"
ADO_PROJECT="<project-name>"
```

- **ORGANIZATION_URL** – Base URL for your Azure DevOps organisation (omit trailing slash).
- **ADO_API_VERSION** – API version used for REST calls. The default `7.0` aligns with current stable endpoints; bump if Microsoft deprecates older APIs.
- **PERSONAL_ACCESS_TOKEN** – PAT with access to Work Item (read) and Boards scopes. Store it securely and rotate regularly.
- **ADO_PROJECT** – Default project whose work items should be referenced in daily and weekly summaries.

Restart the queue/worker processes after updating these values so the Laravel config cache picks them up.

## Customising cadences

- If your team runs multiple standups per day or uses non-standard week boundaries, adjust the date selection logic in `standupReport()` (see @src/app/Http/Controllers/DashboardController.php#139-168).
- Update prompt templates for daily and weekly summaries to reflect alternative question sets or reporting tone.
- Document any deviations from the defaults here so that new teammates can onboard quickly.
