<?php

namespace Tests\Feature;

use App\Enums\ImportStatusEnum;
use App\Models\Import;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

/**
 * The unhappy paths of POST /api/products/import: rows the importer cannot
 * apply, the net-zero row it deliberately skips, and uploads that never become
 * an import at all.
 */
class ProductImportFailureTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] xlsx files generated during the test, removed in tearDown */
    private array $generatedFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(ProductSeeder::class);
        Sanctum::actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        foreach ($this->generatedFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /**
     * @param  array<int, array<int, int|string|null>>  $rows
     * @return array<string, mixed> the row_errors reported for the import
     */
    private function importRows(array $rows): array
    {
        $response = $this->postJson('/api/products/import', [
            'file' => $this->uploadedXlsx($this->makeXlsx($rows)),
        ])->assertOk();

        $import = Import::findOrFail($response->json('data.import_id'));
        $this->assertSame(ImportStatusEnum::COMPLETED, $import->status);

        return $import->row_errors ?? [];
    }

    public function test_a_row_with_no_status_is_skipped_and_reported(): void
    {
        // Heading is row 1, so this is spreadsheet row 2.
        $errors = $this->importRows([
            [4450, null],
            [4451, 'sold'],
        ]);

        $this->assertCount(1, $errors);
        $this->assertSame(2, $errors[0]['row']);
        $this->assertStringContainsString('missing product_id or status', $errors[0]['error']);

        // The healthy row still applied: 20 - 1.
        $this->assertDatabaseHas('products', ['id' => 4451, 'quantity' => 19]);
        // The broken row changed nothing.
        $this->assertDatabaseHas('products', ['id' => 4450, 'quantity' => 13]);
    }

    public function test_a_row_with_an_unrecognised_status_is_skipped_and_reported(): void
    {
        $errors = $this->importRows([
            [4450, 'maybe'],
            [4451, 'buy'],
        ]);

        $this->assertCount(1, $errors);
        $this->assertSame(2, $errors[0]['row']);
        $this->assertEquals(4450, $errors[0]['product_id']);
        $this->assertStringContainsString("Invalid status 'maybe'", $errors[0]['error']);

        $this->assertDatabaseHas('products', ['id' => 4450, 'quantity' => 13]);
        $this->assertDatabaseHas('products', ['id' => 4451, 'quantity' => 21]);
    }

    public function test_a_non_integer_product_id_is_reported_as_an_unknown_product(): void
    {
        $errors = $this->importRows([
            ['NOT-AN-ID', 'sold'],
            [4450, 'buy'],
        ]);

        $this->assertCount(1, $errors);
        $this->assertSame(2, $errors[0]['row']);
        $this->assertSame('NOT-AN-ID', $errors[0]['product_id']);
        $this->assertStringContainsString('Unknown product_id', $errors[0]['error']);

        $this->assertDatabaseHas('products', ['id' => 4450, 'quantity' => 14]);
    }

    public function test_several_bad_rows_are_all_reported_with_their_spreadsheet_row_numbers(): void
    {
        $errors = $this->importRows([
            [4450, 'maybe'],      // row 2 — bad status
            [null, 'sold'],       // row 3 — missing product_id
            [999999, 'buy'],      // row 4 — unknown product
            [4451, 'sold'],       // row 5 — fine
        ]);

        $this->assertCount(3, $errors);
        $this->assertSame([2, 3, 4], array_column($errors, 'row'));

        $this->assertDatabaseHas('products', ['id' => 4451, 'quantity' => 19]);
    }

    public function test_a_net_zero_quantity_leaves_the_product_untouched_and_is_not_reported(): void
    {
        // Documented behaviour (see ProductImport::buildUpsertData and CLAUDE.md):
        // a product whose net quantity would land on exactly 0 is skipped
        // silently — the row is neither applied nor recorded as an error.
        $product = Product::factory()->create(['id' => 1234, 'quantity' => 1]);

        $errors = $this->importRows([
            [1234, 'sold'],   // 1 - 1 = 0 -> skipped
            [4450, 'buy'],    // 13 + 1 = 14 -> applied
        ]);

        $this->assertSame([], $errors, 'the net-zero skip is silent by design');
        $this->assertSame(1, $product->fresh()->quantity, 'the net-zero product is left at its old quantity');
        $this->assertDatabaseHas('products', ['id' => 4450, 'quantity' => 14]);
    }

    public function test_a_whole_file_of_net_zero_rows_changes_nothing(): void
    {
        Product::factory()->create(['id' => 1234, 'quantity' => 1]);

        $errors = $this->importRows([
            [1234, 'buy'],
            [1234, 'sold'],
            [1234, 'sold'],
            [1234, 'buy'],
        ]);

        // Net change is 0 for the only product referenced, so nothing is upserted.
        $this->assertSame([], $errors);
        $this->assertDatabaseHas('products', ['id' => 1234, 'quantity' => 1]);
    }

    public function test_a_non_xlsx_upload_is_rejected_and_records_no_import(): void
    {
        $this->postJson('/api/products/import', [
            'file' => UploadedFile::fake()->create('inventory.csv', 10, 'text/csv'),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'The file must be a .xlsx Excel file.');

        $this->assertDatabaseCount('imports', 0);
        $this->assertSame([], Storage::disk('local')->files('products'));
    }

    public function test_an_upload_over_the_five_megabyte_cap_is_rejected_and_records_no_import(): void
    {
        $this->postJson('/api/products/import', [
            'file' => UploadedFile::fake()->create(
                'huge.xlsx',
                6 * 1024,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('imports', 0);
        $this->assertSame([], Storage::disk('local')->files('products'));
    }

    public function test_a_missing_file_is_rejected_and_records_no_import(): void
    {
        $this->postJson('/api/products/import', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Please upload an Excel file.');

        $this->assertDatabaseCount('imports', 0);
    }

    /**
     * Write a real xlsx with a product_id/status heading row plus the given rows.
     *
     * @param  array<int, array<int, int|string|null>>  $rows
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
