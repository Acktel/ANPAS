@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="text-anpas-green fw-bold mb-4">Convenzioni - Anno {{ session('anno_riferimento', now()->year) }}</h1>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="d-flex mb-3">
    <a href="{{ route('convenzioni.create') }}" class="btn btn-anpas-red me-2">+ Nuova Convenzione</a>
  </div>

  <div id="noDataMessage" class="alert alert-info d-none">
    Nessuna convenzione presente per l’anno {{ session('anno_riferimento', now()->year) }}.<br>
    Vuoi importare le convenzioni dall’anno precedente?
    <div class="mt-2">
      <button id="btn-duplica-si" class="btn btn-sm btn-success">Sì</button>
      <button id="btn-duplica-no" class="btn btn-sm btn-secondary">No</button>
    </div>
  </div>

  <div class="card-anpas">
    <div class="card-body bg-anpas-white p-0">
      <table class="table table-hover table-bordered dt-responsive nowrap mb-0">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Associazione</th>
            <th>Anno</th>
            <th>Descrizione</th>
            <th>Lettera</th>
            <th>Azioni</th>
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
                <a href="{{ route('convenzioni.edit', $c->idConvenzione) }}"
                   class="btn btn-sm btn-warning me-1">Modifica</a>
                <form action="{{ route('convenzioni.destroy', $c->idConvenzione) }}"
                      method="POST" class="d-inline"
                      onsubmit="return confirm('Eliminare questa convenzione?')">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-secondary">Elimina</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center py-3">Nessuna convenzione.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const csrf = document.querySelector('meta[name="csrf-token"]').content;

  fetch("{{ route('convenzioni.checkDuplicazione') }}")
    .then(res => res.json())
    .then(data => {
      if (data.mostraMessaggio) {
        document.getElementById('noDataMessage').classList.remove('d-none');
      }
    });

  document.getElementById('btn-duplica-si')?.addEventListener('click', async function () {
    const btn = this;
    btn.disabled = true;
    btn.innerText = 'Duplicazione...';

    const res = await fetch("{{ route('convenzioni.duplica') }}", {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json'
      }
    });

    const json = await res.json();
    if (res.ok) {
      location.reload();
    } else {
      alert(json.message || 'Errore duplicazione');
      btn.disabled = false;
      btn.innerText = 'Sì';
    }
  });

  document.getElementById('btn-duplica-no')?.addEventListener('click', function () {
    document.getElementById('noDataMessage').classList.add('d-none');
  });
});
</script>
@endpush
