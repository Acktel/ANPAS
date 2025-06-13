@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="text-anpas-green fw-bold mb-4">Associazioni</h1>

  <a href="{{ route('associazioni.create') }}" class="btn btn-anpas-red mb-3">
    + Aggiungi Associazione
  </a>

  <table id="associazioniTable"
         class="table table-bordered table-striped table-hover dt-responsive nowrap w-100">
    <thead class="table-light">
      <tr>
        <th>Associazione</th>
        <th>Email</th>
        <th>Provincia</th>
        <th>Città</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const csrf = document.head.querySelector('meta[name="csrf-token"]').content;

    fetch("{{ route('associazioni.data') }}")
      .then(r => r.json())
      .then(json => {
        $('#associazioniTable').DataTable({
          data: json.data,
          columns: [
            { data: 'Associazione' },
            { data: 'email' },
            { data: 'provincia' },
            { data: 'citta' },
            {
              data: null,
              orderable: false,
              searchable: false,
              render(row) {
                let btns = `
                  <a href="/associazioni/${row.IdAssociazione}/edit"
                     class="btn btn-sm btn-anpas-red me-1">Modifica</a>
                  <form action="/associazioni/${row.IdAssociazione}" method="POST" style="display:inline">
                    <input name="_token" value="${csrf}" hidden>
                    <input name="_method" value="DELETE" hidden>
                    <button class="btn btn-sm btn-secondary">Elimina</button>
                  </form>`;
                if (row.supervisor_user_id) {
                  btns += `
                    <form action="/impersonate/${row.supervisor_user_id}" method="POST" style="display:inline; margin-left:4px;">
                      <input name="_token" value="${csrf}" hidden>
                      <button class="btn btn-sm btn-anpas-red">Impersona</button>
                    </form>`;
                }
                return btns;
              }
            }
          ],
          language: { url: '/js/i18n/Italian.json' }
        });
      });
  });
</script>
@endpush
