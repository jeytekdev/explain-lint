# explain-lint

Re-runs `EXPLAIN` against every SQL query your test suite executes, and fails the build when a query does a full table scan, loses an index, or needs a filesort/temporary table.

## Why

N+1 detectors ([`beyondcode/laravel-query-detector`](https://github.com/beyondcode/laravel-query-detector) and friends) catch too many *similar* queries running in one request. They say nothing about a single query that's structurally bad: a full table scan on a large table, or an index quietly dropped by a migration, executes exactly once per test — it sails straight through an N+1 check and shows up later as a production slowdown under real load.

**explain-lint complements N+1 detectors, it doesn't replace them.** They catch *volume* — the same query shape running too many times. This catches *structural regressions in a single query* — the one query that got slow and nobody noticed because it only ran once.

## How it works

1. A capture adapter (framework-agnostic PDO wrapper, Laravel `DB::listen()` bridge, Doctrine DBAL middleware, or Yii2 connection behavior) records every SQL statement your test executes, on the exact connection it ran on.
2. After the test finishes, explain-lint re-runs a plain `EXPLAIN` for each distinct query **on that same connection/session** — this is what lets it see temp tables, uncommitted data inside a test transaction, and session-level optimizer settings that a fresh connection would never see. `EXPLAIN` never executes the query itself, so this is safe even for `INSERT`/`UPDATE`/`DELETE`.
3. The plan is parsed into structural findings: full table scan, no index available, optimizer rejected an available index, filesort, temporary table, high row estimate.
4. A rule engine filters out likely false positives — tiny tables, small tables without a selective predicate, allowlisted tables/queries — and assigns a severity.
5. In `strict` mode, any Error-severity violation fails the build.

## Packages

This is a monorepo; install only the package for your stack — nothing pulls in dependencies you don't need (the Laravel package doesn't require `doctrine/dbal`, and vice versa).

| Package | Packagist | For |
|---|---|---|
| [`jeytekdev/explain-lint`](packages/core) | [core](https://packagist.org/packages/jeytekdev/explain-lint) | Framework-agnostic PDO wrapper + PHPUnit/Pest integration. Required by both bridges. |
| [`jeytekdev/explain-lint-laravel`](packages/laravel) | [bridge](https://packagist.org/packages/jeytekdev/explain-lint-laravel) | Laravel, via `DB::listen()`, auto-discovered service provider. |
| [`jeytekdev/explain-lint-doctrine`](packages/doctrine) | [bridge](https://packagist.org/packages/jeytekdev/explain-lint-doctrine) | Symfony/Doctrine DBAL, via `Driver\Middleware`. |
| [`jeytekdev/explain-lint-yii2`](packages/yii2) | [bridge](https://packagist.org/packages/jeytekdev/explain-lint-yii2) | Yii2, via a `Connection` behavior, auto-discovered bootstrap. |

## Install — pick your stack

**Laravel:**

```bash
composer require --dev jeytekdev/explain-lint-laravel
vendor/bin/explain-lint explain-lint:install
```

→ [full Laravel guide](packages/laravel/README.md)

**Symfony / Doctrine DBAL:**

```bash
composer require --dev jeytekdev/explain-lint-doctrine
```

→ [full Doctrine guide](packages/doctrine/README.md)

**Yii2:**

```bash
composer require --dev jeytekdev/explain-lint-yii2
vendor/bin/explain-lint explain-lint:install
```

→ [full Yii2 guide](packages/yii2/README.md)

**Bare PDO, no framework:**

```bash
composer require --dev jeytekdev/explain-lint
```

→ [full core guide](packages/core/README.md)

All of these land on the same two minutes: install, run `explain-lint:install` (writes `explain-lint.php` and registers the PHPUnit extension), run your tests.

## Example

```php
// A migration drops an index that a hot query relies on...
Schema::table('orders', function (Blueprint $table) {
    $table->dropIndex(['status']);
});
```

```
explain-lint found 1 issue(s) in 1 test(s):

OrdersTest::test_pending_orders
  [error] Full table scan on orders
      table:       orders
      rows:        48213
      query:       select * from orders where status = ?
      hint:        Add an index covering the query's WHERE/JOIN/ORDER BY columns, or check
                   why an existing index isn't used (leading wildcard LIKE, a function/cast
                   on the column, implicit type mismatch).
      fingerprint: 4f6a1c3e9d2b7a805e4f1c9b6d3a2e7f8c0b1a5d
```

In `strict` mode, this fails `vendor/bin/phpunit`. In `warn` mode, it's reported but the build stays green — useful for rolling this out on a legacy codebase one rule at a time.

## Repository layout

```
explain-lint/
  packages/
    core/       composer.json → jeytekdev/explain-lint
    laravel/    composer.json → jeytekdev/explain-lint-laravel
    doctrine/   composer.json → jeytekdev/explain-lint-doctrine
    yii2/       composer.json → jeytekdev/explain-lint-yii2
  composer.json  # root: path-repositories of packages/* for local dev
```

This is a monorepo for development — atomic PRs across all four packages at once (e.g. add a field to the shared `Violation` DTO and fix every bridge in the same commit) — but each package is split out to its own read-only GitHub repo and Packagist entry on tag (see `.github/workflows/split.yml`), so `composer require jeytekdev/explain-lint-laravel` never pulls in `doctrine/dbal`.

### Local development

```bash
composer install                 # installs all four packages via path repositories
composer test                    # unit tests for all four packages
composer test:core               # just packages/core
docker compose up -d              # MySQL + PostgreSQL for the integration test-suite
vendor/bin/phpunit -c packages/core/phpunit.xml.dist --testsuite integration
```

## Roadmap / not in this release

See [CONTRIBUTING.md](CONTRIBUTING.md) for good-first-issue-sized scope:

- SQLite adapter (currently a no-op that always passes).
- `EXPLAIN ANALYZE` mode with real row counts.
- Historical analytics across runs / HTML report.

## License

MIT — see [LICENSE](LICENSE).
