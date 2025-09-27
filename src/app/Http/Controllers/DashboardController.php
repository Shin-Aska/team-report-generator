<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\Entry;
use App\Models\User;
use App\Services\PromptService;
use App\Services\SummarizerService;
use App\Services\UserManager;
use Illuminate\Auth\Access\AuthorizationException;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date', Carbon::now()->toDateString());

        $user = Auth::user();
        $myEntry = Entry::where('user_id', $user->id)
            ->whereDate('entry_date', $date)
            ->first();

        $teamEntries = Entry::with('user')
            ->whereDate('entry_date', $date)
            ->orderBy('user_id')
            ->get();

        $teamUsers = User::orderBy('name')->get();

        return view('dashboard.index', [
            'date' => $date,
            'myEntry' => $myEntry,
            'teamEntries' => $teamEntries,
            'teamUsers' => $teamUsers,
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
        if ($iso === 2) { // Tuesday -> Monday and last Friday
            $monday = $base->copy()->subDay()->toDateString();
            $lastFriday = $base->copy()->startOfWeek(Carbon::MONDAY)->subDays(3)->toDateString();
            $dates = [$monday, $lastFriday];
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

        $markdown = $sum->summarizeStandup(
            $entries,
            $date,
            $prompts->getDaily1Template(),
            $prompts->getDaily2Template(),
            Auth::user()?->name
        );
        $html = Str::markdown($markdown);
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'title' => 'Standup Report',
                'date' => $date,
                'html' => $html,
                'markdown' => $markdown,
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
        $markdown = $sum->summarizeWeekly($entries, $range, $prompts->getWeeklyTemplate());
        $html = Str::markdown($markdown);
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'title' => 'Weekly Report',
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'html' => $html,
                'markdown' => $markdown,
            ]);
        }
        return view('reports.weekly', compact('html', 'markdown', 'start', 'end'));
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
