<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Tests\TestCase;

class ProductGraphqlTest extends TestCase
{
    use MakesGraphQLRequests, RefreshDatabase, RefreshesSchemaCache;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl(config('app.url'));
        $this->bootRefreshesSchemaCache();
    }

    public function test_get_all_products_graphql()
    {
        // prepare
        Product::factory()->count(20)->create();

        // test
        $response = $this->graphQL(
            /** @lang GraphQL **/
            '
            query {
                products {
                    paginatorInfo {
                        total
                        hasMorePages
                        __typename
                    }
                    data {
                        id
                        type
                        brand
                        model
                        capacity
                        quantity
                        __typename
                    }
                    __typename
                }
            }
            '
        );
        $response->assertOk();

        // assert
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('products', $response->json('data'));
        $this->assertArrayHasKey('paginatorInfo', $response->json('data.products'));
        $this->assertArrayHasKey('total', $response->json('data.products.paginatorInfo'));
        $this->assertArrayHasKey('hasMorePages', $response->json('data.products.paginatorInfo'));
        $this->assertArrayHasKey('data', $response->json('data.products'));

        $this->assertCount(10, $response->json('data.products.data'));
    }

    public function test_get_product_by_id_graphql()
    {
        // prepare
        Product::factory()->create([
            'id' => 9999,
            'type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => 'iPhone SE',
            'capacity' => '2GB/16GB',
            'quantity' => 13,
        ]);

        // test
        $response = $this->graphQL(
            /** @lang GraphQL **/
            '
            query ProductDetailsById($id: Mixed!) {
                products(where: {column: id, operator: EQ, value: $id}) {
                    paginatorInfo {
                        total
                        hasMorePages
                        __typename
                    }
                    data {
                        id
                        type
                        brand
                        model
                        capacity
                        quantity
                        __typename
                    }
                    __typename
                }
            }
            ',
            /* GraphQL Variable */
            [
                'id' => 9999,
            ],
        );
        $response->assertOk();

        // assert
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('products', $response->json('data'));
        $this->assertNotNull($response->json('data.products.data.0'));
        $this->assertEquals(9999, $response->json('data.products.data.0.id'));
    }

    public function test_get_product_by_type_graphql()
    {
        // prepare
        Product::factory()->create([
            'id' => 9999,
            'type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => 'iPhone SE',
            'capacity' => '2GB/16GB',
            'quantity' => 13,
        ]);

        // test
        $response = $this->graphQL(
            /** @lang GraphQL **/
            '
            query ProductDetailsByType($type: Mixed!) {
                products(where: {column: type, operator: EQ, value: $type}) {
                    paginatorInfo {
                        total
                        hasMorePages
                        __typename
                    }
                    data {
                        id
                        type
                        brand
                        model
                        capacity
                        quantity
                        __typename
                    }
                    __typename
                }
            }
            ',
            /* GraphQL Variable */
            [
                'type' => 'Smartphone',
            ],
        );
        $response->assertOk();

        // assert
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('products', $response->json('data'));
        $this->assertNotNull($response->json('data.products.data.0'));
        $this->assertEquals('Smartphone', $response->json('data.products.data.0.type'));
    }

    public function test_find_product_using_like()
    {
        // prepare
        Product::factory()->create([
            'id' => 9999,
            'type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => 'iPhone SE',
            'capacity' => '2GB/16GB',
            'quantity' => 13,
        ]);

        // test
        $response = $this->graphQL(
            /** @lang GraphQL **/
            '
            query ProductDetailsByType($type: Mixed!) {
                products(where: {column: type, operator: LIKE, value: $type}) {
                    paginatorInfo {
                        total
                        hasMorePages
                        __typename
                    }
                    data {
                        id
                        type
                        brand
                        model
                        capacity
                        quantity
                        __typename
                    }
                    __typename
                }
            }
            ',
            /* GraphQL Variable */
            [
                'type' => 'Smart%',
            ],
        );
        $response->assertOk();

        // assert
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('products', $response->json('data'));
        $this->assertNotNull($response->json('data.products.data.0'));
        $this->assertEquals('Smartphone', $response->json('data.products.data.0.type'));
    }

    public function test_using_like_for_2_columns()
    {
        // Prepare
        Product::factory()->create([
            'id' => 9999,
            'type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => 'iPhone SE',
            'capacity' => '2GB/16GB',
            'quantity' => 13,
        ]);

        // Test
        $response = $this->graphQL(
            /** @lang GraphQL **/
            '
            query ProductDetailsByType($type: Mixed!, $brand: Mixed!) {
                products(where: {
                    AND: [
                        { column: type, operator: LIKE, value: $type }
                        { column: brand, operator: LIKE, value: $brand }
                    ]
                }) {
                    paginatorInfo {
                        total
                        hasMorePages
                    }
                    data {
                        id
                        type
                        brand
                        model
                        capacity
                        quantity
                    }
                }
            }
            ',
            [
                'type' => 'Smart%',
                'brand' => 'App%',
            ]
        );

        $response->assertOk();

        // Assert
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('products', $response->json('data'));
        $this->assertNotNull($response->json('data.products.data.0'));
        $this->assertEquals('Smartphone', $response->json('data.products.data.0.type'));
        $this->assertEquals('Apple', $response->json('data.products.data.0.brand'));
        $this->assertEquals(9999, $response->json('data.products.data.0.id'));
    }

    public function test_using_like_for_2_columns_with_or()
    {
        // Prepare
        Product::factory()->create([
            'id' => 9999,
            'type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => 'iPhone SE',
            'capacity' => '2GB/16GB',
            'quantity' => 13,
        ]);

        // Test
        $response = $this->graphQL(
            /** @lang GraphQL **/
            '
            query ProductDetailsByType($type: Mixed!, $brand: Mixed!) {
                products(where: {
                    OR: [
                        { column: type, operator: LIKE, value: $type }
                        { column: brand, operator: LIKE, value: $brand }
                    ]
                }) {
                    paginatorInfo {
                        total
                        hasMorePages

                    }
                    data {
                        id
                        type
                        brand
                        model
                        capacity
                        quantity
                    }
                }
            }
            ',
            [
                'type' => 'Smart%',
                'brand' => 'App%',
            ]
        );

        $response->assertOk();

        // Assert
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('products', $response->json('data'));
        $this->assertNotNull($response->json('data.products.data.0'));
        $this->assertEquals('Smartphone', $response->json('data.products.data.0.type'));
        $this->assertEquals('Apple', $response->json('data.products.data.0.brand'));
        $this->assertEquals(9999, $response->json('data.products.data.0.id'));
    }

    public function test_product_search_scope_graphql()
    {
        // Prepare
        Product::factory()->count(5)->create([
            'type' => 'Smartphone',
        ]);

        Product::factory()->create([
            'id' => 8888,
            'type' => 'Laptop',
            'brand' => 'Dell',
            'model' => 'XPS 15',
            'capacity' => '16GB/512GB',
            'quantity' => 5,
        ]);

        // Test
        $response = $this->graphQL(
            /** @lang GraphQL **/
            '
            query SearchProducts($filter: ProductFilterInput) {
                products(filter: $filter) {
                    paginatorInfo {
                    total
                    }
                    data {
                    id
                    type
                    brand
                    model
                    }
                }
            }
            ',
            [
                'filter' => [
                    'search' => 'Smart',
                ],
            ]
        );

        $response->assertOk();

        // Assert
        $data = $response->json('data.products.data');
        $this->assertCount(5, $data);

        foreach ($data as $product) {
            $this->assertStringContainsString('Smart', $product['type']);
        }
    }

    public function test_graphql_search_matches_rest_search()
    {
        // Prepare — the same seed for both endpoints: 5 seeded iPhones plus one
        // laptop so each search term filters a real subset.
        $this->seed(ProductSeeder::class);

        Product::factory()->create([
            'id' => 8888,
            'type' => 'Laptop',
            'brand' => 'Dell',
            'model' => 'XPS 15',
            'capacity' => '16GB/512GB',
            'quantity' => 5,
        ]);

        $searchTerms = [
            'iPhone', // model column — the 5 seeded phones
            '2GB',    // capacity column — subset of the phones
            '445',    // id column — 4450 + 4451
            'Dell',   // brand column — the laptop only
        ];

        foreach ($searchTerms as $term) {
            // REST result set
            $rest = $this->getJson('/api/products?search='.urlencode($term));
            $rest->assertOk();
            $restIds = collect($rest->json('data.data'))->pluck('id')->sort()->values()->all();

            // GraphQL result set — same search via products(filter: { search: ... })
            $graphql = $this->graphQL(
                /** @lang GraphQL **/
                '
                query SearchProducts($filter: ProductFilterInput) {
                    products(filter: $filter) {
                        data {
                            id
                        }
                    }
                }
                ',
                [
                    'filter' => [
                        'search' => $term,
                    ],
                ]
            );
            $graphql->assertOk();
            $graphqlIds = collect($graphql->json('data.products.data'))->pluck('id')->sort()->values()->all();

            // Assert — both APIs agree on the exact result set for this term.
            $this->assertNotEmpty($restIds, "REST search '{$term}' should match at least one product");
            $this->assertSame($restIds, $graphqlIds, "GraphQL and REST disagree for search '{$term}'");
        }
    }

    /**
     * Deterministic ids well outside ProductFactory's random 4000-9999 range, so
     * the paging assertions can never collide with a seeded or faked product.
     *
     * @return array<int, int> every id, ascending
     */
    private function seedPagingFixture(int $count = 25): array
    {
        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            $id = 1000 + $i;
            Product::factory()->create([
                'id' => $id,
                'type' => 'Smartphone',
                'brand' => 'Apple',
                'model' => 'iPhone SE',
                'capacity' => '2GB/64GB',
                'quantity' => $count - $i, // strictly descending, so quantity ordering is unambiguous
            ]);
            $ids[] = $id;
        }

        return $ids;
    }

    /** @return array<int, int> */
    private function restIds(int $page): array
    {
        $response = $this->getJson('/api/products?page='.$page)->assertOk();

        return array_map('intval', array_column($response->json('data.data'), 'id'));
    }

    /** @return array<int, int> */
    private function graphqlIds(int $page, string $column = 'id', string $order = 'ASC'): array
    {
        $response = $this->graphQL(
            /** @lang GraphQL **/
            '
            query PagedProducts($page: Int!, $column: ProductColumn!, $order: SortOrder!) {
                products(page: $page, orderBy: [{ column: $column, order: $order }]) {
                    paginatorInfo { total currentPage perPage lastPage }
                    data { id quantity }
                }
            }
            ',
            ['page' => $page, 'column' => $column, 'order' => $order]
        )->assertOk();

        return array_map('intval', array_column($response->json('data.products.data'), 'id'));
    }

    public function test_graphql_pagination_matches_rest_pagination()
    {
        $allIds = $this->seedPagingFixture();

        // REST paginates 10 per page ordered by id ascending
        // (ProductRepository::getProducts); Lighthouse's @paginate defaults to 10
        // as well, so the same page number must yield the same slice.
        $restTotal = $this->getJson('/api/products?page=1')->assertOk()->json('data.total');
        $graphqlTotal = $this->graphQL(
            /** @lang GraphQL **/
            '{ products(page: 1, orderBy: [{ column: id, order: ASC }]) { paginatorInfo { total perPage } } }'
        )->assertOk()->json('data.products.paginatorInfo');

        $this->assertSame(count($allIds), $restTotal);
        $this->assertSame($restTotal, $graphqlTotal['total'], 'REST and GraphQL disagree on the total');
        $this->assertSame(10, $graphqlTotal['perPage']);

        foreach ([1, 2, 3] as $page) {
            $rest = $this->restIds($page);
            $graphql = $this->graphqlIds($page);

            $this->assertNotEmpty($rest, "REST page {$page} should not be empty");
            $this->assertSame($rest, $graphql, "REST and GraphQL disagree on page {$page}");
        }

        // Page 2 specifically: ids 1010..1019, in order, no overlap with page 1.
        $this->assertSame(array_slice($allIds, 10, 10), $this->restIds(2));
        $this->assertSame([], array_intersect($this->restIds(1), $this->restIds(2)));

        // Past the last page both APIs return an empty slice rather than wrapping.
        $this->assertSame([], $this->restIds(99));
        $this->assertSame([], $this->graphqlIds(99));
    }

    public function test_graphql_order_by_matches_the_rest_ordering()
    {
        $allIds = $this->seedPagingFixture();

        // REST has one fixed ordering — id ascending — across every page.
        $restAll = array_merge($this->restIds(1), $this->restIds(2), $this->restIds(3));
        $this->assertSame($allIds, $restAll);

        // GraphQL asked for the same ordering agrees page for page.
        $graphqlAsc = array_merge($this->graphqlIds(1), $this->graphqlIds(2), $this->graphqlIds(3));
        $this->assertSame($restAll, $graphqlAsc);

        // And DESC is exactly that sequence reversed — not a differently paged set.
        $graphqlDesc = array_merge(
            $this->graphqlIds(1, 'id', 'DESC'),
            $this->graphqlIds(2, 'id', 'DESC'),
            $this->graphqlIds(3, 'id', 'DESC'),
        );
        $this->assertSame(array_reverse($restAll), $graphqlDesc);

        // Ordering by a non-key column reorders the pages, and the fixture's
        // strictly descending quantities make the expected sequence exact.
        $byQuantityAsc = array_merge(
            $this->graphqlIds(1, 'quantity', 'ASC'),
            $this->graphqlIds(2, 'quantity', 'ASC'),
            $this->graphqlIds(3, 'quantity', 'ASC'),
        );
        $this->assertSame(array_reverse($allIds), $byQuantityAsc);
    }
}
