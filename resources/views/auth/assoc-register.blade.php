<form method="POST" action="{{ route('assoc.register') }}">
  @csrf
  {{-- campi Associazione, email, password, password_confirmation, provincia, città --}}
  <button>Registrati</button>
</form>
