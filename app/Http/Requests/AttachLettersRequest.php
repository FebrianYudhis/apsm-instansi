<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttachLettersRequest extends FormRequest
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
        return [
            'items' => ['required', 'array', 'list', 'min:1'],
            'items.*' => ['required', 'string', 'regex:/^(masuk|keluar):[1-9]\d*$/', 'distinct:strict'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Pilih minimal satu surat untuk dilampirkan.',
            'items.min' => 'Pilih minimal satu surat untuk dilampirkan.',
            'items.*.regex' => 'Data surat yang dipilih tidak valid.',
            'items.*.distinct' => 'Surat yang sama tidak boleh dipilih lebih dari sekali.',
        ];
    }
}
