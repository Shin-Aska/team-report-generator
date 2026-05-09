@extends('layouts.app')

@section('content')
<style>
  .loading-overlay { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.6); z-index: 2000; }
  .loading-overlay.d-none { display: none !important; }
  .subcard { background: var(--bs-light-bg-subtle, #f8f9fc); color: var(--bs-body-color); border: 1px solid var(--bs-border-color-translucent, rgba(0,0,0,.06)); border-radius: .5rem; }
  .avatar-sm { width: 40px; height: 40px; border-radius: 50%; background: var(--bs-primary-bg-subtle, #e7f1ff); display: inline-flex; align-items:center; justify-content:center; font-weight:600; color: var(--bs-primary, #0d6efd); font-size: .95rem; }
  .team-list .list-group-item { border: 0; border-bottom: 1px solid rgba(0,0,0,.05); }
  .team-list .status-icon { font-size: 1.1rem; }
  .report-controls { background: linear-gradient(180deg, rgba(13,110,253,.06), rgba(13,110,253,.02)); border: 1px solid rgba(13,110,253,.14); border-radius: .85rem; }
  .report-section { border: 1px solid var(--bs-border-color-translucent, rgba(0,0,0,.08)); border-radius: 1rem; padding: 1rem 1.1rem; background: var(--bs-body-bg); box-shadow: 0 10px 30px rgba(0,0,0,.03); }
  .report-section + .report-section { margin-top: 1rem; }
  .report-section-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: .75rem; }
  .report-section-label { font-size: .74rem; letter-spacing: .08em; text-transform: uppercase; color: var(--bs-secondary-color, #6c757d); font-weight: 700; }
  .report-section h1,
  .report-section h2,
  .report-section h3 { margin: 0; }
  .report-section-primary { border-left: 4px solid var(--bs-primary, #0d6efd); }
  .report-section-secondary { border-left: 4px solid var(--bs-info, #0dcaf0); }
  .report-section-tertiary { border-left: 4px solid var(--bs-secondary, #6c757d); }
</style>
<div class="row g-4 d-none">
  <div class="col-12">
    <div class="page-card p-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="mb-0">What did you do today?</h5>
        <form id="dateForm" method="GET" action="{{ route('dashboard') }}" class="d-flex gap-2 align-items-center">
          <input type="date" name="date" class="form-control" value="{{ $date }}" onchange="document.getElementById('dateForm').submit()"/>
          <a class="btn btn-outline-secondary" href="{{ route('dashboard') }}">Today</a>
        </form>
      </div>

      <form method="POST" action="{{ route('entries.publish') }}" id="publishForm">
        @csrf
        <input type="hidden" name="entry_date" value="{{ $date }}"/>
        <input type="hidden" name="as_user_id" id="as_user_id" value="{{ auth()->id() }}" />
        <div class="mb-3 position-relative" id="editorContainer">
          <div id="editorLoading" class="textarea-overlay d-none">
            <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
          </div>
          <textarea class="form-control" name="content" rows="7" placeholder="Write your update in Markdown...">{{ old('content', $myEntry->content ?? '') }}</textarea>
        </div>
        <div class="text-muted small mb-2">
          Posting as:
          <span class="badge bg-info text-dark" id="postAsName">{{ auth()->user()->name }} (You)</span>
          <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="resetPostAs()">Post as me</button>
        </div>
        <div class="d-flex justify-content-end">
          <button class="btn btn-primary">Publish</button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-12">
    <div class="page-card p-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="mb-0">Team</h5>
        <div class="d-flex gap-2 align-items-center">
          <form id="teamDateForm" method="GET" action="{{ route('dashboard') }}" class="d-flex gap-2 align-items-center">
            <input type="date" name="date" class="form-control" value="{{ $date }}" onchange="document.getElementById('teamDateForm').submit()"/>
          </form>
          @if(auth()->user()->admin)
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#addUserModal">Add user</button>
          @endif
        </div>
      </div>

      <div class="row g-4">
        @php $submitted = $teamEntries->pluck('user_id')->all(); @endphp
        @foreach($teamUsers as $u)
          <div class="col-6 col-md-4 col-lg-3 text-center">
            <button type="button" class="btn p-0 border-0 bg-transparent" onclick="setPostAs({{ $u->id }}, '{{ addslashes($u->name) }}')" title="Post as {{ $u->name }}">
              <div class="avatar mx-auto mb-2" style="cursor:pointer;">{{ strtoupper(substr($u->name,0,1)) }}</div>
            </button>
            <div class="small {{ $u->id === auth()->id() ? 'fw-semibold' : '' }}">
              <span id="asLabel-{{ $u->id }}">{{ $u->id === auth()->id() ? 'You' : $u->name }}</span>
              @if(in_array($u->id, $submitted))
                <span class="text-success">✓</span>
              @else
                <span class="text-danger">✗</span>
              @endif
            </div>
            <div class="mt-2 d-flex justify-content-center gap-2">
              @if(auth()->user()->admin && $u->id !== auth()->id())
                <form method="POST" action="{{ route('team.users.destroy', $u) }}" onsubmit="return confirm('Delete {{ $u->name }}?');">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                </form>
              @endif
              @if(auth()->user()->admin || $u->id === auth()->id())
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#editUserModal-{{ $u->id }}">Edit</button>
              @endif
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  <!-- Daily Report section -->
  <div class="col-12">
    <div class="page-card p-4">
      <h5 class="mb-3">Daily Report</h5>
      <div class="row g-3 align-items-end">
        <div class="col-12 col-md-4 d-grid">
          <button class="btn btn-outline-primary" type="button" onclick="generateDaily()">Generate standup report</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Weekly Report section -->
  <div class="col-12">
    <div class="page-card p-4">
      <h5 class="mb-3">Weekly Report</h5>
      <div class="row g-3 align-items-end">
        <div class="col-12 col-md-3">
          <label class="form-label mb-1">Start</label>
          <input type="date" class="form-control" id="weeklyStart" value="{{ \Illuminate\Support\Carbon::parse($date)->copy()->startOfWeek(\Illuminate\Support\Carbon::MONDAY)->toDateString() }}">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label mb-1">End</label>
          <input type="date" class="form-control" id="weeklyEnd" value="{{ \Illuminate\Support\Carbon::parse($date)->copy()->startOfWeek(\Illuminate\Support\Carbon::MONDAY)->addDays(4)->toDateString() }}">
        </div>
        <div class="col-12 col-md-2 d-grid">
          <button class="btn btn-outline-primary" type="button" onclick="generateWeekly()">Generate weekly report</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Statuses section -->
  <div class="col-12">
    <div class="page-card p-4">
      <h5 class="mb-3">Statuses</h5>
      <div class="row g-3 align-items-end">
        <div class="col-12 col-md-4 d-grid">
          <button class="btn btn-outline-secondary" type="button" onclick="viewStatusesForDate()">View team statuses (date)</button>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label mb-1">Start</label>
          <input type="date" class="form-control" id="statusStart" value="{{ \Illuminate\Support\Carbon::parse($date)->copy()->subDays(6)->toDateString() }}">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label mb-1">End</label>
          <input type="date" class="form-control" id="statusEnd" value="{{ $date }}">
        </div>
        <div class="col-12 col-md-2 d-grid">
          <button class="btn btn-outline-secondary" type="button" onclick="viewStatusesForRange()">View statuses (range)</button>
        </div>
      </div>
    </div>
  </div>
  @push('modals')
  <div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="reportTitle">Report</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <span class="badge bg-secondary" id="engineLabelBadge">Engine</span>
          </div>
          <div class="report-controls p-3 mb-3 d-none" id="reportRefinePanel">
            <div class="d-flex flex-column gap-3">
              <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="small text-muted">Refine spoken summary:</span>
                <button class="btn btn-sm btn-outline-primary" id="refineShortenBtn" type="button">Shorten</button>
                <button class="btn btn-sm btn-outline-primary" id="refineBulletizeBtn" type="button">Bulletize</button>
                <button class="btn btn-sm btn-outline-primary" id="refineExecutiveBtn" type="button">Exec View</button>
                <button class="btn btn-sm btn-outline-primary" id="refineBlockersBtn" type="button">Blockers First</button>
                <button class="btn btn-sm btn-outline-primary" id="refineSlackBtn" type="button">Slack Style</button>
                <button class="btn btn-sm btn-outline-secondary d-none" id="refineResetBtn" type="button">Reset</button>
              </div>
              <div class="d-flex gap-2 align-items-start flex-column flex-md-row">
                <input class="form-control" id="refinePromptInput" type="text" maxlength="500" placeholder="Refine this report... e.g. make it less formal and 5 bullets max">
                <button class="btn btn-primary" id="refinePromptBtn" type="button">Apply Prompt</button>
              </div>
              <div id="savedRefinementsList" class="d-none mt-2">
                <div class="small fw-semibold text-muted mb-2">Saved Versions:</div>
                <div class="d-flex flex-column gap-2" id="savedRefinementsContainer">
                  <!-- dynamic versions will go here -->
                </div>
              </div>
              <div class="small text-muted d-none" id="reportRefineStatus"></div>
            </div>
          </div>
          <div class="alert alert-warning d-none" id="staleAlert" role="alert">
            <div class="d-flex align-items-center justify-content-between">
              <span>This report is out of date because the underlying entries have changed since it was generated.</span>
              <button class="btn btn-sm btn-warning ms-3" id="staleRegenerateBtn" type="button">Regenerate now</button>
            </div>
          </div>
          <div class="alert alert-danger d-none" id="fallbackAlert" role="alert">
            <div class="fw-semibold mb-1">Report generation failed</div>
            <div class="small">The output below is a simple concatenation of entries. The LLM could not generate a summary.</div>
            <div class="small text-muted mt-1" id="fallbackError"></div>
          </div>
          <div class="markdown-preview" id="reportHtml"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-primary" id="copyBtn" type="button">Copy Markdown</button>
          <button class="btn btn-outline-secondary" id="copyHtmlBtn" type="button">Copy Formatted</button>
          <button class="btn btn-outline-info" id="regenerateBtn" type="button">Regenerate</button>
          <button class="btn btn-primary" data-bs-dismiss="modal" type="button">Close</button>
        </div>
      </div>
    </div>
  </div>
  <!-- Global Loading Overlay -->
  <div id="loadingOverlay" class="loading-overlay d-none">
    <div class="d-flex align-items-center p-3 bg-white border rounded shadow">
      <div class="spinner-border text-primary me-2" role="status" aria-hidden="true"></div>
      <div class="loading-text">Loading…</div>
    </div>
  </div>
  @foreach($teamUsers as $u)
  @if(auth()->user()->admin || $u->id === auth()->id())
  <div class="modal fade" id="editUserModal-{{ $u->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit User - {{ $u->name }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="{{ route('team.users.update', $u) }}">
          @csrf
          @method('PUT')
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Name</label>
              <input type="text" class="form-control" name="name" value="{{ $u->name }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" value="{{ $u->email }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Password (leave blank to keep)</label>
              <input type="password" class="form-control" name="password" placeholder="••••••">
            </div>
            @if(auth()->user()->admin)
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="adminCheck-{{ $u->id }}" name="admin" value="1" {{ $u->admin ? 'checked' : '' }}>
              <label class="form-check-label" for="adminCheck-{{ $u->id }}">Admin</label>
            </div>
            @endif
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  @endif
  @endforeach
  <!-- Add User Modal -->
  <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="{{ route('team.users.store') }}">
          @csrf
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Name</label>
              <input type="text" class="form-control" name="name" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" class="form-control" name="password" value="password" required>
              <div class="form-text">Default is "password" for demo. Change after creating.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Add</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  @endpush
</div>
<!-- New Dashboard UI -->
<div class="row g-4 align-items-start">
  <!-- Left: My Daily Update -->
  <div class="col-12 col-lg-8">
    <!-- Bus Projects card -->
    <div class="page-card p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h5 class="mb-0">Bus Projects (This Month)</h5>
        <button class="btn btn-sm btn-outline-primary" type="button" onclick="toggleAddBusProject()">Add</button>
      </div>
      @if(($busProjects ?? collect())->isEmpty())
        <div class="text-muted small mb-2">No bus projects for this month.</div>
      @else
        <div class="list-group mb-2">
          @foreach($busProjects as $p)
            <div class="list-group-item">
              <div class="d-flex align-items-center justify-content-between">
                <div class="me-2">
                  <div class="fw-semibold">{{ $p->project_name }}</div>
                  @if(($p->project_description ?? '') !== '')
                    <div class="text-muted small">{{ $p->project_description }}</div>
                  @endif
                </div>

                <div class="d-flex align-items-center gap-2">
                  <button class="btn btn-sm btn-outline-secondary" type="button" onclick="toggleEditBusProject({{ $p->id }})">Edit</button>
                  <form method="POST" action="{{ route('bus_project.destroy', $p) }}" onsubmit="return confirm('Remove this project from the bus?');" class="m-0">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                  </form>
                </div>

              </div>

              <div id="editBusProjectForm-{{ $p->id }}" class="mt-2 d-none">
                <form method="POST" action="{{ route('bus_project.update', $p) }}" class="row g-2">
                  @csrf
                  @method('PUT')
                  <div class="col-12 col-md-5">
                    <label class="form-label mb-1">Project Name</label>
                    <input type="text" class="form-control" name="project_name" value="{{ $p->project_name }}" required>
                  </div>
                  <div class="col-12 col-md-7">
                    <label class="form-label mb-1">Description</label>
                    <input type="text" class="form-control" name="project_description" value="{{ $p->project_description }}">
                  </div>
                  <div class="col-12 d-grid d-md-flex justify-content-md-end mt-2 gap-2">
                    <button class="btn btn-primary" type="submit">Save</button>
                    <button class="btn btn-outline-secondary" type="button" onclick="toggleEditBusProject({{ $p->id }})">Cancel</button>
                  </div>
                </form>
              </div>

            </div>
          @endforeach
        </div>
      @endif

      <div id="addBusProjectForm" class="mt-2 d-none">
        <hr class="my-3"/>
        <form method="POST" action="{{ route('bus_project.store') }}" class="row g-2">
          @csrf
          <div class="col-12 col-md-5">
            <label class="form-label mb-1">Project Name</label>
            <input type="text" class="form-control" name="project_name" required placeholder="Enter name">
          </div>
          <div class="col-12 col-md-7">
            <label class="form-label mb-1">Description</label>
            <input type="text" class="form-control" name="project_description" placeholder="Optional">
          </div>
          <div class="col-12 d-grid d-md-flex justify-content-md-end mt-2">
            <button class="btn btn-primary" type="submit">Add Bus Project</button>
          </div>
        </form>
      </div>

    </div>
    <div class="page-card p-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0 fw-semibold">MY DAILY UPDATE FOR {{ \Illuminate\Support\Carbon::parse($date)->format('m/d/Y') }}</h4>
      </div>

      <form method="POST" action="{{ route('entries.publish') }}" id="publishFormNew">
        @csrf
        <input type="hidden" name="entry_date" value="{{ $date }}"/>
        <input type="hidden" name="as_user_id" id="as_user_id" value="{{ auth()->id() }}" />
        <!-- unified textarea is rendered below inside editorContainer -->

        <div class="position-relative" id="editorContainer">
          <div id="editorLoadingNew" class="textarea-overlay d-none">
            <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
          </div>
          <div id="richEditor"></div>
          <textarea id="contentField" name="content" class="d-none">{{ old('content', $myEntry->content ?? '') }}</textarea>
        </div>

        <div class="text-muted small mb-2">
          Posting as:
          <span class="badge bg-info text-dark" id="postAsName">{{ auth()->user()->name }} (You)</span>
          <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="resetPostAs()">Post as me</button>
        </div>
        <div class="d-flex justify-content-end gap-2 flex-wrap">
          <button type="button" class="btn btn-outline-primary" id="copyPreviousUpdateBtn" title="Copy update from {{ $previousEntryLabel }}">Copy My Previous Update</button>
          <button class="btn btn-primary">Submit My Update</button>
        </div>
      </form>
    </div>

    <!-- Statuses card beneath the daily update -->
    <div class="page-card p-4 mt-4">
      <h5 class="mb-3">Statuses</h5>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <div class="subcard p-3 h-100 d-flex flex-column justify-content-between">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="small text-muted">Quick View</div>
                <div class="fw-semibold">Team status for {{ \Illuminate\Support\Carbon::parse($date)->format('m/d/Y') }}</div>
              </div>
              <button class="btn btn-primary" type="button" onclick="viewStatusesForDate()">View</button>
            </div>
            <div class="small text-muted mt-2">Uses the date set in the Team Dashboard.</div>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div class="subcard p-3 h-100">
            <div class="fw-semibold mb-2">Range</div>
            <div class="row g-2 align-items-end">
              <div class="col-6">
                <label class="form-label small mb-1">Start</label>
                <input type="date" class="form-control" id="statusStartNew" value="{{ \Illuminate\Support\Carbon::parse($date)->copy()->startOfWeek(\Illuminate\Support\Carbon::MONDAY)->toDateString() }}">
              </div>
              <div class="col-6">
                <label class="form-label small mb-1">End</label>
                <input type="date" class="form-control" id="statusEndNew" value="{{ \Illuminate\Support\Carbon::parse($date)->copy()->startOfWeek(\Illuminate\Support\Carbon::MONDAY)->addDays(4)->toDateString() }}">
              </div>
              <div class="col-12 d-grid">
                <button class="btn btn-outline-primary" type="button" onclick="viewStatusesForRange()">View Range</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right: Team Dashboard -->
  <div class="col-12 col-lg-4">
    <div class="page-card p-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h5 class="mb-0">TEAM DASHBOARD</h5>
        @if(auth()->user()->admin)
          <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#addUserModal">Add user</button>
        @endif
      </div>

      <div class="mb-3">
        <label class="form-label mb-1">Date:</label>
        <form id="sideDateForm" method="GET" action="{{ route('dashboard') }}" class="input-group">
          <input type="date" name="date" class="form-control" value="{{ $date }}" onchange="document.getElementById('sideDateForm').submit()"/>
          <a class="btn btn-outline-primary" href="{{ route('dashboard') }}" title="Today">Today</a>
        </form>
      </div>

      <div class="small text-muted mb-2">Team Status for {{ \Illuminate\Support\Carbon::parse($date)->format('m/d/Y') }}</div>
      <div class="list-group team-list mb-4">
        @php $submitted = $teamEntries->pluck('user_id')->all(); @endphp
        @foreach($teamUsers as $u)
          <div class="list-group-item list-group-item-action d-flex align-items-center gap-3" role="button" onclick="setPostAs({{ $u->id }}, '{{ addslashes($u->name) }}')" title="Post as {{ $u->name }}">
            <div class="avatar-sm">{{ strtoupper(substr($u->name,0,1)) }}</div>
            <div class="flex-grow-1">
              <div class="{{ $u->id === auth()->id() ? 'fw-semibold' : '' }}">{{ $u->id === auth()->id() ? 'You' : $u->name }}</div>
            </div>
            @if(in_array($u->id, $submitted))
              <span class="status-icon text-success">✓</span>
            @else
              <span class="status-icon text-secondary">🕘</span>
            @endif
            @if(auth()->user()->admin || $u->id === auth()->id())
              <div class="dropdown ms-2" onclick="event.stopPropagation();">
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="User actions">…</button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editUserModal-{{ $u->id }}" onclick="event.preventDefault();">Edit</a></li>
                  @if(auth()->user()->admin && $u->id !== auth()->id())
                  <li><hr class="dropdown-divider"></li>
                  <li>
                    <form method="POST" action="{{ route('team.users.destroy', $u) }}" onsubmit="return confirm('Delete {{ $u->name }}?');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="dropdown-item text-danger">Delete</button>
                    </form>
                  </li>
                  @endif
                </ul>
              </div>
            @endif
          </div>
        @endforeach
      </div>

      @if(!empty($llmEngines))
      <div class="mb-3">
        <label class="form-label mb-1" for="llmEngineSelect">LLM Engine</label>
        <select class="form-select" id="llmEngineSelect">
          @foreach($llmEngines as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      @endif

      <div class="mb-3">
        <div class="fw-semibold mb-2">Reporting</div>
        <button class="btn btn-primary w-100" type="button" onclick="generateDaily()">Generate Standup Report</button>
      </div>

      <div class="fw-semibold mb-2">Generate Team Summary</div>
      <div class="mb-2">
          <label class="form-label mb-1">Start:</label>
          <input type="date" class="form-control" id="weeklyStartNew" value="{{ \Illuminate\Support\Carbon::parse($date)->copy()->startOfWeek(\Illuminate\Support\Carbon::MONDAY)->toDateString() }}">
      </div>
      <div class="mb-3">
        <label class="form-label mb-1">End:</label>
        <input type="date" class="form-control" id="weeklyEndNew" value="{{ \Illuminate\Support\Carbon::parse($date)->copy()->startOfWeek(\Illuminate\Support\Carbon::MONDAY)->addDays(4)->toDateString() }}">
      </div>
      <div class="d-grid">
        <button class="btn btn-primary" type="button" onclick="generateWeekly()">Generate Weekly Summary</button>
      </div>

    </div>
    <!-- Azure DevOps Work Items -->
    <div class="page-card p-4 mt-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h5 class="mb-0">Azure DevOps Work Items</h5>
      </div>
      @php
        $adoError = $adoSummary['error'] ?? null;
        $adoCounts = $adoSummary['counts'] ?? [];
        $adoOrg = $adoSummary['organization_url'] ?? '';
        $adoProject = $adoSummary['project'] ?? null;
      @endphp
      @if($adoError)
        <div class="alert alert-warning">
          <div class="fw-semibold">Not available</div>
          <div class="small">{{ $adoError }}</div>
        </div>
      @else
        @if(empty($adoCounts))
          <div class="text-muted small">No matching work items for the configured area path(s).</div>
        @else
          @if($adoOrg)
            <div class="small text-muted mb-2">
              <span>Org:</span>
              <a href="{{ $adoOrg }}" target="_blank" rel="noopener">{{ $adoOrg }}</a>
              @if($adoProject)
                <span class="ms-2">Project:</span>
                <span class="fw-semibold">{{ $adoProject }}</span>
              @endif
            </div>
          @endif
          @foreach($adoCounts as $area => $counts)
            <div class="mb-3">
              <div class="fw-semibold mb-1">Area Path: {{ $area }}</div>
              @if(empty($counts))
                <div class="text-muted small">No non-blacklisted states</div>
              @else
                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th style="width:70%">State</th>
                        <th class="text-end" style="width:30%">Count</th>
                      </tr>
                    </thead>
                    <tbody>
                    @foreach($counts as $state => $count)
                      <tr>
                        <td>{{ $state }}</td>
                        <td class="text-end">{{ (int) $count }}</td>
                      </tr>
                    @endforeach
                    </tbody>
                  </table>
                </div>
              @endif
            </div>
          @endforeach
        @endif
      @endif
    </div>
  </div>
</div>
@endsection

@push('head')
<link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/toastui-editor.min.css">
<style>
  /* Ensure editor uses theme foreground color */
  .toastui-editor-defaultUI { color: var(--bs-body-color); }
  .toastui-editor-defaultUI .toastui-editor-toolbar-icons { color: var(--bs-body-color); }
  #richEditor { min-height: 300px; }
  /* Fit editor inside card without extra border clash */
  .toastui-editor-defaultUI { border-color: var(--bs-border-color-translucent, rgba(0,0,0,.125)); }
  .toastui-editor-defaultUI-toolbar { border-color: var(--bs-border-color-translucent, rgba(0,0,0,.125)); }
  .toastui-editor-contents { background: var(--bs-body-bg); color: var(--bs-body-color); }

  /* Dark theme (Cyborg) adjustments */
  html[data-theme-name="cyborg"] .toastui-editor-defaultUI {
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    border-color: var(--bs-border-color-translucent, rgba(255,255,255,0.2));
  }
  html[data-theme-name="cyborg"] .toastui-editor-defaultUI-toolbar {
    background: var(--bs-body-bg);
    border-color: var(--bs-border-color-translucent, rgba(255,255,255,0.2));
  }
  html[data-theme-name="cyborg"] .toastui-editor-contents {
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
  }
  html[data-theme-name="cyborg"] .toastui-editor-ww-container .ProseMirror {
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    caret-color: var(--bs-body-color);
  }
  /* Ensure all content text inherits light color */
  html[data-theme-name="cyborg"] .toastui-editor-contents,
  html[data-theme-name="cyborg"] .toastui-editor-contents * {
    color: var(--bs-body-color) !important;
  }
  html[data-theme-name="cyborg"] .toastui-editor-contents a { color: var(--bs-link-color) !important; }
  /* Invert toolbar SVG icon sprite for dark background */
  html[data-theme-name="cyborg"] .toastui-editor-defaultUI .toastui-editor-toolbar-icons {
    filter: invert(1) hue-rotate(180deg);
  }
  /* Code blocks readability in dark */
  html[data-theme-name="cyborg"] .toastui-editor-contents pre,
  html[data-theme-name="cyborg"] .toastui-editor-contents code {
    background: var(--bs-tertiary-bg, rgba(255,255,255,0.06));
    color: var(--bs-body-color);
  }
  html[data-theme-name="cyborg"] .toastui-editor-contents hr {
    border-color: var(--bs-border-color-translucent, rgba(255,255,255,0.2));
  }
  html[data-theme-name="cyborg"] .toastui-editor-contents table,
  html[data-theme-name="cyborg"] .toastui-editor-contents th,
  html[data-theme-name="cyborg"] .toastui-editor-contents td {
    border-color: var(--bs-border-color-translucent, rgba(255,255,255,0.2));
  }
  /* Placeholder and popups */
  html[data-theme-name="cyborg"] .toastui-editor-ww-container .ProseMirror p.is-empty::before {
    color: rgba(255,255,255,0.55);
  }
  html[data-theme-name="cyborg"] .toastui-editor-popup {
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    border-color: var(--bs-border-color-translucent, rgba(255,255,255,0.2));
  }
  html[data-theme-name="cyborg"] .toastui-editor-popup input,
  html[data-theme-name="cyborg"] .toastui-editor-popup label {
    color: var(--bs-body-color);
  }
  /* Toolbar separators and tabs */
  html[data-theme-name="cyborg"] .toastui-editor-defaultUI .toastui-editor-toolbar-divider {
    background-color: var(--bs-border-color-translucent, rgba(255,255,255,0.2));
  }
  html[data-theme-name="cyborg"] .toastui-editor-mode-switch .tab-item { color: var(--bs-body-color); }
  html[data-theme-name="cyborg"] .toastui-editor-mode-switch .tab-item.active {
    border-color: var(--bs-border-color-translucent, rgba(255,255,255,0.2));
  }
</style>
@endpush

@push('scripts')
<script src="https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js"></script>
<script>
  const asUserInput = document.querySelector('#publishFormNew #as_user_id') || document.getElementById('as_user_id');
  const selfId = {{ auth()->id() }};
  const selfName = @json(auth()->user()->name);
  const overlay = document.getElementById('loadingOverlay');
  const modalHeader = document.querySelector('#reportModal .modal-header');
  const editorLoading = document.getElementById('editorLoadingNew') || document.getElementById('editorLoading');
  const previousEntryData = @json($previousEntryData);
  const reportRefinePanel = document.getElementById('reportRefinePanel');
  const reportRefineStatus = document.getElementById('reportRefineStatus');
  const refinePromptInput = document.getElementById('refinePromptInput');
  const reportState = {
    kind: null,
    generatedReportId: null,
    originalHtml: '',
    originalMarkdown: '',
    currentHtml: '',
    currentMarkdown: '',
    engine: null,
    savedRefinements: [],
  };

  const copyPrevBtn = document.getElementById('copyPreviousUpdateBtn');
  if (copyPrevBtn){
    if (!previousEntryData.hasEntry || !previousEntryData.content){
      copyPrevBtn.disabled = true;
      copyPrevBtn.classList.add('disabled');
      copyPrevBtn.setAttribute('aria-disabled', 'true');
      if (!copyPrevBtn.getAttribute('title') && previousEntryData.label){
        copyPrevBtn.setAttribute('title', `No update available for ${previousEntryData.label}`);
      }
    } else {
      copyPrevBtn.dataset.originalText = copyPrevBtn.textContent;
      copyPrevBtn.addEventListener('click', function(){
        const content = previousEntryData.content || '';
        const hidden = document.getElementById('contentField');
        const legacyTextarea = document.querySelector('#publishForm textarea[name="content"]');

        if (window.tuiEditor) {
          window.tuiEditor.setMarkdown(content);
        }
        if (hidden) { hidden.value = content; }
        if (!window.tuiEditor && legacyTextarea) {
          legacyTextarea.value = content;
        }

        copyPrevBtn.blur();
        copyPrevBtn.classList.remove('btn-outline-secondary');
        copyPrevBtn.classList.add('btn-success');
        copyPrevBtn.textContent = 'Copied!';

        window.setTimeout(() => {
          copyPrevBtn.classList.remove('btn-success');
          copyPrevBtn.classList.add('btn-outline-secondary');
          copyPrevBtn.textContent = copyPrevBtn.dataset.originalText || 'Copy My Previous Update';
        }, 2000);
      });
    }
  }

  function showLoading(text = 'Loading…'){
    if (overlay){
      const t = overlay.querySelector('.loading-text');
      if (t) t.textContent = text;
      overlay.classList.remove('d-none');
    }
  }
  function hideLoading(){
    if (overlay){ overlay.classList.add('d-none'); }
  }
  function toggleAddBusProject(){
    const form = document.getElementById('addBusProjectForm');
    if (!form) return;
    form.classList.toggle('d-none');
  }
  function toggleEditBusProject(id){
    const form = document.getElementById('editBusProjectForm-'+id);
    if (!form) return;
    form.classList.toggle('d-none');
  }
  function setPostAs(id, name){
    asUserInput.value = id;
    const label = document.getElementById('asLabel-'+id);
    if(label){ label.classList.add('fw-semibold'); }
    const postAs = document.querySelector('#publishFormNew #postAsName') || document.getElementById('postAsName');
    if(postAs){ postAs.textContent = (id === selfId) ? `${selfName} (You)` : name; }
    loadEntryFor(id);
  }

  function getSelectedEngine(){
    const select = document.getElementById('llmEngineSelect');
    if (!select) return null;
    return select.value;
  }

  function setStaleAlert(isStale, regenerateFn){
    const alert = document.getElementById('staleAlert');
    const btn = document.getElementById('staleRegenerateBtn');
    if (!alert) return;
    alert.classList.toggle('d-none', !isStale);
    if (btn && isStale) {
      btn.onclick = () => { regenerateFn(); };
    }
  }

  function setFallbackAlert(isFallback, error){
    const alert = document.getElementById('fallbackAlert');
    const errorEl = document.getElementById('fallbackError');
    if (!alert) return;
    alert.classList.toggle('d-none', !isFallback);
    if (errorEl && isFallback) {
      errorEl.textContent = error || '';
    }
  }

  function setRefineStatus(message = '', tone = 'muted'){
    if (!reportRefineStatus) return;
    reportRefineStatus.classList.toggle('d-none', !message);
    reportRefineStatus.classList.remove('text-muted', 'text-danger', 'text-success');
    reportRefineStatus.classList.add(tone === 'error' ? 'text-danger' : tone === 'success' ? 'text-success' : 'text-muted');
    reportRefineStatus.textContent = message;
  }

  function setRefinePanelVisible(visible){
    if (!reportRefinePanel) return;
    reportRefinePanel.classList.toggle('d-none', !visible);
  }

  function toggleRefineBusy(busy){
    [
      'refineShortenBtn',
      'refineBulletizeBtn',
      'refineExecutiveBtn',
      'refineBlockersBtn',
      'refineSlackBtn',
      'refineResetBtn',
      'refinePromptBtn',
      'refinePromptInput'
    ].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.disabled = !!busy;
    });
  }

  function hasReportChanged(){
    return !!reportState.originalMarkdown && reportState.currentMarkdown !== reportState.originalMarkdown;
  }

  function updateResetButton(){
    const btn = document.getElementById('refineResetBtn');
    if (!btn) return;
    btn.classList.toggle('d-none', !hasReportChanged());
  }

  function getRefinementLabel(refinement){
    if (!refinement) return '';
    if (refinement.mode === 'custom') {
      return refinement.prompt ? `Custom: ${refinement.prompt}` : 'Custom refinement';
    }
    return ({
      shorten: 'Shorten',
      bulletize: 'Bulletize',
      executive: 'Exec View',
      blockers: 'Blockers First',
      slack: 'Slack Style'
    })[refinement.mode] || refinement.mode;
  }

  function renderSavedRefinements(){
    const container = document.getElementById('savedRefinementsContainer');
    const listWrapper = document.getElementById('savedRefinementsList');
    if (!container || !listWrapper) return;
    
    const refinements = reportState.savedRefinements || [];
    if (refinements.length === 0) {
      listWrapper.classList.add('d-none');
      return;
    }
    
    listWrapper.classList.remove('d-none');
    container.innerHTML = '';
    
    refinements.forEach(ref => {
      const label = getRefinementLabel(ref);
      const staleBadge = ref.stale ? '<span class="badge bg-warning text-dark ms-2">Stale</span>' : '';
      
      const div = document.createElement('div');
      div.className = 'd-flex align-items-center justify-content-between p-2 subcard';
      
      const nameDiv = document.createElement('div');
      nameDiv.className = 'small fw-medium';
      nameDiv.innerHTML = `${escapeHtml(label)}${staleBadge}`;
      
      const actionsDiv = document.createElement('div');
      actionsDiv.className = 'd-flex gap-2';
      
      const openBtn = document.createElement('button');
      openBtn.className = 'btn btn-sm btn-outline-secondary';
      openBtn.textContent = 'Open';
      openBtn.onclick = () => {
        renderReport(ref.html, ref.markdown);
        setRefineStatus(ref.stale ? `Showing saved version "${label}" (Stale).` : `Showing saved version "${label}".`, ref.stale ? 'muted' : 'success');
      };
      
      const regenBtn = document.createElement('button');
      regenBtn.className = 'btn btn-sm btn-outline-primary';
      regenBtn.textContent = 'Regenerate';
      regenBtn.onclick = () => refineReport(ref.mode, ref.prompt || '', reportState.originalMarkdown);
      
      actionsDiv.appendChild(openBtn);
      actionsDiv.appendChild(regenBtn);
      
      div.appendChild(nameDiv);
      div.appendChild(actionsDiv);
      
      container.appendChild(div);
    });
  }

  function getCurrentReportHtml(){
    return document.getElementById('reportHtml')?.innerHTML || '';
  }

  function renderReport(html, markdown){
    const root = document.getElementById('reportHtml');
    if (!root) return;
    root.innerHTML = html;
    enhanceReportMarkup(root, reportState.kind);
    reportState.currentHtml = root.innerHTML;
    if (typeof markdown === 'string') {
      reportState.currentMarkdown = markdown;
      updateResetButton();
    }
  }

  function resetReportState(){
    reportState.kind = null;
    reportState.generatedReportId = null;
    reportState.originalHtml = '';
    reportState.originalMarkdown = '';
    reportState.currentHtml = '';
    reportState.currentMarkdown = '';
    reportState.engine = null;
    reportState.savedRefinements = [];
    setRefineStatus('');
    if (refinePromptInput) refinePromptInput.value = '';
    renderSavedRefinements();
  }

  function initializeReportState(kind, markdown, html, generatedReportId, savedRefinements){
    reportState.kind = kind;
    reportState.generatedReportId = generatedReportId || null;
    reportState.originalHtml = html || '';
    reportState.originalMarkdown = markdown || '';
    reportState.currentHtml = html || '';
    reportState.currentMarkdown = markdown || '';
    reportState.engine = getSelectedEngine();
    reportState.savedRefinements = savedRefinements || [];
    setRefinePanelVisible(kind === 'daily' || kind === 'weekly');
    if (reportState.savedRefinements.length > 0) {
      const activeCount = reportState.savedRefinements.filter(r => !r.stale).length;
      setRefineStatus(activeCount > 0 ? `${reportState.savedRefinements.length} saved version(s) available.` : `${reportState.savedRefinements.length} saved version(s) available, but some/all are stale.`, activeCount > 0 ? 'success' : 'muted');
    } else {
      setRefineStatus(kind === 'daily' ? 'Tip: refine the spoken summary without regenerating the whole report.' : '');
    }
    updateResetButton();
    renderSavedRefinements();
  }

  function enhanceReportMarkup(root, kind){
    if (!(root instanceof HTMLElement)) return;
    const children = Array.from(root.children);
    children.forEach(node => {
      if (node.tagName === 'HR') node.remove();
    });

    let currentSection = null;
    Array.from(root.children).forEach(node => {
      if (!/^H[1-3]$/.test(node.tagName)) {
        if (currentSection) currentSection.appendChild(node);
        return;
      }

      const title = (node.textContent || '').trim().toLowerCase();
      const section = document.createElement('section');
      section.className = 'report-section';
      let sectionType = 'other';
      let label = node.textContent || '';
      let eyebrow = 'Report Section';

      if (title === 'summary') {
        section.classList.add('report-section-primary');
        sectionType = 'summary';
        label = kind === 'daily' ? 'Spoken Standup' : 'Key Summary';
        eyebrow = 'Primary View';
      } else if (title === 'briefdown') {
        section.classList.add('report-section-secondary');
        sectionType = 'briefdown';
        label = 'Structured Notes';
        eyebrow = 'Details';
      } else if (title === 'tickets') {
        section.classList.add('report-section-tertiary');
        sectionType = 'tickets';
        label = 'Tickets';
        eyebrow = 'Tracking';
      }

      section.dataset.sectionType = sectionType;

      const header = document.createElement('div');
      header.className = 'report-section-header';
      header.innerHTML = `<div><div class="report-section-label">${eyebrow}</div><h2 class="h3 mb-0">${escapeHtml(label)}</h2></div>`;
      section.appendChild(header);
      root.appendChild(section);
      currentSection = section;
      node.remove();
    });
  }

  async function refineReport(mode, instruction = '', sourceMarkdown = null){
    const markdownSource = sourceMarkdown ?? reportState.currentMarkdown;
    if (!markdownSource || !reportState.generatedReportId) return;
    toggleRefineBusy(true);
    setRefineStatus('Refining report...', 'muted');
    try {
      const res = await fetch(`{{ route('reports.refine') }}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': @json(csrf_token()),
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
          generated_report_id: reportState.generatedReportId,
          markdown: markdownSource,
          mode,
          instruction,
          engine: getSelectedEngine()
        })
      });
      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.message || 'Refinement failed.');
      }
      renderReport(data.html, data.markdown);
      reportState.savedRefinements = data.savedRefinements || [];
      renderSavedRefinements();
      
      const newRef = reportState.savedRefinements.find(r => r.mode === mode && r.prompt === (instruction || null));
      const actionLabel = getRefinementLabel(newRef || { mode, prompt: instruction });
      setRefineStatus(data.isFallback ? `Saved ${actionLabel}, using a lightweight fallback rewrite because the AI refinement step was unavailable.` : `Saved ${actionLabel}.`, data.isFallback ? 'muted' : 'success');
    } catch (error) {
      setRefineStatus(error.message || 'Refinement failed.', 'error');
    } finally {
      toggleRefineBusy(false);
    }
  }

  // Reuse a single Bootstrap Modal instance and clean up stray backdrops on hide
  const reportModalEl = document.getElementById('reportModal');
  if (reportModalEl) {
    reportModalEl.addEventListener('hidden.bs.modal', function () {
      resetReportState();
      setRefinePanelVisible(false);
      document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('padding-right');
      document.body.style.removeProperty('overflow');
    });
  }

  async function generateDaily(regenerate = false){
    const date = new URLSearchParams(window.location.search).get('date') || '{{ $date }}';
    const engineValue = getSelectedEngine();
    const engine = engineValue ? `&engine=${encodeURIComponent(engineValue)}` : '';
    const regenParam = regenerate ? '&regenerate=1' : '';
    showLoading('Generating daily report…');
    try {
      const res = await fetch(`{{ route('reports.daily') }}?date=${encodeURIComponent(date)}${engine}${regenParam}`, { headers: { 'X-Requested-With':'XMLHttpRequest' } });
      const data = await res.json();
      if (modalHeader) modalHeader.classList.remove('d-none');
      initializeReportState('daily', data.markdown, data.html, data.reportId, data.savedRefinements);
      document.getElementById('reportTitle').innerText = data.title + ' - ' + date;
      renderReport(data.html, data.markdown);
      const engineBadge = document.getElementById('engineLabelBadge');
      if (engineBadge) engineBadge.textContent = data.engineLabel || 'Default';
      const copyBtn = document.getElementById('copyBtn');
      copyBtn.onclick = () => navigator.clipboard.writeText(buildReportMarkdownForCopy(reportState.currentMarkdown || data.markdown));
      const copyHtmlBtn = document.getElementById('copyHtmlBtn');
      copyHtmlBtn.onclick = () => copyFormatted(buildReportHtmlForCopy(document.getElementById('reportHtml')));
      const regenerateBtn = document.getElementById('regenerateBtn');
      regenerateBtn.onclick = () => generateDaily(true);
      setStaleAlert(data.stale === true, () => generateDaily(true));
      setFallbackAlert(data.isFallback === true, data.error);
      const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('reportModal'));
      modal.show();
    } finally {
      hideLoading();
    }
  }

  function toIsoLocal(d){
    const z = new Date(d.getTime() - d.getTimezoneOffset() * 60000);
    return z.toISOString().slice(0,10);
  }
  function monFriRange(baseStr){
    let b = baseStr ? new Date(baseStr) : new Date('{{ $date }}');
    if (isNaN(b.getTime())) b = new Date('{{ $date }}');
    const day = b.getDay(); // 0=Sun..6=Sat
    const diffToMon = (day + 6) % 7; // days since Monday
    const mon = new Date(b);
    mon.setDate(b.getDate() - diffToMon);
    const fri = new Date(mon);
    fri.setDate(mon.getDate() + 4);
    return { start: toIsoLocal(mon), end: toIsoLocal(fri) };
  }
  async function generateWeekly(regenerate = false){
    const base = document.getElementById('weeklyEndNew')?.value || document.getElementById('weeklyEnd')?.value || '{{ $date }}';
    const range = monFriRange(base);
    const sNew = document.getElementById('weeklyStartNew'); if (sNew) sNew.value = range.start;
    const eNew = document.getElementById('weeklyEndNew'); if (eNew) eNew.value = range.end;
    const sOld = document.getElementById('weeklyStart'); if (sOld) sOld.value = range.start;
    const eOld = document.getElementById('weeklyEnd'); if (eOld) eOld.value = range.end;
    const engineValue = getSelectedEngine();
    const engine = engineValue ? `&engine=${encodeURIComponent(engineValue)}` : '';
    const regenParam = regenerate ? '&regenerate=1' : '';
    showLoading('Generating weekly report…');
    try {
      const res = await fetch(`{{ route('reports.weekly') }}?start=${encodeURIComponent(range.start)}&end=${encodeURIComponent(range.end)}${engine}${regenParam}`, { headers: { 'X-Requested-With':'XMLHttpRequest' } });
      const data = await res.json();
      if (modalHeader) modalHeader.classList.remove('d-none');
      initializeReportState('weekly', data.markdown, data.html, data.reportId, data.savedRefinements);
      document.getElementById('reportTitle').innerText = data.title + ` (${data.start} → ${data.end})`;
      renderReport(data.html, data.markdown);
      const engineBadge = document.getElementById('engineLabelBadge');
      if (engineBadge) engineBadge.textContent = data.engineLabel || 'Default';
      const copyBtn = document.getElementById('copyBtn');
      copyBtn.onclick = () => navigator.clipboard.writeText(buildReportMarkdownForCopy(reportState.currentMarkdown || data.markdown));
      const copyHtmlBtn = document.getElementById('copyHtmlBtn');
      copyHtmlBtn.onclick = () => copyFormatted(buildReportHtmlForCopy(document.getElementById('reportHtml')));
      const regenerateBtn = document.getElementById('regenerateBtn');
      regenerateBtn.onclick = () => generateWeekly(true);
      setStaleAlert(data.stale === true, () => generateWeekly(true));
      setFallbackAlert(data.isFallback === true, data.error);
      const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('reportModal'));
      modal.show();
    } finally {
      hideLoading();
    }
  }

  // Auto-adjust when end date changes in either UI block
  const weeklyEndNewEl = document.getElementById('weeklyEndNew');
  if (weeklyEndNewEl) weeklyEndNewEl.addEventListener('change', function(){
    const r = monFriRange(this.value);
    const s = document.getElementById('weeklyStartNew'); if (s) s.value = r.start;
    this.value = r.end;
  });
  const weeklyEndEl = document.getElementById('weeklyEnd');
  if (weeklyEndEl) weeklyEndEl.addEventListener('change', function(){
    const r = monFriRange(this.value);
    const s = document.getElementById('weeklyStart'); if (s) s.value = r.start;
    this.value = r.end;
  });

  function resetPostAs(){
    setPostAs(selfId, selfName);
  }

  async function viewStatusesForDate(){
    const date = new URLSearchParams(window.location.search).get('date') || '{{ $date }}';
    showLoading('Loading statuses…');
    try {
      const res = await fetch(`{{ route('statuses.date') }}?date=${encodeURIComponent(date)}`, { headers: { 'X-Requested-With':'XMLHttpRequest' } });
      const data = await res.json();
      if (modalHeader) modalHeader.classList.add('d-none'); // hide header for statuses
      resetReportState();
      setRefinePanelVisible(false);
      setStaleAlert(false);
      setFallbackAlert(false);
      document.getElementById('reportTitle').innerText = `Statuses - ${date}`;
      document.getElementById('reportHtml').innerHTML = data.html;
      initStatusFilters(document.getElementById('reportHtml'));
      const copyBtn = document.getElementById('copyBtn');
      copyBtn.onclick = () => {
        const root = document.getElementById('reportHtml');
        const md = buildRangeMarkdown(root);
        navigator.clipboard.writeText(md || stripHtml(root?.innerHTML || ''));
      };
      const copyHtmlBtn = document.getElementById('copyHtmlBtn');
      copyHtmlBtn.onclick = () => copyFormatted(document.getElementById('reportHtml'));
      const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('reportModal'));
      modal.show();
    } finally {
      hideLoading();
    }
  }

  async function viewStatusesForRange(){
    const start = document.getElementById('statusStartNew')?.value || document.getElementById('statusStart')?.value;
    const end = document.getElementById('statusEndNew')?.value || document.getElementById('statusEnd')?.value;
    showLoading('Loading statuses…');
    try {
      const res = await fetch(`{{ route('statuses.range') }}?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`, { headers: { 'X-Requested-With':'XMLHttpRequest' } });
      const data = await res.json();
      if (modalHeader) modalHeader.classList.add('d-none'); // hide header for statuses
      resetReportState();
      setRefinePanelVisible(false);
      setStaleAlert(false);
      setFallbackAlert(false);
      document.getElementById('reportTitle').innerText = `Statuses - ${data.start} - ${data.end}`;
      document.getElementById('reportHtml').innerHTML = data.html;
      initStatusFilters(document.getElementById('reportHtml'));
      const copyBtn = document.getElementById('copyBtn');
      copyBtn.onclick = () => {
        const root = document.getElementById('reportHtml');
        const md = buildRangeMarkdown(root);
        navigator.clipboard.writeText(md || stripHtml(root?.innerHTML || ''));
      };
      const copyHtmlBtn = document.getElementById('copyHtmlBtn');
      copyHtmlBtn.onclick = () => copyFormatted(document.getElementById('reportHtml'));
      const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('reportModal'));
      modal.show();
    } finally {
      hideLoading();
    }
  }

  function stripHtml(html){
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
  }

  function escapeHtml(str){
    return (str || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  function inlineFormat(text){
    return text
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/`([^`]+)`/g, '<code>$1</code>');
  }

  function markdownishToHtml(text){
    const safe = escapeHtml(text || '');
    const lines = safe.split(/\r?\n/);
    const out = [];
    let inList = false;
    const closeList = () => { if (inList) { out.push('</ul>'); inList = false; } };
    for (const line of lines){
      const trimmed = line.trim();
      if (!trimmed) { closeList(); continue; }
      const m = trimmed.match(/^[*-]\s+(.*)$/);
      if (m){
        if (!inList) { out.push('<ul>'); inList = true; }
        out.push(`<li>${inlineFormat(m[1])}</li>`);
      } else {
        closeList();
        out.push(`<p>${inlineFormat(trimmed)}</p>`);
      }
    }
    closeList();
    return out.join('');
  }

  function buildRangeHtml(root){
    const container = root instanceof HTMLElement ? root : null;
    if (!container) return '';
    const sections = [];
    container.querySelectorAll('.accordion-item').forEach(item => {
      if (item.classList.contains('d-none')) return;
      const date = item.querySelector('.accordion-button')?.textContent.trim();
      if (!date) return;
      const rows = [];
      item.querySelectorAll('.entry-item').forEach(entry => {
        if (entry.classList.contains('d-none')) return;
        const user = entry.querySelector('.fw-semibold')?.textContent.trim() || 'Unknown';
        const body = entry.querySelector('.mt-2')?.innerText || '';
        const bodyHtml = markdownishToHtml(body);
        rows.push(
          `<div class="mb-3"><div><strong>${escapeHtml(user)}</strong></div>${bodyHtml ? `<div class="mt-1">${bodyHtml}</div>` : ''}</div>`
        );
      });
      if (rows.length) sections.push(`<h3>${escapeHtml(date)}</h3>${rows.join('')}`);
    });
    return sections.join('') || container.innerHTML;
  }

  function stripSummarySectionFromMarkdown(markdown){
    if (!markdown) return '';

    const normalized = markdown.replace(/\r\n/g, '\n').trim();
    const summaryHeader = normalized.match(/^# Summary\s*$/m);
    if (!summaryHeader || summaryHeader.index === undefined) {
      return normalized;
    }

    const start = summaryHeader.index;
    const rest = normalized.slice(start);
    const boundaryMatch = rest.match(/\n(?:---\s*\n|#\s+|##\s+)/);
    const end = boundaryMatch && boundaryMatch.index !== undefined
      ? start + boundaryMatch.index + 1
      : normalized.length;

    const before = normalized.slice(0, start).trim();
    let after = normalized.slice(end).trim();
    after = after.replace(/^---\s*/m, '').trim();

    return [before, after].filter(Boolean).join('\n\n').trim();
  }

  function buildReportMarkdownForCopy(markdown){
    return stripSummarySectionFromMarkdown(markdown || '');
  }

  function buildReportHtmlForCopy(root){
    const container = root instanceof HTMLElement ? root.cloneNode(true) : null;
    if (!container) return '';

    container.querySelectorAll('[data-section-type="summary"]').forEach(section => section.remove());

    return container.innerHTML.trim();
  }

  async function copyFormatted(source){
    const html = source instanceof HTMLElement ? buildRangeHtml(source) : (source || '');
    const plain = stripHtml(html || '');
    if (!plain) return;
    if (navigator.clipboard?.write) {
      const item = new ClipboardItem({
        'text/html': new Blob([html], { type: 'text/html' }),
        'text/plain': new Blob([plain], { type: 'text/plain' })
      });
      await navigator.clipboard.write([item]);
    } else {
      await navigator.clipboard.writeText(plain);
    }
  }

  function buildRangeMarkdown(root){
    const container = root instanceof HTMLElement ? root : null;
    if (!container) return '';
    const sections = [];
    container.querySelectorAll('.accordion-item').forEach(item => {
      if (item.classList.contains('d-none')) return;
      const date = item.querySelector('.accordion-button')?.textContent.trim();
      if (!date) return;
      const rows = [];
      item.querySelectorAll('.entry-item').forEach(entry => {
        if (entry.classList.contains('d-none')) return;
        const user = entry.querySelector('.fw-semibold')?.textContent.trim() || 'Unknown';
        const body = entry.querySelector('.mt-2')?.innerText.trim() || '';
        const indentedBody = body
          ? body.split('\n').map(line => `  ${line}`.replace(/\s+$/, '')).join('\n')
          : '';
        rows.push(indentedBody ? `- **${user}**\n${indentedBody}` : `- **${user}**`);
      });
      if (rows.length) sections.push([`## ${date}`, ...rows].join('\n\n'));
    });
    return sections.join('\n\n').trim();
  }

  function toBullets(text){
    if(!text) return '';
    const lines = text.replace(/\r/g,'').split('\n').map(l=>l.trim()).filter(Boolean);
    return lines.map(l => l.startsWith('-') ? l : `- ${l}`).join('\n');
  }

  function composeMarkdown(){
    // With unified textarea we don't need composition; keep as passthrough
    const contentEl = document.getElementById('contentField');
    return contentEl ? contentEl.value : '';
  }

  function populateFromMarkdown(md){
    // No-op for unified textarea variant
    return;
  }

  // Draft caching helpers
  const DRAFT_KEY_PREFIX = 'reportgen_draft';
  function getDraftKey(){
    const asUserId = document.getElementById('as_user_id').value;
    const date = new URLSearchParams(window.location.search).get('date') || '{{ $date }}';
    return `${DRAFT_KEY_PREFIX}_${asUserId}_${date}`;
  }
  function saveDraft(){
    if (!window.tuiEditor) return;
    const content = window.tuiEditor.getMarkdown();
    const key = getDraftKey();
    if (content.trim()) {
      localStorage.setItem(key, content);
    } else {
      localStorage.removeItem(key);
    }
  }
  function restoreDraft(){
    const hidden = document.getElementById('contentField');
    if (!hidden || hidden.value.trim()) return;
    const key = getDraftKey();
    const draft = localStorage.getItem(key);
    if (draft && window.tuiEditor) {
      window.tuiEditor.setMarkdown(draft);
      hidden.value = draft;
    }
  }

  // Hook new form submit to compose markdown into hidden field
  const publishFormNew = document.getElementById('publishFormNew');
  if (publishFormNew){
    publishFormNew.addEventListener('submit', function(){
      const hidden = document.getElementById('contentField');
      if (hidden && window.tuiEditor){ hidden.value = window.tuiEditor.getMarkdown(); }
      localStorage.removeItem(getDraftKey());
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    const hidden = document.getElementById('contentField');
    const el = document.getElementById('richEditor');
    if (el && window.toastui && window.toastui.Editor){
      window.tuiEditor = new window.toastui.Editor({
        el: el,
        height: '360px',
        initialEditType: 'wysiwyg',
        previewStyle: 'vertical',
        usageStatistics: false,
        toolbarItems: [
          ['heading','bold','italic','strike'],
          ['hr','quote'],
          ['ul','ol','task'],
          ['table','link'],
          ['code','codeblock'],
          ['scrollSync']
        ],
        initialValue: hidden ? hidden.value : ''
      });
    }
    restoreDraft();
  });

  // Auto-save draft every 10 seconds and on page unload
  setInterval(saveDraft, 10000);
  window.addEventListener('beforeunload', saveDraft);

  async function loadEntryFor(userId){
    const date = new URLSearchParams(window.location.search).get('date') || '{{ $date }}';
    if (editorLoading) editorLoading.classList.remove('d-none');
    try {
      const res = await fetch(`{{ route('entries.fetch') }}?user_id=${encodeURIComponent(userId)}&date=${encodeURIComponent(date)}`, { headers: { 'X-Requested-With':'XMLHttpRequest' } });
      if(!res.ok) return;
      const data = await res.json();
      const hidden = document.getElementById('contentField');
      if (data.found) {
        const content = data.content || '';
        if (window.tuiEditor) { window.tuiEditor.setMarkdown(content); }
        if (hidden) { hidden.value = content; }
      } else {
        if (window.tuiEditor) { window.tuiEditor.setMarkdown(''); }
        if (hidden) { hidden.value = ''; }
        restoreDraft();
      }
    } catch (e) {}
    finally { if (editorLoading) editorLoading.classList.add('d-none'); }
  }

  function initStatusFilters(root){
    if (!root) return;
    const cbs = Array.from(root.querySelectorAll('.user-filter'));
    const allBtn = root.querySelector('.filter-select-all');
    const noneBtn = root.querySelector('.filter-select-none');

    function apply(){
      const selected = new Set(cbs.filter(cb => cb.checked).map(cb => cb.value));
      const items = Array.from(root.querySelectorAll('.entry-item'));
      items.forEach(item => {
        const uid = item.getAttribute('data-user-id');
        item.classList.toggle('d-none', !selected.has(uid));
      });
      // Hide accordion groups with no visible items
      const groups = Array.from(root.querySelectorAll('.accordion-item'));
      groups.forEach(g => {
        const visible = g.querySelector('.entry-item:not(.d-none)');
        g.classList.toggle('d-none', !visible);
      });
    }

    cbs.forEach(cb => cb.addEventListener('change', apply));
    if (allBtn) allBtn.addEventListener('click', () => { cbs.forEach(cb => cb.checked = true); apply(); });
    if (noneBtn) noneBtn.addEventListener('click', () => { cbs.forEach(cb => cb.checked = false); apply(); });
    apply();
  }

  document.getElementById('refineShortenBtn')?.addEventListener('click', () => refineReport('shorten'));
  document.getElementById('refineBulletizeBtn')?.addEventListener('click', () => refineReport('bulletize'));
  document.getElementById('refineExecutiveBtn')?.addEventListener('click', () => refineReport('executive'));
  document.getElementById('refineBlockersBtn')?.addEventListener('click', () => refineReport('blockers'));
  document.getElementById('refineSlackBtn')?.addEventListener('click', () => refineReport('slack'));
  document.getElementById('refinePromptBtn')?.addEventListener('click', () => {
    const instruction = refinePromptInput?.value.trim() || '';
    if (!instruction) {
      setRefineStatus('Add a prompt first, like "5 bullets max" or "make it less formal".', 'error');
      return;
    }
    refineReport('custom', instruction);
  });
  document.getElementById('refineResetBtn')?.addEventListener('click', () => {
    if (!reportState.originalMarkdown) return;
    renderReport(reportState.originalHtml, reportState.originalMarkdown);
    setRefineStatus('Restored the original generated report.', 'success');
    setFallbackAlert(false);
  });
</script>
@endpush
