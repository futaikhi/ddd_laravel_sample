<?php

declare(strict_types=1);

namespace Apps\Api\Product;

use Apps\Api\Product\Create\CreateProductAction;
use Apps\Api\Product\Create\CreateProductRequest;
use Illuminate\Http\JsonResponse;

final class ProductController
{
    public function create(
        CreateProductRequest $request,
        CreateProductAction $action,
    ): JsonResponse {
        $resource = $action($request->getDto());

        return response()->json($resource, 201);
    }
}
