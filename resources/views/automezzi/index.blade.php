{{-- resources/views/automezzi/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Elenco Automezzi')

@section('content')
  <div class="page-header d-print-none mb-3">
    <h2 class="page-title">Elenco Automezzi</h2>
    <a href="{{ route('automezzi.create') }}" class="btn btn-primary">Nuovo Automezzo</a>
  </div>

  @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  <div class="card">
    <div class="card-body">
      <table class="table table-striped">
        <thead>
          <tr>
            <th>#</th>
            <th>Associazione</th>
            <th>Anno</th>
            <th>Automezzo</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          @forelse($automezzi as $a)
            <tr>
              <td>{{ $a->idAutomezzo }}</td>
              <td>{{ $a->Associazione }}</td>
              <td>{{ $a->Anno }}</td>
              <td>{{ $a->Automezzo }}</td>
              <td>
                <a href="{{ route('automezzi.edit', $a->idAutomezzo) }}"
                   class="btn btn-sm btn-secondary">Modifica</a>

                <form action="{{ route('automezzi.destroy', $a->idAutomezzo) }}"
                      method="POST" style="display:inline"
                      onsubmit="return confirm('Sei sicuro?')">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-danger">Elimina</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center">Nessun automezzo presente.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
