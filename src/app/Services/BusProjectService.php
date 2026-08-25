<?php

namespace App\Services;

use App\Models\BusProject;
use Illuminate\Support\Carbon;

/**
 * Manage bus projects and convert them into prompt-ready context.
 *
 * Provides both a concise summary and a checklist representation used to classify project status and risk.
 */
class BusProjectService
{
    /**
     * Get all bus projects created within the given month (defaults to now).
     */
    public function getForMonth(?Carbon $base = null)
    {
        $base = $base ? $base->copy() : Carbon::now();

        return BusProject::query()
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Create a new Bus Project which becomes the current one.
     */
    public function add(string $name, ?string $description = null): BusProject
    {
        return BusProject::create([
            'project_name' => $name,
            'project_description' => (string) ($description ?? ''),
        ]);
    }

    /**
     * Update an existing Bus Project.
     */
    public function update(BusProject $project, string $name, ?string $description = null): BusProject
    {
        $project->update([
            'project_name' => $name,
            'project_description' => (string) ($description ?? ''),
        ]);

        return $project;
    }

    /**
     * Remove a specific project.
     */
    public function remove(BusProject $project): bool
    {
        return (bool) $project->delete();
    }

    /**
     * For prompt usage later on, return a concise string for the month.
     */
    public function summarizeForPrompt(?Carbon $base = null): string
    {
        $list = $this->getForMonth($base);
        if ($list->isEmpty()) {
            return 'No bus projects for this month.';
        }
        $lines = $list->map(function ($p) {
            $name = trim($p->project_name ?? '');
            $desc = trim($p->project_description ?? '');

            return $desc !== '' ? "- {$name}: {$desc}" : "- {$name}";
        })->all();

        return implode("\n", $lines);
    }

    public function getPreparedTemplate(?Carbon $base = null): string
    {
        $list = $this->getForMonth($base);
        if ($list->isEmpty()) {
            return 'No bus projects for this month.';
        }

        // Build a checklist-style template for each project
        $lines = $list->map(function ($p) {
            $name = trim($p->project_name ?? '');

            return "- {$name} - On track, Delayed, Stalled/Blocked (low risk | medium risk | high risk)";
        })->all();

        // Add one general instruction about providing a reason when not on track
        $lines[] = '';
        $lines[] = "Note: If a project is not 'On track', briefly state the reason inferred from updates/blockers.";

        return implode("\n", $lines);
    }
}
