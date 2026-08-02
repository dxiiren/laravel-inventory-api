<?php

namespace App\Http\Controllers;

use App\Contracts\ProductRepositoryInterface;
use App\Data\ProductData;
use App\Http\Requests\ImportProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductController extends Controller
{
    public function __construct(public ProductRepositoryInterface $productRepository) {}

    public function index(): LengthAwarePaginator
    {
        $products = $this->productRepository->getProducts();

        return $products;
    }

    public function store(ProductData $data): ProductData
    {
        $product = $this->productRepository->create($data);

        return ProductData::from($product);
    }

    public function update(Product $product, ProductData $data): ProductData
    {
        $product = $this->productRepository->update($product, $data);

        return ProductData::from($product);
    }

    public function destroy(Product $product): ProductData
    {
        $this->productRepository->delete($product);

        return ProductData::from($product);
    }

    public function import(ImportProductRequest $request): JsonResponse
    {
        $import = $this->productRepository->import($request);

        return response()->json([
            'import_id' => $import->id,
            'message' => $import->wasRecentlyCreated
                ? 'Uploading is in process and submitted successfully'
                : 'This file was already imported — duplicate upload ignored',
        ], 200);
    }
}
