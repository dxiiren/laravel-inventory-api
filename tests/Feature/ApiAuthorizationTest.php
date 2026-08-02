<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The authentication contract for /api.
 *
 * Reads are public — the companion vue-inventory-ui browses the catalogue
 * without a token, and GET /api/user has always been behind auth:sanctum.
 * Everything that MUTATES stock (product writes and the Excel import), plus the
 * import report those writes produce, requires a token. Before this suite
 * existed every one of those endpoints was wide open.
 */
class ApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(ProductSeeder::class);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => 'iPhone SE',
            'capacity' => '2GB/16GB',
            'quantity' => 13,
        ], $overrides);
    }

    private function xlsx(): UploadedFile
    {
        return new UploadedFile(
            database_path('seeders/product_status_list.xlsx'),
            'product_sample.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_a_guest_cannot_create_a_product(): void
    {
        $before = Product::query()->count();

        $this->postJson('/api/products', $this->payload())
            ->assertUnauthorized();

        $this->assertSame($before, Product::query()->count());
    }

    public function test_a_guest_cannot_update_a_product(): void
    {
        $product = Product::query()->firstOrFail();

        $this->putJson("/api/products/{$product->id}", $this->payload(['quantity' => 999]))
            ->assertUnauthorized();

        $this->assertSame($product->quantity, $product->fresh()->quantity);
    }

    public function test_a_guest_cannot_delete_a_product(): void
    {
        $product = Product::query()->firstOrFail();

        $this->deleteJson("/api/products/{$product->id}")
            ->assertUnauthorized();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_a_guest_cannot_import_products(): void
    {
        $this->postJson('/api/products/import', ['file' => $this->xlsx()])
            ->assertUnauthorized();

        $this->assertDatabaseCount('imports', 0);
        $this->assertSame([], Storage::disk('local')->files('products'));
    }

    public function test_a_guest_cannot_read_an_import_report(): void
    {
        $import = Import::factory()->create();

        $this->getJson("/api/imports/{$import->id}")
            ->assertUnauthorized();
    }

    public function test_a_guest_cannot_read_the_authenticated_user(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_a_guest_can_still_browse_the_product_catalogue(): void
    {
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('code', 200);
    }

    public function test_a_token_holder_can_create_update_and_delete_a_product(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $id = $this->postJson('/api/products', $this->payload())
            ->assertCreated()
            ->json('data.product_id');

        $this->putJson("/api/products/{$id}", $this->payload(['quantity' => 42]))
            ->assertOk()
            ->assertJsonPath('data.quantity', 42);

        $this->deleteJson("/api/products/{$id}")->assertOk();
        $this->assertDatabaseMissing('products', ['id' => $id]);
    }

    public function test_a_token_holder_can_import_products(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/products/import', ['file' => $this->xlsx()])
            ->assertOk()
            ->assertJsonPath('data.message', 'Uploading is in process and submitted successfully');

        $this->assertDatabaseCount('imports', 1);
    }

    public function test_a_token_holder_can_read_an_import_report(): void
    {
        $import = Import::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/imports/{$import->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $import->id);
    }

    public function test_a_real_bearer_token_works_end_to_end(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', $user->email);

        $this->withToken($token)
            ->postJson('/api/products', $this->payload())
            ->assertCreated();
    }

    public function test_a_bogus_bearer_token_is_rejected(): void
    {
        $this->withToken('1|not-a-real-token')
            ->postJson('/api/products', $this->payload())
            ->assertUnauthorized();

        $this->withToken('1|not-a-real-token')
            ->getJson('/api/user')
            ->assertUnauthorized();
    }
}
