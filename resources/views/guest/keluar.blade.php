@extends('layouts.guest')

@push('js')
    <script>
        $(function () {
            let searchTimeout;
            const $input = $('#pencarian');
            const $results = $('#listSuratKeluar');
            const $pagination = $('#paginationListSuratKeluar');
            const $status = $('#searchStatus');
            const initialResults = $results.html();
            const initialPagination = $pagination.html();
            const initialStatus = $status.text();
            const escapeHtml = (value) => $('<div>').text(value == null || value === '' ? '-' : String(value)).html();

            function accessBadge(data) {
                if (data.access_state === 'undefined') {
                    return '<span class="guest-access-badge undefined"><i class="fas fa-question-circle"></i> Belum Ditentukan</span>';
                }

                const restricted = data.requires_mfa;
                return restricted
                    ? '<span class="guest-access-badge restricted"><i class="fas fa-lock"></i> Terbatas</span>'
                    : '<span class="guest-access-badge"><i class="fas fa-unlock"></i> Publik</span>';
            }

            function documentAction(data) {
                if (data.requires_mfa) {
                    return '<button type="button" class="guest-document-action restricted view-button" data-id="' +
                        escapeHtml(data.id) + '"><i class="fas fa-key mr-1"></i> Verifikasi</button>';
                }

                if (!data.document_url) {
                    return '<span class="guest-document-action restricted"><i class="fas fa-exclamation-triangle mr-1"></i> File tidak tersedia</span>';
                }

                return '<a href="' + escapeHtml(data.document_url) +
                    '" class="guest-document-action" target="_blank" rel="noopener noreferrer">' +
                    '<i class="fas fa-external-link-alt mr-1"></i> Lihat PDF</a>';
            }

            function renderCard(data) {
                return '<article class="guest-document-card">' +
                    '<div class="guest-document-top">' +
                        '<span class="guest-document-type"><i class="fas fa-paper-plane"></i> Surat Keluar</span>' +
                        accessBadge(data) +
                    '</div>' +
                    '<div class="guest-document-body">' +
                        '<h2 class="guest-document-title">' + escapeHtml(data.perihal) + '</h2>' +
                        '<p class="guest-document-subtitle">' + escapeHtml(data.nomor_surat) + '</p>' +
                        '<div class="guest-document-meta">' +
                            '<div class="guest-meta-item"><span>Tahun</span><strong>' + escapeHtml(data.tahun) + '</strong></div>' +
                            '<div class="guest-meta-item"><span>Tanggal surat</span><strong>' + escapeHtml(data.tanggal_surat) + '</strong></div>' +
                            '<div class="guest-meta-item"><span>Jenis naskah</span><strong>' + (data.is_digital == 1 ? 'Digital' : 'Manual') + '</strong></div>' +
                            '<div class="guest-meta-item"><span>Jalur</span><strong>' + (data.is_srikandi == 1 ? 'SRIKANDI' : 'Manual') + '</strong></div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="guest-document-footer">' +
                        '<span class="guest-document-owner" title="' + escapeHtml(data.tujuan) + '">' +
                            '<i class="fas fa-map-marker-alt"></i>' + escapeHtml(data.tujuan) +
                        '</span>' +
                        documentAction(data) +
                    '</div>' +
                '</article>';
            }

            function renderEmpty() {
                $results.html(
                    '<div class="guest-empty-state">' +
                        '<span class="guest-empty-icon"><i class="fas fa-search"></i></span>' +
                        '<h3>Surat tidak ditemukan</h3>' +
                        '<p>Coba gunakan nomor surat, tujuan, atau kata kunci perihal yang berbeda.</p>' +
                    '</div>'
                );
            }

            function restoreInitialResults() {
                $results.html(initialResults);
                $pagination.html(initialPagination);
                $status.text(initialStatus);
            }

            function pageItem(page, label, disabled, active) {
                if (active) {
                    return '<li class="page-item active" aria-current="page">' +
                        '<span class="page-link">' + label + '</span></li>';
                }

                if (disabled) {
                    return '<li class="page-item disabled"><span class="page-link">' +
                        label + '</span></li>';
                }

                return '<li class="page-item"><button type="button" class="page-link guest-search-page" ' +
                    'data-page="' + page + '">' + label + '</button></li>';
            }

            function renderSearchPagination(response) {
                const current = Number(response.current_page) || 1;
                const last = Number(response.last_page) || 1;
                if (last <= 1) {
                    $pagination.empty();
                    return;
                }

                let html = pageItem(current - 1, '&lsaquo;', current <= 1, false);
                const start = Math.max(1, current - 2);
                const end = Math.min(last, current + 2);

                if (start > 1) {
                    html += pageItem(1, '1', false, current === 1);
                    if (start > 2) {
                        html += pageItem(0, '&hellip;', true, false);
                    }
                }

                for (let page = start; page <= end; page++) {
                    html += pageItem(page, String(page), false, page === current);
                }

                if (end < last) {
                    if (end < last - 1) {
                        html += pageItem(0, '&hellip;', true, false);
                    }
                    html += pageItem(last, String(last), false, current === last);
                }

                html += pageItem(current + 1, '&rsaquo;', current >= last, false);
                $pagination.html('<nav aria-label="Navigasi hasil pencarian"><ul class="pagination justify-content-center">' +
                    html + '</ul></nav>');
            }

            function runSearch(keyword, page) {
                $status.html('<i class="fas fa-circle-notch fa-spin mr-1"></i> Mencari arsip...');

                $.ajax({
                    url: '{{ route('guest.keluar') }}',
                    method: 'GET',
                    data: { pencarian: keyword, page: page },
                    success: function (response) {
                        const rows = response && Array.isArray(response.data) ? response.data : [];
                        $results.empty();

                        if (rows.length === 0) {
                            renderEmpty();
                            $pagination.empty();
                            $status.text('Tidak ada hasil untuk: ' + keyword);
                            return;
                        }

                        rows.forEach(function (data) {
                            $results.append(renderCard(data));
                        });
                        renderSearchPagination(response);
                        $status.text('Menampilkan ' + response.from + '-' + response.to +
                            ' dari ' + response.total + ' surat');
                    },
                    error: function () {
                        $status.text('Pencarian gagal. Silakan coba kembali.');
                    }
                });
            }

            $input.on('input', function () {
                clearTimeout(searchTimeout);
                const keyword = this.value.trim();

                if (keyword === '') {
                    restoreInitialResults();
                    return;
                }

                searchTimeout = setTimeout(function () {
                    runSearch(keyword, 1);
                }, 500);
            });

            $pagination.on('click', '.guest-search-page', function () {
                const keyword = $input.val().trim();
                const page = Number($(this).data('page'));
                if (keyword !== '' && page > 0) {
                    runSearch(keyword, page);
                }
            });

            $('#clearSearch').on('click', function () {
                $input.val('').trigger('input').focus();
            });

            $results.on('click', '.view-button', function () {
                const suratId = $(this).data('id');

                MfaCodeInput.prompt({
                    title: 'Verifikasi dokumen',
                    description: 'Masukkan kode MFA 6 digit untuk membuka dokumen terbatas.'
                }).then((result) => {
                    if (!result.isConfirmed) {
                        return;
                    }

                    fetch('{{ route('guest.buka') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            id: suratId,
                            password: result.value,
                            jenis: 'keluar'
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.isSuccess) {
                                window.open(data.url, '_blank', 'noopener');
                                Swal.fire('Akses diberikan', 'Dokumen dibuka pada tab baru.', 'success');
                            } else {
                                Swal.fire(data.message, '', 'error');
                            }
                        })
                        .catch(() => Swal.fire('Terjadi kesalahan', 'Silakan coba kembali.', 'error'));
                });
            });
        });
    </script>
