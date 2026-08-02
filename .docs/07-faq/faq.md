# FAQ

> **TL;DR** Quick answers on the queue, the import math, sqlite vs MySQL, the response
> envelope, GraphQL, and how this repo pairs with the `upload-product-vue` frontend.

## Why does the import need a second terminal (`just queue`)?

`QUEUE_CONNECTION=database`: the upload endpoint stores the file and pushes a job onto the
`jobs` table, returning immediately. Nothing processes that table until a worker runs.
Locally that's a deliberate two-step — you can inspect the `jobs` row, then run `just queue`
and watch it import. Tests don't need it (`.env.testing`/`phpunit.xml` set `QUEUE_CONNECTION=sync`).

## What exactly does the Excel import do?

Each row needs a `product_id` and a `status` (`sold` or `buy` — heading row required). Per
chunk of 100 rows it sums a net change per product (`sold` = −1, `buy` = +1), loads the
matching existing products, and bulk-`upsert`s `quantity + net`. Rows with unknown ids or
unknown statuses are not applied — each is recorded as a row-level error (with its
spreadsheet row number) on the run's `Import` record, readable at `GET /api/imports/{id}`;
a product whose result would be exactly 0 is skipped. It never creates products — use
`POST /api/products` (or the seeder) for that. Re-uploading a byte-identical file is a
no-op: the sha256 hash is the idempotency key, so nets are never applied twice.

## Why is my response wrapped in `{code, message, data, errors}`?

The `ApiDataResponse` middleware wraps every JSON response of the `/api/products*` group.
Note the paginator therefore sits at `data.data` in list responses, and the import endpoint
double-nests (`data: {import_id: ..., message: ...}`) because the controller already returns
its own envelope-shaped body.

## Where is the GraphQL endpoint and playground?

`POST /api/graphql` (set in `config/lighthouse.php`). No GraphiQL package is installed —
use curl, Insomnia/Postman, or the Vue frontend. The schema is `graphql/schema.graphql`
plus its `#import`ed files; `schema-directives.graphql` at the root is IDE support only.

## sqlite locally, but `.env.example` says MySQL — which is real?

Both. The committed defaults document the author's original MySQL setup
(`xlsx_import_backend`, dump at `xlsx_import_backend.sql`). The onboarding kit standardizes
local dev on sqlite — zero services to install, and `just bootstrap` wires it automatically.
Only your git-ignored `.env` differs; never edit `.env.example` or the migrations to "fix"
local issues.

## How does this pair with `upload-product-vue`?

That sibling repo is the frontend: it uploads the `.xlsx` and browses products over REST or
GraphQL. Its default backend URL is `http://127.0.0.1:8000`, so either serve this app there
(`$env:PORT=8000; just start`) or change the frontend's base URL. Keep `just queue` running
if you want its uploads to actually import.

## Is there any authentication?

Not on the product routes. Sanctum is installed and guards only `GET /api/user`; nothing
issues tokens. Fine locally — see
[../04-deployment/deployment.md](../04-deployment/deployment.md) before exposing this
anywhere.

## Why is `id` fillable / client-supplied?

Product ids are external catalog numbers (4450, 4768, ...) shared with the Excel sheets —
the import upserts on them, and the seeder inserts specific ids. Treat `id` as a business
key, not an auto-increment detail.

## Related docs

| Doc | Why |
| --- | --- |
| [../01-overview/project-overview.md](../01-overview/project-overview.md) | The full feature list |
| [../06-troubleshooting/common-issues.md](../06-troubleshooting/common-issues.md) | When something errors instead of confusing |
| [../03-development/workflow.md](../03-development/workflow.md) | Day-2 conventions |
