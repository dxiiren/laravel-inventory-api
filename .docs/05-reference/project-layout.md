# Project layout

> **TL;DR** Standard Laravel 12 skeleton plus four non-stock ideas: a repository/DTO layer
> under `app/`, an Excel import pipeline (`Imports/` + `Jobs/`), a directive-driven GraphQL
> schema in `graphql/`, and the onboarding kit (`justfile`, `setup.ps1`, `.docs/`, `.claude/`).

## Tree

```
upload-product-laravel-excel/
  app/
    Contracts/
      ProductRepositoryInterface.php   # the seam ProductController codes against
    Data/
      ProductData.php                  # spatie/laravel-data DTO (typed request/response shape)
    Enums/
      ProductStatusEnum.php            # sold | buy — the import's row vocabulary
      ImportStatusEnum.php             # pending | processing | completed | failed
    Http/
      Controllers/ProductController.php  # index/store/update/destroy/import — thin, delegates
      Controllers/ImportController.php   # show — the import report (GET /api/imports/{id})
      Middleware/ApiDataResponse.php     # {code, message, data, errors} envelope
      Requests/ImportProductRequest.php  # file: required | mimes:xlsx | max:5120 KB
    Imports/
      ProductImport.php                # chunked (100), nets sold/buy per id, bulk upsert;
                                       # records row-level errors (spreadsheet row numbers)
    Jobs/
      ImportProductsFromExcelJob.php   # queued; sheet-count guard; updates Import status +
                                       # row_errors; deletes file when done
    Models/
      Product.php                      # $fillable incl. id; scopeFilter (search 5 columns)
      Import.php                       # file_name, file_hash (sha256), status, row_errors
      User.php                         # Sanctum scaffolding (unused by product routes)
    Providers/AppServiceProvider.php   # binds ProductRepositoryInterface → ProductRepository
    Repositories/ProductRepository.php # queries, CRUD, import dispatch + idempotency guard
  bootstrap/, config/                  # stock Laravel 12 (cache/session/queue = database)
  database/
    migrations/                        # users/cache/jobs, personal_access_tokens, products, imports
    factories/                         # ProductFactory, UserFactory
    seeders/                           # ProductSeeder (5 iPhones) + product_status_list.xlsx
    database.sqlite                    # local dev db (created by bootstrap; git-ignored)
  graphql/
    schema.graphql                     # Query type; #imports the two below
    product.graphql                    # Product type + ProductColumn enum + filter input
    user.graphql                       # User type + UserColumn enum
  public/                              # index.php + built assets (public/build, git-ignored)
  resources/                           # welcome.blade.php + Vite inputs (css/js)
  routes/
    api.php                            # /api/products CRUD + import, /api/imports/{id} (ApiDataResponse group)
    web.php                            # GET / → welcome
  storage/                             # runtime files; uploads land in app/private/products
  tests/
    Feature/ProductTest.php            # REST + import dispatch (Queue::fake, Excel::fake)
    Feature/ProductGraphqlTest.php     # GraphQL queries + REST/GraphQL search parity
    Feature/ProductImportTest.php      # row-level error report + idempotent re-import (real xlsx)
  schema-directives.graphql            # Lighthouse directive stubs for the IDE (not runtime)
  xlsx_import_backend.sql              # MySQL dump matching .env.example (optional profile)
  .env.example                         # committed defaults (MySQL) — never edit for local fixes
  .env.testing                         # committed test env (sqlite testing.sqlite, sync queue)
  justfile                             # day-2 recipes (just --list)
  setup.ps1                            # one-time machine setup (idempotent)
  CLAUDE.md                            # AI-assistant ground rules + stack quick reference
  .docs/                               # this documentation set
  .claude/                             # skills, statusline hook, settings, memory
  .mcp.json.stub                       # committed MCP placeholders → git-ignored .mcp.json
```

## Naming conventions

- One domain = one set: `Product` model, `ProductData`, `ProductRepository`,
  `ProductController`, `ProductImport`, `ProductFactory`, `ProductSeeder`, `ProductTest`.
- GraphQL: one file per type in `graphql/`, joined by `#import` in `schema.graphql`; each
  sortable/filterable type carries a `{Type}Column` enum.
- Migrations are timestamped and immutable once committed — schema changes are new files.

## Related docs

| Doc | Why |
| --- | --- |
| [commands.md](commands.md) | The recipes that operate on this tree |
| [../01-overview/architecture.md](../01-overview/architecture.md) | How the pieces call each other |
| [../02-setup/getting-started.md](../02-setup/getting-started.md) | Bringing the tree to life |
