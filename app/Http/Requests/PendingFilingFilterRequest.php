<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PendingFilingFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $startYear = (int) config('app.start_year');
        $currentYear = now()->year;

        return [
            'jenis' => ['sometimes', 'required', Rule::in(['masuk', 'keluar'])],
            'tahun' => ['sometimes', 'required', 'integer', "between:{$startYear},{$currentYear}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'jenis.in' => 'Jenis surat harus berupa surat masuk atau surat keluar.',
            'tahun.between' => 'Tahun surat berada di luar rentang yang tersedia.',
        ];
    }
}
