<?php

namespace App\Jobs;

use App\Models\Import;
use App\Imports\ProductImport;
use App\Enums\ImportStatusEnum;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ImportProductsFromExcelJob implements ShouldQueue
{
    use Queueable;

    public int $sheetCount = 0;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $filePath, public ?int $importId = null) {
        $this->sheetCount = $this->getSheetCount();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $import = $this->importId ? Import::find($this->importId) : null;

        $import?->update(['status' => ImportStatusEnum::PROCESSING]);

        try {
            if ($this->sheetCount <= 0) {
                throw new \RuntimeException("❌ Excel file has no sheets.");
            }

            $productImport = new ProductImport();

            Excel::import(
                $productImport,
                $this->filePath, // ✅ relative path
                config('filesystems.default', 'local'),
                \Maatwebsite\Excel\Excel::XLSX
            );

            Storage::delete($this->filePath);

            $import?->update([
                'status' => ImportStatusEnum::COMPLETED,
                'row_errors' => $productImport->rowErrors(),
            ]);
        } catch (\Throwable $e) {
            $import?->update(['status' => ImportStatusEnum::FAILED]);

            throw $e;
        }
    }

    private function getSheetCount(): int
    {
        $fullPath = Storage::path($this->filePath);
        $reader = IOFactory::createReaderForFile($fullPath);
        $spreadsheet = $reader->load($fullPath);
        $sheetCount = $spreadsheet->getSheetCount();
        return $sheetCount;
    }
}
