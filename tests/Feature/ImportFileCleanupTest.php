<?php

namespace Tests\Feature;

use App\Enums\ImportStatusEnum;
use App\Jobs\ImportProductsFromExcelJob;
use App\Models\Import;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The uploaded .xlsx is a throwaway: once ImportProductsFromExcelJob has run,
 * the stored copy must be gone from the products directory — on success AND on
 * failure. A failed run that keeps its file orphans it forever (the documented
 * retry path is a re-upload, which stores a fresh copy), which is exactly how
 * uploads used to pile up in storage/app/private/products.
 */
class ImportFileCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(ProductSeeder::class);
    }

    public function test_the_uploaded_file_is_deleted_after_a_successful_import(): void
    {
        $sample = database_path('seeders/product_status_list.xlsx');

        $response = $this->postJson('/api/products/import', [
            'file' => new UploadedFile(
                $sample,
                'product_status_list.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            ),
        ]);

        // Sync queue in testing runs the job inline, so by now it has completed.
        $response->assertOk();

        $importId = $response->json('data.import_id');
        $this->assertSame(ImportStatusEnum::COMPLETED, Import::find($importId)->status);
        $this->assertSame(
            [],
            Storage::disk('local')->files('products'),
            'the uploaded xlsx must not linger in products/ after the import completes'
        );
    }

    public function test_the_uploaded_file_is_deleted_even_when_the_import_fails(): void
    {
        // Stage an upload the way the repository does, then run the job by hand
        // with its sheet-count guard tripped so handle() takes the failure path.
        $path = 'products/failing-import.xlsx';
        Storage::disk('local')->put(
            $path,
            file_get_contents(database_path('seeders/product_status_list.xlsx'))
        );

        $import = Import::create([
            'file_name' => 'failing-import.xlsx',
            'file_hash' => hash('sha256', 'failing-import'),
            'status' => ImportStatusEnum::PENDING,
        ]);

        $job = new ImportProductsFromExcelJob($path, $import->id);
        $job->sheetCount = 0; // force the "Excel file has no sheets" failure

        try {
            $job->handle();
            $this->fail('handle() should rethrow the import failure');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no sheets', $e->getMessage());
        }

        // The failure is recorded — and the upload is still cleaned up.
        $this->assertSame(ImportStatusEnum::FAILED, $import->fresh()->status);
        Storage::disk('local')->assertMissing($path);
    }
}
