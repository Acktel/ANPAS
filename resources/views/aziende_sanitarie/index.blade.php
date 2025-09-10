@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h1 class="container-title mb-4">
        Aziende Sanitarie
    </h1>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="d-flex mb-3">
        <div class="ms-auto">
            @can('manage-all-associations')
            @if(!session()->has('impersonate'))
            <a href="{{ route('aziende-sanitarie.create') }}" class="btn btn-anpas-green">
                <i class="fas fa-plus me-1"></i> Nuova Azienda Sanitaria
            </a>
            @endif
            @endcan
        </div>
    </div>

    <div class="card-anpas">
        <div class="card-body bg-anpas-white p-0">
            <table id="aziendeSanitarieTable"
                class="common-css-dataTable table table-hover table-striped table-bordered dt-responsive nowrap mb-0 table-striped-anpas">
                <thead class="thead-anpas">
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Indirizzo</th>
                        <th>Email</th>
                        <th>Convenzioni</th>
                        <th>Lotti</th>
                        <th class="col-actions text-center">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($aziende as $a)
                    <tr>
                        <td>{{ $a->idAziendaSanitaria }}</td>
                        <td>{{ $a->Nome }}</td>
                        <td>{{ $a->Indirizzo }}</td>
                        <td>{{ $a->mail }}</td>
                        <td>
                            @if(!empty($a->Convenzioni))
                            {{ implode(', ', $a->Convenzioni) }}
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <a href="{{ route('aziende-sanitarie.edit', $a->idAziendaSanitaria) }}"
                                class="btn btn-sm btn-anpas-edit me-1 btn-icon" title="Modifica">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('aziende-sanitarie.destroy', $a->idAziendaSanitaria) }}"
                                method="POST"
                                class="d-inline"
                                onsubmit="return confirm('Eliminare questa azienda sanitaria?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-anpas-delete btn-icon" title="Elimina">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-3">Nessuna azienda sanitaria trovata.</td>
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
    document.addEventListener('DOMContentLoaded', function() {
        $('#aziendeSanitarieTable').DataTable({
            ajax: '{{ route("aziende-sanitarie.data") }}',
            columns: [{
                    data: 'idAziendaSanitaria'
                },
                {
                    data: 'Nome'
                },
                {
                    data: 'Indirizzo'
                },
                {
                    data: 'mail'
                },
                {
                    data: 'Convenzioni',
                    render: function(data, type, row) {
                        if (Array.isArray(data)) {
                            return data.length ? data.join(', ') : '<span class="text-muted">—</span>';
                        }
                        return '<span class="text-muted">—</span>';
                    }
                },
                {
                    data: 'Lotti', // AGGIUNTO
                    render: function(data, type, row) {
                        if (Array.isArray(data)) {
                            return data.length ? data.join(', ') : '<span class="text-muted">—</span>';
                        }
                        return '<span class="text-muted">—</span>';
                    }
                },
                {
                    data: 'idAziendaSanitaria',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function(data, type, row) {
                        return `
          <a href="/aziende-sanitarie/${data}/edit"
             class="btn btn-sm btn-anpas-edit me-1 btn-icon" title="Modifica">
            <i class="fas fa-edit"></i>
          </a>
          <form action="/aziende-sanitarie/${data}" method="POST"
                class="d-inline"
                onsubmit="return confirm('Eliminare questa azienda sanitaria?')">
            @csrf
            @method('DELETE')
            <button class="btn btn-sm btn-anpas-delete btn-icon" title="Elimina">
              <i class="fas fa-trash-alt"></i>
            </button>
          </form>
        `;
                    }
                }
            ],
            paging: true,
            info: true,
            language: {
                url: '/js/i18n/Italian.json',
                                paginate: {
            first: '<i class="fas fa-angle-double-left"></i>',
            last: '<i class="fas fa-angle-double-right"></i>',
            next: '<i class="fas fa-angle-right"></i>',
            previous: '<i class="fas fa-angle-left"></i>'
        },
            },
            rowCallback: function(row, data, index) {
                $(row).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
            },
            stripeClasses: ['table-white', 'table-striped-anpas'],
        });

    });
</script>

      <script>
    (function () {
      // cerca prima un elemento con id, altrimenti prende il primo .alert.alert-success
      const flash = document.getElementById('flash-message') || document.querySelector('.alert.alert-success');
      if (!flash) return;

      // aspetta 3500ms (3.5s) poi fa fade + collapse e rimuove l'elemento
      setTimeout(() => {
        // animazione: opacità + altezza
        flash.style.transition = 'opacity 0.5s ease, max-height 0.5s ease, padding 0.4s ease, margin 0.4s ease';
        flash.style.opacity = '0';
        // per lo "slide up" imposta max-height e padding a 0
        flash.style.maxHeight = flash.scrollHeight + 'px'; // inizializza
        // forza repaint per sicurezza
        // eslint-disable-next-line no-unused-expressions
        flash.offsetHeight;
        flash.style.maxHeight = '0';
        flash.style.paddingTop = '0';
        flash.style.paddingBottom = '0';
        flash.style.marginTop = '0';
        flash.style.marginBottom = '0';

        // rimuovi dal DOM dopo che l'animazione è finita
        setTimeout(() => {
          if (flash.parentNode) flash.parentNode.removeChild(flash);
        }, 600); // lascia un po' di tempo alla transizione
      }, 3500);
    })();
  </script>
@endpush