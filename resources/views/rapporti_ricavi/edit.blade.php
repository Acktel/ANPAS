@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="mb-4 container-title">
    Modifica ricavi per convenzione — Anno {{ $anno }}
    @isset($associazione) <small class="text-muted">({{ $associazione }})</small> @endisset
  </h1>

  <form method="POST" action="{{ route('rapporti-ricavi.update', ['id' => $idAssociazione]) }}">
    @csrf
    @method('PUT')

    <table class="table table-bordered">
      <thead>
        <tr>
          <th>Convenzione</th>
          <th>Rimborso</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($convenzioni as $conv)
          @php
            $valore = $valori[$conv->idConvenzione]->Rimborso ?? null;
          @endphp
          <tr>
            <td>{{ $conv->Convenzione }}</td>
            <td style="max-width:220px">
              <input
                type="number"
                name="ricavi[{{ $conv->idConvenzione }}]"
                class="form-control text-end"
                value="{{ !is_null($valore) && $valore != 0 ? number_format($valore, 2, '.', '') : '' }}"
                step="0.01" min="0" placeholder="0,00">
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <div class="text-center mt-4">
      <button type="submit" class="btn btn-anpas-green">
    <div class="mt-4 d-flex justify-content-between">
      <a href="{{ route('rapporti-ricavi.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Indietro
      </a>
      <button type="submit" class="btn btn-success">
        <i class="fas fa-check me-1"></i> Salva
      </button>
      <a href="{{ route('rapporti-ricavi.index') }}" class="btn btn-secondary">
        <i class="fas fa-times me-1"></i> Annulla
      </a>
    </div>
  </form>
</div>
@endsection
