<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Customer;

use Tests\TestCase;

final class CreateCustomerTest extends TestCase
{
    public function test_it_can_create_customer(): void
    {
        $response = $this->postJson('/api/customers', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '081234567890',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'John Doe')
            ->assertJsonStructure(['id', 'name', 'email', 'phone']);
    }
}
