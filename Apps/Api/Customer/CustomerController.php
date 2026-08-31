<?php

declare(strict_types=1);

namespace Apps\Api\Customer;

use Apps\Api\Customer\Create\CreateCustomerAction;
use Apps\Api\Customer\Create\CreateCustomerRequest;
use Illuminate\Http\JsonResponse;

final class CustomerController
{
    public function create(
        CreateCustomerRequest $request,
        CreateCustomerAction $action,
    ): JsonResponse {
        $resource = $action($request->getDto());

        return response()->json($resource, 201);
    }
}
