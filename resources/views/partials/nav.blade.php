{{-- resources/views/partials/nav.blade.php --}}

@auth
@php
$assocCorr = \App\Models\Associazione::getById(Auth::user()->IdAssociazione)->Associazione;
if (!$assocCorr || $assocCorr === 'Default Association') {
$assocCorr = 'ANPAS Piemonte';
}
@endphp
@endauth

<nav class="anpas-topbar">
    <div class="container-flex d-flex align-items-center">

        {{-- Logo a sinistra --}}
        <a href="{{ route('dashboard') }}" class="topbar-logo me-auto">
            <img src="{{ asset('images/logo.png') }}" alt="ANPAS" height="60">
        </a>

        {{-- Riquadro Utente / Anno --}}
        <div class="user-consun-box d-flex align-items-center shadow-sm">
            {{-- Utente --}}
            <div class="user-section text-start px-3">
                <small class="text-anpas-green fw-bold d-block mb-1">Utente</small>
                <div class="fw-bold text-padding-topbar">{{ $assocCorr }}</div>
            </div>

            {{-- Separatore verticale --}}
            <div class="vr mx-0" style="height:5rem; border-color:var(--anpas-green)"></div>

            {{-- Consuntivo Anno --}}
            <form method="POST"
                action="{{ route('anno.set') }}"
                class="consuntivo-section d-flex align-items-center px-3 mb-0">
                @csrf
                <div class="user-section text-start px-3">
                    <small class="text-anpas-green fw-bold me-2 mb-0">Consuntivo Anno</small>
                    <div class="d-flex align-items-center text-padding-topbar">
                        <input
                            type="number"
                            name="anno_riferimento"
                            min="2020" max="{{ date('Y') }}" step="1"
                            value="{{ session('anno_riferimento', date('Y')) }}"
                            class="form-control form-control-sm text-center"
                            style="width:4rem;">

                        <button type="submit" class="btn btn-sm btn-anpas-green ms-2 p-1">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>

                </div>
            </form>
        </div>

    </div>
</nav>


@if(session()->has('impersonate'))
<div class="alert alert-danger text-center mb-0">
    Stai impersonificando l'Admin di
    <strong>{{ $assocCorr }}</strong>.
    <form method="POST" action="{{ route('impersonate.stop') }}" class="d-inline ms-2">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-light">Interrompi</button>
    </form>
</div>
@endif

<nav class="navbar navbar-expand-lg navbar-dark bg-anpas-green py-0">
    <div class="container-fluid">
        {{-- Toggler mobile --}}
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#navbarMain" aria-controls="navbarMain"
            aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        {{-- Menù --}}
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a></li>

                @auth
                @php
                $user = Auth::user();
                $imp = session()->has('impersonate_original_user');
                @endphp

                @if($imp || $user->hasRole('AdminUser') || $user->hasRole('User'))
                <li class="nav-item"><a class="nav-link" href="{{ route('my-users.index') }}">Utenti</a></li>
                @else
                @can('manage-all-associations')
                <li class="nav-item"><a class="nav-link" href="{{ route('associazioni.index') }}">Associazioni</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('all-users.index') }}">Utenti</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('convenzioni.index') }}">Convenzioni</a></li>
                @endcan
                @can('manage-own-association')
                <li class="nav-item"><a class="nav-link" href="{{ route('my-users.index') }}">I miei Utenti</a></li>
                @endcan
                @endif

                {{-- Dropdown Automezzi --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="automezziDropdown" data-bs-toggle="dropdown">Automezzi</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('automezzi.index') }}">Elenco</a></li>
                        <li><a class="dropdown-item" href="#">Costi</a></li>
                    </ul>
                </li>

                {{-- Dropdown Voci di bilancio --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="riepiloghiDropdown" data-bs-toggle="dropdown">Voci di bilancio</a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item" href="{{ route('riepiloghi.index') }}">
                                Consuntivo Anno {{ session('anno_riferimento') }}
                            </a>
                        </li>
                        <li><a class="dropdown-item" href="{{ route('riepilogo.costi') }}">Riepilogo Costi</a></li>
                    </ul>
                </li>

                {{-- Dropdown Personale --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="personaleDropdown" data-bs-toggle="dropdown">Personale</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('dipendenti.index') }}">Dipendenti</a></li>
                        <li><a class="dropdown-item" href="{{ route('dipendenti.autisti') }}">Autisti</a></li>
                        <li><a class="dropdown-item" href="{{ route('dipendenti.altro') }}">Altro Personale</a></li>
                    </ul>
                </li>

                {{-- Dropdown Documenti --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="documentiDropdown" data-bs-toggle="dropdown">Documenti</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('documenti.registro') }}">Registro</a></li>
                        <li><a class="dropdown-item" href="{{ route('documenti.distinta') }}">Distinta</a></li>
                        <li><a class="dropdown-item" href="{{ route('documenti.criteri') }}">Criteri</a></li>
                    </ul>
                </li>
                @endauth
            </ul>

            {{-- User menu / Login --}}
            <ul class="navbar-nav ms-auto">
                @auth
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userMenu" data-bs-toggle="dropdown">
                        {{ Auth::user()->firstname }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profilo</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">Logout</button>
                            </form>
                        </li>
                    </ul>
                </li>
                @else
                <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Login</a></li>
                @endauth
            </ul>
        </div>
    </div>
</nav>