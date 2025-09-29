@extends('layouts.app')

@section('content')
<style>
  .loading-overlay { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.6); z-index: 2000; }
  .loading-overlay.d-none { display: none !important; }
  .subcard { background: var(--bs-light-bg-subtle, #f8f9fc); color: var(--bs-body-color); border: 1px solid var(--bs-border-color-translucent, rgba(0,0,0,.06)); border-radius: .5rem; }
  .avatar-sm { width: 40px; height: 40px; border-radius: 50%; background: var(--bs-primary-bg-subtle, #e7f1ff); display: inline-flex; align-items:center; justify-content:center; font-weight:600; color: var(--bs-primary, #0d6efd); font-size: .95rem; }
  .team-list .list-group-item { border: 0; border-bottom: 1px solid rgba(0,0,0,.05); }
  .team-list .status-icon { font-size: 1.1rem; }
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
          <div class="markdown-preview" id="reportHtml"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" id="copyBtn" type="button">Copy Markdown</button>
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
        @if(auth()->user()->admin)
          <button class="btn btn-sm btn-outline-primary" type="button" onclick="toggleAddBusProject()">Add</button>
        @endif
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
                @if(auth()->user()->admin)
                  <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="toggleEditBusProject({{ $p->id }})">Edit</button>
                    <form method="POST" action="{{ route('bus_project.destroy', $p) }}" onsubmit="return confirm('Remove this project from the bus?');" class="m-0">
                      @csrf
                      @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                    </form>
                  </div>
                @endif
              </div>
              @if(auth()->user()->admin)
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
              @endif
            </div>
          @endforeach
        </div>
      @endif

      @if(auth()->user()->admin)
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
      @endif
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
          <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="resetPostAs()">Post as me</button>
        </div>
        <div class="d-flex justify-content-end">
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
          <a class="btn btn-outline-secondary" href="{{ route('dashboard') }}" title="Today">Today</a>
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

  async function generateDaily(){
    const date = new URLSearchParams(window.location.search).get('date') || '{{ $date }}';
    showLoading('Generating daily report…');
    try {
      const res = await fetch(`{{ route('reports.daily') }}?date=${encodeURIComponent(date)}`, { headers: { 'X-Requested-With':'XMLHttpRequest' } });
      const data = await res.json();
      if (modalHeader) modalHeader.classList.remove('d-none');
      document.getElementById('reportTitle').innerText = data.title + ' - ' + date;
      document.getElementById('reportHtml').innerHTML = data.html;
      const copyBtn = document.getElementById('copyBtn');
      copyBtn.onclick = () => navigator.clipboard.writeText(data.markdown);
      const modal = new bootstrap.Modal(document.getElementById('reportModal'));
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
  async function generateWeekly(){
    const base = document.getElementById('weeklyEndNew')?.value || document.getElementById('weeklyEnd')?.value || '{{ $date }}';
    const range = monFriRange(base);
    const sNew = document.getElementById('weeklyStartNew'); if (sNew) sNew.value = range.start;
    const eNew = document.getElementById('weeklyEndNew'); if (eNew) eNew.value = range.end;
    const sOld = document.getElementById('weeklyStart'); if (sOld) sOld.value = range.start;
    const eOld = document.getElementById('weeklyEnd'); if (eOld) eOld.value = range.end;
    showLoading('Generating weekly report…');
    try {
      const res = await fetch(`{{ route('reports.weekly') }}?start=${encodeURIComponent(range.start)}&end=${encodeURIComponent(range.end)}`, { headers: { 'X-Requested-With':'XMLHttpRequest' } });
      const data = await res.json();
      if (modalHeader) modalHeader.classList.remove('d-none');
      document.getElementById('reportTitle').innerText = data.title + ` (${data.start} → ${data.end})`;
      document.getElementById('reportHtml').innerHTML = data.html;
      const copyBtn = document.getElementById('copyBtn');
      copyBtn.onclick = () => navigator.clipboard.writeText(data.markdown);
      const modal = new bootstrap.Modal(document.getElementById('reportModal'));
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
      document.getElementById('reportTitle').innerText = `Statuses - ${date}`;
      document.getElementById('reportHtml').innerHTML = data.html;
      initStatusFilters(document.getElementById('reportHtml'));
      const copyBtn = document.getElementById('copyBtn');
      copyBtn.onclick = () => navigator.clipboard.writeText(stripHtml(data.html));
      const modal = new bootstrap.Modal(document.getElementById('reportModal'));
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
      document.getElementById('reportTitle').innerText = `Statuses - ${data.start} → ${data.end}`;
      document.getElementById('reportHtml').innerHTML = data.html;
      initStatusFilters(document.getElementById('reportHtml'));
      const copyBtn = document.getElementById('copyBtn');
      copyBtn.onclick = () => navigator.clipboard.writeText(stripHtml(data.html));
      const modal = new bootstrap.Modal(document.getElementById('reportModal'));
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

  // Hook new form submit to compose markdown into hidden field
  const publishFormNew = document.getElementById('publishFormNew');
  if (publishFormNew){
    publishFormNew.addEventListener('submit', function(){
      const hidden = document.getElementById('contentField');
      if (hidden && window.tuiEditor){ hidden.value = window.tuiEditor.getMarkdown(); }
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
  });

  async function loadEntryFor(userId){
    const date = new URLSearchParams(window.location.search).get('date') || '{{ $date }}';
    if (editorLoading) editorLoading.classList.remove('d-none');
    try {
      const res = await fetch(`{{ route('entries.fetch') }}?user_id=${encodeURIComponent(userId)}&date=${encodeURIComponent(date)}`, { headers: { 'X-Requested-With':'XMLHttpRequest' } });
      if(!res.ok) return;
      const data = await res.json();
      const hidden = document.getElementById('contentField');
      const content = data.found ? data.content : '';
      if (window.tuiEditor) { window.tuiEditor.setMarkdown(content || ''); }
      if (hidden) { hidden.value = content; }
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
</script>
@endpush
