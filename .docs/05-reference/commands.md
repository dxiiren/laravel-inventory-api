# Commands

> **TL;DR** Everything goes through `just`. `just bootstrap` once, `just start`/`just stop`
> daily, `just queue` when testing imports, `just test`/`just lint` before every commit.
> Run `just` alone to list recipes.

## Setup

| Command | What it does |
| --- | --- |
| `pwsh ./setup.ps1` | One-time machine setup: Git, Node LTS, PHP 8.4 + Composer, uv/Python, just, gh, Claude CLI, `.mcp.json` seed. Idempotent. |
| `just bootstrap` | One-time app setup: `.env` (sqlite-switched), empty sqlite db, `composer install`, `npm install`, `npm run build`, key, migrate. |

## App lifecycle

| Command | What it does |
| --- | --- |
| `just start` | Serve on http://127.0.0.1:8105 in a background window (runs `stop` first — no doubled servers). |
| `just serve` | Serve in the foreground — request log visible, Ctrl+C to stop. |
| `just queue` | Foreground queue worker (`queue:work --tries=1`) — required for Excel imports to actually process. |
| `just stop` | Kill only THIS repo's `php.exe` processes (matched by repo path in the command line). |

Override the port per-invocation: `$env:PORT=8000; just start` (8000 = the
`upload-product-vue` frontend's default backend URL).

## Database

| Command | What it does |
| --- | --- |
| `just migrate` | Run pending migrations against the local sqlite db. |
| `just fresh` | `migrate:fresh --seed` — DROP everything, re-migrate, seed 5 sample products. Irreversible locally. |

## Quality

| Command | What it does |
| --- | --- |
| `just test` | PHPUnit via `php artisan test` (test env: `phpunit.xml` + `.env.testing`, sync queue, own sqlite file). |
| `just test --filter=X` | Narrow to one class/method. |
| `just lint` | Laravel Pint in check mode (`--test`). |
| `just lint-fix` | Pint auto-fix. |

## Tools

| Command | What it does |
| --- | --- |
| `just claudex` / `claudeo` / `claudeh` | Launch Claude Code (Sonnet / Opus / Haiku) with all permissions. |

## Useful raw calls (when a recipe doesn't cover it)

```powershell
$php = "$env:LOCALAPPDATA\Programs\php-8.4\php.exe"
& $php artisan route:list                 # every route incl. /graphql
& $php artisan queue:failed               # inspect failed import jobs
& $php artisan tinker                     # poke at Product::count() etc.
```

## Related docs

| Doc | Why |
| --- | --- |
| [project-layout.md](project-layout.md) | Where the files behind these commands live |
| [../03-development/workflow.md](../03-development/workflow.md) | When to run what |
| [../06-troubleshooting/common-issues.md](../06-troubleshooting/common-issues.md) | When a command fails |
