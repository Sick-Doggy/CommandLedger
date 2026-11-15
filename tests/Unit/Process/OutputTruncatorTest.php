<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Unit\Process;

use ArcaCodicum\CommandLedger\Process\OutputTruncator;
use PHPUnit\Framework\TestCase;

final class OutputTruncatorTest extends TestCase
{
    public function testReturnsOutputUnderLimitUnchanged(): void
    {
        $truncator = new OutputTruncator(10);

        self::assertSame('short', $truncator->truncate('short'));
    }

    public function testKeepsTailWithMarker(): void
    {
        $truncator = new OutputTruncator(10);

        self::assertSame("[truncated]\nghijklmnop", $truncator->truncate('abcdefghijklmnop'));
    }

    public function testDoesNotSplitUtf8Character(): void
    {
        $truncator = new OutputTruncator(8);

        $result = $truncator->truncate(str_repeat('ą', 40)."\n");

        self::assertSame(1, preg_match('//u', $result));
        self::assertStringStartsWith("[truncated]\n", $result);
        self::assertStringEndsWith("ą\n", $result);
    }
}
