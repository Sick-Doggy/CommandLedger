<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Persistence;

final class ExecutionStatus
{
    public const RUNNING = 'running';
    public const SUCCESS = 'success';
    public const FAILED = 'failed';

    private function __construct()
    {
    }
}
