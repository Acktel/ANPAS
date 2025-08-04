@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="container-title mb-4">Nuovo inserimento Servizi Svolti – Anno {{ session('anno_riferimento') }}</h1>
  
  @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
    <form id="assocFilterForm" action="{{ route('sessione.setAssociazione') }}" method="POST" class="mb-4">
      @csrf
      <select id="assocSelect" name="idAssociazione" class="form-select w-auto d-inline-block" onchange="this.form.submit()">
        @foreach($associazioni as $assoc)
          <option value="{{ $assoc->idAssociazione }}" {{ $assoc->idAssociazione == $selectedAssoc ? 'selected' : '' }}>
            {{ $assoc->Associazione }}
          </option>
        @endforeach
      </select>
    </form>
  @endif
  
  <form method="POST" action="{{ route('servizi-svolti.store') }}">
    @csrf

    <div class="mb-4">
      <label for="idAutomezzo" class="form-label">Automezzo</label>
      <select name="idAutomezzo" id="idAutomezzo" class="form-select" required>
        <option value="">-- Seleziona --</option>
        @foreach($automezzi as $a)
          <option value="{{ $a->idAutomezzo }}">
            {{ $a->Automezzo }} ({{ $a->Targa }})
          </option>
        @endforeach
      </select>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered text-center align-middle">
        <thead class="table-light">
          <tr>
            <th>Convenzione</th>
            <th>Numero Servizi Svolti</th>
          </tr>
        </thead>
        <tbody>
          @foreach($convenzioni as $conv)
            <tr>
              <td>{{ $conv->Convenzione }}</td>
              <td>
                <input
                  type="number"
                  step="1"
                  min="0"
                  name="servizi[{{ $conv->idConvenzione }}]"
                  class="form-control text-end"
                >
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="mt-4 d-flex justify-content-between">
      <a href="{{ route('servizi-svolti.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Indietro
      </a>
      <button type="submit" class="btn btn-success">
        <i class="fas fa-save me-1"></i> Salva
      </button>
    </div>
  </form>
</div>
@endsection
