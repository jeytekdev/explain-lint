# jeytekdev/explain-lint-doctrine

Doctrine DBAL bridge for [jeytekdev/explain-lint](../core/README.md) — re-runs `EXPLAIN` against every query your test suite executes, and fails the build on full table scans, lost indexes, filesort and temporary tables.

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

Then wire up the PHPUnit extension (see [core README](../core/README.md#install)):

```bash
vendor/bin/explain-lint explain-lint:install
```

## Known limitation: PDO-only drivers

explain-lint needs the real `\PDO` handle behind a connection to re-run `EXPLAIN` on the exact same session. This bridge supports the `pdo_mysql` and `pdo_pgsql` drivers only — native drivers (`mysqli`, `pgsql`, `sqlite3`) have no `\PDO` to hand back, so queries on those simply aren't captured (no error, nothing to analyze).

## License

MIT
