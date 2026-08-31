<?php

declare(strict_types=1);

namespace Apps\Api\Sales;

use Apps\Api\Sales\Confirm\ConfirmSaleAction;
use Apps\Api\Sales\Confirm\ConfirmSaleRequest;
use Apps\Api\Sales\Create\CreateSaleAction;
use Apps\Api\Sales\Create\CreateSaleRequest;
use Illuminate\Http\JsonResponse;

final class SalesController
{
    public function create(
        CreateSaleRequest $request,
        CreateSaleAction $action,
    ): JsonResponse {
        $resource = $action($request->getDto());

        return response()->json($resource, 201);
    }

    public function confirm(
        ConfirmSaleRequest $request,
        ConfirmSaleAction $action,
    ): JsonResponse {
        $resource = $action($request->getDto());

        return response()->json($resource);
    }
}
