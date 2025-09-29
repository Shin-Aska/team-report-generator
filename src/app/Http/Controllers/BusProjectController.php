<?php

namespace App\Http\Controllers;

use App\Services\BusProjectService;
use App\Models\BusProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BusProjectController extends Controller
{
    public function store(Request $request, BusProjectService $service)
    {
        if (!Auth::user()?->admin) {
            abort(403, 'Only admins can modify the current project.');
        }
        $data = $request->validate([
            'project_name' => ['required','string','max:255'],
            'project_description' => ['nullable','string','max:255'],
        ]);
        $service->add($data['project_name'], $data['project_description'] ?? null);
        return redirect()->route('dashboard')->with('status', 'Bus project added.');
    }

    public function destroy(BusProject $project, BusProjectService $service)
    {
        if (!Auth::user()?->admin) {
            abort(403, 'Only admins can modify the current project.');
        }
        $service->remove($project);
        return redirect()->route('dashboard')->with('status', 'Bus project removed.');
    }

    public function update(Request $request, BusProject $project, BusProjectService $service)
    {
        if (!Auth::user()?->admin) {
            abort(403, 'Only admins can modify the current project.');
        }
        $data = $request->validate([
            'project_name' => ['required','string','max:255'],
            'project_description' => ['nullable','string','max:255'],
        ]);
        $service->update($project, $data['project_name'], $data['project_description'] ?? null);
        return redirect()->route('dashboard')->with('status', 'Bus project updated.');
    }
}
