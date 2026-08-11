(function ($, Swal) {
    'use strict';

    $(function () {
        const pageElement = document.getElementById('berkasBukaPage');

        if (!pageElement) {
            return;
        }

        const filelistId = Number(pageElement.dataset.filelistId);
        const activeFilelistsUrl = pageElement.dataset.activeFilelistsUrl;
        const pendingLettersUrl = pageElement.dataset.pendingLettersUrl;
        const hasBulkSelection = $('#bulkSelectAll').length > 0;
        const datatable = $('#datatabel').DataTable({
            scrollX: true,
            autoWidth: false,
            order: [[hasBulkSelection ? 3 : 2, 'desc']],
        });

        $('#modalPemberkasanBulk').select2({
            width: '100%',
            dropdownParent: $('#modalBulkPindah'),
            placeholder: '- Pilih Pemberkasan -',
        });

        $('#modalKodeKlasifikasi').select2({
            width: '100%',
            dropdownParent: $('#modalEditBerkas'),
            placeholder: '- Pilih Klasifikasi -',
        });

        $('#attachJenis, #attachTahun').select2({
            width: '100%',
            dropdownParent: $('#modalLampirkanSurat'),
            minimumResultsForSearch: Infinity,
        });

        function loadPemberkasanBulkOptions() {
            const select = $('#modalPemberkasanBulk');
            select.prop('disabled', true);
            select.html('<option value="">Memuat data pemberkasan...</option>').trigger('change');

            return $.ajax({
                url: activeFilelistsUrl,
                method: 'GET',
                dataType: 'json',
            }).done(function (response) {
                const items = response && Array.isArray(response.data) ? response.data : [];
                const availableItems = items.filter(function (item) {
                    return Number(item.id) !== filelistId;
                });

                select.empty().append(new Option('- Pilih Pemberkasan -', ''));
                availableItems.forEach(function (item) {
                    select.append(new Option(
                        String(item.kode_klasifikasi || '-') + ' - ' + String(item.nama_berkas || ''),
                        String(item.id),
                    ));
                });

                if (availableItems.length === 0) {
                    select.empty().append(new Option('Tidak ada data pemberkasan aktif', ''));
                }

                select.prop('disabled', availableItems.length === 0);
                select.val('').trigger('change');
            }).fail(function () {
                select.html('<option value="">Gagal memuat data</option>');
                select.prop('disabled', true);
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Data pemberkasan tujuan gagal dimuat dari server.',
                });
            });
        }

        function refreshBulkUiState() {
            const total = datatable.$('.bulk-item').length;
            const checked = datatable.$('.bulk-item:checked').length;
            $('#bulkSelectedCount').text(checked);
            $('#bulkSelectedCountModal').text(checked);
            $('#btnOpenBulkModal').prop('disabled', checked === 0);
            $('#bulkSelectAll').prop('checked', total > 0 && total === checked);
        }

        $('#bulkSelectAll').on('change', function () {
            datatable.$('.bulk-item').prop('checked', $(this).is(':checked'));
            refreshBulkUiState();
        });

        $('#datatabel tbody').on('change', '.bulk-item', refreshBulkUiState);

        $('#btnOpenBulkModal').on('click', function () {
            const selectedItems = datatable.$('.bulk-item:checked').map(function () {
                return $(this).val();
            }).get();
            const selectedContainer = $('#bulkSelectedContainer');
            selectedContainer.empty();

            selectedItems.forEach(function (value) {
                $('<input>', {
                    type: 'hidden',
                    name: 'items[]',
                    value: value,
                }).appendTo(selectedContainer);
            });

            $('#modalBulkPindah').modal('show');
            loadPemberkasanBulkOptions();
        });

        $('#btnOpenEditBerkasModal').on('click', function () {
            $('#modalEditBerkas').modal('show');
        });

        $('#formBulkPindah').on('submit', function (event) {
            event.preventDefault();
            const form = this;

            Swal.fire({
                title: 'Pindahkan surat terpilih?',
                text: 'Pastikan berkas tujuan sudah benar.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, pindahkan',
                cancelButtonText: 'Batal',
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });

        $('#datatabel tbody').on('submit', '.detach-letter-form', function (event) {
            event.preventDefault();
            const form = this;
            const letterNumber = String($(form).attr('data-letter-number') || '').trim();
            const letterLabel = letterNumber === '' ? 'Surat ini' : 'Surat ' + letterNumber;

            Swal.fire({
                title: 'Keluarkan surat dari berkas?',
                text: letterLabel + ' akan dihapus dari pemberkasan ini. Dokumen surat tidak akan dihapus.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, keluarkan',
                cancelButtonText: 'Batal',
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });

        const attachSelections = new Map();
        let attachTable = null;

        function selectedAttachType() {
            return String($('#attachJenis').val() || '');
        }

        function selectedAttachYear() {
            return String($('#attachTahun').val() || '');
        }

        function attachFiltersAreComplete() {
            return selectedAttachType() !== '' && selectedAttachYear() !== '';
        }

        function refreshAttachUiState() {
            const pageCheckboxes = $('#attachLettersTable tbody .attach-item');
            const checkedOnPage = pageCheckboxes.filter(':checked').length;
            const selectAll = $('#attachSelectAll');

            $('#attachSelectedCount, #attachSelectedMenuCount').text(attachSelections.size);
            $('#btnAttachSelected').prop('disabled', attachSelections.size === 0);
            selectAll.prop('checked', pageCheckboxes.length > 0 && checkedOnPage === pageCheckboxes.length);
            selectAll.prop('indeterminate', checkedOnPage > 0 && checkedOnPage < pageCheckboxes.length);

            renderAttachSelections();
        }

        function resetAttachSelection() {
            attachSelections.clear();
            $('#attachLettersTable tbody .attach-item').prop('checked', false);
            refreshAttachUiState();
        }

        function attachSelectionFromRow(row) {
            const key = String(row.jenis) + ':' + Number(row.id);

            return {
                key: key,
                id: Number(row.id),
                jenis: String(row.jenis),
                tahun: Number(row.tahun),
                tanggalSurat: String(row.tanggal_surat || '-'),
                nomorSurat: String(row.nomor_surat || '-'),
                pihak: String(row.pihak || '-'),
                perihal: String(row.perihal || '-'),
            };
        }

        function renderAttachSelections() {
            const tableBody = $('#attachSelectedTableBody');
            const selections = Array.from(attachSelections.values()).sort(function (first, second) {
                return second.tahun - first.tahun
                    || first.jenis.localeCompare(second.jenis)
                    || second.id - first.id;
            });

            tableBody.empty();
            selections.forEach(function (selection) {
                const row = $('<tr>');
                const removeButton = $('<button>', {
                    type: 'button',
                    class: 'btn btn-sm btn-danger remove-attach-selection',
                    title: 'Hapus dari pilihan',
                    'aria-label': 'Hapus surat dari pilihan',
                    'data-selection-key': selection.key,
                }).append($('<i>', {class: 'fa fa-times'}));
                const subjectCell = $('<td>');
                const subjectLayout = $('<div>', {
                    class: 'd-flex justify-content-between align-items-start',
                });

                $('<td>').text(selection.tanggalSurat).appendTo(row);
                $('<td>').text(selection.nomorSurat).appendTo(row);
                $('<td>').text(selection.pihak).appendTo(row);
                subjectLayout.append(
                    $('<span>').text(selection.perihal),
                    removeButton.addClass('ml-2'),
                ).appendTo(subjectCell);
                subjectCell.appendTo(row);
                row.appendTo(tableBody);
            });

            $('#attachSelectedEmpty').toggleClass('d-none', selections.length > 0);
            $('#attachSelectedTableContainer').toggleClass('d-none', selections.length === 0);
        }

        function synchronizeAttachCheckboxes() {
            $('#attachLettersTable tbody .attach-item').each(function () {
                $(this).prop('checked', attachSelections.has(String($(this).val())));
            });
            refreshAttachUiState();
        }

        function initializeAttachTable() {
            attachTable = $('#attachLettersTable').DataTable({
                scrollX: true,
                autoWidth: false,
                paging: true,
                processing: true,
                serverSide: true,
                ajax: {
                    url: pendingLettersUrl,
                    data: function (data) {
                        data.jenis = selectedAttachType();
                        data.tahun = selectedAttachYear();
                    },
                },
                columns: [
                    {
                        data: 'id',
                        name: 'id',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return data;
                            }

                            return '<input type="checkbox" class="attach-item" value="'
                                + String(row.jenis) + ':' + Number(data) + '">';
                        },
                    },
                    {data: 'tanggal_surat', name: 'tanggal_surat'},
                    {data: 'nomor_surat', name: 'nomor_surat'},
                    {data: 'pihak', name: 'pihak'},
                    {data: 'perihal', name: 'perihal'},
                    {
                        data: 'preview_url',
                        name: 'preview_url',
                        orderable: false,
                        searchable: false,
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data || '';
                            }

                            if (typeof data !== 'string' || data === '') {
                                return $('<button>', {
                                    type: 'button',
                                    class: 'btn btn-sm btn-secondary',
                                    title: 'PDF tidak tersedia',
                                    'aria-label': 'PDF tidak tersedia',
                                    disabled: true,
                                }).append($('<i>', {class: 'fa fa-file-pdf'})).prop('outerHTML');
                            }

                            return $('<a>', {
                                href: String(data),
                                target: '_blank',
                                rel: 'noopener noreferrer',
                                class: 'btn btn-sm btn-success',
                                title: 'Lihat PDF di tab baru',
                                'aria-label': 'Lihat PDF di tab baru',
                            }).append($('<i>', {class: 'fa fa-file-pdf'})).prop('outerHTML');
                        },
                    },
                ],
                order: [[1, 'desc']],
                drawCallback: synchronizeAttachCheckboxes,
            });
        }

        function refreshAttachTableForFilters() {
            if (!attachFiltersAreComplete()) {
                $('#attachFilterHint').removeClass('d-none');
                $('#attachTableContainer').addClass('d-none');

                return;
            }

            $('#attachFilterHint').addClass('d-none');
            $('#attachTableContainer').removeClass('d-none');

            if (attachTable === null) {
                initializeAttachTable();
            } else {
                attachTable.ajax.reload(null, true);
            }
        }

        $('#attachJenis, #attachTahun').on('change', refreshAttachTableForFilters);

        $('#attachLettersTable tbody').on('change', '.attach-item', function () {
            const key = String($(this).val());
            const row = attachTable.row($(this).closest('tr')).data();

            if ($(this).is(':checked') && row) {
                attachSelections.set(key, attachSelectionFromRow(row));
            } else {
                attachSelections.delete(key);
            }

            refreshAttachUiState();
        });

        $('#attachSelectAll').on('change', function () {
            const shouldSelect = $(this).is(':checked');

            $('#attachLettersTable tbody .attach-item').each(function () {
                const key = String($(this).val());
                const row = attachTable.row($(this).closest('tr')).data();
                $(this).prop('checked', shouldSelect);

                if (shouldSelect && row) {
                    attachSelections.set(key, attachSelectionFromRow(row));
                } else {
                    attachSelections.delete(key);
                }
            });

            refreshAttachUiState();
        });

        $('#attachSelectedTableBody').on('click', '.remove-attach-selection', function () {
            const key = String($(this).attr('data-selection-key'));
            attachSelections.delete(key);
            synchronizeAttachCheckboxes();
        });

        $('#btnClearAttachSelection').on('click', resetAttachSelection);

        $('#btnOpenAttachModal').on('click', function () {
            $('#modalLampirkanSurat').modal('show');
        });

        $('#modalLampirkanSurat').on('shown.bs.modal', function () {
            if (attachTable !== null) {
                attachTable.columns.adjust();
            }
        });

        $('#attachSearchTab').on('shown.bs.tab', function () {
            if (attachTable !== null) {
                attachTable.columns.adjust();
            }
        });

        $('#formLampirkanSurat').on('submit', function (event) {
            event.preventDefault();

            if (attachSelections.size === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Belum Ada Pilihan',
                    text: 'Pilih minimal satu surat untuk dilampirkan.',
                });

                return;
            }

            const form = this;
            const selectedContainer = $('#attachSelectedContainer');
            selectedContainer.empty();
            Array.from(attachSelections.keys()).sort().forEach(function (key) {
                $('<input>', {
                    type: 'hidden',
                    name: 'items[]',
                    value: key,
                }).appendTo(selectedContainer);
            });

            Swal.fire({
                title: 'Lampirkan surat terpilih?',
                text: attachSelections.size + ' surat akan dimasukkan ke berkas ini.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, lampirkan',
                cancelButtonText: 'Batal',
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });

        if (pageElement.dataset.openEditModal === '1') {
            $('#modalEditBerkas').modal('show');
        }

        if (pageElement.dataset.openAttachModal === '1') {
            $('#modalLampirkanSurat').modal('show');
        }

        refreshBulkUiState();
        refreshAttachUiState();
        refreshAttachTableForFilters();
    });
})(jQuery, Swal);
