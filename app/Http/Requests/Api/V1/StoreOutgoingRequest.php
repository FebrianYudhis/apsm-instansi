<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Access;
use App\Models\Filelist;
use App\Rules\ValidPdf;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOutgoingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tokenCan('surat:create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $startYear = min(
            (int) config('app.start_year', 2025),
            (int) now()->year
        );

        return [
            'is_srikandi' => ['required', 'boolean'],
            'is_digital' => [
                'bail',
                Rule::prohibitedIf($this->boolean('is_srikandi')),
                Rule::requiredIf(! $this->boolean('is_srikandi')),
                'nullable',
                'boolean',
            ],
            'tanggal_surat' => ['required', 'date'],
            'nomor_surat' => ['required', 'string', 'max:255'],
            'tujuan' => ['required', 'string', 'max:65535'],
            'perihal' => ['required', 'string', 'max:65535'],
            'tahun' => [
                'required',
                'integer',
                'between:'.$startYear.','.now()->year,
            ],
            'access_id' => [
                'required',
                'integer',
                Rule::exists(Access::class, 'id'),
            ],
            'filelist_id' => [
                'bail',
                Rule::prohibitedIf($this->boolean('is_srikandi')),
                'nullable',
                'integer',
                Rule::exists(Filelist::class, 'id')->where(
                    fn ($query) => $query
                        ->where('status_id', 1)
                        ->whereNull('alih_media_status_id')
                        ->whereNull('deleted_at')
                ),
            ],
            'berkas' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf,application/x-pdf',
                'max:'.config('documents.max_upload_kb'),
                new ValidPdf,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_digital.required' => 'Status digital wajib diisi untuk surat non-SRIKANDI.',
            'is_digital.prohibited' => 'Status digital tidak boleh dikirim untuk surat SRIKANDI karena selalu ditetapkan digital.',
            'filelist_id.prohibited' => 'Berkas tujuan tidak boleh dikirim untuk surat SRIKANDI.',
            'tahun.between' => 'Tahun surat berada di luar rentang yang diizinkan.',
        ];
    }
}
