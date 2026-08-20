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

        function matchOptgroup(params, data) {
            if ($.trim(params.term) === '') {
                return data;
            }
            if (typeof data.text === 'undefined') {
                return null;
            }
            var term = params.term.toUpperCase();
            var text = data.text.toUpperCase();
            if (data.children && data.children.length > 0) {
                if (text.indexOf(term) > -1) {
                    return data;
                }
                var matchedChildren = [];
                for (var i = 0; i < data.children.length; i++) {
                    var child = data.children[i];
                    if (child.text && child.text.toUpperCase().indexOf(term) > -1) {
                        matchedChildren.push(child);
                    }
                }
                if (matchedChildren.length > 0) {
                    var modifiedData = $.extend({}, data, true);
                    modifiedData.children = matchedChildren;
                    return modifiedData;
                }
                return null;
            }
            if (text.indexOf(term) > -1) {
                return data;
            }
            return null;
        }

        $filelist.select2({
            dropdownParent: $modal,
            placeholder: $filelist.data('placeholder'),
            allowClear: true,
            width: '100%',
            matcher: matchOptgroup,
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
                    const groups = {};

                    filelists.forEach(function (filelist) {
                        const id = String(filelist.id ?? '');

                        if (!/^[1-9]\d*$/.test(id)) {
                            return;
                        }

                        const classification = String(filelist.kode_klasifikasi || '-');
                        const keterangan = String(filelist.keterangan_klasifikasi || '');
                        const groupKey = keterangan ? `[${classification}] ${keterangan}` : classification;

                        if (!groups[groupKey]) {
                            groups[groupKey] = [];
                        }

                        groups[groupKey].push({
                            id: id,
                            name: String(filelist.nama_berkas || '-')
                        });
                    });

                    const groupKeys = Object.keys(groups).sort();

                    groupKeys.forEach(function (groupKey) {
                        const $optgroup = $('<optgroup>').attr('label', groupKey);

                        groups[groupKey].sort(function (a, b) {
                            return a.name.localeCompare(b.name);
                        }).forEach(function (item) {
                            $optgroup.append(new Option(item.name, item.id));
                        });

                        $filelist.append($optgroup);
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
