<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Runner;

final class RollbackReport
{
    /** @param list<string> $reverted */
    public function __construct(
        public readonly array $reverted,
        public readonly ?string $failedVersion = null,
        public readonly ?string $error = null,
    ) {
    }

    public function hasFailed(): bool
    {
        return $this->failedVersion !== null;
    }
}
