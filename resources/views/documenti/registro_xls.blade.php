{{-- resources/views/documenti/registro_xls.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="mb-3 container-title">Esporta REGISTRI (Excel) – anno {{ session('anno_riferimento', now()->year) }}</h1>

  @php
  $user = auth()->user();
  $isElev = $user->hasAnyRole(['SuperAdmin','Admin','Supervisor']);
  $selAssoc = $selectedAssoc ?? session('associazione_selezionata') ?? ($isElev ? null : $user->IdAssociazione);

  $routeSetAssoc = \Illuminate\Support\Facades\Route::has('sessione.setAssociazione')
  ? route('sessione.setAssociazione')
  : null;
  @endphp

  <div class="row g-3">
    {{-- Parametri --}}
    <div class="col-md-5">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title mb-3">Parametri</h5>

          <form id="paramsForm">
            @csrf

            <div class="mb-3">
              <label class="form-label">Associazione</label>
              @if($isElev)
              <select id="idAssociazione" class="form-select" required>
                <option value="">— Seleziona —</option>
                @foreach($associazioni as $asso)
                <option value="{{ $asso->IdAssociazione }}" @selected($selAssoc==$asso->IdAssociazione)>
                  {{ $asso->Associazione }}
                </option>
                @endforeach
              </select>
              @else
              <input type="text" class="form-control"
                value="{{ optional($associazioni->firstWhere('IdAssociazione',$selAssoc))->Associazione ?? '' }}"
                readonly>
              <input type="hidden" id="idAssociazione" value="{{ $selAssoc }}">
              @endif
            </div>

            <div class="mb-3">
              <label for="idAnno" class="form-label">Anno</label>
              <input type="number" id="idAnno" class="form-control"
                min="2000" max="{{ date('Y') + 5 }}"
                value="{{ session('anno_riferimento', now()->year) }}" required>
            </div>

            <div class="d-grid gap-2">
              {{-- Unico bottone: genera il workbook completo REGISTRI_*.xls --}}
              <button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.registri_p1.xls') }}"
                data-type="REGISTRI (XLS)">
                <i class="fas fa-file-excel me-1"></i> Genera REGISTRI – XLS
              </button>
              <!--<button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.schede_riparto_costi.xls') }}"
                data-type="SCHEDE DI RIPARTO DEI COSTI (XLS)">
                <i class="fas fa-file-excel me-1"></i> SCHEDE DI RIPARTO DEI COSTI (XLS)
              </button>-->
            </div>
          </form>

          <small class="text-muted d-block mt-2">
            I file vengono generati in coda; quando pronti comparirà il link di download qui sotto.
          </small>
        </div>
      </div>
    </div>

    {{-- Archivio documenti --}}
    <div class="col-md-7">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title mb-3">Archivio documenti Excel</h5>

          <table class="table table-bordered table-striped align-middle w-100" id="docsTable">
            <thead>
              <tr>
                <th style="width:10%">ID</th>
                <th style="width:25%">Tipo</th>
                <th style="width:15%">Anno</th>
                <th style="width:25%">Generato il</th>
                <th style="width:25%">Azione</th>
              </tr>
            </thead>
            <tbody>
              @foreach($documenti as $doc)
              <tr id="docrow-{{ $doc->id }}">
                <td>{{ $doc->id }}</td>
                <td>{{ strtoupper(str_replace('_',' ',$doc->tipo_documento)) }}</td>
                <td>{{ $doc->idAnno }}</td>
                <td>{{ $doc->generato_il ? \Carbon\Carbon::parse($doc->generato_il)->format('d/m/Y H:i') : '—' }}</td>
                <td>
                  @if($doc->generato_il && Storage::disk('public')->exists($doc->percorso_file))
                  <a href="{{ route('documenti.download', $doc->id) }}" class="btn btn-sm btn-primary">Scarica</a>
                  @else
                  <span class="badge bg-warning text-dark">{{ $doc->stato ?? 'In coda' }}</span>
                  @endif
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>

        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  (() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const params = document.getElementById('paramsForm');
    const idAssEl = document.getElementById('idAssociazione');
    const idAnnoEl = document.getElementById('idAnno');
    const setAssocRoute = @json($routeSetAssoc);

    const dt = $('#docsTable').DataTable({
      pageLength: 25,
      lengthMenu: [5, 10, 25, 50, 100],
      order: [
        [3, 'desc']
      ],
      columnDefs: [{
        targets: 4,
        orderable: false
      }],
      language: {
        url: '/js/i18n/Italian.json'
      }
    });

    async function persistSelection() {
      const idAss = (idAssEl?.value || '').trim();
      if (!idAss) return;
      if (setAssocRoute) {
        try {
          await fetch(setAssocRoute, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify({
              idAssociazione: idAss
            })
          });
        } catch (_) {}
      } else {
        try {
          localStorage.setItem('doc_export_assoc', idAss);
        } catch (_) {}
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      if (idAssEl && !idAssEl.value) {
        try {
          const saved = localStorage.getItem('doc_export_assoc');
          if (saved) idAssEl.value = saved;
        } catch (_) {}
      }
    });

    idAssEl?.addEventListener('change', persistSelection);

    params.querySelectorAll('button[data-endpoint]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const idAssociazione = (idAssEl?.value || '').trim();
        const idAnno = (idAnnoEl?.value || '').trim();
        if (!idAssociazione || !idAnno) {
          alert('Seleziona Associazione e Anno');
          return;
        }

        await persistSelection();

        let res;
        try {
          res = await fetch(btn.dataset.endpoint, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrf,
              'Accept': 'application/json'
            },
            body: JSON.stringify({
              idAssociazione,
              idAnno
            })
          });
        } catch (_) {
          alert('Errore di rete nell’avvio del job');
          return;
        }
        if (!res.ok) {
          alert('Errore avvio job');
          return;
        }

        const {
          id
        } = await res.json();
        const typeLabel = btn.dataset.type || 'Documento Excel';

        const rowNode = dt.row.add([
          id,
          typeLabel,
          idAnno,
          '—',
          `<span class="badge bg-warning text-dark">In coda</span>`
        ]).draw(false).node();
        rowNode.id = 'docrow-' + id;

        pollStatus(id);
      });
    });

    async function pollStatus(id) {
      const timer = setInterval(async () => {
        try {
          const r = await fetch("{{ route('documenti.status','__ID__') }}".replace('__ID__', id), {
            headers: {
              'Accept': 'application/json'
            }
          });
          if (!r.ok) return;
          const j = await r.json();

          const row = dt.row('#docrow-' + id);
          if (!row.node()) return;

          const data = row.data();
          if (j.status === 'ready' && j.download_url) {
            clearInterval(timer);
            data[3] = new Date(j.generato_il).toLocaleString();
            data[4] = `<a href="${j.download_url}" class="btn btn-sm btn-primary">Scarica</a>`;
            row.data(data).draw(false);
          } else if (j.status === 'error') {
            clearInterval(timer);
            data[3] = '—';
            data[4] = `<span class="badge bg-danger">Errore</span>`;
            row.data(data).draw(false);
          } else {
            data[4] = `<span class="badge bg-warning text-dark">${j.status || 'In coda'}</span>`;
            row.data(data).draw(false);
          }
        } catch (_) {}
      }, 2000);
    }
  })();
</script>
@endpush