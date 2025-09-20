@extends('layouts.app')

@section('content')
<div class="page-card p-4">
  <h5 class="mb-3">Team Statuses for {{ $date }}</h5>
  @if($entries->isEmpty())
    <div class="text-muted">No entries.</div>
  @else
    <div class="list-group">
      @foreach($entries as $e)
        <div class="list-group-item">
          <div class="fw-semibold">{{ $e->user?->name ?? 'Unknown' }}</div>
          <div class="small text-muted">{{ $e->entry_date->toDateString() }}</div>
          <div class="mt-2" style="white-space: pre-wrap">{{ $e->content }}</div>
        </div>
      @endforeach
    </div>
  @endif
</div>
@endsection
