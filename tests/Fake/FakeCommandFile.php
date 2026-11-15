<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Fake;

use ArcaCodicum\CommandLedger\AbstractCommandFile;
use ArcaCodicum\CommandLedger\Process\ProcessRunner;

final class FakeCommandFile extends AbstractCommandFile
{
    private ?\Closure $upCallback = null;
    private ?\Closure $downCallback = null;

    public static function create(\Closure $up, \Closure $down): self
    {
        $file = new self(new ProcessRunner('/dev/null', null, 1, 1, 'test', false));
        $file->upCallback = $up;
        $file->downCallback = $down;

        return $file;
    }

    public function up(): void
    {
        if ($this->upCallback !== null) {
            ($this->upCallback)();
        }
    }

    public function down(): void
    {
        if ($this->downCallback !== null) {
            ($this->downCallback)();
        }
    }
}
