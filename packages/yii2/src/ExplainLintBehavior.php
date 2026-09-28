<?php

declare(strict_types=1);

namespace ExplainLint\Yii2;

use ExplainLint\Pdo\ExplainLintPdo;
use yii\base\Behavior;
use yii\base\Event;
use yii\db\Connection;

/**
 * Attached to a `yii\db\Connection`, this replaces `$connection->pdo` with an
 * `ExplainLintPdo` wrapper around the exact same, already-connected `\PDO`
 * instance once Yii2 finishes opening it (`EVENT_AFTER_OPEN` fires after
 * `Connection::open()` has set `pdo` and applied its own attributes/emulate
 * settings, so nothing about the connection's config is disturbed).
 *
 * `Connection::$pdo` is untyped in Yii2 core specifically so driver-specific
 * PDO subclasses (e.g. `yii\db\sqlite\PDO`) can be swapped in; every call
 * site reaches it through `Command`/`Schema`, which use it duck-typed
 * (`->prepare()`, `->quote()`, ...) rather than an `instanceof \PDO` check —
 * this is what lets a composition wrapper stand in for it.
 */
final class ExplainLintBehavior extends Behavior
{
    public string $connectionName = 'default';

    public function events(): array
    {
        return [Connection::EVENT_AFTER_OPEN => 'wrapPdo'];
    }

    public function wrapPdo(Event $event): void
    {
        $connection = $this->owner;
        if (!$connection instanceof Connection || !$connection->pdo instanceof \PDO) {
            return;
        }

        $connection->pdo = new ExplainLintPdo($connection->pdo, connectionName: $this->connectionName);
    }
}
