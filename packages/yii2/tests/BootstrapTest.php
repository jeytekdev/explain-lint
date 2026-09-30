<?php

declare(strict_types=1);

namespace Jeytekdev\ExplainLint\Yii2\Tests;

use Jeytekdev\ExplainLint\Yii2\Bootstrap;
use PHPUnit\Framework\TestCase;
use yii\console\Application;
use yii\db\Connection;

/**
 * Deliberately does not define YII_ENV_TEST (a global constant, so it would
 * leak across every other test in the process once set) — capture is
 * exercised here only through the EXPLAIN_LINT_FORCE escape hatch, which is
 * just as representative of the gating logic in Bootstrap::shouldCapture().
 */
final class BootstrapTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('EXPLAIN_LINT_FORCE');
    }

    public function testDoesNotAttachWhenNotForced(): void
    {
        putenv('EXPLAIN_LINT_FORCE');
        $app = $this->consoleApp();

        (new Bootstrap(['connectionIds' => ['db']]))->bootstrap($app);

        self::assertNull($app->get('db')->getBehavior('explainLint'));
    }

    public function testAttachesToConfiguredConnectionsWhenForced(): void
    {
        putenv('EXPLAIN_LINT_FORCE=true');
        $app = $this->consoleApp();

        (new Bootstrap(['connectionIds' => ['db']]))->bootstrap($app);

        self::assertNotNull($app->get('db')->getBehavior('explainLint'));
    }

    public function testIgnoresUnknownConnectionIds(): void
    {
        putenv('EXPLAIN_LINT_FORCE=true');
        $app = $this->consoleApp();

        (new Bootstrap(['connectionIds' => ['db', 'not_configured']]))->bootstrap($app);

        self::assertNotNull($app->get('db')->getBehavior('explainLint'));
    }

    private function consoleApp(): Application
    {
        return new Application([
            'id' => 'explain-lint-yii2-tests',
            'basePath' => __DIR__,
            'components' => [
                'db' => [
                    'class' => Connection::class,
                    'dsn' => 'sqlite::memory:',
                ],
            ],
        ]);
    }
}
