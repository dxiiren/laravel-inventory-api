<?php

namespace App\Repositories;

use App\Contracts\ProductRepositoryInterface;
use App\Data\ProductData;
use App\Enums\ImportStatusEnum;
use App\Http\Requests\ImportProductRequest;
use App\Jobs\ImportProductsFromExcelJob;
use App\Models\Import;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository implements ProductRepositoryInterface
{
    public function getProducts(): LengthAwarePaginator
    {
        $filters = request()->only(
            'search',
        );

        return Product::query()
            ->filter($filters)
            ->orderBy('id', 'asc')
            ->paginate(10);
    }

    public function create(ProductData $data): Product
    {
        return Product::create($data->toArray());
    }

    public function update(Product $product, ProductData $data): Product
    {
        $product->update($data->toArray());

        return $product;
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function import(ImportProductRequest $request): Import
    {
        $file = $request->file('file');
        $fileHash = hash_file('sha256', $file->getRealPath());

        // Idempotency: the file hash is the key. A file already imported (or still
        // queued/processing) is acknowledged but NOT re-applied — only a failed run
        // may be retried.
        $existing = Import::where('file_hash', $fileHash)
            ->where('status', '!=', ImportStatusEnum::FAILED)
            ->first();

        if ($existing) {
            return $existing;
        }

        $import = Import::create([
            'file_name' => $file->getClientOriginalName(),
            'file_hash' => $fileHash,
            'status' => ImportStatusEnum::PENDING,
        ]);

        $path = $file->store('products');
        dispatch(new ImportProductsFromExcelJob($path, $import->id));

        return $import;
    }
}
