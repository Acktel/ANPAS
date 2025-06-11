<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','ANPAS')</title>

  <link rel="stylesheet" href="{{ mix('css/app.css') }}">
  <link rel="stylesheet" href="{{ mix('css/tabler.min.css') }}">
  <link href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css" rel="stylesheet">

  <!-- CSRF Token -->
  <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
  {{-- Banner di impersonazione --}}
  @if(session()->has('impersonate_original_user'))
    <div class="alert alert-warning text-center mb-0">
      Stai impersonando: <strong>{{ Auth::user()->email }}</strong>
      <form action="{{ route('impersonate.stop') }}" method="POST" style="display:inline-block; margin-left:10px;">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-secondary">Torna al mio account</button>
      </form>
    </div>
  @endif

  <div class="wrapper">
    {{-- Topbar / Navbar --}}
 
    @include('partials.scegliAnno')
    @include('partials.nav')

    <main class="page-wrapper">
      <div class="container-fluid">
        @if(session('status'))
          <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @yield('content')
      </div>
    </main>
  </div>
  <!-- jQuery e DataTables (CDN o tuoi file compilati) -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
  <link  href="https://cdn.datatables.net/1.13.5/css/jquery.dataTables.min.css" rel="stylesheet"/>
  <!-- Include Moment.js -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>

  <!-- il tuo bundle compilato -->
  <script type="module" src="{{ mix('js/app.js') }}"></script>

  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <!-- JS -->
<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
  @stack('scripts')
</body>
</html>
