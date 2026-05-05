<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\Entry;
use App\Models\GeneratedReport;
use App\Models\User;
use App\Services\PromptService;
use App\Services\SummarizerService;
use App\Services\UserManager;
use App\Services\DevopsWorkItemsService;
use App\Services\BusProjectService;
use Illuminate\Auth\Access\AuthorizationException;

class DashboardController extends Controller
{
    public function index(Request $request, DevopsWorkItemsService $ado, BusProjectService $bus)
    {
        $date = $request->query('date', Carbon::now()->toDateString());

        $user = Auth::user();
        $baseDate = Carbon::parse($date);

        $myEntry = Entry::where('user_id', $user->id)
            ->whereDate('entry_date', $date)
            ->first();

        $previousEntryDateObj = $baseDate->isMonday()
            ? $baseDate->copy()->subDays(3)
            : $baseDate->copy()->subDay();
        $previousEntryDate = $previousEntryDateObj->toDateString();

        $previousEntry = Entry::where('user_id', $user->id)
            ->whereDate('entry_date', $previousEntryDate)
            ->first();

        $previousEntryLabel = $previousEntryDateObj->format('l, m/d/Y');

        $previousEntryData = [
            'date' => $previousEntryDate,
            'label' => $previousEntryLabel,
            'content' => $previousEntry?->content,
            'hasEntry' => (bool) $previousEntry,
        ];

        $teamEntries = Entry::with('user')
            ->whereDate('entry_date', $date)
            ->orderBy('user_id')
            ->get();

        $teamUsers = User::orderBy('name')->get();

        $adoSummary = $ado->getSummary();
        $busProjects = $bus->getForMonth(Carbon::parse($date));

        $llmEngines = $this->determineAvailableEngines();

        return view('dashboard.index', [
            'date' => $date,
            'myEntry' => $myEntry,
            'previousEntry' => $previousEntry,
            'previousEntryDate' => $previousEntryDate,
            'previousEntryLabel' => $previousEntryLabel,
            'previousEntryData' => $previousEntryData,
            'teamEntries' => $teamEntries,
            'teamUsers' => $teamUsers,
            'adoSummary' => $adoSummary,
            'busProjects' => $busProjects,
            'llmEngines' => $llmEngines,
        ]);
    }

