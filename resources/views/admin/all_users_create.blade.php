@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Crea Nuovo Utente</h1>

  @if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach ($errors->all() as $err)
      <li>{{ $err }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <form action="{{ route('all-users.store') }}" method="POST" novalidate>
    @csrf

    <div class="card-anpas">
      <div class="card-body bg-anpas-white row g-3">

        <div class="col-md-6">
          <label for="firstname" class="form-label">Nome</label>
          <input type="text" class="form-control" name="firstname" value="{{ old('firstname') }}" required>
        </div>

        <div class="col-md-6">
          <label for="lastname" class="form-label">Cognome</label>
          <input type="text" class="form-control" name="lastname" value="{{ old('lastname') }}">
        </div>

        <div class="col-md-6">
          <label for="email" class="form-label">Email</label>
          <input
            type="text" inputmode="email"
            class="form-control"
            name="email" value="{{ old('email') }}"
            required autocomplete="off" spellcheck="false">
        </div>

        {{-- Password con occhietto --}}
        <div class="col-md-6">
          <label for="password" class="form-label">Password</label>
          <div class="input-group input-group-flat">
            <input type="password" class="form-control" name="password" id="password" required autocomplete="new-password">
            <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Mostra/Nascondi password" title="Mostra/Nascondi">
              <i class="fas fa-eye" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <div class="col-md-6">
          <label for="password_confirmation" class="form-label">Conferma Password</label>
          <div class="input-group input-group-flat">
            <input type="password" class="form-control" name="password_confirmation" id="password_confirmation" required autocomplete="new-password">
            <button class="btn btn-outline-secondary" type="button" id="togglePasswordConfirm" aria-label="Mostra/Nascondi conferma" title="Mostra/Nascondi">
              <i class="fas fa-eye" aria-hidden="true"></i>
            </button>
          </div>
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
            <option value="">-- seleziona --</option>
            @foreach ($associazioni as $a)
            <option value="{{ $a->IdAssociazione }}" {{ old('IdAssociazione') == $a->IdAssociazione ? 'selected' : '' }}>
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
        @php
        // Mappa: nome tecnico → etichetta leggibile
        $labelRuoli = [
        'Admin' => 'Amministrativo Anpas',
        'Supervisor' => 'Dipendente Anpas',
        'AdminUser' => 'Amministrativo Associazione',
        'User' => 'Utente Associazione',
        ];
        @endphp

        <div class="col-md-6">
    <label for="role" class="form-label">Ruolo</label>
    <select name="role" class="form-select" required>
        @foreach ($ruoli as $r)
            @if($isElevated || in_array($r->name, ['AdminUser', 'User']))
                @if($r->name !== 'SuperAdmin')
                    <option value="{{ $r->name }}"
                        {{ old('role') == $r->name ? 'selected' : '' }}>
                        {{ roleLabel($r->name) }}
                    </option>
                @endif
            @endif
        @endforeach
    </select>
</div>



        <div class="form-label col-md-6">
          <label for="note">Note</label>
          <textarea name="note" id="note" class="form-control">{{ old('note') }}</textarea>
        </div>

        <div class="col-12 mt-3 text-center">
          <button type="submit" class="btn btn-anpas-green">
            <i class="fas fa-check me-1"></i>Crea Utente
          </button>
          <a href="{{ route('all-users.index', ['idAssociazione' => $selectedAssoc]) }}" class="btn btn-secondary ms-2">
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