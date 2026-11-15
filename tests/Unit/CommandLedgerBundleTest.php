<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Unit;

use ArcaCodicum\CommandLedger\CommandLedgerBundle;
use PHPUnit\Framework\TestCase;

final class CommandLedgerBundleTest extends TestCase
{
    public function testBundleClassExists(): void
    {
        self::assertTrue(class_exists(CommandLedgerBundle::class));
    }
}
