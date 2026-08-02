<?php

namespace App\Imports;

use App\Models\Product;
use App\Enums\ProductStatusEnum;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ProductImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    /**
     * Spreadsheet row of the last processed data row. The heading occupies
     * row 1, so the first data row is 2. Chunks arrive sequentially on the
     * same import instance, so this counter stays correct across chunks.
     */
    private int $currentRow = 1;

    /** @var array<int, array{row: int, product_id: int|string|null, error: string}> */
    private array $rowErrors = [];

    public function chunkSize(): int
    {
        return 100;
    }

    public function collection(Collection $rows): void
    {
        [$changes, $rowsByProduct] = $this->calculateNetChanges($rows);

        if (empty($changes))
            return;

        $upserts = $this->buildUpsertData($changes, $rowsByProduct);

        if (empty($upserts))
            return;

        Product::upsert($upserts, ['id'], ['quantity']);
    }

    /**
     * Row-level problems collected while importing (malformed rows, unknown
     * product ids). Read by the job after the import to persist the report.
     *
     * @return array<int, array{row: int, product_id: int|string|null, error: string}>
     */
    public function rowErrors(): array
    {
        return $this->rowErrors;
    }

    /**
     * Build upsert data for the products.
     *
     * @param array<int, int> $changes  Associative array of product_id => quantity change
     * @param array<int, array<int, int>> $rowsByProduct  product_id => spreadsheet rows that referenced it
     * @return array<int, array{product_id: int, quantity: int}> $payload
     */
    private function buildUpsertData(array $changes, array $rowsByProduct): array
    {
        $existingProducts = Product::select('id', 'quantity')->whereIntegerInRaw('id', array_keys($changes))
            ->get()
            ->keyBy('id');

        $upserts = [];

        foreach ($changes as $productId => $netChange) {

            if (!isset($existingProducts[$productId])) {
                foreach ($rowsByProduct[$productId] ?? [] as $row) {
                    $this->addRowError($row, $productId, "Unknown product_id {$productId} — row skipped");
                }
                continue;
            }

            $quantity = $existingProducts[$productId]->quantity + $netChange;

            if ($quantity == 0) {
                continue;
            }

            $upserts[] = [
                'id' => $productId,
                'quantity' => $quantity,
                'type' => '',
                'brand' => '',
                'model' => '',
                'capacity' => '',
            ];
        }

        return $upserts;
    }

    /**
     * Calculate net changes for each product based on the status.
     *
     * @param Collection $rows
     * @return array{0: array<int, int>, 1: array<int, array<int, int>>}
     *         Net change per product_id, plus the spreadsheet rows that referenced each product_id
     */
    private function calculateNetChanges(Collection $rows): array
    {
        $changes = [];
        $rowsByProduct = [];

        foreach ($rows as $row) {
            $currentRow = ++$this->currentRow;

            $productId = $row['product_id'] ?? null;
            $status = strtolower(trim($row['status'] ?? ''));

            if (!$productId || !$status) {
                $this->addRowError($currentRow, $productId, 'Row is missing product_id or status — row skipped');
                continue;
            }

            $delta = match ($status) {
                ProductStatusEnum::SOLD->value => -1,
                ProductStatusEnum::BUY->value => 1,
                default => null,
            };

            if ($delta === null) {
                $this->addRowError($currentRow, $productId, "Invalid status '{$status}' — expected 'sold' or 'buy'");
                continue;
            }

            $changes[$productId] = ($changes[$productId] ?? 0) + $delta;
            $rowsByProduct[$productId][] = $currentRow;
        }

        return [$changes, $rowsByProduct];
    }

    private function addRowError(int $row, int|string|null $productId, string $error): void
    {
        $this->rowErrors[] = [
            'row' => $row,
            'product_id' => $productId,
            'error' => $error,
        ];
    }
}
