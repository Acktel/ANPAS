{{-- @extends('layouts.app') --}}

@php
    use \App\Helpers\RoleHelper;

    $user = Auth::user();
    $nome = $user->firstname;
    // $ruolo = $roleName;
    $roleName = $firstRole?->name ?? 'N/A';
    $lastLogin = $user->last_login_at ? $user->last_login_at->format('d/m/Y H:i') : '';


    $ruoloRaw   = Auth::user()->roles->first()?->name ?? 'N/A';
    $ruoloLabel = RoleHelper::label($ruoloRaw);
    
@endphp


<li class="mb-2 d-flex mt-1 justify-content-between"><strong>Nome:</strong><span class="ms-1">{{ $user->firstname }}</span></li>
<li class="mb-2 d-flex justify-content-between align-items-center"><strong>Cognome:</strong><span class="ms-1">{{ $user->lastname }}</span></li>
<li class="mb-2 d-flex justify-content-between align-items-center"><strong>Username:</strong><span class="ms-1">{{ $user->username }}</span></li>
<li class="mb-2 d-flex justify-content-between align-items-center"><strong>Tipologia utente:</strong><span class="ms-1">{{ $ruoloLabel }}</span></li>
<li class="mb-2 d-flex justify-content-between align-items-center"><strong>Email:</strong><span class="ms-1">{{ $user->email }}</span></li>
<li class="mb-2 d-flex justify-content-between align-items-center"><strong>Ultimo accesso:</strong> <span class="ms-1">{{ $lastLogin }}</span></li>
<li class="mb-2 d-flex justify-content-between align-items-center"><strong>Password:</strong>
  <a href="{{ route('password.request') }}" class="btn btn-sm btn-anpas-green">
        Cambia Password
    </a>
</li>
