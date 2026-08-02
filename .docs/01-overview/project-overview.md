# Project overview

> **TL;DR** Laravel Inventory API is a Laravel 12 product-inventory backend: REST + GraphQL
> endpoints over a `products` table, plus a queued bulk Excel import that nets `sold`/`buy`
> rows per product and upserts the resulting stock quantities. It runs locally only, on
> SQLite, at http://127.0.0.1:8105, and is the backend for the sibling `vue-inventory-ui`
> frontend.

## What it is

A small, complete Laravel 12 **API** built around one domain: **products and their stock
quantities**. There is no UI beyond the stock welcome page — the interesting parts are the
repository/DTO layering, the dual REST + GraphQL surface, and the chunked, queued Excel
import pipeline.

## What it does

| Feature | Where |
| --- | --- |
| Welcome page (stock Laravel 12) | `GET /` |
| Product list — paginated (10/page), `?search=` across id/type/brand/model/capacity | `GET /api/products` |
| Product create / update / delete (via `ProductData` DTO) | `POST/PUT/DELETE /api/products[/{id}]` |
| Bulk Excel import — `.xlsx` upload (≤5 MB), queued processing, sha256-idempotent | `POST /api/products/import` |
| Import report — run status + row-level errors (unknown ids, bad statuses) | `GET /api/imports/{id}` |
| GraphQL — `product`, `products`, `user`, `users` queries with pagination, `where`, `orderBy`, `filter` | `POST /api/graphql` |
| Current authenticated user (Sanctum) | `GET /api/user` |

## Key design points

- **Repository pattern + DTOs** — `ProductController` is thin; it delegates to
  `ProductRepositoryInterface` (bound to `ProductRepository` in `AppServiceProvider`) and
  passes typed `ProductData` objects (spatie/laravel-data) instead of raw request arrays.
- **Uniform response envelope** — the `ApiDataResponse` middleware wraps every JSON
  response of the `/api/products*` group as `{code, message, data, errors}`.
- **Queued, chunked Excel import** — the upload is stored to `storage/app/private/products`,
  an `Import` record is created (file name, sha256 hash, status lifecycle
  `pending → processing → completed/failed`) and `ImportProductsFromExcelJob` is dispatched
  on the `database` queue. `ProductImport` reads the sheet in chunks of 100
  (`WithChunkReading`), computes a **net change** per `product_id` (`sold` = −1, `buy` = +1),
  then bulk-`upsert`s quantities. Unknown product ids, missing columns and invalid statuses
  are skipped **and recorded as row-level errors** (real spreadsheet row numbers) on the
  `Import` record, readable at `GET /api/imports/{id}`; a product whose new quantity would
  be exactly 0 is left unchanged. The uploaded file is deleted after a successful import.
- **Idempotent re-imports** — the sha256 of the uploaded file is the idempotency key: a
  byte-identical re-upload is acknowledged with the original `import_id` and never
  dispatched again, so quantity nets can't double-apply. Only a `failed` run may retry.
- **GraphQL mirrors the model, not the controller** — Lighthouse resolves `products`
  straight from Eloquent with `@paginate` / `@whereConditions` / `@orderBy`, and reuses the
  model's `scopeFilter` via `@scope(name: "filter")`. The schema is split across
  `graphql/*.graphql` files joined by `#import`.
- **Local-only** — no CI/CD, no deploy target; `just start` serves on port 8105.

## What it is not

- Not deployed anywhere; there is no production environment.
- No login/registration flows — `User`, Sanctum and the `users` GraphQL query exist but
  nothing issues tokens; `/api/products*` routes are unauthenticated.
- No product-creating import — the Excel pipeline only adjusts quantities of products that
  already exist (seed them first: `just fresh`).

## About the framework

The app is built on the Laravel framework (MIT-licensed skeleton). Framework-level learning
resources: [laravel.com/docs](https://laravel.com/docs), the
[Laravel Bootcamp](https://bootcamp.laravel.com), and [Laracasts](https://laracasts.com).

## Related docs

| Doc | Why |
| --- | --- |
| [architecture.md](architecture.md) | How a request flows (routes → controller → repository → job → import) |
| [../02-setup/getting-started.md](../02-setup/getting-started.md) | Get it running from a fresh PC |
| [../05-reference/project-layout.md](../05-reference/project-layout.md) | Where every file lives |
