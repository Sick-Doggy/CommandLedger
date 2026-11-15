<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Runner;

final class MigrateReport
{
    /** @param list<MigrateResult> $results */
    public function __construct(public readonly array $results)
    {
    }

    public function executed(): int
    {
        return count(array_filter(
            $this->results,
            static fn (MigrateResult $result): bool => $result->status === MigrateResult::EXECUTED,
        ));
    }

    public function skipped(): int
    {
        return count(array_filter(
            $this->results,
            static fn (MigrateResult $result): bool => $result->status === MigrateResult::SKIPPED,
        ));
    }

    public function failed(): ?MigrateResult
    {
        foreach ($this->results as $result) {
            if ($result->status === MigrateResult::FAILED) {
                return $result;
            }
        }

        return null;
    }

    public function hasFailed(): bool
    {
        return $this->failed() !== null;
    }
}