@endpush

@section('konten')
    <section class="guest-page-hero">
        <div class="container">
            <span class="guest-eyebrow">Koleksi arsip</span>
            <h1>Surat Keluar</h1>
            <p>Telusuri naskah yang dikirim berdasarkan nomor surat, tujuan, atau perihal.</p>
        </div>
    </section>

    <section class="guest-page-content">
        <div class="container">
            <div class="guest-search-panel">
                <label for="pencarian" class="sr-only">Cari surat keluar</label>
                <div class="guest-search-field">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" class="form-control guest-search-input" id="pencarian"
                        placeholder="Cari nomor surat, tujuan, atau perihal..." autocomplete="off">
                    <button type="button" class="guest-search-clear" id="clearSearch" aria-label="Hapus pencarian">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="guest-search-meta">
                    <span>Ketik kata kunci untuk mencari 50 hasil per halaman.</span>
                    <span class="guest-search-status" id="searchStatus" aria-live="polite">
                        Menampilkan {{ $suratKeluar->count() }} dari {{ $suratKeluar->total() }} surat
                    </span>
                </div>
            </div>

            <div class="guest-document-grid" id="listSuratKeluar">
                @forelse ($suratKeluar as $data)
                    <article class="guest-document-card">
                        <div class="guest-document-top">
                            <span class="guest-document-type"><i class="fas fa-paper-plane"></i> Surat Keluar</span>
                            @if ($data->access_id === null)
                                <span class="guest-access-badge undefined">
                                    <i class="fas fa-question-circle"></i> Belum Ditentukan
                                </span>
                            @elseif (!$data->isPubliclyAccessible())
                                <span class="guest-access-badge restricted"><i class="fas fa-lock"></i> Terbatas</span>
                            @else
                                <span class="guest-access-badge"><i class="fas fa-unlock"></i> Publik</span>
                            @endif
                        </div>
                        <div class="guest-document-body">
                            <h2 class="guest-document-title">{{ $data->perihal ?: '-' }}</h2>
                            <p class="guest-document-subtitle">{{ $data->nomor_surat ?: '-' }}</p>
                            <div class="guest-document-meta">
                                <div class="guest-meta-item"><span>Tahun</span><strong>{{ $data->tahun ?: '-' }}</strong></div>
                                <div class="guest-meta-item"><span>Tanggal surat</span><strong>{{ $data->tanggal_surat ?: '-' }}</strong></div>
                                <div class="guest-meta-item"><span>Jenis naskah</span><strong>{{ $data->is_digital ? 'Digital' : 'Manual' }}</strong></div>
                                <div class="guest-meta-item"><span>Jalur</span><strong>{{ $data->is_srikandi ? 'SRIKANDI' : 'Manual' }}</strong></div>
                            </div>
                        </div>
                        <div class="guest-document-footer">
                            <span class="guest-document-owner" title="{{ $data->tujuan }}">
                                <i class="fas fa-map-marker-alt"></i>{{ $data->tujuan ?: '-' }}
                            </span>
                            @if (!$data->isPubliclyAccessible())
                                <button type="button" class="guest-document-action restricted view-button"
                                    data-id="{{ $data->id }}"><i class="fas fa-key mr-1"></i> Verifikasi</button>
                            @else
                                @if ($data->document_url)
                                    <a href="{{ $data->document_url }}" class="guest-document-action"
                                        target="_blank" rel="noopener noreferrer">
                                        <i class="fas fa-external-link-alt mr-1"></i> Lihat PDF
                                    </a>
                                @else
                                    <span class="guest-document-action restricted">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> File tidak tersedia
                                    </span>
                                @endif
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="guest-empty-state">
                        <span class="guest-empty-icon"><i class="fas fa-paper-plane"></i></span>
                        <h3>Belum ada surat keluar</h3>
                        <p>Koleksi surat keluar belum tersedia.</p>
                    </div>
                @endforelse
            </div>

            <div class="guest-pagination" id="paginationListSuratKeluar">
                {{ $suratKeluar->onEachSide(1)->links() }}
            </div>
        </div>
    </section>
@endsection
