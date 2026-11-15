<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Locator;

use ArcaCodicum\CommandLedger\AbstractCommandFile;
use ArcaCodicum\CommandLedger\Exception\InvalidCommandFileException;
use ArcaCodicum\CommandLedger\Process\ProcessRunner;

final class CommandFileLoader implements CommandFileLoaderInterface
{
    public function __construct(private readonly ProcessRunner $runner)
    {
    }

    public function load(CommandFileDescriptor $file): AbstractCommandFile
    {
        $className = $file->className();

        if (!class_exists($className, false)) {
            try {
                (static function (string $path): void {
                    require $path;
                })($file->path);
            } catch (\Throwable $exception) {
                throw new InvalidCommandFileException(sprintf(
                    'Could not load command file "%s": %s',
                    $file->fileName(),
                    $exception->getMessage(),
                ), 0, $exception);
            }
        }

        if (!class_exists($className, false)) {
            throw new InvalidCommandFileException(sprintf(
                'Command file "%s" must declare class "%s".',
                $file->fileName(),
                $className,
            ));
        }

        if (!is_subclass_of($className, AbstractCommandFile::class)) {
            throw new InvalidCommandFileException(sprintf(
                'Class "%s" in file "%s" must extend %s.',
                $className,
                $file->fileName(),
                AbstractCommandFile::class,
            ));
        }

        return new $className($this->runner);
    }
}
