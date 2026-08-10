<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FilelistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'kode_klasifikasi' => $this->classification?->kode_klasifikasi,
            'keterangan_klasifikasi' => $this->classification?->keterangan,
            'nama_berkas' => $this->nama_berkas,
            'status' => $this->status?->nama_status,
            'retensi_aktif' => $this->retensi_aktif,
            'retensi_inaktif' => $this->retensi_inaktif,
            'keterangan_akhir' => $this->keterangan_akhir,
            'status_alih_media' => $this->alihMediaStatus?->nama_status,
        ];
    }
}
