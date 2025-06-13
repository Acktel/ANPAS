@extends('layouts.app')

@section('content')
<div class="container-fluid">
  {{-- Titolo --}}
  <h1 class="text-anpas-green fw-bold mb-4">
    Modifica Automezzo #{{ $automezzo->idAutomezzo }}
  </h1>

  {{-- Errori --}}
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <form 
        action="{{ route('automezzi.update', $automezzo->idAutomezzo) }}" 
        method="POST"
      >
        @csrf
        @method('PUT')

        {{-- (stessa struttura di create, con valori old(..., $automezzo->...) ) --}}
        {{-- Associazione e Anno --}}
        <div class="row mb-3">
          @if(session()->has('impersonate'))
            @php
              $assocCorr = \App\Models\Associazione::getById(Auth::user()->IdAssociazione);
            @endphp
            <div class="col-md-4">
              <label class="form-label">Associazione</label>
              <input 
                type="text" 
                class="form-control" 
                value="{{ $assocCorr->Associazione }}" 
                readonly
              >
              <input 
                type="hidden" 
                name="idAssociazione" 
                value="{{ Auth::user()->IdAssociazione }}"
              >
            </div>
          @else
            <div class="col-md-4">
              <label for="idAssociazione" class="form-label">Associazione</label>
              <select 
                name="idAssociazione" 
                id="idAssociazione" 
                class="form-select" 
                required
              >
                <option value="">-- Seleziona Associazione --</option>
                @foreach($associazioni as $asso)
                  <option 
                    value="{{ $asso->idAssociazione }}"
                    {{ old('idAssociazione', $automezzo->idAssociazione) == $asso->idAssociazione ? 'selected' : '' }}
                  >
                    {{ $asso->Associazione }}
                  </option>
                @endforeach
              </select>
            </div>
          @endif

          <div class="col-md-4">
            <label for="idAnno" class="form-label">Anno</label>
            <select 
              name="idAnno" 
              id="idAnno" 
              class="form-select" 
              required
            >
              <option value="">-- Seleziona Anno --</option>
              @foreach($anni as $annoRecord)
                <option 
                  value="{{ $annoRecord->idAnno }}"
                  {{ old('idAnno', $automezzo->idAnno) == $annoRecord->idAnno ? 'selected' : '' }}
                >
                  {{ $annoRecord->anno }}
                </option>
              @endforeach
            </select>
          </div>
        </div>

        {{-- Resto dei campi (come in create), sostituendo old(...) con old(..., $automezzo->...) --}}
        {{-- ... --}}
        
        <hr>

        <button type="submit" class="btn btn-anpas-red">
          Aggiorna Automezzo
        </button>
      </form>
    </div>
  </div>
</div>
@endsection
