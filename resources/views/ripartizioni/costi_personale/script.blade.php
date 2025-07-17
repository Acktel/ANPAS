<script>
    $(async function() {
        const $table = $('#table-costi');

        const staticColumns = [{
                key: 'is_totale',
                label: '',
                hidden: true
            },
            {
                key: 'idDipendente',
                label: 'ID',
                hidden: true
            },
            {
                key: 'Dipendente',
                label: 'COGNOME'
            },
            {
                key: 'Retribuzioni',
                label: 'RETRIBUZIONI'
            },
            {
                key: 'OneriSociali',
                label: 'ONERI<br>SOCIALI'
            },
            {
                key: 'TFR',
                label: 'TFR'
            },
            {
                key: 'Consulenze',
                label: 'CONSULENZE PER PERSONALE DIPENDENTE<br>E SORVEGLIANZA SANITARIA D.Lgs 626/94'
            },
            {
                key: 'Totale',
                label: 'TOTALE'
            },
        ];

        function buildAutistiTable(data, labels) {
            const convenzioni = Object.keys(labels).sort((a, b) => parseInt(a.slice(1)) - parseInt(b.slice(1)));
            let headerMain = '',
                headerSub = '';
            const columns = [],
                costColumnIndexes = [];
            let visibleIndex = 0;

            staticColumns.forEach(col => {
                headerMain += `<th rowspan="2"${col.hidden ? ' style="display:none"' : ''}>${col.label}</th>`;
                columns.push({
                    data: col.key,
                    visible: !col.hidden
                });
                if (!col.hidden) visibleIndex++;
            });

            convenzioni.forEach(conv => {
                headerMain += `<th colspan="2">${labels[conv]}</th>`;
                headerSub += `<th class="text-end">Importo €</th><th class="text-end d-none">%</th>`;
                columns.push({
                    data: `${conv}_importo`,
                    className: 'text-end',
                    defaultContent: "0.00"
                });
                costColumnIndexes.push(visibleIndex++);
                columns.push({
                    data: `${conv}_percent`,
                    className: 'text-end d-none',
                    visible: false,
                    defaultContent: "0.00"
                });
                visibleIndex++;
            });

            headerMain += `<th rowspan="2">Azioni</th>`;
            columns.push({
                data: null,
                orderable: false,
                searchable: false,
                render: row => row.is_totale || !row.idDipendente ? '-' : `
                <a href="/ripartizioni/personale/costi/${row.idDipendente}" class="btn btn-sm btn-info me-1"><i class="fas fa-eye"></i></a>
                <a href="/ripartizioni/personale/costi/${row.idDipendente}/edit" class="btn btn-sm btn-anpas-edit me-1"><i class="fas fa-edit"></i></a>
                <form method="POST" action="/ripartizioni/personale/costi/${row.idDipendente}" class="d-inline-block" onsubmit="return confirm('Confermi eliminazione?')">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-sm btn-anpas-delete"><i class="fas fa-trash-alt"></i></button>
                </form>
            `
            });

            $('#header-main').html(headerMain);
            $('#header-sub').html(headerSub);

            $table.DataTable().clear().destroy();
            $table.DataTable({
                data,
                columns,
                columnDefs: [{
                        targets: 0,
                        visible: false
                    },
                    {
                        targets: costColumnIndexes,
                        className: 'text-end'
                    }
                ],
                order: [
                    [0, 'asc']
                ],
                responsive: true,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.11.3/i18n/it_it.json'
                },
                rowCallback: function(row, data) {
                    if (data.is_totale) $(row).addClass('fw-bold table-totalRow');
                }
            });
        }

        function buildGenericaTable(data) {
            const $generic = $('#tabella-generica table');

            if (!$.fn.DataTable.isDataTable($generic)) {
                $generic.DataTable({
                    data,
                    columns: [{
                            data: 'Dipendente',
                            title: 'Dipendente'
                        },
                        {
                            data: 'Qualifica',
                            title: 'Qualifica'
                        },
                        {
                            data: 'Contratto',
                            title: 'Contratto'
                        },
                        {
                            data: 'Retribuzioni',
                            title: 'Retribuzioni',
                            className: 'text-end'
                        },
                        {
                            data: 'OneriSociali',
                            title: 'Oneri Sociali',
                            className: 'text-end'
                        },
                        {
                            data: 'TFR',
                            title: 'TFR',
                            className: 'text-end'
                        },
                        {
                            data: 'Consulenze',
                            title: 'Consulenze',
                            className: 'text-end'
                        },
                        {
                            data: 'Totale',
                            title: 'Totale',
                            className: 'text-end'
                        },
                        {
                            data: null,
                            title: 'Azioni',
                            orderable: false,
                            searchable: false,
                            className: 'text-center',
                            render: function(data, type, row) {
                                if (row.is_totale || !row.idDipendente) return '-';
                                return `
                    <a href="/ripartizioni/personale/costi/${row.idDipendente}" class="btn btn-sm btn-info me-1"><i class="fas fa-eye"></i></a>
                    <a href="/ripartizioni/personale/costi/${row.idDipendente}/edit" class="btn btn-sm btn-anpas-edit me-1"><i class="fas fa-edit"></i></a>
                    <form method="POST" action="/ripartizioni/personale/costi/${row.idDipendente}" class="d-inline-block" onsubmit="return confirm('Confermi eliminazione?')">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-sm btn-anpas-delete"><i class="fas fa-trash-alt"></i></button>
                    </form>
                `;
                            }
                        }
                    ],
                    paging: false,
                    searching: false,
                    info: false,
                    ordering: false,
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.11.3/i18n/it_it.json'
                    },
                    rowCallback: function(row, data) {
                        if (data.is_totale) $(row).addClass('fw-bold table-totalRow');
                    }
                });
            } else {
                $generic.DataTable().clear().rows.add(data).draw();
            }
        }

        async function loadAutistiTable() {
            const res = await fetch("{{ route('ripartizioni.personale.costi.data') }}");
            const {
                data,
                labels
            } = await res.json();
            if (data.length) buildAutistiTable(data, labels);
        }

        // Default: Autisti e Barellieri
        const defaultQualifica = 'Autisti e Barellieri';
        $(`.btn-qualifica[data-qualifica="${defaultQualifica}"]`).addClass('active btn-anpas-green-active');
        $('#titolo-pagina').text(`Distinta Rilevazione Analitica Costi Personale (${defaultQualifica}) − Anno {{ $anno }}`);
        await loadAutistiTable();

        // Cambio qualifica
        $('.btn-qualifica').on('click', async function() {
            const qualifica = $(this).data('qualifica');

            $('.btn-qualifica').removeClass('active btn-anpas-green-active');
            $(this).addClass('active btn-anpas-green-active');
            $('#titolo-pagina').text(`Distinta Rilevazione Analitica Costi Personale (${qualifica}) − Anno {{ $anno }}`);

            const isAutisti = qualifica.toLowerCase() === 'autisti e barellieri';
            $('#tabella-autisti').toggleClass('d-none', !isAutisti);
            $('#tabella-generica').toggleClass('d-none', isAutisti);

            const url = `{{ route('ripartizioni.personale.costi.data') }}?qualifica=${encodeURIComponent(qualifica)}`;
            const res = await fetch(url);
            const {
                data,
                labels
            } = await res.json();

            if (isAutisti) {
                buildAutistiTable(data, labels);
            } else {
                buildGenericaTable(data);
            }
        });
    });
</script>