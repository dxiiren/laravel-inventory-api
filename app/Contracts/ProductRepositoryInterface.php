<?php

namespace App\Contracts;

use App\Data\ProductData;
use App\Http\Requests\ImportProductRequest;
use App\Models\Import;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    public function getProducts(): LengthAwarePaginator;

    public function create(ProductData $data): Product;

    public function update(Product $product, ProductData $data): Product;

    public function delete(Product $product): void;

    public function import(ImportProductRequest $request): Import;
}
