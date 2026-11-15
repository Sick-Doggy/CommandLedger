<?php

declare(strict_types=1);

use ArcaCodicum\CommandLedger\AbstractCommandFile;

final class Version20260101120000ClearCache extends AbstractCommandFile
{
    public function up(): void
    {
        $this->execute('cache:clear');
    }

    public function down(): void
    {
        // Clearing the cache does not need to be reverted.
    }
}
