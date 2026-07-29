<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Access;
use App\Models\Filelist;
use App\Models\Incoming;
use App\Rules\ValidPdf;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncomingRequest extends FormRequest
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
            'nomor_agenda' => [
                'bail',
                Rule::prohibitedIf($this->boolean('is_srikandi')),
                Rule::requiredIf(! $this->boolean('is_srikandi')),
                'nullable',
                'integer',
                'min:1',
                Rule::unique(Incoming::class, 'nomor_agenda')
                    ->where(fn ($query) => $query->where('tahun', $this->integer('tahun'))),
            ],
            'tanggal_diterima' => ['required', 'date'],
            'tanggal_surat' => ['nullable', 'date'],
            'nomor_surat' => ['required', 'string', 'max:255'],
            'pengirim' => ['required', 'string', 'max:65535'],
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
            'nomor_agenda.required' => 'Nomor agenda wajib diisi untuk surat non-SRIKANDI.',
            'nomor_agenda.prohibited' => 'Nomor agenda tidak boleh dikirim untuk surat SRIKANDI.',
            'nomor_agenda.unique' => 'Nomor agenda sudah digunakan pada tahun tersebut.',
            'filelist_id.prohibited' => 'Berkas tujuan tidak boleh dikirim untuk surat SRIKANDI.',
            'tahun.between' => 'Tahun surat berada di luar rentang yang diizinkan.',
        ];
    }
}
