<div class="page-card p-0">
  <div class="d-flex align-items-center justify-content-between mb-2 px-3 pt-3">
    <h6 class="mb-0">Team Statuses for {{ $date }}</h6>
  </div>
  @php
    $users = $entries->filter(fn($e) => $e->user)->mapWithKeys(fn($e) => [$e->user->id => $e->user->name])->unique();
    $accId = 'acc-'.uniqid();
  @endphp
  <div class="px-3">
    <div class="statuses-filter mb-3">
      <div class="small text-muted mb-1">Filter by members</div>
      <div class="d-flex flex-wrap gap-2 align-items-center">
        @foreach($users as $uid => $uname)
          <div class="form-check form-check-inline">
            <input class="form-check-input user-filter" type="checkbox" id="filter-{{ $uid }}" value="{{ $uid }}" checked>
            <label class="form-check-label" for="filter-{{ $uid }}">{{ $uname }}</label>
          </div>
        @endforeach
        <div class="ms-auto d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-secondary filter-select-all">All</button>
          <button type="button" class="btn btn-sm btn-outline-secondary filter-select-none">None</button>
        </div>
      </div>
    </div>
  </div>
  <div class="px-3 pb-3">
    @if($entries->isEmpty())
      <div class="text-muted">No entries.</div>
    @else
      <div class="accordion" id="{{ $accId }}">
        <div class="accordion-item">
          <h2 class="accordion-header" id="{{ $accId }}-hdr">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $accId }}-col" aria-expanded="true" aria-controls="{{ $accId }}-col">
              {{ $date }}
            </button>
          </h2>
          <div id="{{ $accId }}-col" class="accordion-collapse collapse show" aria-labelledby="{{ $accId }}-hdr" data-bs-parent="#{{ $accId }}">
            <div class="accordion-body">
              <div class="list-group">
                @foreach($entries as $e)
                  <div class="list-group-item entry-item" data-user-id="{{ $e->user_id }}">
                    <div class="fw-semibold">{{ $e->user?->name ?? 'Unknown' }}</div>
                    <div class="small text-muted">{{ $e->entry_date->toDateString() }}</div>
                    <div class="mt-2" style="white-space: pre-wrap">{{ $e->content }}</div>
                  </div>
                @endforeach
              </div>
            </div>
          </div>
        </div>
      </div>
    @endif
  </div>
</div>
