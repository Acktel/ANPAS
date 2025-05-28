@extends('layouts.app')

@section('title', 'Nuovo Veicolo')

@section('content')
<div class="page-header d-print-none">
  <h2 class="page-title">Nuovo Automezzo</h2>
</div>

<div class="card">
  <div class="card-body">
    <form action="{{ route('automezzi.store') }}" method="POST">
      @csrf
      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label">Associazione</label>
          <select name="idAssociazione" class="form-select">
            @foreach($associazioni as $a)
              <option value="{{ $a->idAssociazione }}">{{ $a->Associazione }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Anno</label>
          <select name="idAnno" class="form-select">
            @foreach($anni as $anno)
              <option value="{{ $anno->idAnno }}">{{ $anno->Anno }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Nome Automezzo</label>
        <input name="Automezzo" type="text" class="form-control" placeholder="Inserisci nome">
      </div>
      <button type="submit" class="btn btn-primary">Salva</button>
    </form>
  </div>
</div>
@endsection
