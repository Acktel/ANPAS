{{-- resources/views/documenti/registro.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="mb-3">Esporta documenti (anno {{ session('anno_riferimento', now()->year) }})</h1>

  @php
  $user = auth()->user();
  $isElev = $user->hasAnyRole(['SuperAdmin','Admin','Supervisor']);
  // preferisci ciò che hai già salvato in sessione
  $selAssoc = session('associazione_selezionata')
  ?? ($selectedAssoc ?? ($isElev ? null : $user->IdAssociazione));

  // rotta opzionale per salvare la selezione lato server (se esiste)
  $routeSetAssoc = \Illuminate\Support\Facades\Route::has('sessione.setAssociazione')
  ? route('sessione.setAssociazione')
  : null;
  @endphp

  <div class="row g-3">
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
              {{-- Crea la coda di jobs per generare un solo pdf con tutti i job in un singolo file  --}}
              <button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.bundle_all.pdf') }}"
                data-type="BUNDLE TUTTI I PDF (PDF)">
                <i class="fas fa-book me-1"></i> Crea PDF (tutti i report)
              </button>
<!--
              {{-- Ogni bottone chiama un endpoint che mette in coda un job e ritorna { id } --}}
              <button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.documento_unico.pdf') }}"
                data-type="DOCUMENTO UNICO (PDF)">
                <i class="fas fa-layer-group me-1"></i> Documento unico (tutte le tabelle)
              </button>

              <button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.riepilogo_costi.pdf') }}"
                data-type="RIEPILOGO COSTI (PDF)">
                <i class="fas fa-file-pdf me-1"></i> Riepilogo Costi (PDF)
              </button>

              <button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.registro_automezzi.pdf') }}"
                data-type="REGISTRO AUTOMEZZI (PDF)">
                <i class="fas fa-truck me-1"></i> Registro Automezzi (PDF)
              </button>

              <button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.km_percentuali.pdf') }}"
                data-type="DISTINTA KM / PERCENTUALI (PDF)"
                data-key="kmperc">
                <i class="fas fa-percent me-1"></i> Distinta KM / %
              </button>

              <button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.servizi_svolti.pdf') }}"
                data-type="DISTINTA SERVIZI / % (PDF)">
                <i class="fas fa-people-arrows me-1"></i> Distinta Servizi / %
              </button>

              <button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.rapporti_ricavi.pdf') }}"
                data-type="RAPPORTI RICAVI / % (PDF)">
                <i class="fas fa-euro-sign me-1"></i> Rapporti ricavi / %
              </button>

              <button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.rip_volontari_scn.pdf') }}"
                data-type="VOLONTARI + SERVIZIO CIVILE (PDF)">
                <i class="fas fa-hands-helping me-1"></i> Volontari + Servizio Civile (PDF)
              </button>

              <button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.ripartizione_personale.pdf') }}"
                data-type="RIPARTIZIONE PERSONALE (PDF)">
                <i class="fas fa-user-clock me-1"></i> Ripartizione personale (PDF)
              </button>

              <button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.servizi_svolti_ossigeno.pdf') }}"
                data-type="SERVIZI SVOLTI OSSIGENO/MATERIALE (PDF)">
                <i class="fas fa-notes-medical me-1"></i> Servizi Svolti – Ossigeno/Materiale
              </button>

              <button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.costi_personale.pdf') }}"
                data-type="COSTI PERSONALE (PDF)">
                <i class="fas fa-user-tie me-1"></i> Costi personale (PDF)
              </button>

              <button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.costi_radio.pdf') }}"
                data-type="DISTINTA COSTI RADIO (PDF)">
                <i class="fas fa-broadcast-tower me-1"></i> Costi radio (PDF)
              </button>

              <button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.imputazioni_materiale_ossigeno.pdf') }}"
                data-type="IMPUTAZIONI MATERIALE + OSSIGENO (PDF)">
                <i class="fas fa-first-aid me-1"></i> Materiale sanitario + Ossigeno (PDF)
              </button>

              <button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.costi_automezzi_sanitari.pdf') }}"
                data-type="DISTINTA COSTI AUTOMEZZI/ATTREZZATURE (PDF)">
                <i class="fas fa-tools me-1"></i> Costi automezzi / attrezzatura sanitaria
              </button>

              <button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.ripartizione_costi_automezzi_riepilogo.pdf') }}"
                data-type="RIP. COSTI AUTOMEZZI – TOTALE + PER TARGA (PDF)">
                <i class="fas fa-table me-1"></i> Ripartizione: totale + per targa
              </button>

              <button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.distinta_imputazione_costi.pdf') }}"
                data-type="DISTINTA IMPUTAZIONE COSTI (PDF)">
                <i class="fas fa-file-excel me-1"></i> Distinta imputazione costi (PDF)
              </button>

              <button type="button" class="btn btn-anpas-green"
                data-endpoint="{{ route('documenti.riepiloghi_dati_costi.pdf') }}"
                data-type="RIEPILOGO DATI + COSTI (PDF)">
                <i class="fas fa-table me-1"></i> Riepilogo dati + costi (TOT + per convenzione)
              </button>
-->
            </div>
          </form>

          <small class="text-muted d-block mt-2">
            I documenti vengono generati in coda; appena pronti comparirà il link di download qui sotto.
          </small>
        </div>
      </div>
    </div>

    <div class="col-md-7">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title mb-3">Archivio documenti</h5>

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
                  <span class="badge bg-warning text-dark">In coda</span>
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
    const setAssocRoute = @json($routeSetAssoc); // può essere null

    /* =========================
       DataTable Archivio Documenti
       (usa gli asset globali già presenti nel progetto)
    ========================= */
    const dt = $('#docsTable').DataTable({
      pageLength: 25,
      lengthMenu: [5, 10, 25, 50, 100],
      order: [
        [3, 'desc']
      ], // ordina per "Generato il"
      columnDefs: [{
        targets: 4,
        orderable: false
      }],
      language: {
        url: '/js/i18n/Italian.json'
      }
    });

    // Persistenza selezione: sessione se la rotta esiste, altrimenti localStorage
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

    // ripristino da localStorage se non abbiamo sessione e siamo admin
    document.addEventListener('DOMContentLoaded', () => {
      if (idAssEl && !idAssEl.value) {
        try {
          const saved = localStorage.getItem('doc_export_assoc');
          if (saved) idAssEl.value = saved;
        } catch (_) {}
      }
    });

    idAssEl?.addEventListener('change', persistSelection);

    // click per ciascun bottone (ognuno ha data-endpoint e data-type)
    params.querySelectorAll('button[data-endpoint]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const idAssociazione = (idAssEl?.value || '').trim();
        const idAnno = (idAnnoEl?.value || '').trim();
        if (!idAssociazione || !idAnno) {
          alert('Seleziona Associazione e Anno');
          return;
        }

        // salvo selezione
        await persistSelection();

        // avvio job
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
        } = await res.json(); // il controller deve restituire { id: <documento_id> }
        const typeLabel = btn.dataset.type || 'Documento';

        // aggiungi riga “in coda” tramite DataTable
        const rowNode = dt.row.add([
          id,
          typeLabel,
          idAnno,
          '—',
          `<span class="badge bg-warning text-dark">In coda</span>`
        ]).draw(false).node();

        // assegna un id DOM alla riga per il polling
        rowNode.id = 'docrow-' + id;

        // polling stato
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
          const j = await r.json(); // {status:'queued|processing|ready|error', generato_il, download_url}

          const row = dt.row('#docrow-' + id);
          if (!row.node()) return;

          const data = row.data(); // [ID, Tipo, Anno, Generato il, Azione]

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
        } catch (_) {
          /* ignora, ritenta */
        }
      }, 2500);
    }
  })();
</script>
@endpush