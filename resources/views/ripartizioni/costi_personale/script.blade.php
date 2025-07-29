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
                label: 'Cognome'
            },
            {
                key: 'Retribuzioni',
                label: 'Retribuzioni'
            },
            {
                key: 'OneriSociali',
                label: 'Oneri<br>Sociali'
            },
            {
                key: 'TFR',
                label: 'TFR'
            },
            {
                key: 'Consulenze',
                label: 'Consulenze<br>e Sorveglianza sanitaria'
            },
            {
                key: 'Totale',
                label: 'Totale'
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
                className: 'col-actions text-center',
                render: row => row.is_totale || !row.idDipendente ? '-' : `
                <a href="/ripartizioni/personale/costi/${row.idDipendente}" class="btn btn-sm btn-info me-1 btn-icon" title="Visualizza"><i class="fas fa-eye"></i></a>
                <a href="/ripartizioni/personale/costi/${row.idDipendente}/edit" class="btn btn-sm btn-anpas-edit me-1 btn-icon" title="Modifica"><i class="fas fa-edit"></i></a>
                <form method="POST" action="/ripartizioni/personale/costi/${row.idDipendente}" class="d-inline-block" onsubmit="return confirm('Confermi eliminazione?')">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-sm btn-anpas-delete btn-icon" title="Elimina"><i class="fas fa-trash-alt"></i></button>
                </form>`
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
                paging: false,
                searching: false,
                info: false,
                language: {
                    url: '/js/i18n/Italian.json'
                },
                rowCallback: function(row, data, index) {
                    if (index % 2 === 0) {
                        $(row).removeClass('even').removeClass('odd').addClass('even');
                    } else {
                        $(row).removeClass('even').removeClass('odd').addClass('odd');
                    }
                },
                stripeClasses: ['table-white', 'table-striped-anpas'],
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
                            className: 'col-actions text-center',
                            render: row => row.is_totale || !row.idDipendente ? '-' : `
                            <a href="/ripartizioni/personale/costi/${row.idDipendente}" class="btn btn-sm btn-info me-1 btn-icon btn-icon" title="Visualizza"><i class="fas fa-eye"></i></a>
                            <a href="/ripartizioni/personale/costi/${row.idDipendente}/edit" class="btn btn-sm btn-anpas-edit me-1 btn-icon btn-icon" title="Modifica"><i class="fas fa-edit"></i></a>
                            <form method="POST" action="/ripartizioni/personale/costi/${row.idDipendente}" class="d-inline-block" onsubmit="return confirm('Confermi eliminazione?')">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="btn btn-sm btn-anpas-delete btn-icon" title="Elimina"><i class="fas fa-trash-alt"></i></button>
                            </form>`
                        }
                    ],
                    paging: false,
                    searching: false,
                    info: false,
                    ordering: false,
                    language: {
                        url: '/js/i18n/Italian.json'
                    },
                    rowCallback: function(row, data, index) {
                        if (index % 2 === 0) {
                            $(row).removeClass('even').removeClass('odd').addClass('even');
                        } else {
                            $(row).removeClass('even').removeClass('odd').addClass('odd');
                        }
                    },
                    stripeClasses: ['table-white', 'table-striped-anpas'],
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

        // Inizializzazione default
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

            isAutisti ? buildAutistiTable(data, labels) : buildGenericaTable(data);
        });
    });
</script>