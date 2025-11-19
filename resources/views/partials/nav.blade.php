{{-- resources/views/partials/nav.blade.php --}}
@auth
@php
    $assocCorr = \App\Models\Associazione::getById(Auth::user()->IdAssociazione)->Associazione;
    if (!$assocCorr || $assocCorr === 'Anpas Nazionale' || empty($assocCorr)) {
        $assocCorr = 'Anpas Nazionale';
    }
@endphp
@endauth

<!-- LOADER -->
<div id="pageLoader" class="anpas-loader" style="display:none;">
    <div class="anpas-loader__backdrop"></div>
    <img src="{{ asset('images/anpas_loader.gif') }}" alt="Caricamento..." class="anpas-loader__img">
</div>

{{-- TOPBAR ANPAS --}}
<nav class="anpas-topbar">
    <div class="topbar-logo">
        <a href="{{ route('dashboard') }}">
            <img src="{{ asset('images/logo.png') }}" alt="ANPAS" height="60">
        </a>
    </div>

    <div class="topbar-center-box">
        <div class="user-consun-box">

            {{-- Utente --}}
            <div class="user-section">
                <small>Utente</small>
                <div class="fw-bold">{{ $assocCorr ?? 'Anpas' }}</div>
            </div>

            <div class="vr mx-2"></div>

            {{-- Consuntivo Anno --}}
            <form method="POST" action="{{ route('anno.set') }}" class="consuntivo-section">
                @csrf
                <small>Consuntivo Anno</small>
                <div class="d-flex align-items-center">
                    <input
                        type="number"
                        name="anno_riferimento"
                        min="2020"
                        max="{{ date('Y') }}"
                        step="1"
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

{{-- BANNER IMPERSONAZIONE --}}
@if(session()->has('impersonate'))
<div class="alert alert-danger text-center mb-0">
    Stai impersonificando l'Admin di <strong>{{ $assocCorr }}</strong>.
    <form method="POST" action="{{ route('impersonate.stop') }}" class="d-inline ms-2">
        @csrf
        <button type="submit" class="btn btn-sm btn-anpas-red">Interrompi</button>
    </form>
</div>
@endif

