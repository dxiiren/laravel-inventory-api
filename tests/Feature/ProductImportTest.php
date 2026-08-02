<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] xlsx files generated during the test, removed in tearDown */
    private array $generatedFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);

        // The import endpoint and its report are behind auth:sanctum.
        Sanctum::actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        foreach ($this->generatedFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_import_records_row_level_errors_for_unknown_product_id()
    {
        // Prepare — heading is spreadsheet row 1, so data rows are 2, 3 and 4.
        // Row 2 references a product_id that does not exist in the products table.
        $path = $this->makeXlsx([
            [999999, 'sold'], // row 2 — unknown product_id
            [4450, 'buy'],    // row 3 — 13 + 1 = 14
            [4451, 'sold'],   // row 4 — 20 - 1 = 19
        ]);

        // Test — sync queue in testing runs the import job inline.
        $response = $this->postJson('/api/products/import', [
            'file' => $this->uploadedXlsx($path),
        ]);

        // Assert — the two known rows were applied…
        $response->assertOk();
        $this->assertDatabaseHas('products', ['id' => 4450, 'quantity' => 14]);
        $this->assertDatabaseHas('products', ['id' => 4451, 'quantity' => 19]);

        // …and the import run was recorded with exactly one row-level error, for row 2.
        $importId = $response->json('data.import_id');
        $this->assertNotNull($importId, 'import response should expose the import record id');
        $this->assertDatabaseCount('imports', 1);

        $report = $this->getJson('/api/imports/'.$importId);
        $report->assertOk();

        $errors = $report->json('data.row_errors');
        $this->assertIsArray($errors);
        $this->assertCount(1, $errors);
        $this->assertSame(2, $errors[0]['row']);
        $this->assertEquals(999999, $errors[0]['product_id']);
        $this->assertStringContainsString('999999', $errors[0]['error']);
    }

    public function test_reimporting_identical_file_does_not_change_stock()
    {
        // Prepare — one buy and one sold row against seeded products.
        $path = $this->makeXlsx([
            [4450, 'buy'],  // 13 + 1 = 14
            [4451, 'sold'], // 20 - 1 = 19
        ]);

        $first = $this->postJson('/api/products/import', [
            'file' => $this->uploadedXlsx($path),
        ]);

        $first->assertOk();
        $this->assertDatabaseHas('products', ['id' => 4450, 'quantity' => 14]);
        $this->assertDatabaseHas('products', ['id' => 4451, 'quantity' => 19]);

        // Test — upload the exact same file again (same bytes, same hash).
        $second = $this->postJson('/api/products/import', [
            'file' => $this->uploadedXlsx($path),
        ]);

        // Assert — the re-upload is acknowledged but the nets are NOT applied twice.
        $second->assertOk();
        $this->assertDatabaseHas('products', ['id' => 4450, 'quantity' => 14]);
        $this->assertDatabaseHas('products', ['id' => 4451, 'quantity' => 19]);

        // The duplicate points at the original import record instead of creating a new run.
        $this->assertDatabaseCount('imports', 1);
        $this->assertSame($first->json('data.import_id'), $second->json('data.import_id'));
    }

    /**
     * Write a real xlsx with a product_id/status heading row plus the given data rows.
     *
     * @param  array<int, array{0: int|string, 1: string}>  $rows
     * @return string Absolute path of the generated file
     */
    private function makeXlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['product_id', 'status'],
            ...$rows,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'product_import_').'.xlsx';
        (new XlsxWriter($spreadsheet))->save($path);

        $this->generatedFiles[] = $path;

        return $path;
    }

    private function uploadedXlsx(string $path): UploadedFile
    {
        return new UploadedFile(
            $path,
            basename($path),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
