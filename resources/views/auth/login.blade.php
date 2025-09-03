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
          <h2 class="h3 text-center mb-3">Login to your account</h2>

          <!-- FORM -->
          <form method="POST" action="{{ route('login') }}" autocomplete="off" novalidate>
            @csrf

            <!-- EMAIL -->
            <div class="mb-3">
              <label class="form-label" for="email">Email address</label>
              <input id="email" type="email"
                     class="form-control @error('email') is-invalid @enderror"
                     name="email" value="{{ old('email') }}"
                     required autocomplete="email" autofocus
                     placeholder="you@example.com">
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <!-- PASSWORD -->
            <div class="mb-2">
              <label class="form-label" for="password">
                Password
                <span class="form-label-description">
                  @if(Route::has('password.request'))
                    <a href="{{ route('password.request') }}">Forgot password?</a>
                  @endif
                </span>
              </label>
              <div class="input-group input-group-flat">
                <input id="password" type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       name="password" required autocomplete="current-password"
                       placeholder="Your password">
                <span class="input-group-text">
                  <a href="#" id="togglePassword" class="link-secondary" data-bs-toggle="tooltip" title="Show password">
                    <!-- icona “occhio” -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                         viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                         fill="none" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"></path>
                      <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"></path>
                    </svg>
                  </a>
                </span>
              </div>
              @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <!-- REMEMBER -->
            <div class="mb-3">
              <label class="form-check">
                <input type="checkbox" name="remember"
                       class="form-check-input"
                       {{ old('remember') ? 'checked' : '' }}>
                <span class="form-check-label">Remember me on this device</span>
              </label>
            </div>

            <!-- BOTTONE -->
            <div class="form-footer">
              <button type="submit" class="btn btn-primary w-100">Sign in</button>
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
  const togglePassword = document.getElementById('togglePassword');
  const passwordInput = document.getElementById('password');

  togglePassword.addEventListener('click', function(e) {
    e.preventDefault();
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
  });
});
</script>
@endpush