<script>
(function () {

  // --- utils ---
  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  const staticColumns = [
    { key: 'is_totale',        label: '',                                   hidden: true  },
    { key: 'idDipendente',     label: 'ID',                                 hidden: true  },
    { key: 'Dipendente',       label: 'Dipendente'                                       },
    { key: 'Retribuzioni',     label: 'Retribuzioni'                                     },
    { key: 'OneriSocialiInps', label: 'Oneri<br>Sociali INPS'                            },
    { key: 'OneriSocialiInail',label: 'Oneri<br>Sociali INAIL'                           },
    { key: 'TFR',              label: 'TFR'                                              },
    { key: 'Consulenze',       label: 'Consulenze<br>e Sorveglianza sanitaria'           },
    { key: 'Totale',           label: 'Totale'                                           },
  ];

  function destroyIfExists($t) {
    if ($.fn.DataTable.isDataTable($t)) {
      $t.DataTable().clear().destroy();
    }
    // pulizia markup residuo
    const $thead = $t.find('thead');
    const $tbody = $t.find('tbody');
    if ($thead.length) $thead.empty();
    if ($tbody.length) $tbody.empty();
  }

  // ====== ESPORTO come globali per usarle da index.blade ======
  window.buildAutistiTable = function buildAutistiTable(data, labels) {
    const $table = $('#table-costi-autisti');                    // 👈 ID DEDICATO
    const $thead = $table.find('thead');
    const $tbody = $table.find('tbody');

    // crea thead/tbody se mancanti
    if ($thead.length === 0) $table.append('<thead></thead>');
    if ($tbody.length === 0) $table.append('<tbody></tbody>');

    // header scoped (niente ID globali): due righe thead
    destroyIfExists($table);

    const convenzioni = Object.keys(labels).sort((a, b) => parseInt(a.slice(1)) - parseInt(b.slice(1)));
    let headerMain = '<tr>';
    let headerSub  = '<tr>';
    const columns  = [];
    const costColumnIndexes = [];
    let visibleIndex = 0;

    // colonne statiche
    staticColumns.forEach(col => {
      headerMain += `<th rowspan="2"${col.hidden ? ' style="display:none"' : ''}>${col.label}</th>`;
      columns.push({
        data: col.key,
        className: (col.key === 'Dipendente') ? 'text-start' : 'text-end',
        visible: !col.hidden
      });
      if (!col.hidden) visibleIndex++;
    });

    // colonne dinamiche per convenzioni
    convenzioni.forEach(conv => {
      headerMain += `<th colspan="2">${escapeHtml(labels[conv])}</th>`;
      headerSub  += `<th class="text-end">Importo €</th><th class="text-end d-none">%</th>`;

      columns.push({ data: `${conv}_importo`,  className: 'text-end', visible: true,  defaultContent: "0.00" });
      costColumnIndexes.push(visibleIndex++);
      columns.push({ data: `${conv}_percent`,  className: 'text-end d-none', visible: false, defaultContent: "0.00" });
      visibleIndex++;
    });

    // col Azioni
    headerMain += `<th rowspan="2">Azioni</th>`;
    columns.push({
      data: null,
      orderable: false,
      searchable: false,
      className: 'col-actions text-center',
      render: row => row.is_totale || !row.idDipendente ? '-' : `
        <a href="/ripartizioni/personale/costi/${row.idDipendente}" class="btn btn-anpas-green me-1 btn-icon" title="Visualizza"><i class="fas fa-eye"></i></a>
        <a href="/ripartizioni/personale/costi/${row.idDipendente}/edit" class="btn btn-anpas-edit me-1 btn-icon" title="Modifica"><i class="fas fa-edit"></i></a>`
    });

    headerMain += '</tr>';
    headerSub  += '</tr>';
    $table.find('thead').html(headerMain + headerSub);

    // init DT fresca
    $table.DataTable({
      stateDuration: -1,
      stateSave: true,  
      data,
      columns,
      columnDefs: [
        { targets: 0, visible: false },
        { targets: costColumnIndexes, className: 'text-end' }
      ],
      order: [[0, 'asc']],
      responsive: true,
      paging: true,
      searching: true,
      info: false,
      language: {
        url: '/js/i18n/Italian.json',
        paginate: {
          first: '<i class="fas fa-angle-double-left"></i>',
          last: '<i class="fas fa-angle-double-right"></i>',
          next: '<i class="fas fa-angle-right"></i>',
          previous: '<i class="fas fa-angle-left"></i>'
        },
      },

      drawCallback: function () {
        const api = this.api();

        // rimuovi eventuale clone totale precedente
        api.rows({ page: 'current' }).nodes().to$().filter('.totale-clone').remove();

        const totaleData = api.rows().data().toArray().find(r => r.is_totale === true);
        if (!totaleData) return;

        const $row = $('<tr>').addClass('table-warning fw-bold totale-clone');

        api.columns(':visible').every(function () {
          const col = this.settings()[0].aoColumns[this.index()];
          const dataKey = col.data;
          let cellValue = '';

          if (typeof col.render === 'function') {
            cellValue = col.render(totaleData, 'display', null, { row: -1, col: this.index(), settings: this.settings()[0] });
          } else if (dataKey) {
            cellValue = totaleData[dataKey] ?? '';
          }
          $row.append(`<td class="text-end">${cellValue}</td>`);
        });

        $(api.table().body()).append($row);
      },

      rowCallback: function (rowEl, rowData) {
        if (rowData.is_totale === true) $(rowEl).hide();
        const api = this.api();
        const rowIndex = api.row(rowEl).index();
        $(rowEl).removeClass('even odd').addClass(rowIndex % 2 === 0 ? 'even' : 'odd');
      },

      stripeClasses: ['table-white', 'table-striped-anpas'],
    });
  };

  window.buildGenericaTable = function buildGenericaTable(data) {
    const $table = $('#table-costi-generica');                 // 👈 ID DEDICATO
    if ($table.find('thead').length === 0) $table.append('<thead></thead>');
    if ($table.find('tbody').length === 0) $table.append('<tbody></tbody>');

    if (!$.fn.DataTable.isDataTable($table)) {
      $table.DataTable({
        stateDuration: -1,
        stateSave: true,  
        data,
        columns: [
          { data: 'Dipendente',       title: 'Dipendente' },
          { data: 'Qualifica',        title: 'Qualifica' },
          { data: 'Contratto',        title: 'Contratto' },
          { data: 'Retribuzioni',     title: 'Retribuzioni',      className: 'text-end' },
          { data: 'OneriSocialiInps', title: 'Oneri Sociali INPS',className: 'text-end' },
          { data: 'OneriSocialiInail',title: 'Oneri Sociali INAIL',className: 'text-end' },
          { data: 'TFR',              title: 'TFR',               className: 'text-end' },
          { data: 'Consulenze',       title: 'Consulenze',        className: 'text-end' },
          { data: 'Totale',           title: 'Totale',            className: 'text-end' },
          {
            data: null,
            title: 'Azioni',
            orderable: true,
            searchable: false,
            className: 'col-actions text-center',
            render: row => row.is_totale || !row.idDipendente ? '-' : `
              <a href="/ripartizioni/personale/costi/${row.idDipendente}" class="btn btn-anpas-green me-1 btn-icon" title="Visualizza"><i class="fas fa-eye"></i></a>
              <a href="/ripartizioni/personale/costi/${row.idDipendente}/edit" class="btn btn-anpas-edit me-1 btn-icon" title="Modifica"><i class="fas fa-edit"></i></a>
              <form method="POST" action="/ripartizioni/personale/costi/${row.idDipendente}" class="d-inline-block" onsubmit="return confirm('Confermi eliminazione?')">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="btn btn-anpas-delete btn-icon" title="Elimina"><i class="fas fa-trash-alt"></i></button>
              </form>`
          }
        ],
        paging: true,
        searching: true,
        info: false,
        ordering: true,
        language: {
          url: '/js/i18n/Italian.json',
          paginate: {
            first: '<i class="fas fa-angle-double-left"></i>',
            last: '<i class="fas fa-angle-double-right"></i>',
            next: '<i class="fas fa-angle-right"></i>',
            previous: '<i class="fas fa-angle-left"></i>'
          },
        },
        rowCallback: function (row, data, index) {
          $(row).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
        },
        stripeClasses: ['table-white', 'table-striped-anpas'],
      });
    } else {
      $table.DataTable().clear().rows.add(data).draw();
    }
  };

  // loader opzionale
  window.loadCostiByQualifica = async function loadCostiByQualifica(idQualifica) {
    const url = idQualifica
      ? `{{ route('ripartizioni.personale.costi.data') }}?idQualifica=${encodeURIComponent(idQualifica)}`
      : `{{ route('ripartizioni.personale.costi.data') }}`;
    const res = await fetch(url);
    const { data, labels } = await res.json();
    return { data: data || [], labels: labels || {} };
  };

})();
</script>
