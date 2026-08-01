# Development workflow

> **TL;DR** Branch off `main`, code with `just serve` (+ `just queue` when touching the
> import), verify with `just test` and `just lint`, commit Conventional-Commits style as
> `mohdakmal875@gmail.com`, PR into `main`. No CI — the local suite is the whole gate.

## Daily loop

```powershell
git checkout -b feat/my-change     # never work directly on main
just serve                         # foreground server with request logs (or: just start)
just queue                         # second terminal — only needed for import work
# ...edit...
just test                          # PHPUnit (ProductTest, ProductGraphqlTest, ...)
just lint                          # Pint style check (just lint-fix to auto-fix)
```

`just` with no arguments lists every recipe. Prefer recipes over hand-typed artisan/composer
invocations — they pin the right PHP (`%LOCALAPPDATA%\Programs\php-8.4`) regardless of your
shell's PATH.

## Where things go

| Change | Touch |
| --- | --- |
| New product field | migration (new file — never edit committed ones) + `Product::$fillable` + `ProductData` + `graphql/product.graphql` type & `ProductColumn` enum |
| New REST endpoint | `routes/api.php` (inside the `ApiDataResponse` group) + controller method + repository method (+ FormRequest/DTO for writes) |
| New GraphQL query/field | `graphql/*.graphql` — prefer directives (`@paginate`, `@scope`, `@whereConditions`) over custom resolvers |
| Import rule change | `app/Imports/ProductImport.php` (math) or `app/Jobs/ImportProductsFromExcelJob.php` (lifecycle) — keep chunking and the batch upsert |
| Search behavior | `Product::scopeFilter` — shared by REST **and** GraphQL (`@scope(name: "filter")`) |

## Testing

- `just test` runs PHPUnit against `phpunit.xml` + `.env.testing`: sqlite at
  `database/testing.sqlite`, `QUEUE_CONNECTION=sync` (jobs run inline), array cache/session.
  It never touches your dev `database/database.sqlite`.
- The house patterns live in `tests/Feature/ProductTest.php` (REST + `Queue::fake()` /
  `Excel::fake()` for the import) and `ProductGraphqlTest.php` — extend those, don't
  hand-roll new styles.
- Narrow a run with `just test --filter=ProductGraphqlTest`.

## Git & PR conventions

- Author email **repo-local**: `mohdakmal875@gmail.com` (already configured; check with
  `git config user.email`).
- Conventional Commits: `feat(products): ...`, `fix(import): ...`, `docs: ...`. No
  Co-Authored-By / "Generated with" footers, ever.
- Use the project skills: `/commit` for staging+message flow, `/pre-pr-review` for a
  self-audit against the Laravel/API/import checklist, `/create-pr` to open the GitHub PR.

## Data hygiene

- `just fresh` drops and re-seeds — 5 products, ids 4450–6039. Irreversible locally; never
  run it to "fix" something without asking.
- The sample import sheet `database/seeders/product_status_list.xlsx` matches those seeded
  ids — after `just fresh` it's immediately usable for import testing.
- Never commit `.env`, `database/*.sqlite*`, or anything under `storage/`.

## Related docs

| Doc | Why |
| --- | --- |
| [../05-reference/commands.md](../05-reference/commands.md) | Full recipe reference |
| [../01-overview/architecture.md](../01-overview/architecture.md) | The layers your change flows through |
| [../07-faq/faq.md](../07-faq/faq.md) | Quick answers (ports, queue, MySQL vs sqlite) |
