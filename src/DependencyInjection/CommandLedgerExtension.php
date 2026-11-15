<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\DependencyInjection;

use ArcaCodicum\CommandLedger\Generator\CommandFileGenerator;
use ArcaCodicum\CommandLedger\Locator\CommandFileLoaderInterface;
use ArcaCodicum\CommandLedger\Locator\CommandFileLocator;
use ArcaCodicum\CommandLedger\Locator\CommandFileLocatorInterface;
use ArcaCodicum\CommandLedger\Persistence\ExecutionRepository;
use ArcaCodicum\CommandLedger\Persistence\ExecutionRepositoryInterface;
use ArcaCodicum\CommandLedger\Process\ProcessRunner;
use ArcaCodicum\CommandLedger\Runner\LedgerRunner;
use ArcaCodicum\CommandLedger\Runner\StatusBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class CommandLedgerExtension extends Extension
{
    /** @param array<array<string, mixed>> $configs */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.php');

        $staleThreshold = $config['timeout'] > 0 ? max(60, 2 * $config['timeout']) : 86400;

        $container->getDefinition(ProcessRunner::class)->setArguments([
            $config['console_path'],
            $config['php_binary'],
            $config['timeout'],
            $config['output_limit'],
            $config['environment'] ?? '%kernel.environment%',
            '%kernel.debug%',
        ]);

        $container->getDefinition(CommandFileLocator::class)->setArguments([$config['directory']]);
        $container->getDefinition(CommandFileGenerator::class)->setArguments([$config['directory']]);

        $container->getDefinition(ExecutionRepository::class)->setArguments([
            new Reference('doctrine.dbal.'.$config['connection'].'_connection'),
            $config['table_name'],
        ]);

        $logger = new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE);

        $container->getDefinition(LedgerRunner::class)->setArguments([
            new Reference(CommandFileLocatorInterface::class),
            new Reference(CommandFileLoaderInterface::class),
            new Reference(ExecutionRepositoryInterface::class),
            $staleThreshold,
            $logger,
            $config['output_limit'],
        ]);

        $container->getDefinition(StatusBuilder::class)->setArguments([
            new Reference(CommandFileLocatorInterface::class),
            new Reference(ExecutionRepositoryInterface::class),
            $staleThreshold,
        ]);
    }
}
