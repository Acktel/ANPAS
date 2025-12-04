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
          <label for="email" class="form-label">Email</label>
          <input type="email" class="form-control" name="email" value="{{ old('email', $user->email) }}" required>
        </div>

        @php
        $isImpersonating = session()->has('impersonate');
        $isAdminUser = auth()->user()->hasRole('AdminUser');
        $isElevated = !$isImpersonating && auth()->user()->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);
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

        @php
        function roleLabel($role) {
        return [
        'Admin' => 'Amministrativo ANPAS',
        'Supervisor' => 'Dipendente ANPAS',
        'AdminUser' => 'Amministrativo Associazione',
        'User' => 'Utente Associazione',
        ][$role] ?? $role;
        }
        @endphp
        {{-- Ruolo --}}
        <div class="col-md-6">
          <label for="role" class="form-label">Ruolo</label>
          <select name="role" class="form-select" required>
            @foreach ($ruoli as $r)
            @if($isElevated || in_array($r->name, ['AdminUser', 'User']))
            @if($r->name !== 'SuperAdmin')
            <option value="{{ $r->name }}"
              {{ old('role', $user->role_name) == $r->name ? 'selected' : '' }}>
              {{ roleLabel($r->name) }}
            </option>
            @endif
            @endif
            @endforeach
          </select>
        </div>

        {{-- Info stato password --}}
        @if($hasPassword)
        <div class="col-md-12">
            <div class="alert alert-warning py-2">
                <strong>⚠ Password già impostata.</strong><br>
                Lascia i campi vuoti se NON vuoi cambiarla.
            </div>
        </div>
        @endif
        {{-- Password opzionale --}}
        <div class="col-md-6">
          <label for="password" class="form-label">Nuova Password (opzionale)</label>
          <div class="input-group input-group-flat">
            <input type="password" class="form-control" name="password" id="password" autocomplete="new-password">
            <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Mostra/Nascondi password" title="Mostra/Nascondi">
              <i class="fas fa-eye" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <div class="col-md-6">
          <label for="password_confirmation" class="form-label">Conferma Password</label>
          <div class="input-group input-group-flat">
            <input type="password" class="form-control" name="password_confirmation" id="password_confirmation" autocomplete="new-password">
            <button class="btn btn-outline-secondary" type="button" id="togglePasswordConfirm" aria-label="Mostra/Nascondi conferma" title="Mostra/Nascondi">
              <i class="fas fa-eye" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <div class="col-md-6">
          <label for="note" class="form-label">Note</label>
          <textarea name="note" id="note" class="form-control" rows="3">{{ old('note', $user->note) }}</textarea>
        </div>

        <div class="col-12 mt-3 text-center">
          <button type="submit" class="btn btn-anpas-green"><i class="fas fa-check me-1"></i>Salva Modifiche</button>
          <a href="{{ route('all-users.index', ['idAssociazione' => old('IdAssociazione', $user->IdAssociazione)]) }}" class="btn btn-secondary ms-2">
            <i class="fas fa-times me-1"></i>Annulla
          </a>
        </div>

      </div>
    </div>
  </form>
</div>
@endsection
@push('scripts')
<script>
  (function() {
    function bindToggle(btnId, inputId) {
      const btn = document.getElementById(btnId);
      const input = document.getElementById(inputId);
      if (!btn || !input) return;

      btn.addEventListener('click', function() {
        const isPwd = input.getAttribute('type') === 'password';
        input.setAttribute('type', isPwd ? 'text' : 'password');
        const icon = this.querySelector('i');
        if (icon) {
          icon.classList.toggle('fa-eye');
          icon.classList.toggle('fa-eye-slash');
        }
      });
    }

    document.addEventListener('DOMContentLoaded', function() {
      bindToggle('togglePassword', 'password');
      bindToggle('togglePasswordConfirm', 'password_confirmation');
    });
  })();
</script>
@endpush