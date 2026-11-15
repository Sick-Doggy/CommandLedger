<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Locator;

final class CommandFileDescriptor
{
    public function __construct(
        public readonly string $path,
        public readonly VersionedFileName $name,
    ) {
    }

    public function version(): string
    {
        return $this->name->version;
    }

    public function fileName(): string
    {
        return $this->name->fileName;
    }

    public function className(): string
    {
        return $this->name->className();
    }
}
