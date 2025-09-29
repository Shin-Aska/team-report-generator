<?php

namespace App\Services;

use App\Models\BusProject;
use Illuminate\Support\Carbon;

class BusProjectService
{
    /**
     * Get all bus projects created within the given month (defaults to now).
     */
    public function getForMonth(?Carbon $base = null)
    {
        $base = $base ? $base->copy() : Carbon::now();
        $start = $base->copy()->startOfMonth();
        $end = $base->copy()->endOfMonth();
        return BusProject::query()
            ->whereBetween('created_at', [$start, $end])
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
        $lines = $list->map(function($p){
            $name = trim($p->project_name ?? '');
            $desc = trim($p->project_description ?? '');
            return $desc !== '' ? "- {$name}: {$desc}" : "- {$name}";
        })->all();
        return implode("\n", $lines);
    }
}
