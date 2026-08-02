<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'id' => 4450,
                'type' => 'Smartphone',
                'brand' => 'Apple',
                'model' => 'iPhone SE',
                'capacity' => '2GB/16GB',
                'quantity' => 13,
            ],
            [
                'id' => 4768,
                'type' => 'Smartphone',
                'brand' => 'Apple',
                'model' => 'iPhone SE',
                'capacity' => '2GB/32GB',
                'quantity' => 30,
            ],
            [
                'id' => 4451,
                'type' => 'Smartphone',
                'brand' => 'Apple',
                'model' => 'iPhone SE',
                'capacity' => '2GB/64GB',
                'quantity' => 20,
            ],
            [
                'id' => 4574,
                'type' => 'Smartphone',
                'brand' => 'Apple',
                'model' => 'iPhone SE',
                'capacity' => '2GB/128GB',
                'quantity' => 16,
            ],
            [
                'id' => 6039,
                'type' => 'Smartphone',
                'brand' => 'Apple',
                'model' => 'iPhone SE (2020)',
                'capacity' => '3GB/64GB',
                'quantity' => 18,
            ],
        ];

        foreach ($products as $product) {
            $prod = Product::updateOrCreate(
                ['id' => $product['id']],
                $product
            );
        }
    }
}
