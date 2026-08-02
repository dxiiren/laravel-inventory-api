<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ApiDataResponse rewrites every JsonResponse under /api into
 * {code, message, data, errors}. These tests pin that contract — including the
 * pass-through for responses that are not JSON.
 */
class ApiDataResponseEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
    }

    public function test_a_200_puts_the_payload_in_data_with_no_errors(): void
    {
        $response = $this->getJson('/api/products')->assertOk();

        $response->assertJsonStructure(['code', 'message', 'data', 'errors']);
        $response->assertJsonPath('code', 200);
        $response->assertJsonPath('message', 'Success');
        $response->assertJsonPath('errors', null);

        // The paginator lives one level deeper because of the envelope.
        $this->assertIsArray($response->json('data.data'));
        $this->assertSame(5, $response->json('data.total'));
    }

    public function test_a_201_also_carries_its_payload_in_data(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/products', [
            'type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => 'iPhone SE',
            'capacity' => '2GB/16GB',
            'quantity' => 13,
        ])->assertCreated();

        $response->assertJsonPath('code', 201);
        $response->assertJsonPath('errors', null);
        $response->assertJsonPath('data.brand', 'Apple');
        $response->assertJsonPath('data.quantity', 13);
    }

    public function test_a_422_populates_errors_and_nulls_data(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/products', [
            'type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => 'iPhone SE',
            'capacity' => '2GB/16GB',
        ])->assertUnprocessable();

        $response->assertJsonPath('code', 422);
        $response->assertJsonPath('data', null);
        $this->assertIsArray($response->json('errors'));
        $this->assertArrayHasKey('quantity', $response->json('errors'));
        $this->assertNotSame('Success', $response->json('message'));
    }

    public function test_a_validation_failure_on_the_import_endpoint_uses_the_same_envelope(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/products/import', [])->assertUnprocessable();

        $response->assertJsonPath('code', 422);
        $response->assertJsonPath('data', null);
        $response->assertJsonPath('errors.file.0', 'Please upload an Excel file.');
    }

    public function test_a_401_from_the_auth_middleware_escapes_the_envelope(): void
    {
        // Known asymmetry, pinned rather than assumed: a FormRequest's 422 is
        // produced inside the controller and therefore travels back out through
        // ApiDataResponse, but auth:sanctum rejects the request by throwing, and
        // that exception is rendered past the envelope middleware. Clients must
        // read a 401 as a bare {"message": ...}, not as {code, message, data,
        // errors}.
        $response = $this->postJson('/api/products', [])->assertUnauthorized();

        $response->assertExactJson(['message' => 'Unauthenticated.']);
        $this->assertArrayNotHasKey('code', $response->json());
        $this->assertArrayNotHasKey('data', $response->json());
    }

    public function test_a_non_json_response_passes_through_untouched(): void
    {
        // The landing page is HTML and does not sit under the middleware, but the
        // middleware's own guard is what keeps any non-JsonResponse intact.
        $response = $this->get('/')->assertOk();

        $this->assertStringContainsString('Laravel Inventory API', $response->getContent());
        $this->assertStringNotContainsString('"code"', $response->getContent());
    }

    public function test_a_route_outside_the_envelope_group_is_not_wrapped(): void
    {
        // GET /api/user is registered outside the ApiDataResponse group, so it
        // answers with the bare model rather than the envelope.
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user')->assertOk();

        $response->assertJsonPath('email', $user->email);
        $this->assertArrayNotHasKey('code', $response->json());
        $this->assertArrayNotHasKey('data', $response->json());
    }
}
