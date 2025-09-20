@extends('layouts.app')

@section('content')
<div class="row g-4">
  <div class="col-12">
    <div class="page-card p-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h4 class="mb-0">Daily Report</h4>
        <div class="text-muted small">Date: {{ $date }}</div>
      </div>
      <div class="markdown-preview">{!! $html !!}</div>
      <hr>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(@js($markdown))">Copy Markdown</button>
        <a class="btn btn-primary" href="{{ route('dashboard', ['date' => $date]) }}">Back to Dashboard</a>
      </div>
    </div>
  </div>
</div>
@endsection
