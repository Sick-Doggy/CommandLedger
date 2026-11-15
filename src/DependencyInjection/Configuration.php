<?php

declare(strict_types=1);

namespace ArcaCodicum\CommandLedger\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('command_ledger');
        $root = $treeBuilder->getRootNode();
        \assert($root instanceof ArrayNodeDefinition);

        $children = $root->children();

        $children->scalarNode('directory')
            ->defaultValue('%kernel.project_dir%/command-files')
            ->cannotBeEmpty()
        ->end();

        $children->scalarNode('table_name')
            ->defaultValue('command_ledger_executions')
            ->cannotBeEmpty()
        ->end();

        $children->scalarNode('connection')
            ->defaultValue('default')
            ->cannotBeEmpty()
        ->end();

        $children->scalarNode('console_path')
            ->defaultValue('%kernel.project_dir%/bin/console')
            ->cannotBeEmpty()
        ->end();

        $children->scalarNode('php_binary')
            ->defaultNull()
            ->validate()
                ->ifTrue(static fn ($value): bool => $value !== null && trim((string) $value) === '')
                ->thenInvalid('The "php_binary" option must be null or a non-empty path.')
            ->end()
        ->end();

        $children->integerNode('timeout')->defaultValue(300)->min(0)->end();
        $children->integerNode('output_limit')->defaultValue(65536)->min(1)->end();

        $children->scalarNode('environment')
            ->defaultNull()
            ->validate()
                ->ifTrue(static fn ($value): bool => $value !== null && trim((string) $value) === '')
                ->thenInvalid('The "environment" option must be null or a non-empty name.')
            ->end()
        ->end();

        return $treeBuilder;
    }
}
