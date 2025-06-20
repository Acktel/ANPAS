@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Nuova Convenzione</h1>

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card-anpas mb-4">
    <div class="card-body bg-anpas-white">
      <form action="{{ route('convenzioni.store') }}" method="POST">
        @csrf

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Associazione</label>
            <select name="idAssociazione" class="form-select" required>
              <option value="">-- seleziona --</option>
              @foreach($associazioni as $s)
                <option value="{{ $s->idAssociazione }}"
                  {{ old('idAssociazione') == $s->idAssociazione ? 'selected' : '' }}>
                  {{ $s->Associazione }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Anno</label>
            <select name="idAnno" class="form-select" required>
              <option value="">-- seleziona --</option>
              @foreach($anni as $a)
                <option value="{{ $a->idAnno }}"
                  {{ old('idAnno') == $a->idAnno ? 'selected' : '' }}>
                  {{ $a->anno }}
                </option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Descrizione</label>
            <input type="text"
                   name="Convenzione"
                   class="form-control"
                   value="{{ old('Convenzione') }}"
                   required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Lettera identificativa</label>
            <input type="text"
                   name="lettera_identificativa"
                   class="form-control"
                   value="{{ old('lettera_identificativa') }}"
                   maxlength="5"
                   required>
          </div>
        </div>

        <div class="d-flex justify-content-center mt-4">
          <button type="submit" class="btn btn-anpas-green me-2">
            <i class="fas fa-check me-1"></i> Salva Convenzione
          </button>
          <a href="{{ route('convenzioni.index') }}" class="btn btn-secondary">
            Annulla
          </a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
