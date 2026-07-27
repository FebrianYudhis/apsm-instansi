<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\UploadedFile;

class ValidPdf implements Rule
{
    public function passes($attribute, $value)
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return false;
        }

        $handle = @fopen($value->getRealPath(), 'rb');
        if ($handle === false) {
            return false;
        }

        try {
            return fread($handle, 5) === '%PDF-';
        } finally {
            fclose($handle);
        }
    }

    public function message()
    {
        return 'Berkas harus memiliki struktur awal PDF yang valid.';
    }
}
