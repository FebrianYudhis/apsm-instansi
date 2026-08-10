<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FilelistResource;
use App\Models\Filelist;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FilelistController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $filelists = Filelist::query()
            ->with([
                'classification:id,kode_klasifikasi,keterangan',
                'status:id,nama_status',
                'alihMediaStatus:id,nama_status',
            ])
            ->orderBy('nama_berkas')
            ->get();

        return FilelistResource::collection($filelists);
    }
}
