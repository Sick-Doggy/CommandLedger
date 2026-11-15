<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Fake;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

final class TestConnectionFactory
{
    private static ?Connection $connection = null;

    public static function create(): Connection
    {
        return self::$connection ??= DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
    }

    public static function reset(): void
    {
        self::$connection = null;
    }
}
