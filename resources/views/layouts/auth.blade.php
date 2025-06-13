{{-- resources/views/layouts/auth.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','Login')</title>

  {{-- 1) Bundle CSS principale (include Bootstrap, Tabler, Tailwind, ecc.) --}}
  <link rel="stylesheet" href="{{ mix('css/app.css') }}">

  {{-- CSRF Token --}}
  <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="page page-center d-flex flex-column">
  {{-- Contenuto specifico della pagina (form di login) --}}
  @yield('body')

  {{-- 2) Bundle JS principale (jQuery, Bootstrap, Tabler, Axios, DataTables, ecc.) --}}
  <script src="{{ mix('js/manifest.js') }}" defer></script>
  <script src="{{ mix('js/vendor.js') }}"  defer></script>
  <script src="{{ mix('js/app.js') }}"     defer></script>

  {{-- 3) Alpine.js (dal CDN, se serve) --}}
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

  @stack('scripts')
</body>
</html>
