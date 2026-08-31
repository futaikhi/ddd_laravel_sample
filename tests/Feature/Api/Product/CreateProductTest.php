<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Product;

use Tests\TestCase;

final class CreateProductTest extends TestCase
{
    public function test_it_can_create_product(): void
    {
        $sku = 'LP-'.uniqid('', true);

        $response = $this->postJson('/api/products', [
            'name' => 'Laptop',
            'sku' => $sku,
            'price' => 2500000,
            'currency' => 'IDR',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Laptop')
            ->assertJsonStructure(['id', 'name', 'sku', 'price', 'currency']);
    }
}
