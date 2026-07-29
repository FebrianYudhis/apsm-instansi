<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncomingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'nomor_agenda' => $this->nomor_agenda,
            'tanggal_diterima' => $this->tanggal_diterima,
            'tanggal_surat' => $this->tanggal_surat,
            'nomor_surat' => $this->nomor_surat,
            'pengirim' => $this->pengirim,
            'perihal' => $this->perihal,
            'tahun' => $this->tahun,
            'is_srikandi' => $this->is_srikandi,
            'access_id' => $this->access_id,
            'filelist_id' => $this->filelist_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
