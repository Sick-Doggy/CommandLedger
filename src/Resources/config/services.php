<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use ArcaCodicum\CommandLedger\Console\GenerateCommand;
use ArcaCodicum\CommandLedger\Console\MigrateCommand;
use ArcaCodicum\CommandLedger\Console\RollbackCommand;
use ArcaCodicum\CommandLedger\Console\SetupCommand;
use ArcaCodicum\CommandLedger\Console\StatusCommand;
use ArcaCodicum\CommandLedger\Generator\CommandFileGenerator;
use ArcaCodicum\CommandLedger\Locator\CommandFileLoader;
use ArcaCodicum\CommandLedger\Locator\CommandFileLoaderInterface;
use ArcaCodicum\CommandLedger\Locator\CommandFileLocator;
use ArcaCodicum\CommandLedger\Locator\CommandFileLocatorInterface;
use ArcaCodicum\CommandLedger\Persistence\ExecutionRepository;
use ArcaCodicum\CommandLedger\Persistence\ExecutionRepositoryInterface;
use ArcaCodicum\CommandLedger\Process\ProcessRunner;
use ArcaCodicum\CommandLedger\Runner\LedgerRunner;
use ArcaCodicum\CommandLedger\Runner\StatusBuilder;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(ProcessRunner::class);
    $services->set(CommandFileLocator::class);
    $services->set(CommandFileLoader::class);
    $services->set(ExecutionRepository::class);
    $services->set(CommandFileGenerator::class);
    $services->set(LedgerRunner::class);
    $services->set(StatusBuilder::class);
    $services->set(SetupCommand::class)->tag('console.command');
    $services->set(MigrateCommand::class)->tag('console.command');
    $services->set(StatusCommand::class)->tag('console.command');
    $services->set(RollbackCommand::class)->tag('console.command');
    $services->set(GenerateCommand::class)->tag('console.command');

    $services->alias(CommandFileLocatorInterface::class, CommandFileLocator::class);
    $services->alias(CommandFileLoaderInterface::class, CommandFileLoader::class);
    $services->alias(ExecutionRepositoryInterface::class, ExecutionRepository::class);
};
