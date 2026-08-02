<?php

namespace Tests\Feature;

use Tests\TestCase;

class SmokeTest extends TestCase
{
    public function test_the_landing_page_documents_the_api_rather_than_shipping_the_stock_welcome_screen(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Laravel Inventory API</title>', false)
            ->assertSee('Laravel Inventory API')
            ->assertDontSee('Laracasts', false)
            ->assertDontSee('laravel-news.com', false);
    }

    public function test_the_landing_page_lists_every_endpoint(): void
    {
        $response = $this->get('/')->assertOk();

        foreach ([
            '/api/products',
            '/api/products/{product}',
            '/api/products/import',
            '/api/imports/{import}',
            '/api/graphql',
            '/api/user',
        ] as $path) {
            $response->assertSee($path, false);
        }

        $response->assertSee('{code, message, data, errors}', false);
    }

    public function test_the_landing_page_needs_no_built_vite_manifest(): void
    {
        // The page is deliberately self-contained (inline CSS, no @vite), which
        // is why it renders on a clone that has never run `npm run build`.
        $this->get('/')
            ->assertOk()
            ->assertDontSee('/build/assets/', false);
    }
}
