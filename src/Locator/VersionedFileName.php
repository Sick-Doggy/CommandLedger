<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Locator;

final class VersionedFileName
{
    public const PATTERN = '/^(\d{14})(?:_([A-Za-z][A-Za-z0-9_]*))?\.php$/';

    private function __construct(
        public readonly string $fileName,
        public readonly string $version,
        public readonly ?string $description,
    ) {
    }

    public static function parse(string $fileName): ?self
    {
        if (preg_match(self::PATTERN, $fileName, $matches) !== 1) {
            return null;
        }

        $version = $matches[1];
        $date = \DateTimeImmutable::createFromFormat('!YmdHis', $version);

        if ($date === false || $date->format('YmdHis') !== $version) {
            return null;
        }

        $description = $matches[2] ?? '';

        return new self($fileName, $version, $description !== '' ? $description : null);
    }

    public static function forNew(string $version, string $description): self
    {
        return new self($version.'_'.$description.'.php', $version, $description);
    }

    public function className(): string
    {
        return 'Version'.$this->version.$this->studly($this->description ?? '');
    }

    private function studly(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return implode('', array_map(
            static fn (string $part): string => ucfirst($part),
            explode('_', $value),
        ));
    }
}
