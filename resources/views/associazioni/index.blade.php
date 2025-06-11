@extends('layouts.app')

@section('content')
  <div class="container-fluid">
    <h1>Associazioni</h1>
    <a href="{{ route('associazioni.create') }}" class="btn btn-primary mb-3">Aggiungi Associazione</a>

    <table id="associazioniTable" class="table table-bordered table-striped table-hover dt-responsive nowrap w-100">
      <thead>
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
document.addEventListener('DOMContentLoaded', function () {
  const csrfToken = document.head.querySelector('meta[name="csrf-token"]').content;

  fetch("{{ route('associazioni.data') }}")
    .then(res => res.json())
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
            render: function(row) {             
              // row.IdAssociazione è l’ID dell’associazione
              // row.supervisor_user_id è l’ID dell’utente Supervisor (da includere nel JSON)
              let html = `
                <a href="/associazioni/${row.IdAssociazione}/edit" class="btn btn-warning btn-sm">Modifica</a>
                <form action="/associazioni/${row.IdAssociazione}" method="POST" style="display:inline-block; margin-left:4px;">
                  <input type="hidden" name="_token" value="${csrfToken}">
                  <input type="hidden" name="_method" value="DELETE">
                  <button type="submit" class="btn btn-danger btn-sm">Elimina</button>
                </form>
              `;

              if (row.supervisor_user_id) {
                html += `
                  <form action="/impersonate/${row.supervisor_user_id}" method="POST" style="display:inline-block; margin-left:4px;">
                    <input type="hidden" name="_token" value="${csrfToken}">
                    <button type="submit" class="btn btn-secondary btn-sm">Impersona</button>
                  </form>
                `;
              }

              return html;
            }
          }
        ],
        language: {
          url: "https://cdn.datatables.net/plug-ins/1.11.3/i18n/Italian.json"
        },
        paging: true,
        searching: true,
        ordering: true
      });
    })
    .catch(err => console.error("Errore fetch:", err));
});
</script>
@endpush
