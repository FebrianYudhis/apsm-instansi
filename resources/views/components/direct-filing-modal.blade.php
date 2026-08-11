@php($directFilingUrlPlaceholder = 987654321)

<div
    id="directFilingConfig"
    class="d-none"
    data-active-filelists-url="{{ route('berkas.aktif.list') }}"
    data-attach-url-template="{{ route('berkas.lampirkanBulk', $directFilingUrlPlaceholder) }}"
    data-attach-url-placeholder="{{ $directFilingUrlPlaceholder }}"
></div>

<div
    class="modal fade"
    id="directFilingModal"
    tabindex="-1"
    aria-labelledby="directFilingModalLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form
                id="directFilingForm"
                action="{{ route('berkas.lampirkanBulk', $directFilingUrlPlaceholder) }}"
                method="POST"
            >
                @csrf
                <input id="directFilingLetterKey" type="hidden" name="items[]">

                <div class="modal-header">
                    <h5 class="modal-title" id="directFilingModalLabel">
                        <i class="fa fa-folder-open mr-1" aria-hidden="true"></i>
                        Berkaskan Surat
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border py-2">
                        Nomor surat: <strong id="directFilingLetterNumber">-</strong>
                    </div>
                    <div class="form-group mb-2">
                        <label for="directFilingFilelist">Pilih Berkas Aktif</label>
                        <select
                            id="directFilingFilelist"
                            class="form-control"
                            data-placeholder="Cari kode klasifikasi atau nama berkas"
                            required
                            disabled
                        ></select>
                    </div>
                    <small id="directFilingStatus" class="form-text text-muted">
                        Daftar berkas aktif akan dimuat saat modal dibuka.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button id="directFilingSubmit" type="submit" class="btn btn-primary" disabled>
                        <i class="fa fa-folder-open mr-1" aria-hidden="true"></i>
                        Berkaskan Surat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
