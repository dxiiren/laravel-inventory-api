---
name: pre-pr-review
description: Use when the developer says 'pre-pr review', 'review my branch', 'audit my work', or 'self review' — self-reviews the current branch's diff against a Laravel / Eloquent / API / Excel-import / security checklist before opening a PR, then saves a report to .claude/workspace/reports/pr/.
model: opus
---

# Pre-PR Review (Self-Audit)

Self-review your feature-branch diff **before** opening a PR. This is an API-first
Laravel 12 app — REST + GraphQL (Lighthouse) endpoints over a `products` table, with a
queued Excel import; the only view is the stock welcome page. The goal is to catch query,
validation, import-correctness, and security problems early, not to nitpick style Pint
already handles.

## Trigger

- `"pre-pr review"` / `"self review"`
- `"review my branch"` / `"review my work"` / `"review my code"`
- `"audit my work"` / `"audit my branch"`

## Do NOT flag (owned elsewhere)

- **Formatting / code style** — Laravel Pint owns it (`just lint`). Run it; don't hand-review it.
- **Pre-existing patterns** the developer copied from the codebase — not this branch's problem.

## Step 1 — Branch & base

```bash
git branch --show-current
```

If on `main`: **STOP** — "You're on `main`; switch to your feature branch first."

```bash
git fetch origin main
git diff origin/main...HEAD --name-only
```

If no files changed: **STOP** — "No changes vs `main`."

Scope the review to reviewable source: `app/**/*.php`, `routes/*.php`,
`database/migrations/`, `database/factories/`, `database/seeders/`,
`graphql/*.graphql`, `schema-directives.graphql`, `config/*.php`. **Exclude**
`composer.lock`, `package-lock.json`, and `.claude/`. If only excluded files changed:
**STOP** — "No reviewable source changed."

Report: "Branch `{name}` changed {N} source files ({php} .php, {graphql} .graphql). Running review."

## Step 2 — Fetch the diff

```bash
git diff origin/main...HEAD -- 'app' 'routes' 'database' 'graphql' 'config'
```

For context-dependent checks (import math, scope correctness, route binding), read
the **full file**, not just the hunk. If the diff exceeds ~4000 lines, prioritise the
highest-change files and note "focused review on largest files".

## Step 3 — Run the checklist

Verify each finding against the actual code before reporting it (grep how existing code does
the same thing; don't invent a rule the codebase doesn't follow).

| #   | Check                       | Label      | What to look for                                                                                                                                                                                                                                                  |
| --- | --------------------------- | ---------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | **Validation**              | issue      | Controller reading `$request->input()` and persisting without a FormRequest or `ProductData` DTO (`ImportProductRequest` + spatie/laravel-data are the house patterns); missing `rules()` for a new field; `authorize()` returning `true` where a real policy check belongs. |
| 2   | **Mass assignment**         | issue      | New model attributes not in `$fillable`; `create($request->all())` instead of `create($request->validated())` / `$data->toArray()`.                                                                                                                                 |
| 3   | **Query efficiency (N+1)**  | issue      | A query inside a per-row loop (the import's house pattern is batch: one `select` for existing ids + one `upsert`); unbounded `->get()` on a list endpoint that should paginate; `whereIn` on user input where `whereIntegerInRaw` fits.                              |
| 4   | **Import correctness**      | issue      | Changes to `ProductImport` math: net change per `product_id` (`sold` = −1, `buy` = +1) must aggregate before the upsert; rows with unknown ids are skipped, not created; chunk size stays bounded (`WithChunkReading`); the queued job still deletes the uploaded file on success. |
| 5   | **Response envelope**       | issue      | New API routes outside the `ApiDataResponse` middleware group (breaking the `{code, message, data, errors}` envelope); an error path returning a raw 500 body where a handled 422/404 belongs.                                                                       |
| 6   | **Routing & binding**       | issue      | A new route bypassing route-model binding to run manual `find()`; missing `only([...])` leaving unintended resource actions exposed; a GraphQL field added to `graphql/*.graphql` without the matching column/scope on the model.                                    |
| 7   | **Upload / abuse limits**   | issue      | New public write/upload endpoints with no size or mime limit (the house pattern: `ImportProductRequest` — `mimes:xlsx`, `max:5120`); a queued job with unbounded retries on poison input (`--tries` matters).                                                        |
| 8   | **Migrations**              | issue      | Editing an already-committed migration instead of adding a new one; missing `down()`; a foreign key without `constrained()` / an explicit cascade decision.                                                                                                        |
| 9   | **Secrets / config**        | issue      | Hardcoded credentials/API keys; reading `env()` outside `config/`; committing `.env` or `database/database.sqlite`.                                                                                                                                                |
| 10  | **Tests**                   | issue      | New/changed behavior with no feature test (`ProductTest` / `ProductGraphqlTest` are the house patterns — `Queue::fake`/`Excel::fake` for import paths); a changed assertion watered down to pass.                                                                    |
| 11  | **No debug leftovers**      | issue      | `dd()` / `dump()` / `ray()` / `Log::debug` spam / commented-out dead blocks / `TODO` without a follow-up.                                                                                                                                                          |
| 12  | **Repository / DTO design** | suggestion | Query logic inline in a controller that belongs in `ProductRepository` (controllers stay thin — the house pattern binds `ProductRepositoryInterface` in `AppServiceProvider`); filter logic that belongs in the model's `scopeFilter`.                               |
| 13  | **GraphQL schema design**   | suggestion | Duplicating REST logic instead of reusing model scopes via `@scope`; a new type not split into its own `graphql/*.graphql` file with `#import`; missing entry in the matching `*Column` enum for a sortable/filterable field.                                        |
| 14  | **Naming / conventions**    | nitpick    | Non-RESTful controller method names; a route name that breaks the `products.*` convention; migration filename not matching its table.                                                                                                                              |

## Step 4 — Run the quality suite

```powershell
just lint
just test
```

Both must be green. A failure is an **issue** (blocking) — paste the failing output line.

## Step 5 — Finding labels & caps

- **issue** (blocking) — fix before opening the PR.
- **suggestion** (non-blocking) — recommended.
- **nitpick** (non-blocking) — minor/optional.

Every finding must carry: the label, the `file:line`, and **WHY** it matters (not just what).
Issues: uncapped. Suggestions + nitpicks: cap at 15 total; note "{X} more non-blocking findings
omitted" if over.

## Step 6 — Present

```
## Pre-PR Review: {branch}
Branch: {branch} -> main   |   Files: {N} ({php} .php, {graphql} .graphql)
Quality suite: {pint pass/fail} · {test pass/fail}

### Issues (fix before PR)
1. [path:line] Finding — why it matters

### Suggestions
2. [path:line] Finding

### Nitpicks
3. [path:line] Finding

---
{Total} findings: {issues} issues, {suggestions} suggestions, {nitpicks} nitpicks
```

Zero findings → "No issues found — branch looks clean. Ready to open the PR."

## Step 7 — Save the report

Path: `.claude/workspace/reports/pr/{branch}-{YYYY-MM-DD}.md` (replace `/` in the branch name
with `-`; overwrite on a same-day re-run; create the folder if missing). Frontmatter then the
same body as the terminal output:

```yaml
---
branch: { branch }
base: main
date: { YYYY-MM-DD }
files_changed: { N }
issues: { count }
suggestions: { count }
nitpicks: { count }
---
```

Confirm: "Report saved to `{path}`".

## Tone

Self-improvement, not a verdict from a lead. "Consider extracting…", not "You must fix…". Never
directive, never judgmental.
