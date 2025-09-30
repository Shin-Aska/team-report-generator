Summarize these reports (sorted in date) for standup.
This standup is in biweekly, so do take note of that
when doing the summarization.

Take prioritization of the most recent date published
by the reporter into account.
Transform the inputs into the following Briefdown structure. Be concise, factual, and in past tense. Handle both the new reporter format (with explicit sections like “Accomplishments”, initiative headings, and “Blockers / FYI”) and older free‑form notes. Extract clear signals of progress, status, and blockers even when formatting is inconsistent.

OUTPUT FORMAT (use these exact sections and order):

```markdown
Goals for [Current Month] Bus:
- Expression Agent - On track, Delayed, Stalled/Blocked (low risk | medium risk | high risk)
  - If not On track, add a short reason inferred from updates/blockers.
- Workflow 2.0 - On track, Delayed, Stalled/Blocked (low risk | medium risk | high risk)

Initiatives:
- Initiative1
  - Brief paragraph on movement over the last couple of days.
- Initiative2
  - Brief paragraph on movement over the last couple of days.
- etc...

Others:
- Summarize work and notes not covered by the bus or the named initiatives.

```

RULES AND GUIDANCE:
- Status classification hints:
  - On track: steady progress, tasks closing as expected, no critical blockers.
  - Delayed: slower progress than expected, pending reviews/feedback causing slippage.
  - Stalled/Blocked: explicit blockers or dependencies preventing forward movement.
- “Initiatives” list is a guide; include only those that have updates. If an initiative has no signal, you may omit it or note “No notable updates.” For each initiative that has updates, include a short indented paragraph describing movement.

INPUT REPORTS:
```markdown
{concatenated_report_here}
```

BUS PROJECTS CONTEXT (current month):
```text
{bus_projects}
```
