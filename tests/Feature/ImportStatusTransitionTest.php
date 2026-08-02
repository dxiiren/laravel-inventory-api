<?php

namespace Tests\Feature;

use App\Enums\ImportStatusEnum;
use App\Jobs\ImportProductsFromExcelJob;
use App\Models\Import;
use App\Models\User;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The Import lifecycle: pending -> processing -> completed (or failed), plus the
 * `status != failed` half of the idempotency key — a failed run may be retried,
 * every other status is treated as already handled.
 */
class ImportStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(ProductSeeder::class);
        Sanctum::actingAs(User::factory()->create());
    }

    private function samplePath(): string
    {
        return database_path('seeders/product_status_list.xlsx');
    }

    private function sampleHash(): string
    {
        return hash_file('sha256', $this->samplePath());
    }

    private function upload(): UploadedFile
    {
        return new UploadedFile(
            $this->samplePath(),
            'product_status_list.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_an_import_starts_pending_while_the_job_is_still_queued(): void
    {
        Queue::fake();

        $id = $this->postJson('/api/products/import', ['file' => $this->upload()])
            ->assertOk()
            ->json('data.import_id');

        $import = Import::findOrFail($id);
        $this->assertSame(ImportStatusEnum::PENDING, $import->status);
        $this->assertSame($this->sampleHash(), $import->file_hash);
        $this->assertSame('product_status_list.xlsx', $import->file_name);
        $this->assertNull($import->row_errors);

        Queue::assertPushed(ImportProductsFromExcelJob::class);
    }

    public function test_the_job_moves_the_import_from_pending_through_processing_to_completed(): void
    {
        $path = 'products/lifecycle.xlsx';
        Storage::disk('local')->put($path, file_get_contents($this->samplePath()));

        $import = Import::factory()->create(['file_hash' => $this->sampleHash()]);
        $this->assertSame(ImportStatusEnum::PENDING, $import->status);

        $observed = [];
        Import::updated(function (Import $updated) use (&$observed) {
            $observed[] = $updated->status;
        });

        (new ImportProductsFromExcelJob($path, $import->id))->handle();

        $this->assertSame(
            [ImportStatusEnum::PROCESSING, ImportStatusEnum::COMPLETED],
            $observed,
            'the import must pass through processing before it completes'
        );
        $this->assertSame(ImportStatusEnum::COMPLETED, $import->fresh()->status);
        $this->assertIsArray($import->fresh()->row_errors);
    }

    public function test_a_crashing_job_leaves_the_import_failed(): void
    {
        $path = 'products/broken.xlsx';
        Storage::disk('local')->put($path, file_get_contents($this->samplePath()));

        $import = Import::factory()->create();

        $job = new ImportProductsFromExcelJob($path, $import->id);
        $job->sheetCount = 0; // trips the "Excel file has no sheets" guard

        try {
            $job->handle();
            $this->fail('handle() should rethrow the import failure');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no sheets', $e->getMessage());
        }

        $this->assertSame(ImportStatusEnum::FAILED, $import->fresh()->status);
    }

    public function test_a_failed_import_may_be_retried_with_the_same_file(): void
    {
        // The repository looks for `file_hash = ? AND status != failed`, so a
        // failed run does not block a fresh attempt at the same bytes.
        $failed = Import::factory()->failed()->forHash($this->sampleHash())->create();

        $response = $this->postJson('/api/products/import', ['file' => $this->upload()])
            ->assertOk()
            ->assertJsonPath('data.message', 'Uploading is in process and submitted successfully');

        $newId = $response->json('data.import_id');

        $this->assertNotSame($failed->id, $newId, 'a failed run must be retryable');
        $this->assertDatabaseCount('imports', 2);
        $this->assertSame(ImportStatusEnum::COMPLETED, Import::findOrFail($newId)->status);
        $this->assertSame(ImportStatusEnum::FAILED, $failed->fresh()->status);
    }

    public function test_a_completed_import_blocks_a_re_upload_of_the_same_file(): void
    {
        $completed = Import::factory()->completed()->forHash($this->sampleHash())->create();

        $this->postJson('/api/products/import', ['file' => $this->upload()])
            ->assertOk()
            ->assertJsonPath('data.import_id', $completed->id)
            ->assertJsonPath('data.message', 'This file was already imported — duplicate upload ignored');

        $this->assertDatabaseCount('imports', 1);
    }

    public function test_a_pending_or_processing_import_also_blocks_a_re_upload(): void
    {
        $pending = Import::factory()->forHash($this->sampleHash())->create();

        $this->postJson('/api/products/import', ['file' => $this->upload()])
            ->assertOk()
            ->assertJsonPath('data.import_id', $pending->id);

        $this->assertDatabaseCount('imports', 1);

        $pending->update(['status' => ImportStatusEnum::PROCESSING]);

        $this->postJson('/api/products/import', ['file' => $this->upload()])
            ->assertOk()
            ->assertJsonPath('data.import_id', $pending->id);

        $this->assertDatabaseCount('imports', 1);
    }

    public function test_a_different_file_always_gets_its_own_import_record(): void
    {
        Import::factory()->completed()->forHash($this->sampleHash())->create();

        $other = tempnam(sys_get_temp_dir(), 'other_').'.xlsx';
        copy($this->samplePath(), $other);
        file_put_contents($other, file_get_contents($other)."\n"); // different bytes, different hash

        $this->postJson('/api/products/import', [
            'file' => new UploadedFile(
                $other,
                'other.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            ),
        ])->assertOk();

        $this->assertDatabaseCount('imports', 2);

        @unlink($other);
    }
}
