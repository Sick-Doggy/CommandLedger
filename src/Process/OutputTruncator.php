<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Process;

final class OutputTruncator
{
    public function __construct(private readonly int $limitBytes)
    {
        if ($limitBytes < 1) {
            throw new \InvalidArgumentException('Output limit must be at least 1 byte.');
        }
    }

    public function truncate(string $output): string
    {
        if (strlen($output) <= $this->limitBytes) {
            return $output;
        }

        $tail = substr($output, -$this->limitBytes);

        while ($tail !== '' && (ord($tail[0]) & 0xC0) === 0x80) {
            $tail = substr($tail, 1);
        }

        return "[truncated]\n".$tail;
    }
}
