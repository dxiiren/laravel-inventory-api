# Common issues

> **TL;DR** Every symptom below was actually hit while verifying this kit. Check here before
> debugging from scratch: GraphQL lives at `/api/graphql` (not `/graphql`), imports need
> `just queue`, `just test` needs `database/testing.sqlite`, and uploaded sheets can linger
> in storage on Windows.

## POST /graphql returns 404

The Lighthouse endpoint is **`/api/graphql`**, not the default `/graphql` —
`config/lighthouse.php` sets `route.uri = 'api/graphql'`. Confirm with:

```powershell
& "$env:LOCALAPPDATA\Programs\php-8.4\php.exe" artisan route:list
```

## GraphQL returns 400 "expects JSON object or array" from curl on PowerShell

PowerShell mangles inline JSON quoting for `curl.exe`. Put the body in a file:

```powershell
'{"query":"{ products(first: 3) { data { id quantity } } }"}' | Set-Content q.json
curl.exe -s -X POST http://127.0.0.1:8105/api/graphql -H "Content-Type: application/json" --data "@q.json"
```

## POST /api/products/import returns 200 but quantities never change

Working as designed: the endpoint only **queues** `ImportProductsFromExcelJob`
(`QUEUE_CONNECTION=database`). Run a worker — `just queue` — and watch the job process.
Also remember the import only adjusts **existing** product ids (seed first: `just fresh`)
and skips a product whose net quantity would land exactly on 0. Check
`GET /api/imports/{id}` (the `import_id` is in the upload response) — unknown ids and
malformed rows are listed there per spreadsheet row. And if you re-uploaded the **same
file**, that's the sha256 idempotency guard: duplicates are acknowledged but never
re-applied.

## `just test` fails with `SQLiteDatabaseDoesNotExistException ... database/testing.sqlite`

The committed `.env.testing` points the test suite at `database/testing.sqlite`, which is
git-ignored and doesn't exist on a fresh clone. `just bootstrap` now creates it; if you
bootstrapped before that fix, create it once:

```powershell
New-Item database\testing.sqlite -ItemType File
```

The **first** suite run after creating it may still fail with `no such table: products` —
run `just test` again; `RefreshDatabase` migrates the file on that run and the suite goes
green (21 tests).

## Uploaded .xlsx files pile up in `storage/app/private/products`

`ImportProductsFromExcelJob` calls `Storage::delete` after importing, but on Windows the
delete can fail silently (the serving process still holds a handle from the sheet-count
check that runs at dispatch time). Harmless — the folder is git-ignored — delete old files
manually if they bother you.

## `just start` succeeds but every request 500s with "Unable to locate file in Vite manifest"

The welcome view calls `@vite` and the build output is missing. Run `npm run build` (or the
full `just bootstrap`, which includes it), then reload.

## MySQL errors (`could not find driver`, connection refused) on artisan commands

Your `.env` still carries the committed `.env.example` MySQL defaults. `just bootstrap`
creates `.env` with `DB_CONNECTION=sqlite` and the `DB_*` lines commented out — delete your
`.env` and re-run `just bootstrap`, or make those two edits by hand. Never edit
`.env.example` itself.

## Port 8105 already in use

A previous serve is lingering. `just stop` kills only this repo's `php.exe` (it matches the
repo path in the process command line), then `just start` again. `just start` also runs
`stop` first, so simply re-running it usually suffices.

## Related docs

| Doc | Why |
| --- | --- |
| [../02-setup/getting-started.md](../02-setup/getting-started.md) | The happy path these issues deviate from |
| [../07-faq/faq.md](../07-faq/faq.md) | Conceptual questions (why queue, why sqlite) |
| [../05-reference/commands.md](../05-reference/commands.md) | What each recipe actually runs |
