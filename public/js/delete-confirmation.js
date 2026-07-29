(function ($, Swal) {
    'use strict';

    $(document).on('click', '.konfirmasi-hapus', function (event) {
        const form = $(this).closest('form');

        event.preventDefault();

        Swal.fire({
            title: 'Hapus data',
            text: 'Data akan ditandai sebagai terhapus dan tindakan ini akan dicatat.',
            icon: 'warning',
            input: 'textarea',
            inputLabel: 'Alasan penghapusan',
            inputPlaceholder: 'Tuliskan alasan penghapusan...',
            inputAttributes: {
                'aria-label': 'Alasan penghapusan',
                maxlength: 1000,
            },
            showCancelButton: true,
            confirmButtonText: 'Hapus',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc3545',
            inputValidator: function (value) {
                if (! value || value.trim().length < 5) {
                    return 'Alasan penghapusan minimal 5 karakter.';
                }

                return null;
            },
        }).then(function (result) {
            if (! result.isConfirmed) {
                return;
            }

            let reasonInput = form.find('input[name="alasan_penghapusan"]');

            if (reasonInput.length === 0) {
                reasonInput = $('<input>', {
                    type: 'hidden',
                    name: 'alasan_penghapusan',
                }).appendTo(form);
            }

            reasonInput.val(result.value.trim());
            form.get(0).submit();
        });
    });
})(jQuery, Swal);
