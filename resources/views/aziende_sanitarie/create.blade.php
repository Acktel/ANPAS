@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Nuova Azienda Sanitaria</h1>

  @if($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach($errors->all() as $error)
      <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <form action="{{ route('aziende-sanitarie.store') }}" method="POST">
    @csrf

    <div class="card-anpas mb-4">
      <div class="card-body bg-anpas-white">

        {{-- Nome --}}
        <div class="mb-3">
          <label for="Nome" class="form-label">Nome Azienda</label>
          <input type="text" name="Nome" id="Nome" class="form-control" required value="{{ old('Nome') }}">
        </div>

        {{-- Indirizzo --}}
        <div class="mb-3">
          <label for="Indirizzo" class="form-label">Indirizzo</label>
          <input type="text" name="Indirizzo" id="Indirizzo" class="form-control" required value="{{ old('Indirizzo') }}">
        </div>

        {{-- Email --}}
        <div class="mb-3">
          <label for="mail" class="form-label">Email</label>
          <input type="email" name="mail" id="mail" class="form-control" required value="{{ old('mail') }}">
        </div>

        <div class="row">
        {{-- Convenzioni --}}
        <div class="col-md-6 mb-3">
          <label for="convenzioni" class="form-label">Convenzioni associate</label>
          <select name="convenzioni[]" id="convenzioni" class="form-select" multiple required>
            @foreach($convenzioni as $c)
              <option value="{{ $c->idConvenzione }}" {{ collect(old('convenzioni'))->contains($c->idConvenzione) ? 'selected' : '' }}>
                {{ $c->Convenzione }}
              </option>
            @endforeach
          </select>
          <div class="form-text">Puoi selezionare una o più convenzioni</div>
        </div>

          {{-- RIGA 9: Note --}}
            <div class="col-md-6">
                <label for="note" class="form-label">Note</label>
                <textarea name="note" id="note" class="form-control" rows="4">{{ old('note') }}</textarea>
            </div>
          </div>

        <div class="text-center">
          <button type="submit" class="btn btn-anpas-green me-3"><i class="fas fa-check me-1"></i>Salva Azienda</button>
          <a href="{{ route('aziende-sanitarie.index') }}" class="btn btn-secondary">Annulla</a>
        </div>
      </div>
    </div>
  </form>
</div>
@endsection
