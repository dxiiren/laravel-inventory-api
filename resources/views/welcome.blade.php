<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laravel Inventory API</title>

        {{-- Self-contained styling: no build step, no CDN — the page renders the same
             whether or not the Vite assets have been built. --}}
        <style>
            :root {
                --bg: #f8fafc;
                --card: #ffffff;
                --border: #e2e8f0;
                --text: #0f172a;
                --muted: #64748b;
                --accent: #0284c7;
                --accent-soft: #e0f2fe;
                --accent-text: #0369a1;
                --code-bg: #f1f5f9;
            }
            @media (prefers-color-scheme: dark) {
                :root {
                    --bg: #0b1017;
                    --card: #131c26;
                    --border: #24313f;
                    --text: #e2e8f0;
                    --muted: #94a3b8;
                    --accent: #0ea5e9;
                    --accent-soft: #082f49;
                    --accent-text: #7dd3fc;
                    --code-bg: #1a2531;
                }
            }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
                background: var(--bg);
                color: var(--text);
                min-height: 100vh;
                line-height: 1.6;
                -webkit-font-smoothing: antialiased;
            }
            .wrap { max-width: 60rem; margin: 0 auto; padding: 3.5rem 1.5rem 4rem; }
            .brand { display: flex; align-items: center; gap: 0.9rem; margin-bottom: 1.25rem; }
            .brand-mark {
                width: 3rem; height: 3rem; border-radius: 0.9rem;
                background: var(--accent); color: #fff;
                display: flex; align-items: center; justify-content: center;
                font-weight: 700; font-size: 1.15rem; letter-spacing: -0.02em;
                box-shadow: 0 10px 20px -8px color-mix(in srgb, var(--accent) 55%, transparent);
            }
            h1 { font-size: 1.9rem; letter-spacing: -0.03em; line-height: 1.2; }
            .tag {
                display: inline-block; margin-top: 0.25rem;
                background: var(--accent-soft); color: var(--accent-text);
                font-size: 0.72rem; font-weight: 600; text-transform: uppercase;
                letter-spacing: 0.06em; padding: 0.15rem 0.6rem; border-radius: 999px;
            }
            .lede { color: var(--muted); max-width: 46rem; margin-bottom: 2.25rem; }
            code {
                background: var(--code-bg); border: 1px solid var(--border);
                border-radius: 0.35rem; padding: 0.1rem 0.4rem;
                font-size: 0.85em;
                font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            }
            .base { color: var(--muted); font-size: 0.9rem; margin-bottom: 0.9rem; }
            .card {
                background: var(--card); border: 1px solid var(--border);
                border-radius: 1rem; overflow: hidden; margin-bottom: 2rem;
                box-shadow: 0 1px 3px rgb(2 6 23 / 0.06);
            }
            .card h2 {
                font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.07em;
                color: var(--muted); padding: 1rem 1.25rem 0.25rem;
            }
            .tbl-scroll { overflow-x: auto; }
            table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
            th {
                text-align: left; font-size: 0.7rem; text-transform: uppercase;
                letter-spacing: 0.06em; color: var(--muted);
                padding: 0.6rem 1.25rem; border-bottom: 1px solid var(--border);
                white-space: nowrap;
            }
            td { padding: 0.55rem 1.25rem; border-bottom: 1px solid var(--border); vertical-align: top; }
            tr:last-child td { border-bottom: 0; }
            td code { font-size: 0.82rem; white-space: nowrap; }
            .section td {
                background: var(--code-bg); color: var(--accent-text);
                font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
                letter-spacing: 0.07em; padding: 0.45rem 1.25rem;
            }
            .m {
                display: inline-block; min-width: 3.6rem; text-align: center;
                font-size: 0.7rem; font-weight: 700; letter-spacing: 0.04em;
                padding: 0.14rem 0.5rem; border-radius: 0.4rem;
            }
            .m-get    { background: #dcfce7; color: #15803d; }
            .m-post   { background: var(--accent-soft); color: var(--accent-text); }
            .m-put    { background: #fef3c7; color: #b45309; }
            .m-delete { background: #ffe4e6; color: #be123c; }
            @media (prefers-color-scheme: dark) {
                .m-get    { background: #052e16; color: #4ade80; }
                .m-put    { background: #451a03; color: #fbbf24; }
                .m-delete { background: #4c0519; color: #fb7185; }
            }
            .desc { color: var(--muted); }
            .links { display: flex; flex-wrap: wrap; gap: 0.75rem; }
            .links a {
                display: inline-flex; align-items: center; gap: 0.45rem;
                padding: 0.55rem 1.1rem; border-radius: 0.6rem;
                font-size: 0.875rem; font-weight: 600; text-decoration: none;
                border: 1px solid var(--border); color: var(--text); background: var(--card);
                transition: border-color 0.15s, background 0.15s;
            }
            .links a:hover { border-color: var(--accent); }
            .links a.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
            .links a.primary:hover { filter: brightness(1.08); }
            footer { margin-top: 2rem; font-size: 0.8rem; color: var(--muted); }
        </style>
    </head>
    <body>
        <div class="wrap">
            <header>
                <div class="brand">
                    <div class="brand-mark">IA</div>
                    <div>
                        <h1>Laravel Inventory API</h1>
                        <span class="tag">Laravel 12 · REST + GraphQL · Excel import</span>
                    </div>
                </div>
                <p class="lede">
                    A product-inventory API: CRUD and search over a <code>products</code> table via REST
                    <em>and</em> GraphQL (Lighthouse), plus a bulk Excel import — POST an <code>.xlsx</code>
                    of <code>product_id</code>/<code>status</code> rows (<code>sold</code> = −1,
                    <code>buy</code> = +1) and a queued job nets the changes per product. There is no web
                    UI here — the frontend lives in the companion <strong>vue-inventory-ui</strong> repo.
                </p>
            </header>

            <p class="base">Base URL: <code>{{ url('/api') }}</code> — every JSON response is wrapped in the <code>{code, message, data, errors}</code> envelope.</p>

            <div class="card">
                <h2>Endpoints</h2>
                <div class="tbl-scroll">
                    <table>
                        <thead>
                            <tr><th>Method</th><th>Path</th><th>Description</th></tr>
                        </thead>
                        <tbody>
                            <tr class="section"><td colspan="3">REST</td></tr>
                            <tr><td><span class="m m-get">GET</span></td><td><code>/api/products</code></td><td class="desc">List/search products — <code>?search=</code> matches id, type, brand, model and capacity (paginated)</td></tr>
                            <tr><td><span class="m m-post">POST</span></td><td><code>/api/products</code></td><td class="desc">Create a product</td></tr>
                            <tr><td><span class="m m-put">PUT/PATCH</span></td><td><code>/api/products/{product}</code></td><td class="desc">Update a product</td></tr>
                            <tr><td><span class="m m-delete">DELETE</span></td><td><code>/api/products/{product}</code></td><td class="desc">Delete a product</td></tr>
                            <tr><td><span class="m m-get">GET</span></td><td><code>/api/user</code></td><td class="desc">The authenticated user (Sanctum bearer token)</td></tr>
                            <tr class="section"><td colspan="3">GraphQL</td></tr>
                            <tr><td><span class="m m-post">POST</span></td><td><code>/api/graphql</code></td><td class="desc"><code>products</code> / <code>users</code> queries — pagination, <code>whereConditions</code>, <code>orderBy</code>, and the same search via <code>ProductFilterInput</code></td></tr>
                            <tr class="section"><td colspan="3">Excel import</td></tr>
                            <tr><td><span class="m m-post">POST</span></td><td><code>/api/products/import</code></td><td class="desc">Upload an <code>.xlsx</code> (≤5 MB) of <code>product_id</code>/<code>status</code> rows — queued; an identical re-upload is ignored (sha256 idempotency)</td></tr>
                            <tr><td><span class="m m-get">GET</span></td><td><code>/api/imports/{import}</code></td><td class="desc">Import status + row-level errors (unknown product ids are skipped and reported)</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="links">
                <a class="primary" href="https://github.com/dxiiren/laravel-inventory-api#api-examples">API examples (README)</a>
                <a href="https://github.com/dxiiren/vue-inventory-ui">vue-inventory-ui frontend</a>
                <a href="https://github.com/dxiiren/laravel-inventory-api/tree/main/.docs">Developer docs</a>
            </div>

            <footer>
                Seed 5 sample products with <code>just fresh</code>; imports only run once a queue worker picks them up — <code>just queue</code> in a second terminal.
            </footer>
        </div>
    </body>
</html>
