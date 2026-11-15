<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger;

interface CommandFileInterface
{
    public function up(): void;

    public function down(): void;
}
