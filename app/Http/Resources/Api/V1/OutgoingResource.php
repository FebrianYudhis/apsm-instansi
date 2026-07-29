<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutgoingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'tanggal_surat' => $this->tanggal_surat,
            'nomor_surat' => $this->nomor_surat,
            'tujuan' => $this->tujuan,
            'perihal' => $this->perihal,
            'tahun' => $this->tahun,
            'is_digital' => $this->is_digital,
            'is_srikandi' => $this->is_srikandi,
            'access_id' => $this->access_id,
            'filelist_id' => $this->filelist_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
