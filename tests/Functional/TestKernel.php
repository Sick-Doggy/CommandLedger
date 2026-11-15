<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\Tests\Functional;

use ArcaCodicum\CommandLedger\CommandLedgerBundle;
use ArcaCodicum\CommandLedger\Tests\Fake\TestConnectionFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    public function __construct(string $environment, bool $debug, private readonly string $commandsDirectory)
    {
        parent::__construct($environment, $debug);
    }

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new CommandLedgerBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
            ]);

            $container->loadFromExtension('command_ledger', [
                'directory' => $this->commandsDirectory,
                'console_path' => __DIR__.'/../Fixtures/console.php',
                'php_binary' => PHP_BINARY,
                'connection' => 'default',
                'table_name' => 'command_ledger_executions',
                'timeout' => 5,
                'output_limit' => 4096,
            ]);

            $container->register('doctrine.dbal.default_connection', Connection::class)
                ->setFactory([TestConnectionFactory::class, 'create'])
                ->setPublic(true);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/command-ledger-cache-'.md5($this->commandsDirectory);
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/command-ledger-logs-'.md5($this->commandsDirectory);
    }
}
