<?php

declare(strict_types=1);

use ArcaCodicum\CommandLedger\AbstractCommandFile;

final class Version20260101120500ImportProducts extends AbstractCommandFile
{
    public function up(): void
    {
        $this->execute(
            'app:import-products',
            ['products.csv'],
            ['--limit' => '100', '--dry-run' => true],
        );
    }

    public function down(): void
    {
        // The import runs once; reverting it would require a separate operation.
    }
}
