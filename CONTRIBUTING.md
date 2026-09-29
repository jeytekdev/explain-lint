# Contributing to explain-lint

Thanks for looking at this. A few things that make review faster, then the good-first-issue list.

## Repository layout

This is a monorepo. Each package under `packages/*` has its own `composer.json`, `src/`, `tests/` and `README.md`. The root `composer.json` wires them together via `path` repositories so `composer install` at the repo root gives you all five packages linked to each other's working copy — no `composer require` round-trip needed while developing across package boundaries.

```bash
composer install
composer test              # unit tests, all five packages
docker compose up -d       # MySQL + PostgreSQL, for the core integration suite
vendor/bin/phpunit -c packages/core/phpunit.xml.dist --testsuite integration
```

## Where things live

- `packages/core` — everything engine/rule/reporting related, framework-agnostic. Most PRs that add a new rule or reason code touch only this package.
- `packages/laravel`, `packages/doctrine`, `packages/yii2` — thin capture adapters. They should stay thin: SQL parsing, rule evaluation and reporting all belong in core.
- `packages/codeception` — not a capture adapter, an *analysis-trigger* adapter: Codeception never bootstraps PHPUnit's native `<extensions>` mechanism, so `ExplainLint\PHPUnit\ExplainLintExtension` never runs under `codecept run`. This package wires the same `TestAnalysisRunner`/reporters to Codeception's own `Extension`/event dispatcher instead. Required alongside any capture adapter whenever the consuming project runs tests via `codecept run`.
- If you're changing a shared DTO (`CapturedQuery`, `Violation`, `Verdict`, ...), update every bridge in the same PR — that's the whole point of the monorepo.

## Adding a new EXPLAIN rule

1. Add a case to `ExplainLint\ReasonCode` (core) with a `defaultSeverity()`.
2. Emit `PlanFinding` for it from `MySqlAdapter`/`PostgresAdapter::analyze()`.
3. Add unit tests against fixture EXPLAIN output (`packages/core/tests/Unit/Adapter`) — no real database needed, `analyze()` is a pure function of the parsed plan.
4. If the rule needs table-size tiering, add the reason code to `RuleEngine::SCAN_REASON_CODES`.
5. Document it in `packages/core/stubs/explain-lint.php.stub` and the core README's configuration section.

## Testing a change against a real downstream app

Useful while iterating on a package here, before it's tagged/published — point a real Laravel/Symfony/Yii2 app's `composer.json` at your local checkout instead of Packagist. Composer's `path` repository type needs no VCS at all — it just links (symlinks by default on Linux/macOS) to a directory on disk:

```json
{
    "repositories": [
        { "type": "path", "url": "/absolute/path/to/explain-lint/packages/core" },
        { "type": "path", "url": "/absolute/path/to/explain-lint/packages/laravel" }
    ]
}
```

```bash
composer require --dev jeytekdev/explain-lint-laravel:@dev
```

Swap `packages/laravel` / `explain-lint-laravel` for `doctrine`/`yii2` as needed — `packages/core` is always required alongside whichever bridge you're testing.

**Running inside Docker:** the path above must exist *inside the container*, not just on the host — bind-mount it:

```yaml
services:
  app:
    volumes:
      - /absolute/path/to/explain-lint:/opt/explain-lint:ro
```

and point the `path` repository `url` at `/opt/explain-lint/packages/...` instead.

## Good first issues (not in v1)

### SQLite adapter

`packages/core/src/Adapter/SqliteNoopAdapter.php` currently always passes. SQLite's `EXPLAIN QUERY PLAN` output has a different enough shape (no `possible_keys`/`key` columns, different filesort/temp-table signaling) that it deserves its own rule mapping rather than a half-fit reuse of `MySqlAdapter`. Look at `detail` column values like `SCAN TABLE x` vs `SEARCH TABLE x USING INDEX`, and `USE TEMP B-TREE FOR ORDER BY`.

### Yii2 console command wrapper

`packages/yii2` (`jeytekdev/explain-lint-yii2`) exists now — a `yii\db\Connection` behavior on `EVENT_AFTER_OPEN` feeds `QueryRecorder`, same shape as `packages/laravel`. Still missing: a `yii\console\Controller` wrapper around `explain-lint:install`/`explain-lint:check` for projects that prefer running everything through `./yii` instead of `vendor/bin/explain-lint` (which already works standalone in a Yii2 app today — this is convenience sugar, not a functional gap).

### `EXPLAIN ANALYZE` mode

`ExplainLint\Engine\ExplainMode` has a single `Plan` case today by design — `Analyze` executes the query for real, which isn't safe to do unconditionally against every captured statement (including writes). This needs an explicit opt-in story (e.g. read-only queries only, or a config allowlist) before it can be added safely.

### Historical analytics / HTML report

Everything currently reports on a single run. Nothing exists yet for e.g. "this row-estimate has grown 5x over the last month" — would need a place to persist `Verdict` snapshots across runs (`report.junit`-style artifact, most likely) and a small aggregator.

## Pull requests

- Keep bridge packages (`laravel`, `doctrine`, `yii2`, `codeception`) free of SQL parsing/rule logic — if you find yourself writing analysis code there, it probably belongs in `core`.
- Unit tests for `packages/core` should not require a real database — feed adapters fixture EXPLAIN output directly. Real MySQL/PostgreSQL behavior belongs in `packages/core/tests/Integration`.
- One logical change per PR, but feel free to touch all four packages in it if the change is a DTO/interface shared across them.
