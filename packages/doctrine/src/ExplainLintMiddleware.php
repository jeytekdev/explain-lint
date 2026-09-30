<?php

declare(strict_types=1);

namespace Jeytekdev\ExplainLint\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * Register in your DBAL configuration:
 *
 *   $configuration->setMiddlewares([new ExplainLintMiddleware('default')]);
 *
 * Deliberately implemented as a Driver\Middleware rather than the older
 * SQLLogger — SQLLogger is deprecated and removed entirely in DBAL 4.x.
 */
final class ExplainLintMiddleware implements Middleware
{
    public function __construct(private readonly string $connectionName = 'default')
    {
    }

    public function wrap(Driver $driver): Driver
    {
        return new ExplainLintDriver($driver, $this->connectionName);
    }
}
