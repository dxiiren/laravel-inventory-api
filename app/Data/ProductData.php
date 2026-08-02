<?php

namespace App\Data;

use App\Models\Product;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class ProductData extends Data
{
    public function __construct(
        public Optional|int $product_id,
        public string $type,
        public string $brand,
        public string $model,
        public string $capacity,
        public int $quantity,
    ) {}

    public static function fromModel(Product $product): self
    {
        return new self(
            product_id: $product->id,
            type: $product->type,
            brand: $product->brand,
            model: $product->model,
            capacity: $product->capacity,
            quantity: $product->quantity,
        );
    }
}
