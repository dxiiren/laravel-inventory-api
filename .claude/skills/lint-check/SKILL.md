---
name: lint-check
description: Use when the developer says 'lint check', 'run lint', 'check lint', 'run the quality suite', or 'lint everything' — runs the full quality suite for this repo (Laravel Pint style check + the PHPUnit test suite) and reports pass/fail per layer, with the Pint auto-fix path.
model: sonnet
---

# lint-check — Full quality suite (Pint · PHPUnit)

Run the two quality layers this repo has and report pass/fail per layer. There is
no CI — this suite is the whole quality gate, run it before every commit/PR.

## Trigger

When the developer says any of: "lint check", "run lint", "check lint",
"run the quality suite", "lint everything".

---

## What to Do

Run each layer and record its result. Run them independently so one failure doesn't
hide the others.

### 1 — Pint (code style)

```powershell
just lint          # php vendor\bin\pint --test  (read-only)
```

Pass = exit 0, "PASS" summary, no style issues. If it lists offending files,
**auto-fix** and re-check:

```powershell
just lint-fix      # php vendor\bin\pint  (writes fixes)
just lint          # confirm green
```

### 2 — Test suite (PHPUnit via artisan)

```powershell
just test          # php artisan test --parallel --processes=6
```

Pass = all tests green, exit 0. Filter a single test with
`just test --filter=SomethingTest`. Tests run against `phpunit.xml` (sqlite at
`:memory:`, sync queue, array cache/session), so they touch neither the dev
`database/database.sqlite` nor `database/testing.sqlite`.

`.env.testing` does name `database/testing.sqlite`, but it never takes effect
here: phpunit's `<env>` entries are set before Laravel's immutable Dotenv load,
so `:memory:` wins. Verified — `testing.sqlite`'s mtime and size are unchanged
by a full run. This matters, because `:memory:` is what makes the parallel run
safe: each process gets a private database instead of sharing one file.

`just test` runs in parallel, so its output interleaves and the summary line
reads `OK (64 tests, 374 assertions)` rather than `Tests: 64 passed`. When a
failure needs reading rather than counting, use `just test-serial`.

---

## Reporting back

Report a per-layer table, then an overall verdict:

```
LAYER   TOOL             STATUS
style   pint --test      PASS | FAIL (N files)  [auto-fixed → re-checked green]
test    artisan test -p 6  PASS | FAIL (N failures)
OVERALL: PASS | FAIL
```

- **style** is the only layer safe to auto-fix mechanically (`just lint-fix`).
  After auto-fixing, always re-run `just lint` and report the green result.
- **test** — never weaken an assertion to force green; fix the root cause in the
  source. If a test fails on a missing app key or database, the test env in
  `phpunit.xml` is the ground truth — don't point tests at the dev sqlite db.

---

## Notes

- Run from the **repo root** — `just` recipes resolve PHP by absolute path
  (`%LOCALAPPDATA%\Programs\php-8.4\php.exe`), so they work even in stale shells.
- Pint needs `vendor/` — if it's missing, run `just bootstrap` first.
- There are no JS/CSS lint layers: this is an API-first app — the only view is the
  stock Laravel welcome page, and `resources/js` is the stock Laravel bootstrap.
- Don't add ESLint/Prettier/typecheck layers here — this is a PHP API app.
