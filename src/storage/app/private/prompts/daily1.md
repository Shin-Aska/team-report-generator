Summarize these reports (sorted by date) for standup.
This standup is biweekly, so prioritize the most recent entries.

IMPORTANT: Organize the summary by **topic or initiative**, NOT by person. Multiple people may contribute to the same initiative — merge their work together under that initiative. Only mention names where it adds clarity (e.g. who resolved a blocker, who is the point of contact), but do not structure the output as "Person A did X. Person B did Y."

Be concise, factual, and use past tense throughout. Handle both structured reporter formats (with "Accomplishments", initiative headings, "Blockers / FYI") and older free-form notes. Extract clear signals of progress, status, and blockers even when formatting is inconsistent.

OUTPUT FORMAT (use these exact sections and order):

```markdown
Goals for [Current Month] Bus:
{bus_entries}

Initiatives:
- Initiative1
  - Brief paragraph describing collective team movement on this initiative over the last couple of days. Mention contributors only when relevant.
- Initiative2
  - Brief paragraph describing collective team movement on this initiative over the last couple of days.
- etc...

Others:
- Summarize remaining work and notes not covered above, grouped by topic rather than by person.

Blockers / FYI:
- List any blockers, dependencies, leaves, or noteworthy items.
```

RULES AND GUIDANCE:
- Group work by topic or initiative, not by individual. If two people worked on the same thing, combine their updates into one paragraph under that initiative.
- Status classification hints:
  - On track: steady progress, tasks closing as expected, no critical blockers.
  - Delayed: slower progress than expected, pending reviews/feedback causing slippage.
  - Stalled/Blocked: explicit blockers or dependencies preventing forward movement.
- "Initiatives" list is a guide; include only those that have updates. Omit initiatives with no signal. For each initiative with updates, include a short indented paragraph describing team movement.
- Use people's real names from the input reports. Never use "Myself" or "I".

INPUT REPORTS:
```markdown
{concatenated_report_here}
```

BUS PROJECTS CONTEXT (current month):
```text
{bus_projects}
```
