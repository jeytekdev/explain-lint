<?php

declare(strict_types=1);

namespace ExplainLint\Laravel\Tests\Feature;

use ExplainLint\Laravel\Tests\TestCase;
use ExplainLint\Laravel\Testing\ExplainLintTesting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

/**
 * End-to-end: a migration that drops an index on a large table must be
 * caught by explain-lint in strict mode. Requires a real MySQL test
 * database (see docker-compose.yml at the repo root); skips itself
 * otherwise.
 */
final class IndexRegressionTest extends TestCase
{
    use ExplainLintTesting;

    private const CONFIG = __DIR__ . '/../Fixtures/explain-lint-strict.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoMysql();

        Schema::dropIfExists('el_posts');
        Schema::create('el_posts', function ($table) {
            $table->id();
            $table->string('status', 20);
        });

        $rows = [];
        for ($i = 0; $i < 5000; $i++) {
            $rows[] = ['status' => $i % 2 === 0 ? 'published' : 'draft'];
            if (count($rows) === 500) {
                DB::table('el_posts')->insert($rows);
                $rows = [];
            }
        }
        DB::statement('ANALYZE TABLE el_posts');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('el_posts');
        parent::tearDown();
    }

    #[Test]
    public function query_using_the_index_passes(): void
    {
        Schema::table('el_posts', function ($table) {
            $table->index('status');
        });
        DB::statement('ANALYZE TABLE el_posts');

        DB::table('el_posts')->where('status', 'published')->get();

        $this->assertNoQueryRegressions(self::CONFIG);
    }

    #[Test]
    public function query_after_the_index_is_dropped_by_a_migration_fails(): void
    {
        // Simulates a migration that (accidentally) drops the index.
        DB::table('el_posts')->where('status', 'published')->get();

        $this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);

        $this->assertNoQueryRegressions(self::CONFIG);
    }
}
