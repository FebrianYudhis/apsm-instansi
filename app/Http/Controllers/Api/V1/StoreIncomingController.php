<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateIncomingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreIncomingRequest;
use App\Http\Resources\Api\V1\IncomingResource;
use Illuminate\Http\JsonResponse;

class StoreIncomingController extends Controller
{
    public function __invoke(
        StoreIncomingRequest $request,
        CreateIncomingAction $createIncoming
    ): JsonResponse {
        $incoming = $createIncoming->handle(
            $request->validated(),
            $request->file('berkas')
        );

        return (new IncomingResource($incoming))
            ->response()
            ->setStatusCode(201);
    }
}
