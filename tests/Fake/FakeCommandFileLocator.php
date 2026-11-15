<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Fake;

use ArcaCodicum\CommandLedger\Locator\CommandFileDescriptor;
use ArcaCodicum\CommandLedger\Locator\CommandFileLocatorInterface;

final class FakeCommandFileLocator implements CommandFileLocatorInterface
{
    /** @param list<CommandFileDescriptor> $files */
    public function __construct(private array $files)
    {
    }

    public function locate(): array
    {
        return $this->files;
    }
}
