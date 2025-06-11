{{-- resources/views/partials/nav.blade.php --}}
{{-- Banner di impersonificazione --}}

@auth
@php
$assocCorr = \App\Models\Associazione::getById(Auth::user()->IdAssociazione)->Associazione;

@endphp
@endauth

{{-- Mostra il banner di impersonificazione solo se l'utente sta impersonificando un altro utente --}}
@if(session()->has('impersonate'))
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 text-center">
   Stai impersonificando l'Admin dell' associazione
    <strong>{{ $assocCorr }}</strong>.
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

                {{-- Caso 1: sto impersonificando → “Utenti” (utenti della stessa associazione) --}}
                @if($isImpersonating || $user->hasRole('AdminUser') || $user ->hasRole('User'))
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('my-users.index') }}">
                        Utenti
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
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('all-users.index') }}">
                        Utenti
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('convenzioni.index') }}">
                        Convenzioni
                    </a>
                </li>

                <li class="nav-item dropdown">

                    <a class="nav-link dropdown-toggle" href="#" id="riepiloghiDropdown" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Voci di bilancio
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="riepiloghiDropdown">
                        <li>
                            <a class="dropdown-item" href="{{ route('riepiloghi.index') }}">
                                Riepilogo dati caratteristici
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#">
                                Esercizio finanziario anno
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#">
                                Automezzi
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#">
                                Attrezzatura Sanitaria
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#">
                                Telecomunicazioni
                            </a>
                        </li>
                </li>
                <li>
                    <a class="dropdown-item" href="#">
                        Costi gestione struttura
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#">
                        Costo del personale
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#">
                        Materiale sanitario di consumo
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#">
                        Costi amministrativi
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#">
                        Quote di ammortamento
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#">
                        Beni Strumentali Inferiori a 516,00 euro
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#">
                        Altri costi
                    </a>
                </li>
            </ul>
            </li>
            @endcan

            {{-- Chi può gestire gli utenti della propria associazione --}}
            @can('manage-own-association')
            <li class="nav-item">
                <a class="nav-link" href="{{ route('my-users.index') }}">
                    I miei Utenti
                </a>
            </li>
            @endcan
            @endif
            @endauth
            {{-- Altri link generici (visibili a chiunque sia autenticato) --}}
            @auth
            <!--<li class="nav-item">
                <a class="nav-link" href="#">Servizi</a>
            </li>-->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="automezziDropdown" role="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                    Automezzi
                </a>
                <ul class="dropdown-menu" aria-labelledby="automezziDropdown">
                    <li>
                        <a class="dropdown-item" href="{{ route('automezzi.index') }}">
                            Elenco
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#">
                            Costi
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
                    <li>
                        <a class="dropdown-item" href="{{ route('dipendenti.index') }}">
                            Dipendenti
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('dipendenti.autisti') }}">
                            Personale Dipendente Autisti
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('dipendenti.altro') }}">
                            Altro Personale Dipendente
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#">
                            Costi Personale
                        </a>
                    </li>
                </ul>
            </li>
            <!-- Documenti -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="documentiDropdown" role="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                    Documenti
                </a>
                <ul class="dropdown-menu" aria-labelledby="documentiDropdown">
                    <li>
                        <a class="dropdown-item" href="{{ route('documenti.registro') }}">
                            Crea file Registro
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('documenti.distinta') }}">
                            Crea file Distinta Imputazione
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('documenti.criteri') }}">
                            Crea file Criteri Imputazione
                        </a>
                    </li>
                </ul>
            </li>


            <!--<li class="nav-item">
                <a class="nav-link" href="#">Costi fissi</a>
            </li>-->
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