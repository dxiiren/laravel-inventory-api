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

1. **Auth** — done for writes: `auth:sanctum` now guards `POST/PUT/PATCH/DELETE
   /api/products*`, `POST /api/products/import` and `GET /api/imports/{id}`
   (`ApiAuthorizationTest` pins it). Still open before exposing this anywhere: `GET
   /api/products` and `POST /api/graphql` are deliberately public, and the GraphQL `users`
   query publishes every user's name and email to anonymous callers — restrict or drop it.
   Nothing in the app issues tokens yet either; create them with
   `$user->createToken(...)`.
2. **Queue supervision** — the import depends on a worker. `php artisan queue:work` under a
   supervisor (or a `sync`-free horizon setup), not a terminal window.
3. **Real web server + real DB** — `artisan serve` is a dev tool; front with nginx/Apache +
   php-fpm, move `DB_CONNECTION` to MySQL (the dump at `xlsx_import_backend.sql` matches the
   expected schema), and keep `upsert` semantics in mind (needs MySQL 5.7+/8).
4. **Storage** — uploaded sheets land on the local disk (`storage/app/private/products`).
   Multiple app instances need a shared disk (S3 config scaffolding is already in
   `.env.example`).
5. **CORS** — the companion `vue-inventory-ui` frontend calls this API cross-origin;
   verify `config/cors.php` (framework default allows `api/*` from `*` — tighten it).
6. **Build pipeline** — `npm run build` at deploy time; `composer install --no-dev
   --optimize-autoloader`; `php artisan config:cache route:cache`.

## Related docs

| Doc | Why |
| --- | --- |
| [../01-overview/architecture.md](../01-overview/architecture.md) | Trust boundaries and environment table |
| [../02-setup/getting-started.md](../02-setup/getting-started.md) | The local "deployment" that does exist |
| [../06-troubleshooting/common-issues.md](../06-troubleshooting/common-issues.md) | Local serving failure modes |
