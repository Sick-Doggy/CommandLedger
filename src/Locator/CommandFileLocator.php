<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Locator;

use ArcaCodicum\CommandLedger\Exception\DirectoryNotFoundException;
use ArcaCodicum\CommandLedger\Exception\InvalidCommandFileException;

final class CommandFileLocator implements CommandFileLocatorInterface
{
    public function __construct(private readonly string $directory)
    {
    }

    /** @return list<CommandFileDescriptor> */
    public function locate(): array
    {
        if (!is_dir($this->directory)) {
            throw new DirectoryNotFoundException(sprintf(
                'Command directory "%s" does not exist. Configure command_ledger.directory.',
                $this->directory,
            ));
        }

        $entries = @scandir($this->directory);

        if ($entries === false) {
            throw new DirectoryNotFoundException(sprintf(
                'Command directory "%s" is not readable.',
                $this->directory,
            ));
        }

        $descriptors = [];
        $versions = [];

        foreach ($entries as $entry) {
            if (!str_ends_with($entry, '.php')) {
                continue;
            }

            $path = rtrim($this->directory, '/\\').DIRECTORY_SEPARATOR.$entry;

            if (!is_file($path)) {
                continue;
            }

            $fileName = $entry;
            $name = VersionedFileName::parse($fileName);

            if ($name === null) {
                throw new InvalidCommandFileException(sprintf(
                    'Invalid command file name "%s": expected YYYYMMDDHHMMSS_optional_name.php with a valid date.',
                    $fileName,
                ));
            }

            if (isset($versions[$name->version])) {
                throw new InvalidCommandFileException(sprintf(
                    'Duplicate version %s in files "%s" and "%s".',
                    $name->version,
                    $versions[$name->version],
                    $fileName,
                ));
            }

            $versions[$name->version] = $fileName;
            $descriptors[] = new CommandFileDescriptor($path, $name);
        }

        usort(
            $descriptors,
            static fn (CommandFileDescriptor $a, CommandFileDescriptor $b): int
                => strcmp($a->version(), $b->version()),
        );

        return $descriptors;
    }
}
