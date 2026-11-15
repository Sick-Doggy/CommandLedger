<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Locator;

interface CommandFileLocatorInterface
{
    /** @return list<CommandFileDescriptor> */
    public function locate(): array;
}
