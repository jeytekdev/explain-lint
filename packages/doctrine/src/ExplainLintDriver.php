<?php

declare(strict_types=1);

namespace ExplainLint\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

final class ExplainLintDriver extends AbstractDriverMiddleware
{
    public function __construct(Driver $driver, private readonly string $connectionName)
    {
        parent::__construct($driver);
    }

    public function connect(array $params): Connection
    {
        return new ExplainLintConnection(parent::connect($params), $this->connectionName);
    }
}
