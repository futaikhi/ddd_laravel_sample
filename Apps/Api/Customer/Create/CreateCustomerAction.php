<?php

declare(strict_types=1);

namespace Apps\Api\Customer\Create;

use App\Models\Customer;
use Apps\Api\Customer\Shared\CustomerCreatedRes;

final readonly class CreateCustomerAction
{
    public function __invoke(CreateCustomerDto $dto): CustomerCreatedRes
    {
        $customer = Customer::query()->create([
            'id' => $dto->id,
            'name' => $dto->name,
            'email' => $dto->email,
            'phone' => $dto->phone,
        ]);

        return new CustomerCreatedRes(
            id: $customer->id,
            name: $customer->name,
            email: $customer->email,
            phone: $customer->phone,
        );
    }
}
