@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Modifica Utente</h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('all-users.update', $user->id) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="card-anpas">
      <div class="card-body bg-anpas-white row g-3">

        <div class="col-md-6">
          <label for="firstname" class="form-label">Nome</label>
          <input type="text" class="form-control" name="firstname" value="{{ old('firstname', $user->firstname) }}" required>
        </div>

        <div class="col-md-6">
          <label for="lastname" class="form-label">Cognome</label>
          <input type="text" class="form-control" name="lastname" value="{{ old('lastname', $user->lastname) }}">
        </div>

        <div class="col-md-6">
          <label for="username" class="form-label">Username</label>
          <input type="text" class="form-control" name="username" value="{{ old('username', $user->username) }}" required>
        </div>

        <div class="col-md-6">
          <label for="email" class="form-label">Email</label>
          <input type="email" class="form-control" name="email" value="{{ old('email', $user->email) }}" required>
        </div>

        @php
          $isImpersonating = session()->has('impersonate');
          $isAdminUser     = auth()->user()->hasRole('AdminUser');
          $isElevated      = !$isImpersonating && auth()->user()->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);
        @endphp

        {{-- Associazione --}}
        <div class="col-md-6">
          <label for="IdAssociazione" class="form-label">Associazione</label>

          @if($isElevated)
            <select name="IdAssociazione" class="form-select" required>
              @foreach ($associazioni as $a)
                <option value="{{ $a->IdAssociazione }}"
                  {{ old('IdAssociazione', $user->IdAssociazione) == $a->IdAssociazione ? 'selected' : '' }}>
                  {{ $a->Associazione }}
                </option>
              @endforeach
            </select>
          @else
            <input type="text" class="form-control" disabled value="{{ auth()->user()->associazione->Associazione ?? 'N/D' }}">
            <input type="hidden" name="IdAssociazione" value="{{ auth()->user()->IdAssociazione }}">
          @endif
        </div>

        {{-- Ruolo --}}
        <div class="col-md-6">
          <label for="role" class="form-label">Ruolo</label>
          <select name="role" class="form-select" required>
            @foreach ($ruoli as $r)
              @if($isElevated || in_array($r->name, ['AdminUser', 'User']))
                <option value="{{ $r->name }}" {{ old('role', $user->role_name) == $r->name ? 'selected' : '' }}>
                  {{ $r->name }}
                </option>
              @endif
            @endforeach
          </select>
        </div>

        {{-- Password opzionale --}}
        <div class="col-md-6">
          <label for="password" class="form-label">Nuova Password (opzionale)</label>
          <input type="password" class="form-control" name="password">
        </div>

        <div class="col-md-6">
          <label for="password_confirmation" class="form-label">Conferma Password</label>
          <input type="password" class="form-control" name="password_confirmation">
        </div>

        <div class="col-12 mt-3">
          <button type="submit" class="btn btn-anpas-primary">Salva Modifiche</button>
          <a href="{{ route('all-users.index') }}" class="btn btn-secondary ms-2">Annulla</a>
        </div>

      </div>
    </div>
  </form>
</div>
@endsection
