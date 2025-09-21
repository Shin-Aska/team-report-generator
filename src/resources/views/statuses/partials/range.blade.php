<div class="page-card p-0">
  <div class="d-flex align-items-center justify-content-between mb-2 px-3 pt-3">
    <h6 class="mb-0">Team Statuses</h6>
  </div>
  @php
    $users = [];
    foreach ($grouped as $date => $entries) {
      foreach ($entries as $e) { if ($e->user) { $users[$e->user->id] = $e->user->name; } }
    }
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
    @if($grouped->isEmpty())
      <div class="text-muted">No entries.</div>
    @else
      <div class="accordion" id="{{ $accId }}">
        @foreach($grouped as $date => $entries)
          @php $itemId = $accId.'-'.md5($date.uniqid()); @endphp
          <div class="accordion-item">
            <h2 class="accordion-header" id="{{ $itemId }}-hdr">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $itemId }}-col" aria-expanded="false" aria-controls="{{ $itemId }}-col">
                {{ $date }}
              </button>
            </h2>
            <div id="{{ $itemId }}-col" class="accordion-collapse collapse" aria-labelledby="{{ $itemId }}-hdr" data-bs-parent="#{{ $accId }}">
              <div class="accordion-body">
                <div class="list-group">
                  @foreach($entries as $e)
                    <div class="list-group-item entry-item" data-user-id="{{ $e->user_id }}">
                      <div class="fw-semibold">{{ $e->user?->name ?? 'Unknown' }}</div>
                      <div class="mt-2" style="white-space: pre-wrap">{{ $e->content }}</div>
                    </div>
                  @endforeach
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</div>
