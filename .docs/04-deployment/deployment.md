# Deployment

> **TL;DR** There is no deployment. No CI/CD, no server, no container — the app runs locally
> via `just start` on http://127.0.0.1:8105. This page records what WOULD need to change if
> that ever stops being true.

## Current state

| Question | Answer |
| --- | --- |
| CI/CD | None — no workflow files; `just test` + `just lint` locally are the whole gate |
| Hosting | None — `php artisan serve` (dev server) behind `just start` |
| Database | Local SQLite file (`.env.example` documents a MySQL profile: `xlsx_import_backend` + root SQL dump) |
| Queue worker | Manual foreground `just queue` — nothing supervises it |
| Secrets | Local `.env` only (git-ignored); committed `.env.testing` holds a throwaway test key |

## If you ever deploy this for real

Honest minimum checklist, in order of pain:

1. **Auth** — `/api/products*` write routes (store/update/destroy/import) are completely
   unauthenticated. Sanctum is installed and `auth:sanctum` already guards `GET /api/user`;
   extend it to the product group before anything else.
2. **Queue supervision** — the import depends on a worker. `php artisan queue:work` under a
   supervisor (or a `sync`-free horizon setup), not a terminal window.
3. **Real web server + real DB** — `artisan serve` is a dev tool; front with nginx/Apache +
   php-fpm, move `DB_CONNECTION` to MySQL (the dump at `xlsx_import_backend.sql` matches the
   expected schema), and keep `upsert` semantics in mind (needs MySQL 5.7+/8).
4. **Storage** — uploaded sheets land on the local disk (`storage/app/private/products`).
   Multiple app instances need a shared disk (S3 config scaffolding is already in
   `.env.example`).
5. **CORS** — the companion `upload-product-vue` frontend calls this API cross-origin;
   verify `config/cors.php` (framework default allows `api/*` from `*` — tighten it).
6. **Build pipeline** — `npm run build` at deploy time; `composer install --no-dev
   --optimize-autoloader`; `php artisan config:cache route:cache`.

## Related docs

| Doc | Why |
| --- | --- |
| [../01-overview/architecture.md](../01-overview/architecture.md) | Trust boundaries and environment table |
| [../02-setup/getting-started.md](../02-setup/getting-started.md) | The local "deployment" that does exist |
| [../06-troubleshooting/common-issues.md](../06-troubleshooting/common-issues.md) | Local serving failure modes |