{{-- MENU PRINCIPALE --}}
<nav class="navbar navbar-expand-lg navbar-dark bg-anpas-green py-0">
<div class="container-fluid">

    {{-- Toggler mobile --}}
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
        data-bs-target="#navbarMain" aria-controls="navbarMain"
        aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    {{-- MENU --}}
    <div class="collapse navbar-collapse" id="navbarMain">
        <ul class="navbar-nav me-auto">

            {{-- DASHBOARD --}}
            <li class="nav-item"><a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a></li>

            @auth
            @php
                $user = Auth::user();
                $imp = session()->has('impersonate');
            @endphp

            {{-- ASSOCIAZIONI + AZIENDE SANITARIE + UTENTI (solo permessi alti e NON in impersonazione) --}}
            @can('manage-all-associations')
                @if(!$imp)
                <li class="nav-item"><a class="nav-link" href="{{ route('associazioni.index') }}">Associazioni</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('all-users.index') }}">Utenti</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('aziende-sanitarie.index') }}">Aziende Sanitarie</a></li>
                @endif
                <li class="nav-item"><a class="nav-link" href="{{ route('convenzioni.index') }}">Convenzioni</a></li>
            @endcan

            {{-- UTENTI PROPRI --}}
            @can('manage-own-association')
                <li class="nav-item"><a class="nav-link" href="{{ route('my-users.index') }}">Utenti</a></li>
            @endcan

            {{-- AUTOMEZZI --}}
            <li class="nav-item dropdown dropdown-hover">
                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Automezzi</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('automezzi.index') }}">Elenco Automezzi</a></li>
                    <li><a class="dropdown-item" href="{{ route('km-percorsi.index') }}">KM Percorsi</a></li>
                    <li><a class="dropdown-item" href="{{ route('servizi-svolti.index') }}">Servizi effettuati</a></li>
                    <li><a class="dropdown-item" href="{{ route('ripartizioni.costi_automezzi.index') }}">Costi Automezzi</a></li>
                    <li><a class="dropdown-item" href="{{ route('ripartizioni.costi_radio.index') }}">Costi Radio</a></li>
                    <li><a class="dropdown-item" href="{{ route('ripartizioni.materiale_sanitario.index') }}">Materiale sanitario</a></li>
                    <li><a class="dropdown-item" href="{{ route('imputazioni.ossigeno.index') }}">Ossigeno</a></li>
                    <li><a class="dropdown-item" href="{{ route('imputazioni.materiale_sanitario.index') }}">Tabella % Mat. san. + O2</a></li>
                    <li><a class="dropdown-item" href="{{ route('ripartizioni.costi_automezzi_sanitari.index') }}">Riepilogo Totale</a></li>
                </ul>
            </li>

            {{-- PERSONALE --}}
            <li class="nav-item dropdown dropdown-hover">
                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Personale</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('dipendenti.index') }}">Elenco Dipendenti</a></li>
                    <li><a class="dropdown-item" href="{{ route('ripartizioni.personale.index') }}">Ore Dipendenti</a></li>
                    <li><a class="dropdown-item" href="{{ route('ripartizioni.personale.costi.index') }}">Costi Dipendenti</a></li>
                    <li><a class="dropdown-item" href="{{ route('ripartizioni.servizio_civile.index') }}">Unità SCU</a></li>
                    <li><a class="dropdown-item" href="{{ route('ripartizioni.volontari.index') }}">Personale Volontario</a></li>
                </ul>
            </li>

            {{-- VOCI DI BILANCIO --}}
            <li class="nav-item dropdown dropdown-hover">
                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Voci di bilancio</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('rapporti-ricavi.index') }}">% Ricavi</a></li>
                    <li><a class="dropdown-item" href="{{ route('distinta.imputazione.index') }}">Distinta imputazione costi</a></li>
                </ul>
            </li>

            {{-- PREVENTIVO & CONSUNTIVO --}}
            <li class="nav-item"><a class="nav-link" href="{{ route('riepilogo.costi') }}">Preventivo & Consuntivo</a></li>

            {{-- ESPORTAZIONI --}}
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Esportazioni</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('documenti.registro') }}">Esportazioni PDF</a></li>
                    <li><a class="dropdown-item" href="{{ route('documenti.registro_xls') }}">Esportazioni Excel</a></li>
                </ul>
            </li>
            @endauth
        </ul>

        {{-- MENU UTENTE --}}
        <ul class="navbar-nav ms-auto">
            @auth
            {{-- CONFIGURAZIONI --}}
            @can('manage-all-associations')
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="configDropdown" data-bs-toggle="dropdown">
                        Configurazioni
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('configurazioni.veicoli') }}">Veicoli</a></li>
                        <li><a class="dropdown-item" href="{{ route('configurazioni.personale') }}">Personale</a></li>
                        <li><a class="dropdown-item" href="{{ route('configurazioni.aziende_sanitarie') }}">Aziende Sanitarie</a></li>
                        <li><a class="dropdown-item" href="{{ route('configurazioni.riepilogo.index') }}">Riepilogo Costi</a></li>
                    </ul>
                </li>
                @endcan()
            <li class="nav-item dropdown">
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
            <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Login</a></li>
            @endauth
        </ul>
    </div>
</div>
</nav>

{{-- JS PROFILO --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('profile-toggle');
    const submenu = document.getElementById('profile-submenu');
    const dropdownMenu = toggle.closest('.dropdown-menu');

    toggle.addEventListener('click', function(event) {
        event.preventDefault();
        event.stopPropagation();
        submenu.classList.toggle('d-none');
    });

    document.addEventListener('click', function(event) {
        if (!dropdownMenu.contains(event.target))
            submenu.classList.add('d-none');
    });
});
</script>

<script>
(function () {
    const $loader = $('#pageLoader');
    const show = () => $loader.stop(true,true).fadeIn(120);
    const hide = () => $loader.stop(true,true).fadeOut(120);

    $(document).ajaxStart(show);
    $(document).ajaxStop(hide);

    window.AnpasLoader = { show, hide };
})();
</script>
@endpush
