@extends('layouts.app')

@section('content')
<style>
  .loading-overlay { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.6); z-index: 2000; }
  .loading-overlay.d-none { display: none !important; }
</style>
<div class="row g-4">
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
          <input type="date" class="form-control" id="weeklyStart" value="{{ \Illuminate\Support\Carbon::parse($date)->copy()->subDays(6)->toDateString() }}">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label mb-1">End</label>
          <input type="date" class="form-control" id="weeklyEnd" value="{{ $date }}">
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
@endsection

@push('scripts')
<script>
  const asUserInput = document.getElementById('as_user_id');
  const selfId = {{ auth()->id() }};
  const selfName = @json(auth()->user()->name);
  const overlay = document.getElementById('loadingOverlay');
  const modalHeader = document.querySelector('#reportModal .modal-header');
  const editorLoading = document.getElementById('editorLoading');

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
  function setPostAs(id, name){
    asUserInput.value = id;
    const label = document.getElementById('asLabel-'+id);
    if(label){ label.classList.add('fw-semibold'); }
    const postAs = document.getElementById('postAsName');
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

  async function generateWeekly(){
    const start = document.getElementById('weeklyStart')?.value || '{{ \Illuminate\Support\Carbon::parse($date)->copy()->subDays(6)->toDateString() }}';
    const end = document.getElementById('weeklyEnd')?.value || '{{ $date }}';
    showLoading('Generating weekly report…');
    try {
      const res = await fetch(`{{ route('reports.weekly') }}?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`, { headers: { 'X-Requested-With':'XMLHttpRequest' } });
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
    const start = document.getElementById('statusStart').value;
    const end = document.getElementById('statusEnd').value;
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

  async function loadEntryFor(userId){
    const date = new URLSearchParams(window.location.search).get('date') || '{{ $date }}';
    if (editorLoading) editorLoading.classList.remove('d-none');
    try {
      const res = await fetch(`{{ route('entries.fetch') }}?user_id=${encodeURIComponent(userId)}&date=${encodeURIComponent(date)}`, { headers: { 'X-Requested-With':'XMLHttpRequest' } });
      if(!res.ok) return;
      const data = await res.json();
      const ta = document.querySelector('textarea[name="content"]');
      if (ta) { ta.value = data.found ? data.content : ''; }
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
