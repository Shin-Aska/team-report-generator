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
    public function storeUser(Request $request)
    {
        if (!Auth::user()->admin) {
            abort(403);
        }
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:4'],
        ]);

        // password will be hashed via User::$casts
        User::create($data);

        return back()->with('status', 'User added.');
    }

    public function destroyUser(User $user)
    {
        if (!Auth::user()->admin) {
            abort(403);
        }
        if (Auth::id() === $user->id) {
            return back()->withErrors(['user' => 'You cannot delete yourself.']);
        }
        $user->delete();
        return back()->with('status', 'User removed.');
    }

    public function updateUser(Request $request, User $user)
    {
        $actor = Auth::user();
        $isSelf = $actor->id === $user->id;
        if (!$isSelf && !$actor->admin) {
            abort(403);
        }
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255', Rule::unique('users','email')->ignore($user->id)],
            'password' => ['nullable','string','min:4'],
            'admin' => ['nullable','boolean'],
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if (!$actor->admin) {
            unset($data['admin']);
        }
        $user->update($data);
        return back()->with('status', 'User updated.');
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

    public function dailyReport(Request $request, PromptService $prompts, SummarizerService $sum)
    {
        $date = $request->query('date', Carbon::now()->toDateString());
        $entries = Entry::with('user')
            ->whereDate('entry_date', $date)
            ->get()
            ->map(fn($e) => [
                'date' => $e->entry_date->toDateString(),
                'user' => $e->user?->name ?? 'Unknown',
                'content' => $e->content,
            ])->all();

        $markdown = $sum->summarizeDaily(
            $entries,
            $date,
            $prompts->getDaily1Template(),
            $prompts->getDaily2Template()
        );
        $html = Str::markdown($markdown);
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'title' => 'Daily Report',
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

        $html = view('statuses.date', compact('entries','date'))->render();

        if ($request->ajax() || $request->wantsJson()) {
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

        $html = view('statuses.range', [
            'grouped' => $entries,
            'start' => $start,
            'end' => $end,
        ])->render();

        if ($request->ajax() || $request->wantsJson()) {
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
