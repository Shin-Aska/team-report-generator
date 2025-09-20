@extends('layouts.app')

@section('content')
<div class="row g-4">
  <div class="col-12">
    <div class="page-card p-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h4 class="mb-0">Weekly Report</h4>
        <div class="text-muted small">Range: {{ $start->toDateString() }} to {{ $end->toDateString() }}</div>
      </div>
      <div class="markdown-preview" id="reportHtml">{!! $html !!}</div>
      <hr>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(@js($markdown))">Copy Markdown</button>
        <a class="btn btn-primary" href="{{ route('dashboard', ['date' => $end->toDateString()]) }}">Back to Dashboard</a>
      </div>
    </div>
  </div>
</div>
@endsection
