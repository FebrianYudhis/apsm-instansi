<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateOutgoingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOutgoingRequest;
use App\Http\Resources\Api\V1\OutgoingResource;
use Illuminate\Http\JsonResponse;

class StoreOutgoingController extends Controller
{
    public function __invoke(
        StoreOutgoingRequest $request,
        CreateOutgoingAction $createOutgoing
    ): JsonResponse {
        $outgoing = $createOutgoing->handle(
            $request->validated(),
            $request->file('berkas')
        );

        return (new OutgoingResource($outgoing))
            ->response()
            ->setStatusCode(201);
    }
}
