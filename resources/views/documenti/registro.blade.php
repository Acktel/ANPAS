@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1>Crea file Registro</h1>

  <form action="{{ route('documenti.registro.generate') }}" method="POST">
    @csrf

    <div class="row">
      {{-- Associazione --}}
      <div class="col-md-4 mb-3">
        <label for="idAssociazione" class="form-label">Associazione</label>
        <select name="idAssociazione"
                id="idAssociazione"
                class="form-select"
                required>
          <option value="">-- Seleziona Associazione --</option>
          @foreach($associazioni as $asso)
            <option value="{{ $asso->idAssociazione }}"
              {{ old('idAssociazione') == $asso->idAssociazione ? 'selected' : '' }}>
              {{ $asso->Associazione }}
            </option>
          @endforeach
        </select>
        @error('idAssociazione')
          <div class="text-danger">{{ $message }}</div>
        @enderror
      </div>
    </div>
    <div class="row">
      {{-- Anno con spinner e input libero --}}
      <div class="col-md-4 mb-3">
        <label for="idAnno" class="form-label">Anno</label>
        <input type="number"
               name="idAnno"
               id="idAnno"
               class="form-control"
               min="2000"
               max="{{ date('Y') + 5 }}"
               step="1"
               value="{{ old('idAnno') }}"
               placeholder="Inserisci l'anno, es. 2024"
               required>
        @error('idAnno')
          <div class="text-danger">{{ $message }}</div>
        @enderror
      </div>
    </div>

    <button type="submit" class="btn btn-success">Crea file Excel</button>
  </form>
</div>
@endsection
