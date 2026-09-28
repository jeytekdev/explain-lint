<?php

declare(strict_types=1);

namespace ExplainLint\Doctrine\Tests;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use ExplainLint\Doctrine\ExplainLintMiddleware;
use ExplainLint\Recorder\QueryRecorder;
use PHPUnit\Framework\TestCase;

/**
 * Structural test that the Driver\Middleware chain (Driver -> Connection ->
 * Statement) actually reaches QueryRecorder. Uses pdo_sqlite purely because
 * it needs no external database — pdo_sqlite is itself a PDO-backed DBAL
 * driver, so NativePdoResolver finds a real \PDO here the same way it would
 * for pdo_mysql/pdo_pgsql. It does not exercise EXPLAIN analysis (SQLite
 * uses SqliteNoopAdapter); that's covered by the MySQL/PostgreSQL
 * integration tests in packages/core.
 */
final class MiddlewareCaptureTest extends TestCase
{
    protected function setUp(): void
    {
        QueryRecorder::instance()->clear();
    }

    public function testExecuteStatementWithBoundParamsIsCaptured(): void
    {
        $connection = $this->connect();

        $connection->executeStatement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $connection->executeStatement('INSERT INTO users (name) VALUES (?)', ['Ada']);

        $captured = QueryRecorder::instance()->all();
        $inserts = array_values(array_filter($captured, static fn ($q) => str_starts_with($q->sql, 'INSERT')));

        self::assertCount(1, $inserts);
        self::assertSame(['Ada'], array_values($inserts[0]->params));
        self::assertInstanceOf(\PDO::class, $inserts[0]->connection);
    }

    public function testExecuteQueryIsCaptured(): void
    {
        $connection = $this->connect();
        $connection->executeStatement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $connection->executeStatement('INSERT INTO users (name) VALUES (?)', ['Ada']);

        $connection->executeQuery('SELECT * FROM users WHERE id = ?', [1])->fetchAllAssociative();

        $captured = QueryRecorder::instance()->all();
        $selects = array_filter($captured, static fn ($q) => str_starts_with($q->sql, 'SELECT'));

        self::assertNotEmpty($selects);
    }

    private function connect(): \Doctrine\DBAL\Connection
    {
        $configuration = new Configuration();
        $configuration->setMiddlewares([new ExplainLintMiddleware('default')]);

        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
    }
}
