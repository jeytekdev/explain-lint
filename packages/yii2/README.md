# jeytekdev/explain-lint-yii2

Yii2 bridge for [jeytekdev/explain-lint](../core/README.md) — re-runs `EXPLAIN` against every query your test suite executes, and fails the build on full table scans, lost indexes, filesort and temporary tables.

Implemented as a `yii\base\Behavior` attached to `yii\db\Connection`, not a query-log parser — it replaces `$connection->pdo` with a thin wrapper around the exact same, already-open `\PDO` handle right after Yii2 finishes opening it (`Connection::EVENT_AFTER_OPEN`), so nothing about your connection's own setup (attributes, `emulatePrepare`, charset) is disturbed, and EXPLAIN always runs on that same connection/session.

## Install (2 minutes)

```bash
composer require --dev jeytekdev/explain-lint-yii2
```

The bootstrap class auto-discovers via `extra.bootstrap` (the standard Yii2 extension mechanism) and only attaches to the `db` component while `YII_ENV_TEST` is true (or `EXPLAIN_LINT_FORCE=true` is set) — nothing wraps your connection outside a test run.

`YII_ENV_TEST` is the constant every Yii2 app already defines from `YII_ENV` in its entry script (`web/index-test.php`, `tests/bootstrap.php`, the `yii2-app-basic`/`yii2-app-advanced` templates' `Yii.php`, and Codeception's `yii2` module all set it):

```php
defined('YII_ENV') or define('YII_ENV', 'test');
defined('YII_ENV_TEST') or define('YII_ENV_TEST', YII_ENV === 'test');
```

If your test connection uses a different component ID than `db`, or you have more than one connection to capture, list them explicitly in your test app config instead of relying on auto-discovery:

```php
'bootstrap' => [
    ['class' => \ExplainLint\Yii2\Bootstrap::class, 'connectionIds' => ['db', 'db_reporting']],
],
```

Then wire up the PHPUnit extension (see [core README](../core/README.md#install)):

```bash
vendor/bin/explain-lint explain-lint:install
```

Run this from your **project root** (where `composer.json`/`vendor/` live). Set the connection driver to match your database in the generated `explain-lint.php`:

```php
'connections' => [
    'default' => [
        'driver' => 'mysql', // or 'pgsql'
    ],
],
```

Then run your suite as usual (plain PHPUnit, or Codeception's `Unit`/`Db` suites, which run on the same PHPUnit event bus) — no other code changes needed.

## Reading the report

```
explain-lint found 1 issue(s) in 1 test(s):

OrdersTest::testPendingOrders
  [error] Full table scan on orders
      table:       orders
      rows:        48213
      query:       select * from orders where status = ?
      hint:        Add an index covering the query's WHERE/JOIN/ORDER BY columns, or check
                   why an existing index isn't used (leading wildcard LIKE, a function/cast
                   on the column, implicit type mismatch).
      fingerprint: 4f6a1c3e9d2b7a805e4f1c9b6d3a2e7f8c0b1a5d
```

- In `mode => 'warn'` this is informational only — the build stays green.
- In `mode => 'strict'`, any `[error]`-severity violation fails the run.

## Allowlisting a known-OK query

Two ways, both in `explain-lint.php`, both require a non-empty reason:

```php
// Every violation on this table, regardless of query:
'allowlist' => [
    'audit_log' => 'Intentional full scan for the nightly export job — JIRA-123',
],

// One specific query, by the fingerprint shown in the report above:
'allowlist_fingerprints' => [
    '4f6a1c3e9d2b7a805e4f1c9b6d3a2e7f8c0b1a5d' => 'Known slow report query — JIRA-456',
],
```

## Known limitation

Only connections whose `pdoClass` resolves to a real `\PDO` (or a `\PDO` subclass) are captured — this covers every stock Yii2 driver (`mysql`, `pgsql`, `sqlite`). A connection swapped out for something else entirely (e.g. a non-PDO custom `Connection` subclass) simply isn't wrapped; nothing errors, there's just nothing to analyze.

## Local development against an unreleased version (Composer path repository)

```json
{
    "repositories": [
        { "type": "path", "url": "/absolute/path/to/explain-lint/packages/core" },
        { "type": "path", "url": "/absolute/path/to/explain-lint/packages/yii2" }
    ]
}
```

```bash
composer require --dev jeytekdev/explain-lint-yii2:@dev
```

## License

MIT
