<?php

declare(strict_types=1);

namespace Jeytekdev\ExplainLint\Yii2\Tests;

use Jeytekdev\ExplainLint\Recorder\QueryRecorder;
use Jeytekdev\ExplainLint\Yii2\ExplainLintBehavior;
use PHPUnit\Framework\TestCase;
use yii\db\Connection;

/**
 * Structural test that the behavior reaches QueryRecorder through the real
 * Command -> PDOStatement flow. Uses pdo_sqlite purely because it needs no
 * external database (mirrors packages/doctrine's MiddlewareCaptureTest). It
 * does not exercise EXPLAIN analysis (SQLite uses SqliteNoopAdapter); that's
 * covered by the MySQL/PostgreSQL integration tests in packages/core.
 */
final class ExplainLintBehaviorTest extends TestCase
{
    protected function setUp(): void
    {
        QueryRecorder::instance()->clear();
    }

    public function testCommandExecuteIsCaptured(): void
    {
        $db = $this->connect();

        $db->createCommand('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)')->execute();
        $db->createCommand('INSERT INTO users (name) VALUES (:name)', [':name' => 'Ada'])->execute();

        $captured = QueryRecorder::instance()->all();
        $inserts = array_values(array_filter($captured, static fn ($q) => str_starts_with($q->sql, 'INSERT')));

        self::assertCount(1, $inserts);
        self::assertSame(['Ada'], array_column($inserts[0]->params, 'value'));
        self::assertInstanceOf(\PDO::class, $inserts[0]->connection);
    }

    public function testQueryAllIsCaptured(): void
    {
        $db = $this->connect();
        $db->createCommand('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)')->execute();
        $db->createCommand('INSERT INTO users (name) VALUES (:name)', [':name' => 'Ada'])->execute();

        $db->createCommand('SELECT * FROM users WHERE id = :id', [':id' => 1])->queryAll();

        $captured = QueryRecorder::instance()->all();
        $selects = array_filter($captured, static fn ($q) => str_starts_with($q->sql, 'SELECT'));

        self::assertNotEmpty($selects);
    }

    private function connect(): Connection
    {
        $db = new Connection(['dsn' => 'sqlite::memory:']);
        $db->attachBehavior('explainLint', new ExplainLintBehavior(['connectionName' => 'default']));
        $db->open();

        return $db;
    }
}
