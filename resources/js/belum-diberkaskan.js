(function ($) {
    'use strict';

    $(function () {
        const $page = $('#pendingFilingPage');

        if ($page.length === 0) {
            return;
        }

        const table = $('#datatabel').DataTable({
            scrollX: true,
            autoWidth: false,
            paging: true,
            processing: true,
            serverSide: true,
            ajax: {
                url: String($page.data('list-url')),
            },
            columns: [
                {
                    data: 'jenis',
                    name: 'jenis',
                    render: function (data) {
                        const badgeClass = data === 'masuk' ? 'badge-info' : 'badge-success';
                        const label = data === 'masuk' ? 'Surat Masuk' : 'Surat Keluar';

                        return `<span class="badge ${badgeClass}">${label}</span>`;
                    },
                },
                { data: 'tanggal_pencatatan', name: 'tanggal_pencatatan' },
                { data: 'tanggal_surat', name: 'tanggal_surat' },
                {
                    data: 'nomor_agenda',
                    name: 'nomor_agenda',
                    render: function (data) {
                        return data ? data : '-';
                    },
                },
                { data: 'nomor_surat', name: 'nomor_surat' },
                { data: 'pihak', name: 'pihak' },
                { data: 'perihal', name: 'perihal' },
                {
                    data: 'aksi',
                    name: 'aksi',
                    orderable: false,
                    searchable: false,
                },
            ],
            order: [[1, 'desc']],
        });

        table.on('xhr.dt', function (event, settings, json) {
            if (json) {
                $('#totalSuratBelumDiberkaskan').text(json.recordsFiltered ?? 0);
            }
        });
    });
})(jQuery);
