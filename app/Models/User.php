{{-- resources/views/partials/nav.blade.php --}}

{{-- Banner di impersonificazione --}}
@if(session()->has('impersonate_original_user'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 text-center">
        Sei in impersonificazione come 
        <strong>{{ Auth::user()->firstname }} {{ Auth::user()->lastname }}</strong>.
        <form method="POST" action="{{ route('impersonate.stop') }}" class="d-inline ms-2">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger">Interrompi impersonificazione</button>
        </form>
    </div>
@endif

<nav class="navbar navbar-expand-lg navbar-light bg-white">
    <div class="container">
        <a class="navbar-brand" href="{{ route('dashboard') }}">
            <img src="{{ asset('images/logo.png') }}" alt="ANPAS" style="height: 60px;">
        </a>
        <button class="navbar-toggler" type="button" 
                data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" 
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                {{-- Dashboard: sempre visibile --}}
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a>
                </li>

                @auth
                    @php
                        $user = Auth::user();
                        $isImpersonating = session()->has('impersonate_original_user');
                    @endphp

                    {{-- Caso 1: sto impersonificando o sono AdminUser/User → “Utenti” + pulsante “Nuovo Utente” --}}
                    @if($isImpersonating || $user->hasRole('AdminUser') || $user->hasRole('User'))
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('my-users.index') }}">
                                Utenti
                            </a>
                        </li>
                        {{-- Pulsante per creare un nuovo utente --}}
                        <li class="nav-item">
                            <a class="btn btn-sm btn-success ms-2" href="{{ route('my-users.create') }}">
                                Nuovo Utente
                            </a>
                        </li>

                    @else
                        {{-- Chi può gestire tutte le associazioni --}}
                        @can('manage-all-associations')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('associazioni.index') }}">
                                    Associazioni
                                </a>
                            </li>
                        @endcan

                        {{-- Chi può gestire gli utenti della propria associazione --}}
                        @can('manage-own-association')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('my-users.index') }}">
                                    I miei Utenti
                                </a>
                            </li>
                            {{-- Qui si può aggiungere, se lo si desidera, lo stesso pulsante “Nuovo Utente” --}}
                            <li class="nav-item">
                                <a class="btn btn-sm btn-success ms-2" href="{{ route('my-users.create') }}">
                                    Nuovo Utente
                                </a>
                            </li>
                        @endcan
                    @endif
                @endauth

                {{-- Altri link generici (visibili a chiunque sia autenticato) --}}
                @auth
                    <li class="nav-item">
                        <a class="nav-link" href="#">Servizi</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Mezzi</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Persone</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Costi fissi</a>
                    </li>
                @endauth
            </ul>

            {{-- Voci a destra: Profilo / Logout --}}
            <ul class="navbar-nav ms-auto">
                @auth
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userMenu" 
                           role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ Auth::user()->firstname }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                            <li>
                                <a class="dropdown-item" href="{{ route('profile.edit') }}">
                                    Profilo
                                </a>
                            </li>
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
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('login') }}">Login</a>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>
