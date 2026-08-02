# CLAUDE.md — laravel-inventory-api

> Human-facing developer docs live in [`.docs/`](./.docs/README.md) — start at
> [`.docs/tldr.md`](./.docs/tldr.md). Keep them in sync when changing behavior they document.

## Project: Laravel Inventory API

A Laravel 12 product-inventory API (no UI beyond a self-contained API landing page at `/`,
screenshot in `docs/images/api-landing.png`): CRUD and search
over a `products` table via REST **and** GraphQL (Lighthouse), plus a bulk Excel import —
POST an `.xlsx` of `product_id`/`status` rows (`sold` = −1, `buy` = +1) and a queued job
nets the changes per product and upserts the new stock quantities. Companion frontend:
the sibling `vue-inventory-ui` repo.

- **Repo:** GitHub — `github.com/dxiiren/laravel-inventory-api`
- **Runs locally only** — no CI/CD, no deployment target. `just start` serves on
  `http://127.0.0.1:8105`.

### Tech Stack Quick Reference

| Layer | Technology | Key details |
| --- | --- | --- |
| Framework | **Laravel 12** (PHP ^8.2, local PHP 8.4) | API routes in `routes/api.php` under the `ApiDataResponse` envelope middleware; `routes/web.php` only serves the API landing page |
| REST API | Resource controller + repository pattern | `ProductController` → `ProductRepositoryInterface` (bound in `AppServiceProvider`) → `ProductRepository`; DTOs via **spatie/laravel-data** (`ProductData`) |
| GraphQL | **nuwave/lighthouse 6** at `POST /api/graphql` | Schema split across `graphql/*.graphql` (`#import`); `products`/`users` queries with `@paginate`, `@whereConditions`, `@orderBy`, `@scope(name: "filter")` |
| Excel import | **maatwebsite/excel 3** (queued) | `POST /api/products/import` (`mimes:xlsx`, `max:5120` KB) stores the file, creates an `Import` record (sha256 hash = idempotency key; duplicates are acknowledged, not re-applied), dispatches `ImportProductsFromExcelJob`; `ProductImport` reads chunks of 100, nets `sold`/`buy` per `product_id`, `upsert`s quantities — unknown ids are skipped and recorded as row-level errors, reported at `GET /api/imports/{id}` |
| Queue | `database` connection (`jobs` table) | Import only runs once a worker picks it up — `just queue` (foreground) |
| ORM | Eloquent | `Product` (`$fillable` incl. `id`; `scopeFilter` searches id/type/brand/model/capacity), `User` (Sanctum) |
| Database | **SQLite** locally (`database/database.sqlite`, git-ignored) | `just bootstrap` writes the local `.env` with `DB_CONNECTION=sqlite`; committed `.env.example` stays MySQL (`xlsx_import_backend`, dump at root `xlsx_import_backend.sql`) |
| Validation | FormRequest + data DTO | `ImportProductRequest` (`file` required, `mimes:xlsx`, `max:5120`), `ProductData` (typed constructor promotion) |
| API envelope | `ApiDataResponse` middleware | Wraps every JSON response as `{code, message, data, errors}` |
| Assets | Vite 6 + Tailwind CSS 4 (npm) | Stock scaffolding only — the landing page is fully self-contained (inline CSS, no `@vite`); `just bootstrap` still builds once |
| Tests | PHPUnit 11 via `php artisan test` | `ProductTest` (REST + import dispatch), `ProductGraphqlTest` (incl. REST/GraphQL search parity), `ProductImportTest` (row-level error report + idempotency, real generated xlsx); test env = `phpunit.xml` + `.env.testing` (sqlite `database/testing.sqlite`, sync queue) |
| Style | Laravel Pint | `just lint` / `just lint-fix` |
| Task runner | `just` | wraps php/composer/npm (`justfile`); PHP pinned to `%LOCALAPPDATA%\Programs\php-8.4` |

### Project Structure

