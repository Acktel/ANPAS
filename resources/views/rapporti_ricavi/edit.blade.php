@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="container-title mb-4">
    Modifica Ricavi Convenzioni – {{ $associazione }} – Anno {{ $anno }}
  </h1>

  <form method="POST" action="{{ route('rapporti-ricavi.update', $idAssociazione) }}">
    @csrf @method('PUT')

    <div class="table-responsive">
      <table class="table table-bordered text-center align-middle">
        <thead class="table-light">
          <tr>
            <th>Convenzione</th>
            <th>Rimborso (€)</th>
          </tr>
        </thead>
        <tbody>
          @foreach($convenzioni as $conv)
            @php
              $val = $valori->firstWhere('idConvenzione',$conv->idConvenzione)->Rimborso ?? 0;
            @endphp
            <tr>
              <td>{{ $conv->Convenzione }}</td>
              <td>
                <input type="number"
                       step="0.01" min="0"
                       name="ricavi[{{ $conv->idConvenzione }}]"
                       class="form-control text-end"
                       value="{{ $val }}">
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="mt-4 d-flex justify-content-between">
      <a href="{{ route('rapporti-ricavi.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Indietro
      </a>
      <button type="submit" class="btn btn-success">
        <i class="fas fa-save me-1"></i> Salva
      </button>
    </div>
  </form>
</div>
@endsection
