<?php

namespace Database\Seeders;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Each user has a scripted narrative across the last ~10 working days
     * (skipping weekends) so reports are coherent and predictable.
     * Index 0 = most recent working day, index 9 = oldest.
     * A null entry means the user did not post that day.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => 'password', 'admin' => true]
        );

        $ronald = User::updateOrCreate(
            ['email' => 'ronald@example.com'],
            ['name' => 'Ronald', 'password' => 'password', 'admin' => false]
        );

        $terrence = User::updateOrCreate(
            ['email' => 'terrence@example.com'],
            ['name' => 'Terrence', 'password' => 'password', 'admin' => false]
        );

        // Per-user scripted entries indexed by working-day offset.
        // 0 = most recent working day, null = no post that day.
        $scripts = [
            $admin->id => [
                // WD 0
                <<<MD
                ## Done
                - Coordinated production deployment of v2.3 with Ronald and Terrence
                - Wrote and circulated release notes covering the new reports endpoint and UI
                - Reviewed and approved Terrence's final QA sign-off PR

                ## Notes
                - All systems green post-deploy; no rollback needed
                MD,

                // WD 1
                <<<MD
                ## Done
                - Ran sprint retrospective; captured three action items for next cycle
                - Reviewed and merged Ronald's webhook retry-logic fix
                - Updated JIRA board to reflect completed items

                ## Blockers
                - None
                MD,

                // WD 2
                <<<MD
                ## Done
                - Reviewed Terrence's loading-states and error-handling PR — approved with minor nits
                - Updated the deployment runbook to document the new reports endpoint rollout steps
                - Synced with stakeholders on the v2.3 release timeline; confirmed Thursday window
                MD,

                // WD 3 — out of office
                null,

                // WD 4
                <<<MD
                ## Done
                - Deployed reports API to staging and ran a full smoke-test pass
                - Filed two bugs for Ronald: incorrect pagination total on filtered queries, missing 404 on unknown report ID
                - Reviewed architecture diagram for the caching layer

                ## Blockers
                - Staging DB seed was stale — refreshed manually
                MD,

                // WD 5
                <<<MD
                ## Done
                - Sprint mid-point check-in with Ronald and Terrence
                - Unblocked Terrence on the API response format — agreed on a shared schema document
                - Reviewed and left comments on the reports API architecture doc Ronald drafted
                MD,

                // WD 6
                <<<MD
                ## Done
                - Reviewed Ronald's pagination PR; requested one method rename and additional test coverage
                - Synced with product on scope: confirmed CSV export is out of this sprint
                - Updated team capacity plan for the remaining sprint days
                MD,

                // WD 7
                <<<MD
                ## Done
                - Reviewed Ronald's N+1 fix PR — approved and merged
                - Updated the JIRA board with revised estimates after the N+1 discovery
                - Short sync with Terrence to align on which API fields the frontend needs
                MD,

                // WD 8
                <<<MD
                ## Done
                - Ran architecture review session for the new reporting pipeline with Ronald and Terrence
                - Agreed on a two-phase approach: API-first, UI integration in parallel
                - Created tracking tickets for the reporting feature breakdown
                MD,

                // WD 9
                <<<MD
                ## Done
                - Sprint planning: broke down the backlog, estimated all reporting feature tickets
                - Assigned reports API work to Ronald, dashboard UI to Terrence
                - Clarified acceptance criteria for the weekly summary modal with product
                MD,
            ],

            $ronald->id => [
                // WD 0
                <<<MD
                ## Done
                - Fixed webhook retry logic — implemented exponential backoff with jitter
                - Deployed fix to staging; all retry smoke tests passing
                - Opened PR for review, linked to the original bug report

                ## Notes
                - Root cause: missing idempotency key caused duplicate deliveries on transient failures
                MD,

                // WD 1
                <<<MD
                ## Done
                - Investigated webhook delivery failures reported in staging logs
                - Traced root cause to missing idempotency key on retries
                - Drafted fix and wrote regression test to confirm the failure path

                ## Blockers
                - Needed read access to the staging message queue logs — resolved with help from team lead
                MD,

                // WD 2
                <<<MD
                ## Done
                - Merged reports API after addressing final review comments
                - Renamed `buildResult` to `formatReportPayload` for clarity
                - Added missing docblocks flagged in review
                - Started scoping the webhook handler task
                MD,

                // WD 3 — sick day
                null,

                // WD 4
                <<<MD
                ## Done
                - Addressed code review feedback on the reports API
                - Fixed incorrect pagination total on filtered queries (off-by-one in the COUNT subquery)
                - Added 404 response for unknown report IDs
                - Pushed updated branch; ready for re-review
                MD,

                // WD 5
                <<<MD
                ## Done
                - Wrote unit tests for pagination logic; coverage up from 71% to 87%
                - Tested edge cases: empty result set, single page, max page size exceeded
                - Synced with team lead on shared API schema document for Terrence
                MD,

                // WD 6
                <<<MD
                ## Done
                - Added cursor-based pagination to the reports endpoint
                - Updated API docs with new `cursor` and `per_page` parameters
                - Opened draft PR for early feedback

                ## Blockers
                - Waiting on team lead review before marking ready
                MD,

                // WD 7
                <<<MD
                ## Done
                - Fixed N+1 query issue in the reports endpoint
                - Replaced per-row lazy loads with a single eager-loaded join
                - Query count dropped from 42 to 3 on a 100-row result set; P95 latency down ~340 ms
                - Pushed fix and pinged team lead for review
                MD,

                // WD 8
                <<<MD
                ## Done
                - Implemented initial query logic for the reports endpoint
                - Seeded local DB with representative test data to validate output shape
                - Identified a potential N+1 issue — flagged for tomorrow

                ## Notes
                - API response matches the agreed schema from today's architecture session
                MD,

                // WD 9
                <<<MD
                ## Done
                - Designed database schema for report result caching
                - Wrote and ran the migration locally; reviewed with team lead
                - Drafted architecture doc outlining the query pipeline

                ## Notes
                - Chose a denormalised cache table over a Redis layer to keep the stack simple for now
                MD,
            ],

            $terrence->id => [
                // WD 0
                <<<MD
                ## Done
                - Completed final QA pass on the reports UI
                - Verified behaviour in Chrome, Firefox, Safari, and Edge — all green
                - PR approved and merged; feature is live in v2.3

                ## Notes
                - No outstanding issues; closing out the sprint dashboard tasks
                MD,

                // WD 1
                <<<MD
                ## Done
                - Added loading skeleton and error state to the report modal
                - Addressed accessibility feedback: added `aria-live` region for async content
                - Rebased branch on main to pick up Ronald's merged API changes
                MD,

                // WD 2
                <<<MD
                ## Done
                - Addressed design review feedback on the reports modal
                - Adjusted spacing to match updated Figma tokens
                - Fixed colour contrast issue on the date range label (WCAG AA)
                - Pushed final changes; ready for QA
                MD,

                // WD 3 — no post
                null,

                // WD 4
                <<<MD
                ## Done
                - Cross-browser testing on Safari 17 and Firefox 124
                - Fixed two flexbox regressions in the dashboard header introduced by the modal refactor
                - Verified report modal renders correctly at 320 px viewport width

                ## Blockers
                - Safari has a known bug with `gap` inside certain flex containers — worked around it
                MD,

                // WD 5
                <<<MD
                ## Done
                - Integrated Ronald's reports API into the dashboard
                - Wired up the date range picker to the `/reports/weekly` endpoint
                - Handled loading and error states for the async fetch

                ## Notes
                - Used the shared schema doc to align field names — no surprises
                MD,

                // WD 6
                <<<MD
                ## Done
                - Styled the weekly report modal using the existing Bootstrap modal scaffold
                - Worked through responsive breakpoints — stacks cleanly on tablet and mobile
                - Synced with team lead to confirm the modal should lazy-load on open, not on page load
                MD,

                // WD 7
                <<<MD
                ## Done
                - Fixed layout regressions on mobile viewports introduced during the dashboard refactor
                - Tested on 375 px (iPhone SE) and 390 px (iPhone 15) breakpoints
                - Pushed fixes; dashboard header and entry form now render correctly at all sizes
                MD,

                // WD 8
                <<<MD
                ## Done
                - Connected the new date picker component to the status entry form
                - Added client-side validation: blocks future dates, highlights missing entries
                - Opened PR for team lead review

                ## Blockers
                - Waiting on Ronald to finalise the API response shape before wiring up the reports call
                MD,

                // WD 9
                <<<MD
                ## Done
                - Started the dashboard redesign spike
                - Built a new date picker component using vanilla JS — no added dependencies
                - Matched the visual spec from the updated Figma file shared in sprint planning
                MD,
            ],
        ];

        // Collect the last 14 calendar days that are weekdays, newest first.
        $workdays = [];
        $today = Carbon::today();
        for ($i = 0; $i < 14; $i++) {
            $day = $today->copy()->subDays($i);
            if (!$day->isWeekend()) {
                $workdays[] = $day;
            }
        }

        foreach ($scripts as $userId => $entries) {
            foreach ($entries as $wdIndex => $content) {
                if ($content === null || !isset($workdays[$wdIndex])) {
                    continue;
                }

                Entry::updateOrCreate(
                    ['user_id' => $userId, 'entry_date' => $workdays[$wdIndex]->toDateString()],
                    ['content' => trim($content)]
                );
            }
        }
    }
}
