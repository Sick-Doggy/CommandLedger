<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Runner;

final class StaleExecutionPolicy
{
    public function __construct(private readonly int $thresholdSeconds)
    {
    }

    public function isStale(?\DateTimeImmutable $startedAt, \DateTimeImmutable $now): bool
    {
        if ($startedAt === null) {
            return true;
        }

        return $startedAt < $now->modify(sprintf('-%d seconds', $this->thresholdSeconds));
    }
}
