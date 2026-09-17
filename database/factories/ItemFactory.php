<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'barcode' => 'BAR-'.fake()->unique()->numerify('######'),
            'sku' => 'SKU-'.fake()->unique()->numerify('######'),
            'unit' => 'قطعة',
            'quantity' => 100,
            'purchase_price' => 10.00,
            'selling_price' => 15.00,
            'valuation_method' => 'fifo',
            'min_alert_level' => 10,
        ];
    }
}