```
laravel-inventory-api/
  app/
    Contracts/              # ProductRepositoryInterface
    Data/                   # ProductData (spatie/laravel-data DTO)
    Enums/                  # ProductStatusEnum (sold | buy), ImportStatusEnum (pending → completed/failed)
    Http/Controllers/       # ProductController (index/store/update/destroy/import), ImportController (show)
    Http/Middleware/        # ApiDataResponse ({code, message, data, errors} envelope)
    Http/Requests/          # ImportProductRequest (xlsx, <=5MB)
    Imports/                # ProductImport (chunked, nets sold/buy, upserts quantity, records row errors)
    Jobs/                   # ImportProductsFromExcelJob (queued; updates Import record, deletes file)
    Models/                 # Product (scopeFilter), Import (file_hash, status, row_errors), User
    Providers/              # AppServiceProvider (repository binding)
    Repositories/           # ProductRepository
  bootstrap/, config/       # stock Laravel 12 config (cache/session/queue on database)
  database/
    migrations/             # users/cache/jobs + personal_access_tokens + products + imports
    factories/, seeders/    # ProductFactory, ProductSeeder (5 iPhones) + sample xlsx
  graphql/                  # schema.graphql (+ product/user via #import)
  resources/                # welcome view (API landing page, inline CSS) + Vite inputs (app.css, app.js)
  routes/                   # api.php (products CRUD + import + GET imports/{id}), web.php (landing page)
  tests/                    # ProductTest, ProductGraphqlTest, ProductImportTest + stock examples
  xlsx_import_backend.sql   # MySQL dump matching .env.example defaults (optional)
  justfile, setup.ps1       # dev recipes + one-time machine setup
  .docs/                    # numbered documentation set
  .claude/                  # skills, hooks, settings
```

## Git Commits

- **Conventional Commits** (`feat:`, `fix:`, `chore:`, `docs:` ...).
- **NEVER** add `Co-Authored-By` lines or "Generated with Claude Code" / session-link footers to
  **any** outward artifact — commit messages, PR descriptions, or issue comments.
- Commit author email for this repo is `mohdakmal875@gmail.com` (set repo-locally).
- Only stage and commit files relevant to the change. **Never auto-commit** after a fix — the
  developer says "commit" first.

## Local Development

- One-time machine setup: `pwsh ./setup.ps1` (idempotent — installs Git, Node.js, PHP 8.4 +
  Composer, uv/Python, just, the Claude Code CLI). Then `just bootstrap` (composer + npm +
  `.env` + sqlite + migrate + asset build), then `just start`.
- All day-2 commands are `just` recipes — run `just` to list them. Never invent an alternative
  command for something a recipe already covers.
- `just stop` kills only THIS repo's server processes (matched by repo path on the command
  line) — safe to run while other projects are serving.
- The database starts **empty** — `just fresh` seeds 5 products (ids 4450–6039). The sample
  import file is `database/seeders/product_status_list.xlsx`.
- `QUEUE_CONNECTION=database`: `POST /api/products/import` only *queues* the import. Nothing
  changes in `products` until a worker runs — `just queue` in a second terminal. Without it
  the job sits in the `jobs` table forever.
- The import **only updates quantities of existing product ids** — rows whose `product_id`
  isn't already in `products` are skipped and recorded as row-level errors on the `Import`
  record (`GET /api/imports/{id}`), and a product whose net quantity would land on exactly
  0 is left unchanged (see `ProductImport::buildUpsertData`). Re-uploading a byte-identical
  file is a no-op: the sha256 `file_hash` is the idempotency key (only `failed` runs retry).
- The local `.env` is sqlite; the committed `.env.example` is MySQL. Never "fix" `.env.example`,
  `config/database.php`, or committed migrations.
- The companion `vue-inventory-ui` frontend expects this backend on port **8000** by
  default — pair them with `$env:PORT=8000; just start` (or point the frontend at 8105).

## Project Skills

Development skills live in `.claude/skills/` — check `.claude/skills/README.md` for the catalog
and **follow the relevant skill before writing code**. Notables: `/commit`, `/create-pr`,
`/pre-pr-review`, `/lint-check`, `/claude-transfer`, `/llm-transfer`, `/define-goal`,
`/setup-mcp`, `/test-all-mcp`, `/audit-skills`.

## MCP Servers

Wired via the committed-stub + git-ignored-secret pattern: `.mcp.json.stub` (committed,
placeholders) → `.mcp.json` (git-ignored, real — seeded by `setup.ps1`). Turnkey: `context7`
(library docs — call `resolve-library-id` then `query-docs` instead of recalling APIs),
`playwright` (drive a real browser). Per-dev: `github` (fill the PAT in `.mcp.json`).
Health check: `/test-all-mcp`. Fall back to native tools silently if a server is unavailable.

## Memory

Lightweight, single-developer, file-based project memory at `.claude/memory/`:

- **`MEMORY.md`** is the index (one line per memory: `- [Title](file.md) — hook`), loaded each
  session.
- Each memory is **one fact in its own `*.md` file** with frontmatter (`name`, `description`,
  `metadata.type` = `reference` | `feedback` | `project`). Read the fact file on demand when its
  index hook is relevant.
- After writing a fact file, add its one-line pointer to `MEMORY.md`. Update rather than
  duplicate; delete a memory that turns out wrong. Don't store what the repo already records.
