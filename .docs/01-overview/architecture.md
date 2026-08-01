# Architecture

> **TL;DR** Two API surfaces (REST under `ApiDataResponse`, GraphQL via Lighthouse) share one
> Eloquent model. Writes go controller → repository → model with `ProductData` DTOs; the
> Excel import goes controller → repository → queued job → chunked `ProductImport` →
> bulk upsert. Locally everything (db, cache, session, queue) lives in one SQLite file.

## Request flow — REST

```
POST /api/products/import
  routes/api.php                    (ApiDataResponse middleware group)
    → ImportProductRequest          validates: file required, mimes:xlsx, max:5120 KB
    → ProductController::import
      → ProductRepository::import   stores upload under storage/app/private/products/
        → dispatch(ImportProductsFromExcelJob)   ← queued (database connection, jobs table)
    ← 200 {code, message: "Uploading is in process...", data: null}

(later, when a worker runs — `just queue`)
ImportProductsFromExcelJob::handle
  → Excel::import(new ProductImport, path, disk, XLSX)
    → ProductImport::collection      per chunk of 100 rows (WithChunkReading, WithHeadingRow)
      → calculateNetChanges          product_id → Σ(sold = −1, buy = +1)
      → buildUpsertData              fetch existing ids, add net change, skip unknown ids
                                     and results that land exactly on 0
      → Product::upsert(..., ['id'], ['quantity'])
  → Storage::delete(uploaded file)
```

`GET /api/products` is the same pattern without the job: controller → repository →
`Product::query()->filter(request('search'))->paginate(10)` — the `LengthAwarePaginator` is
serialized inside the envelope, so the payload shape is `data.data` for the rows.

The **`ApiDataResponse` middleware** wraps every JSON response in the group:

```json
{ "code": 200, "message": "Success", "data": { ... }, "errors": null }
```

`errors` is only populated for 422 validation failures; `data` is nulled for non-2xx codes.

## Request flow — GraphQL

```
POST /api/graphql   (Lighthouse 6; uri set in config/lighthouse.php, schema in graphql/schema.graphql)
  #import product.graphql + user.graphql
  products(where: ..., orderBy: ..., filter: {search: "..."})
    → @paginate(defaultCount: 10) on the Product model
    → @whereConditions / @orderBy generated from the ProductColumn enum
    → @scope(name: "filter") reuses Product::scopeFilter — same search behavior as REST
```

No custom resolvers exist — the whole GraphQL surface is schema directives over Eloquent.
`schema-directives.graphql` at the repo root is an IDE aid (directive definitions), not
runtime schema.

## Data model

One domain table plus Laravel's infrastructure tables:

| Table | Notes |
| --- | --- |
| `products` | `id` (client-supplied, in `$fillable`), `type`, `brand`, `model`, `capacity`, `quantity`, timestamps |
| `users` + `personal_access_tokens` | Sanctum scaffolding, unused by the product routes |
| `cache`, `jobs`, `sessions` | `CACHE_STORE` / `QUEUE_CONNECTION` / `SESSION_DRIVER` all = `database` |

Note `id` is **not** auto-increment-only in practice: the seeder and importer treat it as an
external product number (4450, 4768, ...) — which is why `id` sits in `$fillable` and the
import upserts on it.

## Environments

| Env | DB | Queue | Where defined |
| --- | --- | --- | --- |
| local | sqlite `database/database.sqlite` | `database` (needs `just queue`) | `.env` (written by `just bootstrap`; git-ignored) |
| testing | sqlite `database/testing.sqlite` | `sync` (runs inline) | `phpunit.xml` + committed `.env.testing` |
| reference | MySQL `xlsx_import_backend` | `database` | committed `.env.example` (+ `xlsx_import_backend.sql` dump) |

## Trust boundaries

- The import trusts sheet **content** shape (`product_id`, `status` heading row) but not its
  values — unknown ids and unknown statuses are ignored, never created.
- Upload abuse is bounded at the request edge: `mimes:xlsx` + 5 MB cap.
- `/api/products` write routes have **no auth** — fine locally, a known gap for any real
  deployment (see [../04-deployment/deployment.md](../04-deployment/deployment.md)).

## Related docs

| Doc | Why |
| --- | --- |
| [project-overview.md](project-overview.md) | What the app does, feature by feature |
| [../03-development/workflow.md](../03-development/workflow.md) | Day-2 loop: serve, queue, test |
| [../06-troubleshooting/common-issues.md](../06-troubleshooting/common-issues.md) | Real failure modes and fixes |
