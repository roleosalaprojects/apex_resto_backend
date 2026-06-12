<?php

namespace Database\Factories\Pos;

use App\Models\Pos\SaleLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pos\SaleLine>
 */
class SaleLineFactory extends Factory
{
    protected $model = SaleLine::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 10);
        $price = fake()->randomFloat(2, 10, 500);
        $cost = $price * 0.7;
        $subTotal = $qty * $price;
        $vatable = $subTotal / 1.12;
        $vat = $subTotal - $vatable;

        return [
            'qty' => $qty,
            'unit' => 'pcs',
            'unit_qty' => 1,
            'price' => $price,
            'cost' => $cost,
            'discount' => 0,
            'sub_total' => $subTotal,
            'vatable' => round($vatable, 2),
            'vat' => round($vat, 2),
            'exempt' => 0,
            'zero_rated' => 0,
            'refundable' => true,
            'refunded' => false,
            'profit' => round(($price - $cost) * $qty, 2),
            'sc_discount' => 0,
            'pwd_discount' => 0,
            'sp_discount' => 0,
            'naac_discount' => 0,
            'item_id' => 1,
            'sales_id' => 1,
        ];
    }

    public function forSale(int $saleId): static
    {
        return $this->state(fn (array $attributes) => [
            'sales_id' => $saleId,
        ]);
    }

    public function forItem(int $itemId): static
    {
        return $this->state(fn (array $attributes) => [
            'item_id' => $itemId,
        ]);
    }
}
