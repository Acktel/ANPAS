@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Modifica Azienda Sanitaria</h1>

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('aziende-sanitarie.update', $azienda->idAziendaSanitaria) }}" method="POST">
    @csrf
    @method('PATCH')

    <div class="card-anpas mb-4">
      <div class="card-body bg-anpas-white">

        {{-- Nome --}}
        <div class="mb-3">
          <label for="Nome" class="form-label">Nome Azienda</label>
          <input
            type="text"
            name="Nome"
            id="Nome"
            class="form-control"
            required
            value="{{ old('Nome', $azienda->Nome) }}">
        </div>

        {{-- Indirizzo --}}
        <div class="mb-3">
          <label for="Indirizzo" class="form-label">Indirizzo</label>
          <input
            type="text"
            name="Indirizzo"
            id="Indirizzo"
            class="form-control"
            required
            value="{{ old('Indirizzo', $azienda->Indirizzo ?? '') }}">
        </div>

        {{-- Email --}}
        <div class="mb-3">
          <label for="mail" class="form-label">Email</label>
          <input
            type="email"
            name="mail"
            id="mail"
            class="form-control"
            required
            value="{{ old('mail', $azienda->mail ?? '') }}">
        </div>

        {{-- Convenzioni associate --}}
        <div class="mb-3">
          <label for="convenzioni" class="form-label">Convenzioni associate</label>
          <select
            name="convenzioni[]"
            id="convenzioni"
            class="form-select"
            multiple
            required
            size="6">
            @foreach($convenzioni as $c)
              <option value="{{ $c->idConvenzione }}"
                {{ in_array($c->idConvenzione, $convenzioniSelezionate) ? 'selected' : '' }}>
                {{ $c->Convenzione }}
              </option>
            @endforeach
          </select>
          <div class="form-text">Puoi selezionare una o più convenzioni</div>
        </div>

        {{-- Pulsanti --}}
        <div class="text-center">
          <button type="submit" class="btn btn-anpas-green me-3">Salva Modifiche</button>
          <a href="{{ route('aziende-sanitarie.index') }}" class="btn btn-secondary">Annulla</a>
        </div>
      </div>
    </div>
  </form>
</div>
@endsection
