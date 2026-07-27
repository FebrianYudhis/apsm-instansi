(function (window, document) {
    'use strict';

    const digitSelector = '.mfa-code-digit';

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = value;
        return element.innerHTML;
    }

    function readCode(inputs) {
        return inputs.map((input) => input.value).join('');
    }

    function resetValidation() {
        if (typeof Swal.resetValidationMessage === 'function') {
            Swal.resetValidationMessage();
        }
    }

    function fillDigits(inputs, startIndex, value) {
        const digits = String(value || '').replace(/\D/g, '').slice(0, inputs.length - startIndex);

        digits.split('').forEach((digit, offset) => {
            inputs[startIndex + offset].value = digit;
        });

        if (digits.length > 0) {
            inputs[Math.min(startIndex + digits.length, inputs.length - 1)].focus();
            resetValidation();
        }
    }

    function bindDigitInputs(popup) {
        const inputs = Array.from(popup.querySelectorAll(digitSelector));

        inputs.forEach((input, index) => {
            input.addEventListener('focus', () => input.select());

            input.addEventListener('keydown', (event) => {
                if (/^\d$/.test(event.key)) {
                    event.preventDefault();
                    input.value = event.key;
                    resetValidation();

                    if (index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                    return;
                }

                if (event.key === 'Backspace') {
                    event.preventDefault();

                    if (input.value !== '') {
                        input.value = '';
                    } else if (index > 0) {
                        inputs[index - 1].value = '';
                        inputs[index - 1].focus();
                    }
                    return;
                }

                if (event.key === 'ArrowLeft' && index > 0) {
                    event.preventDefault();
                    inputs[index - 1].focus();
                } else if (event.key === 'ArrowRight' && index < inputs.length - 1) {
                    event.preventDefault();
                    inputs[index + 1].focus();
                } else if (event.key === 'Enter') {
                    event.preventDefault();
                    Swal.clickConfirm();
                }
            });

            input.addEventListener('input', () => {
                const digits = input.value.replace(/\D/g, '');

                if (digits.length > 1) {
                    input.value = '';
                    fillDigits(inputs, index, digits);
                    return;
                }

                input.value = digits;
                if (digits !== '' && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
                resetValidation();
            });

            input.addEventListener('paste', (event) => {
                event.preventDefault();
                fillDigits(inputs, index, event.clipboardData.getData('text'));
            });
        });

        inputs[0].focus();
        return inputs;
    }

    function inputMarkup() {
        return Array.from({ length: 6 }, (_, index) => {
            const autocomplete = index === 0 ? 'one-time-code' : 'off';
            const maxlength = index === 0 ? 6 : 1;

            return '<input type="text" class="mfa-code-digit" inputmode="numeric" pattern="[0-9]*" ' +
                'autocomplete="' + autocomplete + '" maxlength="' + maxlength + '" ' +
                'aria-label="Digit ' + (index + 1) + ' dari 6" aria-describedby="mfaCodeHelp">';
        }).join('');
    }

    function prompt(options) {
        const settings = options || {};
        const title = settings.title || 'Verifikasi MFA';
        const description = settings.description || 'Masukkan kode MFA 6 digit untuk melanjutkan.';
        const confirmButtonText = settings.confirmButtonText || 'Verifikasi';
        let inputs = [];

        return Swal.fire({
            title: escapeHtml(title),
            html: '<div class="mfa-code-icon" aria-hidden="true"><i class="fas fa-shield-alt"></i></div>' +
                '<p class="mfa-code-description" id="mfaCodeHelp">' +
                    escapeHtml(description) +
                '</p>' +
                '<div class="mfa-code-inputs" role="group" aria-label="Kode MFA 6 digit">' +
                    inputMarkup() +
                '</div>',
            showCancelButton: true,
            cancelButtonText: 'Batal',
            confirmButtonText: escapeHtml(confirmButtonText),
            focusConfirm: false,
            customClass: {
                popup: 'mfa-code-modal',
                confirmButton: 'mfa-code-confirm'
            },
            didOpen: (popup) => {
                inputs = bindDigitInputs(popup);
            },
            preConfirm: () => {
                const code = readCode(inputs);

                if (!/^\d{6}$/.test(code)) {
                    Swal.showValidationMessage('Kode MFA harus terdiri dari 6 digit.');
                    const emptyInput = inputs.find((input) => input.value === '');
                    if (emptyInput) {
                        emptyInput.focus();
                    }
                    return false;
                }

                return code;
            }
        });
    }

    window.MfaCodeInput = { prompt };
})(window, document);
