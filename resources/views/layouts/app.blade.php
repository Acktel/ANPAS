<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','ANPAS')</title>

  {{-- Carica il CSS bundle (Tabler, demo, app.css) --}}
  <link rel="stylesheet" href="{{ mix('css/app.css') }}">
</head>
<body>
  <div class="wrapper">
    {{-- Topbar / Navbar --}}
    @include('partials.nav')

    <main class="page-wrapper">
      <div class="container-xl">
        @if(session('status'))
          <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @yield('content')
      </div>
    </main>
  </div>

  {{-- Carica il JS bundle (demo, tabler, Alpine, etc.) --}}
  <script src="{{ mix('js/app.js') }}"></script>
</body>
</html>
