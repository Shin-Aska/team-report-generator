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
        <button class="btn btn-outline-secondary" onclick="copyFormattedHtml(@js($html))">Copy Formatted</button>
        <a class="btn btn-primary" href="{{ route('dashboard', ['date' => $end->toDateString()]) }}">Back to Dashboard</a>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
async function copyFormattedHtml(html){
  if (!html) return;
  const tmp = document.createElement('div');
  tmp.innerHTML = html;
  const plain = tmp.textContent || tmp.innerText || '';
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
</script>
@endpush
