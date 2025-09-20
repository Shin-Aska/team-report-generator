@extends('layouts.app')

@section('content')
<div class="page-card p-4">
  <h5 class="mb-3">Team Statuses ({{ $start->toDateString() }} → {{ $end->toDateString() }})</h5>
  @if($grouped->isEmpty())
    <div class="text-muted">No entries.</div>
  @else
    @foreach($grouped as $date => $entries)
      <div class="mb-3">
        <div class="fw-semibold">{{ $date }}</div>
        <div class="list-group">
          @foreach($entries as $e)
            <div class="list-group-item">
              <div class="fw-semibold">{{ $e->user?->name ?? 'Unknown' }}</div>
              <div class="mt-2" style="white-space: pre-wrap">{{ $e->content }}</div>
            </div>
          @endforeach
        </div>
      </div>
    @endforeach
  @endif
</div>
@endsection
