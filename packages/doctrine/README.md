# jeytekdev/explain-lint-doctrine

Doctrine DBAL bridge for [jeytekdev/explain-lint](https://github.com/jeytekdev/explain-lint/blob/master/packages/core/README.md) — re-runs `EXPLAIN` against every query your test suite executes, and fails the build on full table scans, lost indexes, filesort and temporary tables.

Implemented as a `Doctrine\DBAL\Driver\Middleware`, not the old `SQLLogger` — `SQLLogger` is deprecated and has been removed entirely in DBAL 4.x. Compatible with `doctrine/dbal: ^3.2 || ^4.0`.

## Install (2 minutes)

```bash
composer require --dev jeytekdev/explain-lint-doctrine
```

Register the middleware wherever you build your `Doctrine\DBAL\Configuration` (in a plain DBAL app, or via Symfony's `doctrine.yaml`):

```php
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use ExplainLint\Doctrine\ExplainLintMiddleware;

$configuration = new Configuration();
$configuration->setMiddlewares([new ExplainLintMiddleware('default')]);

$connection = DriverManager::getConnection($connectionParams, $configuration);
```

Symfony (`config/packages/doctrine.yaml`):

```yaml
doctrine:
    dbal:
        connections:
            default:
                middlewares:
                    - ExplainLint\Doctrine\ExplainLintMiddleware
```

Then wire up the PHPUnit extension (see [core README](https://github.com/jeytekdev/explain-lint/blob/master/packages/core/README.md#install)):

```bash
vendor/bin/explain-lint explain-lint:install
```

## Running under Codeception

This package only handles capture (the DBAL `Middleware`) — the report step
is core's PHPUnit `<extensions>` mechanism, registered via `phpunit.xml`.

**If your suite runs via `vendor/bin/codecept run` instead of
`vendor/bin/phpunit`/`pest`, that mechanism never fires** — Codeception 5
doesn't bootstrap PHPUnit's native extension system. The middleware will
still capture every query, but nothing will ever be analyzed or printed:
no error, no warning, just a report that never appears.

Install [`jeytekdev/explain-lint-codeception`](https://github.com/jeytekdev/explain-lint/blob/master/packages/codeception/README.md) too,
and register it in `codeception.yml` instead of `phpunit.xml`. Use
`explain-lint:install --config-only` (not the plain form) to generate
`explain-lint.php` without also wiring `phpunit.xml`, since Codeception never
reads that file.

## Known limitation: PDO-only drivers

explain-lint needs the real `\PDO` handle behind a connection to re-run `EXPLAIN` on the exact same session. This bridge supports the `pdo_mysql` and `pdo_pgsql` drivers only — native drivers (`mysqli`, `pgsql`, `sqlite3`) have no `\PDO` to hand back, so queries on those simply aren't captured (no error, nothing to analyze).

## License

MIT
