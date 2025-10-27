{{-- resources/views/riepilogo_costi/partials/rot_sost.blade.php --}}
@php
  // opzionali se vuoi un fallback lato server, ma il partial lavora via JS/AJAX
  $anno = $anno ?? (int) session('anno_riferimento', now()->year);
@endphp

<div id="box-rot-sost" class="card-anpas mb-3 d-none">
  <div class="card-body bg-anpas-white">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
      <h5 class="mb-2 mb-md-0">
        <span id="rot-sost-title">—</span>
      </h5>
      <div class="d-flex gap-2 align-items-center">
        <span class="badge rounded-pill text-bg-primary" id="rot-sost-perc">—</span>
        <span class="badge rounded-pill text-bg-secondary">Anno: <span id="rot-sost-anno">{{ $anno }}</span></span>
      </div>
    </div>

    <div id="rot-sost-alert" class="alert alert-info py-2 mb-3 d-none"></div>

    {{-- form costo fascia oraria (solo per "Mezzi sostitutivi") --}}
    <form id="form-mezzi-sostitutivi" class="row g-3 d-none">
      @csrf
      <input type="hidden" name="idAssociazione" id="rot-sost-ass">
      <input type="hidden" name="idConvenzione" id="rot-sost-conv">
      <input type="hidden" name="idAnno"         id="rot-sost-year" value="{{ $anno }}">

      <div class="col-md-4">
        <label for="costo_fascia_oraria" class="form-label">Costo fascia oraria (€)</label>
        <input type="number" step="0.01" min="0" class="form-control text-end"
               id="costo_fascia_oraria" name="costo_fascia_oraria" value="">
        <small class="text-muted">Valorizza solo in regime <strong>Mezzi sostitutivi</strong>.</small>
      </div>

      <div class="col-12">
        <button type="submit" class="btn btn-anpas-green">
          <i class="fas fa-save me-1"></i> Salva costo sostitutivi
        </button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
(function(){
  const $box    = document.getElementById('box-rot-sost');
  const $title  = document.getElementById('rot-sost-title');
  const $perc   = document.getElementById('rot-sost-perc');
  const $alert  = document.getElementById('rot-sost-alert');
  const $form   = document.getElementById('form-mezzi-sostitutivi');
  const $ass    = document.getElementById('rot-sost-ass');
  const $conv   = document.getElementById('rot-sost-conv');
  const $year   = document.getElementById('rot-sost-year');
  const $costo  = document.getElementById('costo_fascia_oraria');

  const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.content || '';

  function currentAssociazione(){
    const el = document.getElementById('assocSelect');
    return (el?.value || '').trim();
  }
  function currentConvenzione(){
    const el = document.getElementById('convSelect');
    return (el?.value || '').trim();
  }

  async function loadStato(){
    const ass = currentAssociazione();
    const conv = currentConvenzione();
    const anno = $year?.value || '';

   
    // visibilità base
    if (!conv || conv === 'TOT') {
      $box?.classList.add('d-none');
      return;
    }

    try {
      const qs = new URLSearchParams({ idAssociazione: ass, idConvenzione: conv, anno });
      const r = await fetch(`/ajax/rot-sost/stato?${qs.toString()}`);
      const j = await r.json();

      // nascondo di default
      $box.classList.add('d-none');
      $form.classList.add('d-none');
      $alert.classList.add('d-none');

      if (!j?.ok) return;

      // set hidden form fields
      if ($ass)  $ass.value  = ass;
      if ($conv) $conv.value = conv;
 console.log("modalita: ",j);
      // modalità: 'sostitutivi' | 'rotazione' | 'off'
      const mode = j.modalita;
      const percTxt = (j.percentuale ?? null) !== null ? `${Number(j.percentuale).toFixed(2)}%` : '—';
      $perc.textContent = `KM titolare: ${percTxt}`;

      if (mode === 'off') {
        // se il flag non è attivo o non c'è mezzo titolare => non mostro nulla
        $box.classList.add('d-none');
        return;
      }

      // Mostro il box
      $box.classList.remove('d-none');

      if (mode === 'sostitutivi') {
        $title.textContent = 'Mezzi sostitutivi';
        $alert.textContent = 'La convenzione è in regime di Mezzi sostitutivi (KM mezzo titolare ≥ 98%): eventuali costi sostitutivi vanno gestiti con il costo fascia oraria.';
        $alert.classList.remove('d-none');
        if ($form) {
          $form.classList.remove('d-none');
          if ($costo) $costo.value = (j.costo_fascia_oraria ?? 0).toFixed(2);
        }
      } else if (mode === 'rotazione') {
        $title.textContent = 'Rotazione mezzi';
        $alert.textContent = 'La convenzione è in regime di Rotazione mezzi (KM mezzo titolare < 98%): i costi vengono ripartiti con la regola di rotazione. Niente calcolo sostitutivi.';
        $alert.classList.remove('d-none');
      }
    } catch (e) {
      // silenzio: il box resta nascosto
    }
  }

  // submit costo fascia oraria
  $form?.addEventListener('submit', async function(e){
    e.preventDefault();
    const payload = {
      idAssociazione: $ass?.value || '',
      idConvenzione:  $conv?.value || '',
      idAnno:         $year?.value || '',
      costo_fascia_oraria: Number($costo?.value || 0)
    };
    try {
      const r = await fetch(`/mezzi-sostitutivi/salva`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify(payload)
      });
      const j = await r.json();
      if (j?.ok) {
        // mini feedback
        $costo?.classList.add('is-valid');
        setTimeout(()=> $costo?.classList.remove('is-valid'), 1200);
      } else {
        alert(j?.message || 'Errore salvataggio costo.');
      }
    } catch (e) {
      alert('Errore salvataggio costo.');
    }
  });

  // esponi funzione globale per richiamarla da index quando cambia convenzione
  window.__reloadRotSostBox = loadStato;

  // bootstrap lazy: prova a caricare se i select sono già presenti
  setTimeout(loadStato, 0);
})();
</script>
@endpush
