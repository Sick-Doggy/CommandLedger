<?php

declare(strict_types=1);

$argv = $_SERVER['argv'] ?? [];
$command = $argv[1] ?? '';

switch ($command) {
    case 'test:ok':
        fwrite(STDOUT, "ok\n");
        exit(0);
    case 'test:fail':
        fwrite(STDERR, "boom\n");
        exit(3);
    case 'test:echo':
        fwrite(STDOUT, json_encode(array_slice($argv, 2))."\n");
        exit(0);
    case 'test:sleep':
        sleep((int) ($argv[2] ?? 1));
        exit(0);
    case 'test:long-output':
        fwrite(STDOUT, str_repeat('a', 100)."\n");
        exit(0);
    case 'test:fail-with-output':
        fwrite(STDOUT, "partial output\n");
        fwrite(STDERR, "boom\n");
        exit(3);
    case 'test:slow-output':
        fwrite(STDOUT, "started\n");
        flush();
        sleep((int) ($argv[2] ?? 2));
        exit(0);
    case 'test:utf8-output':
        fwrite(STDOUT, str_repeat('ą', 40)."\n");
        exit(0);
    default:
        fwrite(STDERR, "unknown command\n");
        exit(1);
}
