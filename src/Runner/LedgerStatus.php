<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Runner;

final class LedgerStatus
{
    public const PENDING = 'pending';
    public const SUCCESS = 'success';
    public const FAILED = 'failed';
    public const RUNNING = 'running';
    public const STALE_RUNNING = 'stale running';
    public const MISSING = 'missing';

    private function __construct()
    {
    }
}
