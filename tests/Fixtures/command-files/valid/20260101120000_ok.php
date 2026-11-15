<?php

declare(strict_types=1);

use ArcaCodicum\CommandLedger\AbstractCommandFile;

final class Version20260101120000Ok extends AbstractCommandFile
{
    public function up(): void
    {
        $this->execute('test:ok');
    }

    public function down(): void
    {
    }
}
