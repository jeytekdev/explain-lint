<?php

declare(strict_types=1);

namespace Jeytekdev\ExplainLint\Doctrine;

use Doctrine\DBAL\Driver\Connection;

/**
 * ExplainRunner needs the real \PDO handle behind a connection to run
 * EXPLAIN on the exact same session (see core README). Doctrine also
 * supports non-PDO drivers (mysqli, pgsql native, sqlite3) that have no
 * \PDO to hand back — for those this bridge captures nothing, silently.
 * Only the pdo_mysql / pdo_pgsql drivers are supported by this bridge.
 *
 * Tries both `getNativeConnection()` (the driver-agnostic accessor added
 * to Doctrine\DBAL\Driver\Connection implementations) and the older
 * PDO-specific `getWrappedConnection()`, defensively, since the exact
 * availability differs across DBAL 3.2–4.x point releases.
 */
final class NativePdoResolver
{
    public static function resolve(Connection $connection): ?\PDO
    {
        if (method_exists($connection, 'getNativeConnection')) {
            $native = $connection->getNativeConnection();
            if ($native instanceof \PDO) {
                return $native;
            }
        }

        if (method_exists($connection, 'getWrappedConnection')) {
            $wrapped = $connection->getWrappedConnection();
            if ($wrapped instanceof \PDO) {
                return $wrapped;
            }
        }

        return null;
    }
}
