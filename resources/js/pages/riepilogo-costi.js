import $ from 'jquery';
import 'datatables.net-bs5';
import Sortable from 'sortablejs';

document.addEventListener('DOMContentLoaded', function () {
  const italian = "https://cdn.datatables.net/plug-ins/1.11.3/i18n/it_it.json";
  const { sezioni, csrf } = window.riepilogoCosti;
  let totalePreventivo = 0;
  let totaleConsuntivo = 0;

  Object.keys(sezioni).forEach(id => {
    const tableId = `#table-sezione-${id}`;
    const table = $(tableId).DataTable({
      ajax: `/riepilogo-costi/sezione/${id}`,
      columns: [
        { data: 'descrizione' },
        { data: 'preventivo' },
        { data: 'consuntivo' },
        { data: 'scostamento' },
        { data: 'actions', orderable: false, searchable: false }
      ],
      language: { url: italian },
      stripeClasses: ['table-striped-anpas', ''],
      rowId: 'id',
      drawCallback: () => enableDrag(id),
      initComplete: (settings, json) => {
        let prev = 0, cons = 0;
        json.data.forEach(r => {
          prev += parseFloat(r.preventivo) || 0;
          cons += parseFloat(r.consuntivo) || 0;
        });

        const sc = prev ? (((cons - prev) / prev) * 100).toFixed(2) + '%' : '0%';

        document.getElementById(`summary-prev-${id}`).textContent = '€' + prev.toFixed(2);
        document.getElementById(`summary-cons-${id}`).textContent = '€' + cons.toFixed(2);
        document.getElementById(`summary-scos-${id}`).textContent = sc;

        totalePreventivo += prev;
        totaleConsuntivo += cons;
        const totScostamento = totalePreventivo
          ? (((totaleConsuntivo - totalePreventivo) / totalePreventivo) * 100).toFixed(2) + '%'
          : '0%';

        document.getElementById('tot-prev').textContent = '€' + totalePreventivo.toFixed(2);
        document.getElementById('tot-cons').textContent = '€' + totaleConsuntivo.toFixed(2);
        document.getElementById('tot-scos').textContent = totScostamento;
      }
    });
  });

  function enableDrag(sezioneId) {
    const tbody = document.querySelector(`#table-sezione-${sezioneId} tbody.sortable`);
    if (!tbody) return;

    Sortable.create(tbody, {
      animation: 150,
      handle: 'td',
      onEnd: () => {
        const order = [...tbody.querySelectorAll('tr')].map(row => row.id);
        fetch(`/riepilogo-costi/riordina/${sezioneId}`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf
          },
          body: JSON.stringify({ order })
        });
      }
    });
  }

  // duplicazione voci
  fetch("/riepilogo-costi/check-duplicazione")
    .then(r => r.json())
    .then(d => {
      if (d.mostraMessaggio) {
        document.getElementById('noDataMessage')?.classList.remove('d-none');
      }
    });

  document.getElementById('btn-duplica-si')?.addEventListener('click', async function () {
    this.disabled = true;
    this.innerText = 'Duplicazione…';
    const res = await fetch("/riepilogo-costi/duplica", {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json'
      }
    });
    if (res.ok) location.reload();
    else {
      alert('Errore duplicazione');
      this.disabled = false;
      this.innerText = 'Sì';
    }
  });

  document.getElementById('btn-duplica-no')?.addEventListener('click', () => {
    document.getElementById('noDataMessage')?.classList.add('d-none');
  });
});
