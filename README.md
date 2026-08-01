# Upload Product API

A Laravel 12 product-inventory API: CRUD and search over a `products` table via REST
**and** GraphQL (Lighthouse), plus a bulk Excel import — POST an `.xlsx` of
`product_id`/`status` rows (`sold` = −1, `buy` = +1) and a queued job nets the changes
per product and upserts the new stock quantities. Backend for the companion
`upload-product-vue` frontend; the only page it serves itself is the stock welcome page.

> **New developer? Start with [`.docs/tldr.md`](.docs/tldr.md)** — every doc summarised on one
> page. The full guide lives in [`.docs/`](.docs/README.md).

## Prerequisites

| Tool | Version | Installed by |
| --- | --- | --- |
| PowerShell + winget | Windows 10/11 stock | — (the only true prerequisites) |
| Git | any recent | `setup.ps1` |
| PHP | 8.4 (pinned to `%LOCALAPPDATA%\Programs\php-8.4`) | `setup.ps1` |
| Composer | 2.x (`composer.phar` next to PHP) | `setup.ps1` |
| Node.js | LTS (npm for the Vite asset build) | `setup.ps1` |
| uv + Python | latest (Claude tooling/statusline) | `setup.ps1` |
| just | any recent | `setup.ps1` |
| Claude Code CLI | latest | `setup.ps1` (optional, for AI-assisted dev) |

## Quick start

```powershell
# 1. One-time machine setup (idempotent — safe to re-run)
pwsh ./setup.ps1

# 2. Close and reopen PowerShell so PATH updates land
# 3. One-time app bootstrap: composer + npm + .env (sqlite) + migrate + asset build
just bootstrap

# 4. Start the dev server
just start

# 5. Optional: seed 5 sample products
just fresh
```

The app is now at **http://127.0.0.1:8105**. Stop it with `just stop`.

Try it: `curl.exe http://127.0.0.1:8105/api/products` — a paginated JSON product list
wrapped in the `{code, message, data, errors}` envelope. GraphQL lives at
`POST http://127.0.0.1:8105/api/graphql`.

**Excel import needs a queue worker.** `POST /api/products/import` only queues the job —
run `just queue` in a second terminal to actually process it. A sample upload file is at
`database/seeders/product_status_list.xlsx`.

## Commands

Run `just` with no arguments to list every recipe. The ones you'll use daily:

| Command | What it does |
| --- | --- |
| `just bootstrap` | One-time app setup: deps, `.env` (sqlite), db, migrate, asset build |
| `just start` | Serve on http://127.0.0.1:8105 in the background |
| `just stop` | Stop only THIS repo's `php.exe` processes |
| `just serve` | Serve in the foreground (Ctrl+C to stop) |
| `just queue` | Run the queue worker (foreground) — processes Excel import jobs |
| `just migrate` | Run pending migrations |
| `just fresh` | Drop, re-migrate and seed (5 sample products) — destroys local data |
| `just test` | PHPUnit suite (`just test --filter=ProductTest` to narrow) |
| `just lint` / `just lint-fix` | Laravel Pint style check / auto-fix |
| `just claudex` | Launch Claude Code (Sonnet, all permissions) |

## Troubleshooting

### `just start` succeeds but every request returns 500

The Vite build manifest is missing — the welcome route calls `@vite` and Laravel throws
`Unable to locate file in Vite manifest`. Run `npm run build` (or the full `just bootstrap`),
then reload.

### POST /api/products/import returns 200 but products never change

That's the queue: the upload is stored and `ImportProductsFromExcelJob` is queued on the
`database` connection. Run `just queue` to process it. Also note the import only adjusts
quantities of **existing** product ids — unknown ids in the sheet are skipped silently.

### `could not find driver` or MySQL connection errors on artisan commands

Your `.env` still points at MySQL (the committed `.env.example` default). `just bootstrap`
writes a fresh `.env` with `DB_CONNECTION=sqlite` — either delete `.env` and re-run
`just bootstrap`, or set `DB_CONNECTION=sqlite` and comment out the `DB_*` lines yourself.
Don't edit `.env.example`.

### Port 8105 already in use

Another serve is lingering. `just stop` kills only this repo's `php.exe` processes; re-run
`just start`. To serve on a different port (e.g. 8000 for the Vue frontend's default):
`$env:PORT=8000; just start`.

## Project layout

```
upload-product-laravel-excel/
  app/
    Contracts/              # ProductRepositoryInterface
    Data/                   # ProductData (spatie/laravel-data DTO)
    Enums/                  # ProductStatusEnum (sold | buy)
    Http/Controllers/       # ProductController (index/store/update/destroy/import)
    Http/Middleware/        # ApiDataResponse ({code, message, data, errors} envelope)
    Http/Requests/          # ImportProductRequest (xlsx, <=5MB)
    Imports/                # ProductImport (chunked, nets sold/buy, upserts quantity)
    Jobs/                   # ImportProductsFromExcelJob (queued)
    Models/                 # Product, User
    Repositories/           # ProductRepository
  database/                 # migrations, ProductFactory, ProductSeeder + sample xlsx
  graphql/                  # schema.graphql + product.graphql + user.graphql
  routes/                   # api.php (products CRUD + import), web.php (welcome)
  tests/                    # ProductTest, ProductGraphqlTest
  xlsx_import_backend.sql   # MySQL dump matching .env.example defaults (optional)
  justfile, setup.ps1       # dev recipes + one-time machine setup
  .docs/                    # numbered documentation set
  .claude/                  # skills, hooks, settings
```
