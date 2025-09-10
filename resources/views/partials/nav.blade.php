{{-- resources/views/partials/nav.blade.php --}}

@auth
@php
$assocCorr = \App\Models\Associazione::getById(Auth::user()->IdAssociazione)->Associazione;
if (!$assocCorr || $assocCorr === 'Anpas Nazionale') {
$assocCorr = 'Anpas Nazionale';
}
@endphp
@endauth

<nav class="anpas-topbar">
    <div class="topbar-logo">
        <a href="{{ route(name: 'dashboard') }}">
            <img src="{{ asset('images/logo.png') }}" alt="ANPAS" height="60">
        </a>
    </div>

    <div class="topbar-center-box">
        <div class="user-consun-box">

            {{-- Utente --}}
            <div class="user-section">
                <small>Utente</small>
                <div class="fw-bold">{{ $assocCorr }}</div>
            </div>
            {{-- Separatore verticale --}}
            <div class="vr mx-0"></div>
            {{-- Consuntivo Anno --}}
            <form method="POST" action="{{ route('anno.set') }}" class="consuntivo-section">
                @csrf
                <small>Consuntivo Anno</small>
                <div class="d-flex align-items-center">
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
        <button type="submit" class="btn btn-sm btn-anpas-red">Interrompi</button>
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


                @can('manage-all-associations')
                @if(!session()->has('impersonate'))
                <li class="nav-item"><a class="nav-link" href="{{ route('associazioni.index') }}">Associazioni</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('all-users.index') }}">Utenti</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('aziende-sanitarie.index') }}">Aziende Sanitarie</a></li>
                @endif
                <li class="nav-item"><a class="nav-link" href="{{ route('convenzioni.index') }}" >Convenzioni</a></li>
                @endcan
                @can('manage-own-association')
                <li class="nav-item"><a class="nav-link" href="{{ route('my-users.index') }}">Utenti</a></li>
                @endcan
                <li class="nav-item"><a class="nav-link" href="{{ route('automezzi.index') }}">Automezzi</a></li>                 
                {{-- Dropdown Voci di bilancio --}}
                <li class="nav-item dropdown dropdown-hover">
                    <a class="nav-link dropdown-toggle" href="#" id="riepiloghiDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        Voci di bilancio
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="riepiloghiDropdown">
                        <li><a class="dropdown-item" href="{{ route('riepiloghi.index') }}">Riepilogo dati caratteristici</a></li>
                        <li><a class="dropdown-item" href="{{ route('riepilogo.costi') }}">Riepilogo Costi</a></li>                      
                        {{-- Sottomenu Schede di riparto costi --}}
                        <li class="dropdown-submenu">
                            <a class="dropdown-item dropdown-toggle" href="#">Schede di riparto costi</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('ripartizioni.personale.index') }}">Personale dipendente (Autisti e Barellieri)</a></li>
                                <li><a class="dropdown-item" href="{{ route('ripartizioni.volontari.index') }}">Personale volontario</a></li>
                                <li><a class="dropdown-item" href="{{ route('ripartizioni.servizio_civile.index') }}">Servizio Civile</a></li>
                                <li><a class="dropdown-item" href="{{ route('ripartizioni.materiale_sanitario.index') }}">Materiale sanitario</a></li>
                               <li><a class="dropdown-item" href="{{ route('rapporti-ricavi.index') }}">Rapporto tra ricavi e convenzioni</a></li>
                            </ul>
                        </li>
                        <li class="dropdown-submenu">
                            <a class="dropdown-item dropdown-toggle" href="#">Distinta riparto costi</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('km-percorsi.index') }}">Km percorsi per convenzione</a></li>
                                <li><a class="dropdown-item" href="{{ route('servizi-svolti.index') }}">Servizi svolti per convenzione</a></li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('ripartizioni.personale.costi.index') }}">
                                       Dipendenti
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('ripartizioni.costi_automezzi.index') }}">
                                        Automezzi
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('ripartizioni.costi_radio.index')   }}">
                                       Radio
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('ripartizioni.costi_automezzi_sanitari.index')   }}">
                                        Automezzi - materiale ed attrezzatura sanitaria - costi radio
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li class="dropdown-submenu">
                            <a class="dropdown-item dropdown-toggle" href="#">Imputazioni Costi</a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="{{ route('imputazioni.materiale_sanitario.index') }}">
                                        Materiale sanitario
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('imputazioni.ossigeno.index') }}">
                                        Ossigeno
                                    </a>
                                </li>
                            </ul>                        
                        </li>    
                        <li><a class="dropdown-item" href="{{ route('distinta.imputazione.index') }}">Distinta imputazione costi</a></li>                         
                    </ul>
                    
                    
                </li>
               {{-- Dropdown Personale --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="personaleDropdown" data-bs-toggle="dropdown">Personale</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('dipendenti.index') }}">Dipendente</a></li>
                        <li><a class="dropdown-item" href="{{ route('dipendenti.autisti') }}">Autista</a></li>
                        <li><a class="dropdown-item" href="{{ route('dipendenti.amministrativi') }}">Amministrativo</a></li>
                    </ul>
                </li>

                {{-- Dropdown Documenti --}}
                <!--
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="documentiDropdown" data-bs-toggle="dropdown">Documenti</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('documenti.registro') }}">Esportazioni</a></li>                      
                    </ul>
                </li>-->
                @endauth

            </ul>

            {{-- User menu / Login --}}
            <ul class="navbar-nav ms-auto">
                @auth

                <li class="nav-item dropdown">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="configDropdown" data-bs-toggle="dropdown">
                        Configurazioni
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('configurazioni.veicoli') }}">Veicoli</a></li>
                        <li><a class="dropdown-item" href="{{ route('configurazioni.personale') }}">Personale</a></li>
                        <li><a class="dropdown-item"  href="{{ route('configurazioni.aziende_sanitarie') }}">Aziende Sanitarie</a></li>
                        <li><a class="dropdown-item" href="{{ route('configurazioni.riepilogo.index') }}">Riepilogo Costi</a></li>
                        {{-- <li><a class="dropdown-item" href="{{ route('configurazioni.convenzioni') }}">Convenzioni</a></li> --}}
                        <li><a class="dropdown-item" href="#">Altro</a></li>
                    </ul>
                </li>
                <a class="nav-link dropdown-toggle" href="#" id="userMenu" data-bs-toggle="dropdown">
                    {{ Auth::user()->firstname }}
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <button id="profile-toggle" class="dropdown-item" type="button">
                                Profilo
                            </button>
                            <ul id="profile-submenu"
                                class="list-unstyled d-none bg-light rounded mx-2 my-2 px-3 py-2 overflow-hidden">
                                @include('profilo.edit')
                            </ul>
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














@push('scripts')
    <script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('profile-toggle');
    const submenu = document.getElementById('profile-submenu');
    const dropdownMenu = toggle.closest('.dropdown-menu');
    toggle.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        submenu.classList.toggle('d-none');
    });
    // Nascondi submenu quando il dropdown principale si chiude
    document.addEventListener('click', function (event) {
        // Se il click NON è dentro il dropdown
        if (!dropdownMenu.contains(event.target)) {
            submenu.classList.add('d-none');
        }
    });
});
    </script>
    <script>
        function toggleSubmenu() {
            const submenu = document.getElementById('profile-submenu');
            submenu.classList.toggle('d-none');
        }
    </script>
@endpush