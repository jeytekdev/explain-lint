<?php

declare(strict_types=1);

namespace ExplainLint\Doctrine;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use ExplainLint\Recorder\CapturedQuery;
use ExplainLint\Recorder\QueryRecorder;

final class ExplainLintConnection extends AbstractConnectionMiddleware
{
    private readonly QueryRecorder $recorder;
    private readonly ?\PDO $pdo;

    public function __construct(Connection $connection, private readonly string $connectionName)
    {
        parent::__construct($connection);
        $this->recorder = QueryRecorder::instance();
        $this->pdo = NativePdoResolver::resolve($connection);
    }

    public function prepare(string $sql): Statement
    {
        return new ExplainLintStatement(parent::prepare($sql), $sql, $this->pdo, $this->connectionName, $this->recorder);
    }

    public function query(string $sql): Result
    {
        $result = parent::query($sql);
        $this->record($sql, []);

        return $result;
    }

    public function exec(string $sql): int
    {
        $result = parent::exec($sql);
        $this->record($sql, []);

        return $result;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    private function record(string $sql, array $params): void
    {
        if ($this->pdo === null) {
            return;
        }

        $this->recorder->record(new CapturedQuery(
            $sql,
            $params,
            $this->pdo,
            $this->connectionName,
            $this->recorder->currentPhase(),
        ));
    }
}
