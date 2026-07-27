@extends('layouts.guest')

@push('js')
    <script>
        $(function () {
            let searchTimeout;
            const $input = $('#pencarian');
            const $results = $('#listSuratDigital');
            const $pagination = $('#paginationListSuratDigital');
            const $status = $('#searchStatus');
            const initialResults = $results.html();
            const initialPagination = $pagination.html();
            const initialStatus = $status.text();
            const escapeHtml = (value) => $('<div>').text(value == null || value === '' ? '-' : String(value)).html();

            function renderCard(data) {
                const action = data.document_url
                    ? '<a href="' + escapeHtml(data.document_url) +
                        '" class="guest-document-action" target="_blank" rel="noopener noreferrer">' +
                        '<i class="fas fa-external-link-alt mr-1"></i> Lihat PDF</a>'
                    : '<span class="guest-document-action restricted"><i class="fas fa-exclamation-triangle mr-1"></i> File tidak tersedia</span>';

                return '<article class="guest-document-card">' +
                    '<div class="guest-document-top">' +
                        '<span class="guest-document-type"><i class="fas fa-file-alt"></i> Surat Digital</span>' +
                        '<span class="guest-access-badge"><i class="fas fa-unlock"></i> Publik</span>' +
                    '</div>' +
                    '<div class="guest-document-body">' +
                        '<h2 class="guest-document-title">' + escapeHtml(data.perihal) + '</h2>' +
                        '<p class="guest-document-subtitle">Dokumen digital</p>' +
                    '</div>' +
                    '<div class="guest-document-footer">' +
                        '<span class="guest-document-owner"><i class="fas fa-database"></i>Arsip digital</span>' +
                        action +
                    '</div>' +
                '</article>';
            }

            function renderEmpty() {
                $results.html(
                    '<div class="guest-empty-state">' +
                        '<span class="guest-empty-icon"><i class="fas fa-search"></i></span>' +
                        '<h3>Dokumen tidak ditemukan</h3>' +
                        '<p>Coba gunakan kata kunci perihal yang berbeda.</p>' +
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
                    url: '{{ route('guest.digital') }}',
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
                            ' dari ' + response.total + ' dokumen');
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
        });
    </script>
@endpush

@section('konten')
    <section class="guest-page-hero">
        <div class="container">
            <span class="guest-eyebrow">Koleksi arsip</span>
            <h1>Surat Digital</h1>
            <p>Telusuri dokumen digital berdasarkan perihal.</p>
        </div>
    </section>

    <section class="guest-page-content">
        <div class="container">
            <div class="guest-search-panel">
                <label for="pencarian" class="sr-only">Cari surat digital</label>
                <div class="guest-search-field">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" class="form-control guest-search-input" id="pencarian"
                        placeholder="Cari perihal dokumen digital..." autocomplete="off">
                    <button type="button" class="guest-search-clear" id="clearSearch" aria-label="Hapus pencarian">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="guest-search-meta">
                    <span>Ketik kata kunci untuk mencari 50 hasil per halaman.</span>
                    <span class="guest-search-status" id="searchStatus" aria-live="polite">
                        Menampilkan {{ $suratDigital->count() }} dari {{ $suratDigital->total() }} dokumen
                    </span>
                </div>
            </div>

            <div class="guest-document-grid" id="listSuratDigital">
                @forelse ($suratDigital as $data)
                    <article class="guest-document-card">
                        <div class="guest-document-top">
                            <span class="guest-document-type"><i class="fas fa-file-alt"></i> Surat Digital</span>
                            <span class="guest-access-badge"><i class="fas fa-unlock"></i> Publik</span>
                        </div>
                        <div class="guest-document-body">
                            <h2 class="guest-document-title">{{ $data->perihal ?: '-' }}</h2>
                            <p class="guest-document-subtitle">Dokumen digital</p>
                        </div>
                        <div class="guest-document-footer">
                            <span class="guest-document-owner"><i class="fas fa-database"></i>Arsip digital</span>
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
                        </div>
                    </article>
                @empty
                    <div class="guest-empty-state">
                        <span class="guest-empty-icon"><i class="fas fa-file-alt"></i></span>
                        <h3>Belum ada surat digital</h3>
                        <p>Koleksi dokumen digital belum tersedia.</p>
                    </div>
                @endforelse
            </div>

            <div class="guest-pagination" id="paginationListSuratDigital">
                {{ $suratDigital->onEachSide(1)->links() }}
            </div>
        </div>
    </section>
@endsection
