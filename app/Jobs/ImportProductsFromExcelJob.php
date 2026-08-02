<?php

namespace App\Jobs;

use App\Enums\ImportStatusEnum;
use App\Imports\ProductImport;
use App\Models\Import;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportProductsFromExcelJob implements ShouldQueue
{
    use Queueable;

    public int $sheetCount = 0;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $filePath, public ?int $importId = null)
    {
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
                throw new \RuntimeException('❌ Excel file has no sheets.');
            }

            $productImport = new ProductImport;

            Excel::import(
                $productImport,
                $this->filePath, // ✅ relative path
                config('filesystems.default', 'local'),
                \Maatwebsite\Excel\Excel::XLSX
            );

            $import?->update([
                'status' => ImportStatusEnum::COMPLETED,
                'row_errors' => $productImport->rowErrors(),
            ]);
        } catch (\Throwable $e) {
            $import?->update(['status' => ImportStatusEnum::FAILED]);

            throw $e;
        } finally {
            // The stored upload is a throwaway once the job has run — success
            // or failure. Deleting it only on the success path orphaned the
            // file whenever an import failed (a retry is a fresh re-upload,
            // which stores its own copy — see ProductRepository::import()).
            Storage::delete($this->filePath);
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
