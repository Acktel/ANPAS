@extends('layouts.tabler')

@section('content')
<div class="page">
  <div class="container-fluid px-0">
    <div class="row g-0">

      <!-- FORM -->
      <div class="col-12 col-lg-6 col-xl-4 border-top-wide border-primary d-flex align-items-center">
        <div class="w-100 px-4 px-lg-5 py-4">

          <!-- LOGO -->
          <div class="text-center mb-4">
            <a href="{{ url('/') }}" class="navbar-brand navbar-brand-autodark">
              <img src="{{ asset('images/logo.png') }}" height="32" alt="Logo ANPAS">
            </a>
          </div>

          <!-- TITOLO -->
          <h2 class="h3 text-center mb-3">Reset your password</h2>

          <!-- ALERT GLOBALE (errori o status) -->
          @if ($errors->any() || session('status') || session('error'))
            <div class="alert alert-danger">
              {{ session('error') ?? session('status') ?? $errors->first() }}
            </div>
          @endif

          <!-- FORM -->
          <form method="POST" action="{{ route('password.update') }}" autocomplete="on">
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <!-- EMAIL -->
            <div class="mb-3">
              <label for="email" class="form-label">Email address</label>
              <input
                id="email"
                type="email"
                name="email"
                class="form-control @error('email') is-invalid @enderror"
                value="{{ old('email', request('email')) }}"
                required
                inputmode="email"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
                placeholder="you@example.com"
              >
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <!-- PASSWORD -->
            <div class="mb-3">
              <label class="form-label" for="password">New password</label>
              <div class="input-group input-group-flat">
                <input
                  id="password"
                  type="password"
                  name="password"
                  class="form-control @error('password') is-invalid @enderror"
                  required
                  autocomplete="new-password"
                  placeholder="Choose a strong password"
                >
                <span class="input-group-text">
                  <a href="#" id="togglePassword" class="link-secondary" role="button"
                     aria-pressed="false" data-bs-toggle="tooltip" title="Show password">
                    <!-- icona “occhio” -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                         viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                         fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"></path>
                      <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"></path>
                    </svg>
                    <span class="visually-hidden">Toggle password visibility</span>
                  </a>
                </span>
              </div>
              @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <!-- CONFERMA PASSWORD -->
            <div class="mb-3">
              <label class="form-label" for="password-confirm">Confirm new password</label>
              <div class="input-group input-group-flat">
                <input
                  id="password-confirm"
                  type="password"
                  name="password_confirmation"
                  class="form-control"
                  required
                  autocomplete="new-password"
                  placeholder="Repeat the new password"
                >
                <span class="input-group-text">
                  <a href="#" id="togglePasswordConfirm" class="link-secondary" role="button"
                     aria-pressed="false" data-bs-toggle="tooltip" title="Show password">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                         viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                         fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"></path>
                      <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"></path>
                    </svg>
                    <span class="visually-hidden">Toggle password visibility</span>
                  </a>
                </span>
              </div>
            </div>

            <!-- AZIONI -->
            <div class="form-footer">
              <button type="submit" class="btn btn-primary w-100">Reset password</button>
            </div>

            <div class="text-center mt-3">
              @if(Route::has('login'))
                <a href="{{ route('login') }}" class="small">Back to login</a>
              @endif
            </div>
          </form>

        </div>
      </div>

      <!-- IMMAGINE DI SFONDO -->
      <div class="col-12 col-lg-6 col-xl-8 d-none d-lg-block">
        <div class="bg-cover h-100 min-vh-100"
             style="background-image: url('{{ asset('images/bg_ampas.png') }}')">
        </div>
      </div>

    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const toggle = (btnId, inputId) => {
    const btn = document.getElementById(btnId);
    const input = document.getElementById(inputId);
    if (!btn || !input) return;
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const isPw = input.getAttribute('type') === 'password';
      input.setAttribute('type', isPw ? 'text' : 'password');
      btn.setAttribute('aria-pressed', String(isPw));
      btn.setAttribute('title', isPw ? 'Hide password' : 'Show password');
    });
  };

  toggle('togglePassword', 'password');
  toggle('togglePasswordConfirm', 'password-confirm');
});
</script>
@endpush