    // Team management
    public function storeUser(Request $request, UserManager $users)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:4'],
        ]);
        try {
            // password will be hashed via User::$casts
            $users->create($data, Auth::user());
            return back()->with('status', 'User added.');
        } catch (AuthorizationException $e) {
            return back()->withErrors(['user' => $e->getMessage()]);
        }
    }

    public function destroyUser(User $user, UserManager $users)
    {
        try {
            $users->delete($user, Auth::user());
            return back()->with('status', 'User removed.');
        } catch (AuthorizationException $e) {
            return back()->withErrors(['user' => $e->getMessage()]);
        }
    }

    public function updateUser(Request $request, User $user, UserManager $users)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255', Rule::unique('users','email')->ignore($user->id)],
            'password' => ['nullable','string','min:4'],
            'admin' => ['nullable','boolean'],
        ]);
        try {
            $users->update($user, $data, Auth::user());
            return back()->with('status', 'User updated.');
        } catch (AuthorizationException $e) {
            return back()->withErrors(['user' => $e->getMessage()]);
        }
    }

    public function fetchEntry(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required','integer','exists:users,id'],
            'date' => ['required','date'],
        ]);
        $entry = Entry::where('user_id', $data['user_id'])
            ->whereDate('entry_date', $data['date'])
            ->first();
        return response()->json([
            'found' => (bool) $entry,
            'content' => $entry?->content ?? '',
        ]);
    }

    public function publishEntry(Request $request)
    {
        $data = $request->validate([
            'entry_date' => ['required','date'],
            'content' => ['required','string'],
            'as_user_id' => ['nullable','integer','exists:users,id'],
        ]);

        $authUser = Auth::user();
        $targetUserId = $data['as_user_id'] ?? $authUser->id;

        $entry = Entry::updateOrCreate([
            'user_id' => $targetUserId,
            'entry_date' => $data['entry_date'],
        ], [
            'content' => $data['content']
        ]);

        return redirect()->route('dashboard', ['date' => $data['entry_date']])
            ->with('status', 'Entry published.');
    }

    public function standupReport(Request $request, PromptService $prompts, SummarizerService $sum)
    {
        // Base date comes from query or defaults to today
        $base = Carbon::parse($request->query('date', Carbon::now()->toDateString()));
        $iso = $base->isoWeekday(); // 1=Mon ... 7=Sun

        // Determine which dates to include
        $dates = [];
        if ($iso === 1) { // Monday -> previous Friday
            $dates = [$base->copy()->subDays(3)->toDateString()];
        }
        else if ($iso === 2) { // Tuesday -> Monday and last Friday
            $monday = $base->copy()->subDay()->toDateString();
            $lastFriday = $base->copy()->startOfWeek(Carbon::MONDAY)->subDays(3)->toDateString();
            $dates = [$monday, $lastFriday];
        } else if ($iso === 4) { // Thursday -> Wednesday and Tuesday
            $wednesday = $base->copy()->subDay()->toDateString();
            $tuesday = $base->copy()->subDays(2)->toDateString();
            $dates = [$wednesday, $tuesday];
        } else { // Thursday and all other days -> yesterday
            $dates = [$base->copy()->subDay()->toDateString()];
        }

        // Fetch entries for the computed dates
        $query = Entry::with('user');
        if (count($dates) === 1) {
            $query->whereDate('entry_date', $dates[0]);
        } else {
            $query->whereIn('entry_date', $dates);
        }
        $entries = $query
            ->orderBy('entry_date')
            ->get()
            ->map(fn($e) => [
                'date' => $e->entry_date->toDateString(),
                'user' => $e->user?->name ?? 'Unknown',
                'content' => $e->content,
            ])->all();

        // Keep the original requested/base date for labeling
        $date = $base->toDateString();
        $engine = $request->query('engine');
        $signature = hash('sha256', json_encode($entries) . $date . ($engine ?? ''));

        if (!$request->boolean('regenerate')) {
            $cached = GeneratedReport::where('user_id', Auth::id())
                ->where('report_type', 'daily')
                ->where('date', $date)
                ->where(function ($q) use ($engine) {
                    $q->where('engine', $engine)->orWhereNull('engine');
                })
                ->first();

            if ($cached) {
                $stale = $cached->signature !== $signature;
                $html = Str::markdown($cached->content);
                $engineLabel = $this->determineAvailableEngines()[$cached->engine] ?? 'Default';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'title' => 'Standup Report',
                        'date' => $date,
                        'html' => $html,
                        'markdown' => $cached->content,
                        'stale' => $stale,
                        'engineLabel' => $engineLabel,
                    ]);
                }
                return view('reports.daily', ['html' => $html, 'markdown' => $cached->content, 'date' => $date, 'stale' => $stale]);
            }
        }

        $result = $sum->summarizeStandup(
            $entries,
            $date,
            $prompts->getDaily1Template(),
            $prompts->getDaily2Template(),
            Auth::user()?->name,
            $engine
        );
        $markdown = $result['content'];

        if (!$result['isFallback']) {
            GeneratedReport::updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'report_type' => 'daily',
                    'date' => $date,
                    'engine' => $engine,
                ],
                ['content' => $markdown, 'signature' => $signature]
            );
        }

        $html = Str::markdown($markdown);
        $engineLabel = $this->determineAvailableEngines()[$engine] ?? 'Default';
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'title' => 'Standup Report',
                'date' => $date,
                'html' => $html,
                'markdown' => $markdown,
                'stale' => false,
                'isFallback' => $result['isFallback'],
                'error' => $result['error'],
                'engineLabel' => $engineLabel,
            ]);
        }
        return view('reports.daily', compact('html', 'markdown', 'date'));
    }

    public function weeklyReport(Request $request, PromptService $prompts, SummarizerService $sum)
    {
        $end = Carbon::parse($request->query('end', Carbon::now()->toDateString()));
        $start = Carbon::parse($request->query('start', $end->copy()->subDays(6)->toDateString()));

        $entries = Entry::with('user')
            ->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('entry_date')
            ->get()
            ->map(fn($e) => [
                'date' => $e->entry_date->toDateString(),
                'user' => $e->user?->name ?? 'Unknown',
                'content' => $e->content,
            ])->all();

        $range = $start->toFormattedDateString().' - '.$end->toFormattedDateString();
        $engine = $request->query('engine');
        $signature = hash('sha256', json_encode($entries) . $start->toDateString() . $end->toDateString() . ($engine ?? ''));

        if (!$request->boolean('regenerate')) {
            $cached = GeneratedReport::where('user_id', Auth::id())
                ->where('report_type', 'weekly')
                ->where('start_date', $start->toDateString())
                ->where('end_date', $end->toDateString())
                ->where(function ($q) use ($engine) {
                    $q->where('engine', $engine)->orWhereNull('engine');
                })
                ->first();

            if ($cached) {
                $stale = $cached->signature !== $signature;
                $html = Str::markdown($cached->content);
                $engineLabel = $this->determineAvailableEngines()[$cached->engine] ?? 'Default';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'title' => 'Weekly Report',
                        'start' => $cached->start_date->toDateString(),
                        'end' => $cached->end_date->toDateString(),
                        'html' => $html,
                        'markdown' => $cached->content,
                        'stale' => $stale,
                        'engineLabel' => $engineLabel,
                    ]);
                }
                return view('reports.weekly', [
                    'html' => $html,
                    'markdown' => $cached->content,
                    'start' => $cached->start_date,
                    'end' => $cached->end_date,
                    'stale' => $stale,
                ]);
            }
        }

        $result = $sum->summarizeWeekly(
            $entries,
            $range,
            $prompts->getWeeklyTemplate(),
            $engine
        );
        $markdown = $result['content'];

        if (!$result['isFallback']) {
            GeneratedReport::updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'report_type' => 'weekly',
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'engine' => $engine,
                ],
                ['content' => $markdown, 'signature' => $signature]
            );
        }

        $html = Str::markdown($markdown);
        $engineLabel = $this->determineAvailableEngines()[$engine] ?? 'Default';
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'title' => 'Weekly Report',
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'html' => $html,
                'markdown' => $markdown,
                'stale' => false,
                'isFallback' => $result['isFallback'],
                'error' => $result['error'],
                'engineLabel' => $engineLabel,
            ]);
        }
        return view('reports.weekly', compact('html', 'markdown', 'start', 'end'));
    }

    protected function determineAvailableEngines(): array
    {
        $engines = [];
        if (env('AZURE_API_KEY') && env('AZURE_ENDPOINT')) {
            $engines['azure'] = 'Azure AI';
        }
        if (env('OPENAI_API_KEY')) {
            $engines['openai'] = 'OpenAI';
        }
        if (env('GEMINI_API_KEY')) {
            $engines['gemini'] = 'Gemini';
        }
        if (env('MISTRAL_API_KEY')) {
            $engines['mistral'] = 'Mistral';
        }
        return $engines;
    }

    public function statusesByDate(Request $request)
    {
        $date = $request->query('date', Carbon::now()->toDateString());
        $entries = Entry::with('user')
            ->whereDate('entry_date', $date)
            ->orderBy(User::select('name')->whereColumn('users.id','entries.user_id'))
            ->get();

        if ($request->ajax() || $request->wantsJson()) {
            $html = view('statuses.partials.date', compact('entries','date'))->render();
            return response()->json([
                'title' => 'Statuses',
                'date' => $date,
                'html' => $html,
            ]);
        }

        return view('statuses.date', compact('entries','date'));
    }

    public function statusesByRange(Request $request)
    {
        $end = Carbon::parse($request->query('end', Carbon::now()->toDateString()));
        $start = Carbon::parse($request->query('start', $end->copy()->subDays(6)->toDateString()));

        $entries = Entry::with('user')
            ->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('entry_date')
            ->orderBy(User::select('name')->whereColumn('users.id','entries.user_id'))
            ->get()
            ->groupBy(fn($e) => $e->entry_date->toDateString());

        if ($request->ajax() || $request->wantsJson()) {
            $html = view('statuses.partials.range', [
                'grouped' => $entries,
                'start' => $start,
                'end' => $end,
            ])->render();
            return response()->json([
                'title' => 'Statuses',
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'html' => $html,
            ]);
        }

        return view('statuses.range', [
            'grouped' => $entries,
            'start' => $start,
            'end' => $end,
        ]);
    }
}
