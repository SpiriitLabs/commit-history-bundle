<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Spiriit\Bundle\CommitHistoryBundle\Command\ClearCacheCommand;
use Spiriit\Bundle\CommitHistoryBundle\Command\RefreshCacheCommand;
use Spiriit\Bundle\CommitHistoryBundle\Controller\DependenciesChangesController;
use Spiriit\Bundle\CommitHistoryBundle\Controller\TimelineController;
use Spiriit\Bundle\CommitHistoryBundle\Provider\Github\CommitParser as GithubCommitParser;
use Spiriit\Bundle\CommitHistoryBundle\Provider\Github\Provider as GithubProvider;
use Spiriit\Bundle\CommitHistoryBundle\Provider\Gitlab\CommitParser as GitlabCommitParser;
use Spiriit\Bundle\CommitHistoryBundle\Provider\Gitlab\Provider as GitlabProvider;
use Spiriit\Bundle\CommitHistoryBundle\Service\DependencyDetectionService;
use Spiriit\Bundle\CommitHistoryBundle\Service\DiffParser\ComposerDiffParser;
use Spiriit\Bundle\CommitHistoryBundle\Service\DiffParser\DiffParserRegistry;
use Spiriit\Bundle\CommitHistoryBundle\Service\DiffParser\PackageDiffParser;
use Spiriit\Bundle\CommitHistoryBundle\Service\FeedFetcher;
use Spiriit\Bundle\CommitHistoryBundle\Service\FeedFetcherInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // GitLab services
    $services->set('spiriit_commit_history.gitlab.parser', GitlabCommitParser::class);

    $services->set('spiriit_commit_history.gitlab.provider', GitlabProvider::class)
        ->args([
            service('http_client'),
            service('spiriit_commit_history.gitlab.parser'),
            param('spiriit_commit_history.gitlab.base_url'),
            param('spiriit_commit_history.gitlab.project_id'),
            param('spiriit_commit_history.gitlab.token'),
            param('spiriit_commit_history.gitlab.ref'),
        ]);

    // GitHub services
    $services->set('spiriit_commit_history.github.parser', GithubCommitParser::class);

    $services->set('spiriit_commit_history.github.provider', GithubProvider::class)
        ->args([
            service('http_client'),
            service('spiriit_commit_history.github.parser'),
            param('spiriit_commit_history.github.base_url'),
            param('spiriit_commit_history.github.owner'),
            param('spiriit_commit_history.github.repo'),
            param('spiriit_commit_history.github.token'),
            param('spiriit_commit_history.github.ref'),
        ]);

    // FeedFetcher (caching wrapper)
    $services->set('spiriit_commit_history.feed_fetcher', FeedFetcher::class)
        ->args([
            service('spiriit_commit_history.provider'),
            service('cache.app'),
            param('spiriit_commit_history.cache_ttl'),
            param('spiriit_commit_history.available_years_count'),
            service('spiriit_commit_history.dependency_detection'),
        ]);

    $services->alias(FeedFetcherInterface::class, 'spiriit_commit_history.feed_fetcher');

    // Controller
    $services->set('spiriit_commit_history.controller.timeline', TimelineController::class)
        ->args([
            service('spiriit_commit_history.feed_fetcher'),
            service('twig'),
            param('spiriit_commit_history.feed_name'),
        ])
        ->tag('controller.service_arguments');

    // Commands
    $services->set('spiriit_commit_history.command.refresh_cache', RefreshCacheCommand::class)
        ->args([
            service(FeedFetcherInterface::class),
            service('cache.app'),
        ])
        ->tag('console.command');

    // Diff Parsers (tagged for auto-discovery)
    $services->set('spiriit_commit_history.diff_parser.composer', ComposerDiffParser::class)
        ->tag('spiriit_commit_history.diff_parser');

    $services->set('spiriit_commit_history.diff_parser.package', PackageDiffParser::class)
        ->tag('spiriit_commit_history.diff_parser');

    // Diff Parser Registry
    $services->set('spiriit_commit_history.diff_parser_registry', DiffParserRegistry::class)
        ->args([
            tagged_iterator('spiriit_commit_history.diff_parser'),
        ]);

    // Dependency Detection Service
    $services->set('spiriit_commit_history.dependency_detection', DependencyDetectionService::class)
        ->args([
            service('spiriit_commit_history.provider'),
            service('cache.app'),
            param('spiriit_commit_history.dependency_files'),
            param('spiriit_commit_history.track_dependency_changes'),
        ]);

    // Dependencies Changes Controller
    $services->set('spiriit_commit_history.controller.dependencies_changes', DependenciesChangesController::class)
        ->args([
            service('spiriit_commit_history.provider'),
            service('spiriit_commit_history.diff_parser_registry'),
            service('cache.app'),
            param('spiriit_commit_history.dependency_files'),
            param('spiriit_commit_history.track_dependency_changes'),
        ])
        ->tag('controller.service_arguments');

    // Clear Cache Command
    $services->set('spiriit_commit_history.command.clear_cache', ClearCacheCommand::class)
        ->args([
            service('cache.app'),
            service(FeedFetcherInterface::class),
        ])
        ->tag('console.command');
};
