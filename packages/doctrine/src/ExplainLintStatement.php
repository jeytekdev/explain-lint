<?php

declare(strict_types=1);

namespace ExplainLint\Doctrine;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use ExplainLint\Recorder\CapturedQuery;
use ExplainLint\Recorder\QueryRecorder;

/**
 * `bindValue()`'s third parameter's type changed between DBAL 3.x (plain
 * int constants on ParameterType) and 4.x (ParameterType became a real
 * backed enum) — a genuine, incompatible type-hint change between the two
 * supported DBAL major versions. `mixed` is used for every parameter here
 * instead of a concrete type: PHP's contravariance rules allow an override
 * to widen a parameter's type, and `mixed` is a supertype of both the 3.x
 * and 4.x declarations, so this one signature stays valid against either
 * installed DBAL version.
 */
final class ExplainLintStatement extends AbstractStatementMiddleware
{
    /** @var array<int|string, mixed> */
    private array $boundParams = [];

    public function __construct(
        Statement $statement,
        private readonly string $sql,
        private readonly ?\PDO $pdo,
        private readonly string $connectionName,
        private readonly QueryRecorder $recorder,
    ) {
        parent::__construct($statement);
    }

    public function bindValue(mixed $param, mixed $value, mixed $type = ParameterType::STRING): bool
    {
        $this->boundParams[$param] = $value;

        return parent::bindValue($param, $value, $type);
    }

    /**
     * `$params` must stay untyped: DBAL 3's `AbstractStatementMiddleware`
     * declares `execute($params = null)` with no type hint, and PHP's
     * contravariance rules forbid narrowing it to `?array` here. Declaring
     * it as an extra optional parameter (DBAL 4's `execute()` takes none)
     * is legal — passing an extra argument to a zero-parameter PHP method
     * is always allowed, it is simply ignored by the callee — so
     * `parent::execute($params)` is safe to call unconditionally on either
     * version.
     */
    public function execute($params = null): Result
    {
        $result = parent::execute($params);

        if ($this->pdo !== null) {
            $this->recorder->record(new CapturedQuery(
                $this->sql,
                $params ?? $this->boundParams,
                $this->pdo,
                $this->connectionName,
                $this->recorder->currentPhase(),
            ));
        }

        return $result;
    }
}
