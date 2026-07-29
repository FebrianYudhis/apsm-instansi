<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class DeleteDataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'alasan_penghapusan' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alasan_penghapusan.required' => 'Alasan penghapusan wajib diisi.',
            'alasan_penghapusan.min' => 'Alasan penghapusan minimal 5 karakter.',
            'alasan_penghapusan.max' => 'Alasan penghapusan maksimal 1000 karakter.',
        ];
    }

    public function deletionReason(): string
    {
        return Str::squish((string) $this->validated('alasan_penghapusan'));
    }
}
