<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Unit\DependencyInjection;

use ArcaCodicum\CommandLedger\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testProvidesDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);

        self::assertSame('%kernel.project_dir%/command-files', $config['directory']);
        self::assertSame('command_ledger_executions', $config['table_name']);
        self::assertSame('default', $config['connection']);
        self::assertSame('%kernel.project_dir%/bin/console', $config['console_path']);
        self::assertNull($config['php_binary']);
        self::assertSame(300, $config['timeout']);
        self::assertSame(65536, $config['output_limit']);
        self::assertNull($config['environment']);
    }

    public function testRejectsUnknownKeys(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [['unknown' => true]]);
    }

    public function testRejectsNegativeTimeoutAndZeroOutputLimit(): void
    {
        $processor = new Processor();

        try {
            $processor->processConfiguration(new Configuration(), [['timeout' => -1]]);
            self::fail('Expected invalid timeout.');
        } catch (InvalidConfigurationException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidConfigurationException::class);
        $processor->processConfiguration(new Configuration(), [['output_limit' => 0]]);
    }

    public function testRejectsEmptyPhpBinary(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [['php_binary' => '']]);
    }

    public function testRejectsEmptyEnvironment(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [['environment' => '']]);
    }
}
