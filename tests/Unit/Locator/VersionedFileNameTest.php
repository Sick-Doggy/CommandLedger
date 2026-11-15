<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Unit\Locator;

use ArcaCodicum\CommandLedger\Locator\VersionedFileName;
use PHPUnit\Framework\TestCase;

final class VersionedFileNameTest extends TestCase
{
    public function testParsesFileWithDescription(): void
    {
        $name = VersionedFileName::parse('20260101120000_clear_cache.php');

        self::assertNotNull($name);
        self::assertSame('20260101120000', $name->version);
        self::assertSame('clear_cache', $name->description);
        self::assertSame('Version20260101120000ClearCache', $name->className());
    }

    public function testParsesFileWithoutDescription(): void
    {
        $name = VersionedFileName::parse('20260101120000.php');

        self::assertNotNull($name);
        self::assertNull($name->description);
        self::assertSame('Version20260101120000', $name->className());
    }

    public function testRejectsInvalidPattern(): void
    {
        self::assertNull(VersionedFileName::parse('clear_cache.php'));
        self::assertNull(VersionedFileName::parse('20260101120000_clear-cache.php'));
    }

    public function testRejectsInvalidDate(): void
    {
        self::assertNull(VersionedFileName::parse('20261332250000_bad.php'));
    }

    public function testForNewBuildsMatchingFileAndClass(): void
    {
        $name = VersionedFileName::forNew('20260101120001', 'import_products');

        self::assertSame('20260101120001_import_products.php', $name->fileName);
        self::assertSame('Version20260101120001ImportProducts', $name->className());
    }
}
