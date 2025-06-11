@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1>Convenzioni</h1>
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <a href="{{ route('convenzioni.create') }}" class="btn btn-primary mb-3">+ Nuova</a>

  <table class="table table-hover table-bordered dt-responsive nowrap">
    <thead>
      <tr>
        <th>ID</th><th>Associazione</th><th>Anno</th>
        <th>Descrizione</th><th>Lettera identificativa</th><th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      @forelse($convenzioni as $c)
        <tr>
          <td>{{ $c->idConvenzione }}</td>
          <td>{{ $c->Associazione }}</td>
          <td>{{ $c->idAnno }}</td>
          <td>{{ $c->Convenzione }}</td>
          <td>{{ $c->lettera_identificativa }}</td>
          <td>
            <a href="{{ route('convenzioni.edit',$c->idConvenzione) }}"
               class="btn btn-sm btn-warning">Modifica</a>
            <form action="{{ route('convenzioni.destroy',$c->idConvenzione) }}"
                  method="POST" class="d-inline"
                  onsubmit="return confirm('Eliminare?')">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-danger">Elimina</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center">Nessuna convenzione.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
