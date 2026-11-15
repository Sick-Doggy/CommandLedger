<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Generator;

use ArcaCodicum\CommandLedger\Exception\CommandLedgerException;
use ArcaCodicum\CommandLedger\Locator\VersionedFileName;

final class CommandFileGenerator
{
    /** @var \Closure(): \DateTimeImmutable */
    private readonly \Closure $clock;

    public function __construct(
        private readonly string $directory,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable();
    }

    public function generate(string $name): string
    {
        $description = $this->normalize($name);
        $directory = rtrim($this->directory, '/');

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new CommandLedgerException(sprintf('Could not create command directory "%s".', $directory));
        }

        $lockPath = sys_get_temp_dir().'/command-ledger-generate-'.md5($directory).'.lock';
        $lock = @fopen($lockPath, 'c');

        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new CommandLedgerException(sprintf('Could not lock command directory "%s".', $directory));
        }

        try {
            $date = ($this->clock)();

            while (true) {
                $version = $date->format('YmdHis');
                $fileName = VersionedFileName::forNew($version, $description);
                $path = $directory.'/'.$fileName->fileName;

                if ($this->versionExists($directory, $version)) {
                    $date = $date->modify('+1 second');
                    continue;
                }

                $handle = @fopen($path, 'xb');

                if ($handle === false) {
                    if (!file_exists($path)) {
                        throw new CommandLedgerException(sprintf('Could not write command file "%s".', $path));
                    }

                    $date = $date->modify('+1 second');
                    continue;
                }

                break;
            }

            $content = $this->template($fileName->className());
            $written = fwrite($handle, $content);
            fclose($handle);

            if ($written !== strlen($content)) {
                @unlink($path);

                throw new CommandLedgerException(sprintf('Could not write command file "%s".', $path));
            }

            return $path;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function normalize(string $name): string
    {
        $normalized = strtolower(trim($name));
        $normalized = preg_replace('/[\s\-]+/', '_', $normalized) ?? '';
        $normalized = preg_replace('/[^a-z0-9_]+/', '', $normalized) ?? '';
        $normalized = trim(preg_replace('/_+/', '_', $normalized) ?? '', '_');

        if (preg_match('/^[a-z][a-z0-9_]*$/', $normalized) !== 1) {
            throw new CommandLedgerException(sprintf(
                'Invalid command file name "%s": use letters, digits and underscores, starting with a letter.',
                $name,
            ));
        }

        return $normalized;
    }

    private function versionExists(string $directory, string $version): bool
    {
        $entries = @scandir($directory);

        if ($entries === false) {
            throw new CommandLedgerException(sprintf('Could not read command directory "%s".', $directory));
        }

        foreach ($entries as $entry) {
            if ($entry === $version.'.php' || str_starts_with($entry, $version.'_')) {
                return true;
            }
        }

        return false;
    }

    private function template(string $className): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

use ArcaCodicum\CommandLedger\AbstractCommandFile;

final class {$className} extends AbstractCommandFile
{
    public function up(): void
    {
        // TODO: uruchom komendy, np. \$this->execute('cache:clear');
    }

    public function down(): void
    {
        // TODO: cofnij zmiany z up(), jeśli to możliwe
    }
}

PHP;
    }
}
