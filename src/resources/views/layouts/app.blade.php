<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Report Generator' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f7fb; }
        .page-card { background: #fff; box-shadow: 0 6px 18px rgba(0,0,0,.06); border-radius: 12px; }
        .toolbar { background: #0d6efd; color: #fff; }
        .avatar { width: 64px; height: 64px; border-radius: 50%; background: #e7f1ff; display: inline-flex; align-items:center; justify-content:center; font-weight:600; color:#0d6efd; }
        .markdown-preview h1, .markdown-preview h2, .markdown-preview h3 { margin-top:1.25rem; }
        .markdown-preview ul { padding-left: 1.4rem; }
    </style>
    @stack('head')
</head>
<body>
<nav class="navbar navbar-expand-lg toolbar mb-4">
  <div class="container">
    <a class="navbar-brand text-white fw-semibold" href="{{ route('dashboard') }}">ReportGen</a>
    <div class="ms-auto">
      <form method="POST" action="{{ route('logout') }}" class="d-inline">
        @csrf
        <button class="btn btn-sm btn-light">Logout</button>
      </form>
    </div>
  </div>
</nav>

<main class="container mb-5">
  @yield('content')
  @stack('modals')
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
