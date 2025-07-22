@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1>Crea file Registro</h1>

  {{-- Messaggi --}}
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  {{-- FORM --}}
  <form action="{{ route('documenti.registro.generate') }}" method="POST">
    @csrf
    <div class="row">
      <div class="col-md-4 mb-3">
        @php
          $assocCorr = \App\Models\Associazione::getById(Auth::user()->IdAssociazione);
        @endphp

        @if(session()->has('impersonate') || Auth::user()->role_id == 4)
          <label class="form-label">Associazione</label>
          <input type="text" class="form-control" value="{{ $assocCorr->Associazione }}" readonly>
          <input type="hidden" name="idAssociazione" value="{{ $assocCorr->IdAssociazione }}">
        @else
          <label for="idAssociazione" class="form-label">Associazione</label>
          <select name="idAssociazione" id="idAssociazione" class="form-select" required>
            <option value="">-- Seleziona Associazione --</option>
            @foreach($associazioni as $asso)
              <option value="{{ $asso->idAssociazione }}" {{ old('idAssociazione') == $asso->idAssociazione ? 'selected' : '' }}>
                {{ $asso->Associazione }}
              </option>
            @endforeach
          </select>
        @endif

        @error('idAssociazione')
          <div class="text-danger">{{ $message }}</div>
        @enderror
      </div>
    </div>

    <div class="row">
      <div class="col-md-4 mb-3">
        <label for="idAnno" class="form-label">Anno</label>
        <input type="number" name="idAnno" id="idAnno" class="form-control"
               min="2000" max="{{ date('Y') + 5 }}" step="1"
               value="{{ old('idAnno', now()->year) }}"
               placeholder="Inserisci l'anno, es. 2024" required>
        @error('idAnno')
          <div class="text-danger">{{ $message }}</div>
        @enderror
      </div>
    </div>

    <button type="submit" class="btn btn-success">Crea file Excel</button>
  </form>

  {{-- ELENCO DOCUMENTI GENERATI --}}
  <hr>
  <h5 class="mt-4">Documenti generati di recente</h5>

  @if($documenti->count())
    <table class="table table-bordered table-striped mt-2">
      <thead>
        <tr>
          <th>Anno</th>
          <th>Tipo</th>
          <th>Generato il</th>
          <th>Stato</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        @foreach($documenti as $doc)
          <tr>
            <td>{{ $doc->idAnno }}</td>
            <td>{{ strtoupper($doc->tipo_documento) }}</td>
            <td>
              {{ $doc->generato_il ? \Carbon\Carbon::parse($doc->generato_il)->format('d/m/Y H:i') : '—' }}
            </td>
            <td>
              @if($doc->generato_il)
                <span class="badge bg-success">Pronto</span>
              @else
                <span class="badge bg-warning text-dark">In attesa</span>
              @endif
            </td>
            <td>
              @if($doc->generato_il && Storage::disk('public')->exists($doc->percorso_file))
                <a href="{{ route('documenti.download', $doc->id) }}" class="btn btn-sm btn-primary">
                  Scarica
                </a>
              @else
                <em>Non disponibile</em>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @else
    <p class="text-muted">Nessun documento ancora generato.</p>
  @endif
</div>
@endsection
