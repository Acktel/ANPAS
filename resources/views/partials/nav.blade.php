{{-- resources/views/partials/nav.blade.php --}}

@auth
    @php
        $assocCorr = \App\Models\Associazione::getById(Auth::user()->IdAssociazione)->Associazione;
    @endphp
@endauth

@if(session()->has('impersonate'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 text-center">
        Stai impersonificando l'Admin dell'associazione <strong>{{ $assocCorr }}</strong>.
        <form method="POST" action="{{ route('impersonate.stop') }}" class="d-inline ms-2">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger">Interrompi impersonificazione</button>
        </form>
    </div>
@endif

<nav class="navbar navbar-expand-lg navbar-light bg-white">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('dashboard') }}">
            <img src="{{ asset('images/logo.png') }}" alt="ANPAS" style="height: 60px;">
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false"
            aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a>
                </li>

                @auth
                    @php
                        $user = Auth::user();
                        $isImpersonating = session()->has('impersonate_original_user');
                    @endphp

                    {{-- Se impersonificato o è un AdminUser/User --}}
                    @if($isImpersonating || $user->hasRole('AdminUser') || $user->hasRole('User'))
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('my-users.index') }}">Utenti</a>
                        </li>
                    @else
                        @can('manage-all-associations')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('associazioni.index') }}">Associazioni</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('all-users.index') }}">Utenti</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('convenzioni.index') }}">Convenzioni</a>
                            </li>                            
                        @endcan

                        @can('manage-own-association')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('my-users.index') }}">I miei Utenti</a>
                            </li>
                        @endcan
                    @endif
                @endauth

                @auth
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="automezziDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            Automezzi
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="automezziDropdown">
                            <li>
                                <a class="dropdown-item" href="{{ route('automezzi.index') }}">Elenco</a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#">Costi</a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="riepiloghiDropdown" role="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    Voci di bilancio
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="riepiloghiDropdown">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('riepiloghi.index') }}">
                                            Consuntivo Anno {{ session('anno_riferimento') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('riepilogo.costi') }}">
                                            Riepilogo Costi
                                        </a>
                                    </li>
                                </ul>
                            </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="personaleDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            Personale
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="personaleDropdown">
                            <li><a class="dropdown-item" href="{{ route('dipendenti.index') }}">Dipendenti</a></li>
                            <li><a class="dropdown-item" href="{{ route('dipendenti.autisti') }}">Personale Dipendente Autisti</a></li>
                            <li><a class="dropdown-item" href="{{ route('dipendenti.altro') }}">Altro Personale Dipendente</a></li>
                            <li><a class="dropdown-item" href="#">Costi Personale</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="documentiDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            Documenti
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="documentiDropdown">
                            <li><a class="dropdown-item" href="{{ route('documenti.registro') }}">Crea file Registro</a></li>
                            <li><a class="dropdown-item" href="{{ route('documenti.distinta') }}">Crea file Distinta Imputazione</a></li>
                            <li><a class="dropdown-item" href="{{ route('documenti.criteri') }}">Crea file Criteri Imputazione</a></li>
                        </ul>
                    </li>
                @endauth
            </ul>

            {{-- Utente + logout --}}
            <ul class="navbar-nav ms-auto">
                @auth
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userMenu" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            {{ Auth::user()->firstname }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profilo</a></li>
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
