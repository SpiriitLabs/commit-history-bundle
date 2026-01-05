<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('spiriit_commit_history');

        $treeBuilder->getRootNode()
            ->children()
                ->enumNode('provider')
                    ->values(['gitlab', 'github'])
                    ->isRequired()
                    ->info('Commit history provider (gitlab or github)')
                ->end()
                ->scalarNode('feed_name')
                    ->defaultValue('Commits')
                    ->info('Display name for the feed')
                ->end()
                ->integerNode('cache_ttl')
                    ->defaultValue(3600)
                    ->min(0)
                    ->info('Cache duration in seconds (default: 1 hour)')
                ->end()
                ->integerNode('available_years_count')
                    ->defaultValue(6)
                    ->min(1)
                    ->info('Number of years to show in the year filter dropdown')
                ->end()
                ->arrayNode('dependency_files')
                    ->scalarPrototype()->end()
                    ->defaultValue(['composer.json', 'composer.lock', 'package.json', 'package-lock.json'])
                    ->info('List of dependency files to track for changes')
                ->end()
                ->arrayNode('gitlab')
                    ->children()
                        ->scalarNode('project_id')
                            ->isRequired()
                            ->cannotBeEmpty()
                            ->info('Numeric project ID or URL-encoded path (e.g., "group%2Fproject")')
                        ->end()
                        ->scalarNode('base_url')
                            ->defaultValue('https://gitlab.com')
                            ->info('GitLab instance URL')
                        ->end()
                        ->scalarNode('token')
                            ->defaultNull()
                            ->info('GitLab Personal Access Token (only required for private repos)')
                        ->end()
                        ->scalarNode('ref')
                            ->defaultNull()
                            ->info('Branch or tag name (defaults to repository default branch)')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('github')
                    ->children()
                        ->scalarNode('owner')
                            ->isRequired()
                            ->cannotBeEmpty()
                            ->info('Repository owner (user or organization)')
                        ->end()
                        ->scalarNode('repo')
                            ->isRequired()
                            ->cannotBeEmpty()
                            ->info('Repository name')
                        ->end()
                        ->scalarNode('base_url')
                            ->defaultValue('https://api.github.com')
                            ->info('GitHub API URL (use https://github.mycompany.com/api/v3 for Enterprise)')
                        ->end()
                        ->scalarNode('token')
                            ->defaultNull()
                            ->info('GitHub Personal Access Token (only required for private repos)')
                        ->end()
                        ->scalarNode('ref')
                            ->defaultNull()
                            ->info('Branch or tag name (defaults to repository default branch)')
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(function (array $v): bool {
                    return 'gitlab' === $v['provider'] && empty($v['gitlab']);
                })
                ->thenInvalid('GitLab provider selected but gitlab configuration is missing')
            ->end()
            ->validate()
                ->ifTrue(function (array $v): bool {
                    return 'github' === $v['provider'] && empty($v['github']);
                })
                ->thenInvalid('GitHub provider selected but github configuration is missing')
            ->end()
        ;

        return $treeBuilder;
    }
}
