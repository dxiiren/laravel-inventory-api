# Getting started

> **TL;DR** `pwsh ./setup.ps1` once (installs Git, Node, PHP 8.4 + Composer, uv, just),
> reopen PowerShell, `just bootstrap`, `just start` → http://127.0.0.1:8105. Seed data with
> `just fresh`. Imports additionally need `just queue` in a second terminal.

## Prerequisites

A stock Windows 10/11 machine with PowerShell and winget. Everything else is installed by
the setup script.

## Step 1 — Machine setup (once per PC)

```powershell
pwsh ./setup.ps1
```

Idempotent — safe to re-run any time; installed tools are skipped with `[OK]`. It installs:

| Tool | Detail |
| --- | --- |
| Git, Node.js LTS, GitHub CLI | via winget |
| PHP 8.4 | zip from php.net into `%LOCALAPPDATA%\Programs\php-8.4` + a `php.ini` with the Laravel/Excel extensions (gd, zip, mbstring, intl, sqlite3, ...) |
| Composer | `composer.phar` + `composer.bat` next to php.exe |
| uv + Python | runs the Claude statusline/skill scripts |
| just | the task runner every day-2 command goes through |
| Claude Code CLI | optional, for AI-assisted development |
| `.mcp.json` | seeded from the committed `.mcp.json.stub` (git-ignored; fill the GitHub PAT by hand) |

**Close and reopen PowerShell afterwards** so the PATH changes land.

## Step 2 — App bootstrap (once per clone)

```powershell
just bootstrap
```

This: creates `.env` from `.env.example` **switched to sqlite** (the committed example
defaults to MySQL), creates an empty `database/database.sqlite`, runs `composer install`,
`npm install`, `npm run build` (Vite — kept for parity; no page depends on the built
assets anymore, the landing page is self-contained), generates the app key, and migrates.

## Step 3 — Run

```powershell
just start        # background serve on http://127.0.0.1:8105
just fresh        # optional: seed 5 sample products (ids 4450–6039)
```

Verify:

```powershell
curl.exe http://127.0.0.1:8105/api/products
```

You should get the `{code, message, data, errors}` envelope with a paginated product list.

## Step 4 — Try the Excel import

```powershell
# Terminal 1: keep serving          # Terminal 2: the queue worker
just start                          just queue

# Terminal 3: upload the sample sheet
curl.exe -F "file=@database/seeders/product_status_list.xlsx" http://127.0.0.1:8105/api/products/import
```

The upload returns immediately ("Uploading is in process..."); the worker terminal shows
the job run, and product quantities change on the next `GET /api/products`.

## Optional — MySQL instead of sqlite

The committed `.env.example` and the root `xlsx_import_backend.sql` dump target a local
MySQL `xlsx_import_backend` database. If you want parity with that setup, point your `.env`
back at MySQL and import the dump — but sqlite is the supported local default; don't edit
`.env.example` or the migrations.

## Related docs

| Doc | Why |
| --- | --- |
| [../03-development/workflow.md](../03-development/workflow.md) | The day-2 development loop |
| [../05-reference/commands.md](../05-reference/commands.md) | Every `just` recipe |
| [../06-troubleshooting/common-issues.md](../06-troubleshooting/common-issues.md) | When a step above fails |
