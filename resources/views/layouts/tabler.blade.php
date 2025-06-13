<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', config('app.name'))</title>

  {{-- Stili compilati da Mix --}}
  <link rel="stylesheet" href="{{ mix('css/app.css') }}">
  @stack('styles')
</head>
<body class="d-flex flex-column bg-white">
  @yield('content')

  {{-- Qui deve esserci il tuo bundle JS --}}
  <script src="{{ mix('js/app.js') }}" defer></script>
  @stack('scripts')
</body>
</html>
