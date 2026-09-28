<?php

declare(strict_types=1);

namespace ExplainLint\Yii2;

use yii\base\Application;
use yii\base\BootstrapInterface;
use yii\db\Connection;

/**
 * Auto-discovered via composer.json `extra.bootstrap`, exactly like every
 * other Yii2 extension — `yii\base\Application::bootstrap()` instantiates
 * and runs this for every request/command unless capture is gated (see
 * `shouldCapture()`), the same reasoning as the Laravel bridge's service
 * provider.
 *
 * Only attaches to `db` by default. Add extra connection component IDs (and,
 * if needed, override which ones) via app config instead of relying on
 * auto-discovery:
 *
 *   'bootstrap' => [
 *       ['class' => \ExplainLint\Yii2\Bootstrap::class, 'connectionIds' => ['db', 'db_reporting']],
 *   ],
 */
final class Bootstrap implements BootstrapInterface
{
    /** @var list<string> */
    public array $connectionIds = ['db'];

    public function bootstrap($app): void
    {
        if (!$app instanceof Application || !$this->shouldCapture()) {
            return;
        }

        foreach ($this->connectionIds as $id) {
            if (!$app->has($id)) {
                continue;
            }

            $connection = $app->get($id);
            if (!$connection instanceof Connection) {
                continue;
            }

            $connection->attachBehavior('explainLint', new ExplainLintBehavior(['connectionName' => $id]));
        }
    }

    /**
     * Only captures under YII_ENV_TEST (the constant every Yii2 app
     * boilerplate — basic/advanced templates, Codeception's yii2 module —
     * defines from YII_ENV) or when explicitly forced, so a normal
     * dev/prod request never pays the wrapping cost.
     */
    private function shouldCapture(): bool
    {
        return (\defined('YII_ENV_TEST') && \YII_ENV_TEST)
            || filter_var(getenv('EXPLAIN_LINT_FORCE') ?: false, FILTER_VALIDATE_BOOLEAN);
    }
}
