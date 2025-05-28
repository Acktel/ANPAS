{{-- resources/views/partials/nav.blade.php --}}
<nav class="navbar navbar-expand-md navbar-light bg-white sticky-top">
  <div class="container-xl">
    {{-- logo ANPAS --}}
    <a class="navbar-brand" href="{{ route('dashboard') }}">
      <img src="{{ asset('images/logo.png') }}" alt="ANPAS" style="height:40px;">
    </a>

    {{-- toggler per mobile --}}
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      {{-- voci di sinistra --}}
      <ul class="navbar-nav me-auto">

        {{-- Associazioni --}}
        @can('view_any', App\Models\Associazione::class)
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('associazioni.*') ? 'active' : '' }}"
               href="{{ route('associazioni.index') }}">
              Associazioni
            </a>
          </li>
        @endcan

        {{-- Convenzioni --}}
        @can('view_any', App\Models\Convenzione::class)
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('convenzioni.*') ? 'active' : '' }}"
               href="{{ route('convenzioni.index') }}">
              Convenzioni
            </a>
          </li>
        @endcan

        {{-- Costi fissi --}}
        @can('view_any', App\Models\Costo::class)
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('costi.*') ? 'active' : '' }}"
               href="{{ route('costi.index') }}">
              Costi fissi
            </a>
          </li>
        @endcan

        {{-- Solo per admin: pannello di controllo --}}
        @role('admin')
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle {{ request()->is('admin/*') ? 'active' : '' }}"
               href="#"
               id="adminMenu"
               role="button"
               data-bs-toggle="dropdown">
              Admin
            </a>
            <ul class="dropdown-menu" aria-labelledby="adminMenu">
              <li>
                {{-- Gestione ruoli utenti --}}
                <a class="dropdown-item {{ request()->routeIs('admin.users.roles.*') ? 'active' : '' }}"
                   href="{{ route('admin.users.roles.edit', ['user' => auth()->id()]) }}">
                  Gestione utenti
                </a>
              </li>
              <li>
                {{-- Ruoli e Permessi --}}
                <a class="dropdown-item {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}"
                   href="{{ route('admin.roles.index') }}">
                  Ruoli & Permessi
                </a>
              </li>
            </ul>
          </li>
        @endrole
      </ul>

      {{-- voci di destra: utente --}}
      <ul class="navbar-nav">
        @auth
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle"
               href="#"
               id="userMenu"
               role="button"
               data-bs-toggle="dropdown">
              {{ Auth::user()->name }}
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
              <li>
                <a class="dropdown-item"
                   href="{{ route('profile.edit') }}">
                  Profilo
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <form method="POST" action="{{ route('logout') }}">
                  @csrf
                  <button type="submit" class="dropdown-item">Logout</button>
                </form>
              </li>
            </ul>
          </li>
        @else
          <li class="nav-item">
            <a class="nav-link" href="{{ route('login') }}">Login</a>
          </li>
        @endauth
      </ul>
    </div>
  </div>
</nav>
