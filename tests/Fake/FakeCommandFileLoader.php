<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Fake;

use ArcaCodicum\CommandLedger\AbstractCommandFile;
use ArcaCodicum\CommandLedger\Locator\CommandFileDescriptor;
use ArcaCodicum\CommandLedger\Locator\CommandFileLoaderInterface;

final class FakeCommandFileLoader implements CommandFileLoaderInterface
{
    /** @param array<string, AbstractCommandFile> $files */
    public function __construct(private array $files)
    {
    }

    public function load(CommandFileDescriptor $file): AbstractCommandFile
    {
        return $this->files[$file->version()]
            ?? throw new \RuntimeException('No fake command file for version '.$file->version());
    }
}
