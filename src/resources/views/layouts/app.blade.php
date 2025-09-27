<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Report Generator' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        window.THEME_ASSETS = {
          sketch: "{{ asset('css/bootstrap-sketch-theme.css') }}",
          brite: "{{ asset('css/bootstrap-brite.css') }}",
          cyborg: "{{ asset('css/bootstrap-cyborg.css') }}",
          flatly: "{{ asset('css/bootstrap-flatly.css') }}",
          quartz: "{{ asset('css/bootstrap-quartz.css') }}",
          sandstone: "{{ asset('css/bootstrap-sandstone.css') }}"
        };
        window.THEME_VARIANTS = { // maps theme -> data-bs-theme
          cyborg: 'dark',
          sketch: 'light',
          brite: 'light',
          flatly: 'light',
          quartz: 'light',
          sandstone: 'light'
        };
        (function(){
          function getCookie(name){
            var parts = document.cookie ? document.cookie.split('; ') : [];
            for (var i = 0; i < parts.length; i++){
              var p = parts[i].split('=');
              if (p[0] === name) return decodeURIComponent(p.slice(1).join('='));
            }
            return null;
          }
          var chosen = getCookie('theme') || 'sketch';
          var variant = (window.THEME_VARIANTS && window.THEME_VARIANTS[chosen]) || 'light';
          try { document.documentElement.setAttribute('data-bs-theme', variant); } catch(e) {}
          try { document.documentElement.setAttribute('data-theme-name', chosen); } catch(e) {}
          var href = (window.THEME_ASSETS && window.THEME_ASSETS[chosen]) ? window.THEME_ASSETS[chosen] : window.THEME_ASSETS.sketch;
          document.write('<link rel="stylesheet" id="themeStylesheet" href="' + href + '">');
        })();
    </script>
    <noscript>
        <link href="{{ asset('css/bootstrap-sketch-theme.css') }}" rel="stylesheet">
    </noscript>
    <style>
        body { background: var(--bs-body-bg, #f5f7fb); color: var(--bs-body-color); }
        .page-card { background: var(--bs-card-bg, var(--bs-body-bg)); color: var(--bs-body-color); border: 1px solid var(--bs-border-color-translucent, rgba(0,0,0,.125)); box-shadow: 0 6px 18px rgba(0,0,0,.06); border-radius: 12px; }
        /* Navbar/tooling uses theme-aware variables for contrast */
        .toolbar { background: var(--bs-navbar-bg, var(--bs-body-bg)); color: var(--bs-navbar-color, var(--bs-body-color)); border-bottom: 1px solid var(--bs-border-color-translucent, rgba(0,0,0,.125)); }
        .avatar { width: 64px; height: 64px; border-radius: 50%; background: var(--bs-primary-bg-subtle, #e7f1ff); display: inline-flex; align-items:center; justify-content:center; font-weight:600; color: var(--bs-primary, #0d6efd); }
        .markdown-preview { color: var(--bs-body-color); }
        .markdown-preview h1, .markdown-preview h2, .markdown-preview h3, .markdown-preview h4, .markdown-preview h5, .markdown-preview h6 { margin-top:1.25rem; color: var(--bs-emphasis-color); }
        .markdown-preview a { color: var(--bs-link-color); }
        .markdown-preview pre, .markdown-preview code { color: var(--bs-code-color, var(--bs-emphasis-color)); background: var(--bs-tertiary-bg, transparent); }
        .markdown-preview ul { padding-left: 1.4rem; }
        /* Quartz readability overrides: ensure secondary/subtitle text is dark enough */
        html[data-theme-name="quartz"] { 
          --bs-secondary-color: rgba(33, 37, 41, 0.75);
          --bs-tertiary-color: rgba(33, 37, 41, 0.55);
          --bs-card-color: var(--bs-body-color);
        }
        html[data-theme-name="quartz"] .text-body-secondary,
        html[data-theme-name="quartz"] .text-muted,
        html[data-theme-name="quartz"] .card-subtitle,
        html[data-theme-name="quartz"] .text-secondary,
        html[data-theme-name="quartz"] .subtext,
        html[data-theme-name="quartz"] small {
          color: rgba(33, 37, 41, 0.75) !important;
        }
        html[data-theme-name="quartz"] .card-text { color: var(--bs-body-color) !important; }
        html[data-theme-name="quartz"] .subcard {
          background: var(--bs-tertiary-bg, var(--bs-light-bg-subtle));
          color: var(--bs-body-color) !important;
          border-color: var(--bs-border-color-translucent, rgba(0,0,0,.125));
        }
        html[data-theme-name="quartz"] .subcard a { color: var(--bs-link-color) !important; }
        html[data-theme-name="quartz"] .subcard .fw-semibold,
        html[data-theme-name="quartz"] .subcard .small,
        html[data-theme-name="quartz"] .subcard p,
        html[data-theme-name="quartz"] .subcard div { color: var(--bs-body-color) !important; }
        .loading-overlay { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.6); z-index: 2000; }
        .loading-overlay.d-none { display: none !important; }
        .textarea-overlay { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.6); z-index: 10; }
    </style>
    @stack('head')
</head>
<body>
<nav class="navbar navbar-expand-lg toolbar mb-4">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="{{ route('dashboard') }}">ReportGen</a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <div class="dropdown me-2">
        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          Theme: <span id="currentThemeLabel">Sketch</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item theme-option" data-theme="sketch" href="#">Sketch</a></li>
          <li><a class="dropdown-item theme-option" data-theme="brite" href="#">Brite</a></li>
          <li><a class="dropdown-item theme-option" data-theme="cyborg" href="#">Cyborg</a></li>
          <li><a class="dropdown-item theme-option" data-theme="flatly" href="#">Flatly</a></li>
          <li><a class="dropdown-item theme-option" data-theme="quartz" href="#">Quartz</a></li>
          <li><a class="dropdown-item theme-option" data-theme="sandstone" href="#">Sandstone</a></li>
        </ul>
      </div>
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
<!-- Global Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay d-none">
  <div class="d-flex align-items-center p-3 bg-white border rounded shadow">
    <div class="spinner-border text-primary me-2" role="status" aria-hidden="true"></div>
    <div class="loading-text">Loading…</div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  (function(){
    const overlay = document.getElementById('loadingOverlay');
    window.showLoading = function(text){
      if (!overlay) return;
      const t = overlay.querySelector('.loading-text');
      if (t && text) t.textContent = text; else if (t) t.textContent = 'Loading…';
      overlay.classList.remove('d-none');
    };
    window.hideLoading = function(){
      if (!overlay) return;
      overlay.classList.add('d-none');
    };
  })();
  </script>
  <script>
    (function(){
      function setCookie(name, value, days){
        var expires = '';
        if (days){
          var d = new Date();
          d.setTime(d.getTime() + (days*24*60*60*1000));
          expires = '; expires=' + d.toUTCString();
        }
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
      }
      function getCookie(name){
        var parts = document.cookie ? document.cookie.split('; ') : [];
        for (var i = 0; i < parts.length; i++){
          var p = parts[i].split('=');
          if (p[0] === name) return decodeURIComponent(p.slice(1).join('='));
        }
        return null;
      }
      function updateThemeLabel(theme){
        var label = document.getElementById('currentThemeLabel');
        var display = { sketch: 'Sketch', brite: 'Brite', cyborg: 'Cyborg', flatly: 'Flatly', quartz: 'Quartz', sandstone: 'Sandstone' };
        if (label) label.textContent = display[theme] || 'Sketch';
      }
      function setTheme(theme){
        setCookie('theme', theme, 365);
        var link = document.getElementById('themeStylesheet');
        if (link && window.THEME_ASSETS && window.THEME_ASSETS[theme]){
          link.href = window.THEME_ASSETS[theme];
        }
        var variant = (window.THEME_VARIANTS && window.THEME_VARIANTS[theme]) || 'light';
        try { document.documentElement.setAttribute('data-bs-theme', variant); } catch(e) {}
        try { document.documentElement.setAttribute('data-theme-name', theme); } catch(e) {}
        updateThemeLabel(theme);
      }
      document.addEventListener('DOMContentLoaded', function(){
        var theme = getCookie('theme') || 'sketch';
        var variant = (window.THEME_VARIANTS && window.THEME_VARIANTS[theme]) || 'light';
        try { document.documentElement.setAttribute('data-bs-theme', variant); } catch(e) {}
        updateThemeLabel(theme);
        document.querySelectorAll('.theme-option').forEach(function(el){
          el.addEventListener('click', function(e){
            e.preventDefault();
            var t = el.getAttribute('data-theme');
            if (t) setTheme(t);
          });
        });
      });
    })();
  </script>
@stack('scripts')
</body>
</html>
