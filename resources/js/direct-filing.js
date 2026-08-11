(function ($) {
    'use strict';

    $(function () {
        const $config = $('#directFilingConfig');

        if ($config.length === 0) {
            return;
        }

        const $modal = $('#directFilingModal');
        const $form = $('#directFilingForm');
        const $filelist = $('#directFilingFilelist');
        const $letterKey = $('#directFilingLetterKey');
        const $letterNumber = $('#directFilingLetterNumber');
        const $status = $('#directFilingStatus');
        const $submit = $('#directFilingSubmit');
        const attachUrlTemplate = String($config.data('attach-url-template'));
        const attachUrlPlaceholder = String($config.data('attach-url-placeholder'));
        let activeFilelistsRequest = null;

        $filelist.select2({
            dropdownParent: $modal,
            placeholder: $filelist.data('placeholder'),
            allowClear: true,
            width: '100%',
        });

        function resetFilelistOptions() {
            $filelist.empty().append(new Option('', '', true, true));
            $filelist.prop('disabled', true).trigger('change.select2');
            $submit.prop('disabled', true);
        }

        function loadActiveFilelists() {
            resetFilelistOptions();
            $status.removeClass('text-danger').addClass('text-muted').text('Memuat berkas aktif...');

            if (activeFilelistsRequest) {
                activeFilelistsRequest.abort();
            }

            activeFilelistsRequest = $.getJSON(String($config.data('active-filelists-url')))
                .done(function (response) {
                    const filelists = Array.isArray(response.data) ? response.data : [];

                    filelists.forEach(function (filelist) {
                        const id = String(filelist.id ?? '');

                        if (!/^[1-9]\d*$/.test(id)) {
                            return;
                        }

                        const classification = String(filelist.kode_klasifikasi || '-');
                        const name = String(filelist.nama_berkas || '-');
                        $filelist.append(new Option(`${classification} - ${name}`, id));
                    });

                    if ($filelist.find('option[value!=""]').length === 0) {
                        $status.text('Tidak ada berkas aktif yang dapat dipilih.');

                        return;
                    }

                    $filelist.prop('disabled', false).trigger('change.select2');
                    $status.text('Cari berdasarkan kode klasifikasi atau nama berkas.');
                })
                .fail(function (request, status) {
                    if (status === 'abort') {
                        return;
                    }

                    $status.removeClass('text-muted').addClass('text-danger')
                        .text('Daftar berkas aktif gagal dimuat. Tutup lalu buka kembali modal.');
                })
                .always(function () {
                    activeFilelistsRequest = null;
                });
        }

        $(document).on('click', '.open-direct-filing-modal', function () {
            const selectionKey = String($(this).data('letter-key') || '');

            if (!/^(masuk|keluar):[1-9]\d*$/.test(selectionKey)) {
                Swal.fire('Gagal', 'Data surat tidak valid.', 'error');

                return;
            }

            $letterKey.val(selectionKey);
            $letterNumber.text(String($(this).data('letter-number') || '-'));
            loadActiveFilelists();
            $modal.modal('show');
        });

        $filelist.on('change', function () {
            $submit.prop('disabled', !/^[1-9]\d*$/.test(String($filelist.val() || '')));
        });

        $modal.on('hidden.bs.modal', function () {
            if (activeFilelistsRequest) {
                activeFilelistsRequest.abort();
            }

            $letterKey.val('');
            $letterNumber.text('-');
            resetFilelistOptions();
        });

        $form.on('submit', function (event) {
            event.preventDefault();

            const filelistId = String($filelist.val() || '');
            const selectionKey = String($letterKey.val() || '');

            if (!/^[1-9]\d*$/.test(filelistId) || !/^(masuk|keluar):[1-9]\d*$/.test(selectionKey)) {
                Swal.fire('Belum lengkap', 'Pilih berkas aktif terlebih dahulu.', 'warning');

                return;
            }

            Swal.fire({
                title: 'Berkaskan surat ini?',
                text: 'Surat akan dimasukkan ke berkas aktif yang dipilih.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, berkaskan',
                cancelButtonText: 'Batal',
            }).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }

                $form.attr('action', attachUrlTemplate.replace(attachUrlPlaceholder, filelistId));
                $submit.prop('disabled', true);
                HTMLFormElement.prototype.submit.call($form[0]);
            });
        });
    });
})(jQuery);
