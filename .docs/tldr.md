# TL;DR — every doc in 30 seconds

## [01-overview/project-overview.md](01-overview/project-overview.md)

Laravel 12 product-inventory **API** (backend of `vue-inventory-ui`): REST CRUD + search
at `/api/products`, GraphQL at `/api/graphql`, and a bulk Excel import at
`/api/products/import` that queues a job to net `sold`/`buy` rows per product and upsert
stock quantities. Local-only, sqlite, port 8105. No auth on the product routes, no UI
beyond an API landing page at `/`, and the import never creates products — it only adjusts
existing ids. Each run is recorded: row-level errors at `GET /api/imports/{id}`, and a
byte-identical re-upload is ignored (sha256 idempotency key).

## [01-overview/architecture.md](01-overview/architecture.md)

Request flow: routes → `ApiDataResponse` envelope (`{code, message, data, errors}`) →
thin `ProductController` → `ProductRepository` (interface-bound) → Eloquent. The import
detours through a queued job (`database` queue) into chunked `ProductImport` and one bulk
`upsert`. GraphQL is pure Lighthouse directives over the same model, reusing `scopeFilter`.
Three environments: local sqlite, testing sqlite (sync queue), and a documented MySQL
profile (`.env.example` + root SQL dump).

## [02-setup/getting-started.md](02-setup/getting-started.md)

`pwsh ./setup.ps1` once (Git, Node, PHP 8.4 + Composer, uv, just — idempotent), reopen the
shell, `just bootstrap` (deps + sqlite `.env` + migrate + Vite build), `just start` →
http://127.0.0.1:8105. `just fresh` seeds 5 products; the import walkthrough uses
`just queue` plus the sample sheet in `database/seeders/`.

## [03-development/workflow.md](03-development/workflow.md)

Branch off `main`, `just serve` (+ `just queue` for import work), `just test` +
`just lint` before every commit (no CI — this is the whole gate). Conventional Commits as
`mohdakmal875@gmail.com`, no AI-attribution footers. Includes a "where things go" table
mapping change types (new field, new endpoint, new GraphQL query) to the files to touch.

## [04-deployment/deployment.md](04-deployment/deployment.md)

Honest: there is **no deployment** — no CI/CD, no server. The page records the minimum
checklist if that changes: auth on product routes first, then a supervised queue worker,
real web server + MySQL, shared upload storage, CORS tightening, and a build pipeline.

## [05-reference/commands.md](05-reference/commands.md)

Every `just` recipe with what it actually runs: `bootstrap`, `start`/`serve`/`stop`,
`queue`, `migrate`/`fresh`, `test`, `lint`/`lint-fix`, `claudex/o/h` — plus the PORT
override for pairing with the Vue frontend and a few raw artisan calls worth knowing.

## [05-reference/project-layout.md](05-reference/project-layout.md)

Annotated tree: the repository/DTO layer under `app/`, the import pipeline
(`Imports/` + `Jobs/`), the split GraphQL schema in `graphql/`, seeders + the sample
`.xlsx`, and the onboarding kit files. Plus the one-domain naming convention
(`Product*` everywhere) and the immutable-migrations rule.

## [06-troubleshooting/common-issues.md](06-troubleshooting/common-issues.md)

Real symptoms hit during kit verification: GraphQL 404 (`/api/graphql`, not `/graphql`),
PowerShell curl JSON quoting, imports "doing nothing" without `just queue`, the missing
`database/testing.sqlite` test failure (+ first-run migration hiccup), lingering uploads in
`storage/app/private/products` on Windows, MySQL leftovers in `.env`,
and port conflicts.

## [07-faq/faq.md](07-faq/faq.md)

Why the queue exists, exactly what the import math does, why responses are enveloped (and
`data.data` in lists), where GraphQL lives, sqlite vs the documented MySQL profile, pairing
with `vue-inventory-ui`, the no-auth status, and why `id` is a client-supplied business
key.
