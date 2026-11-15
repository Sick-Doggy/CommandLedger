<?php

declare(strict_types=1);

use ArcaCodicum\CommandLedger\AbstractCommandFile;

final class Version20260101121000GenerateReports extends AbstractCommandFile
{
    public function up(): void
    {
        $this->executeScript('scripts/build-report.sh', ['--format=pdf']);
        $this->executeBash('ls -la var/reports | wc -l');
    }

    public function down(): void
    {
        // Report generation is append-only; remove the files manually if needed.
    }
}
