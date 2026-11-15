<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Locator;

use ArcaCodicum\CommandLedger\AbstractCommandFile;

interface CommandFileLoaderInterface
{
    public function load(CommandFileDescriptor $file): AbstractCommandFile;
}
