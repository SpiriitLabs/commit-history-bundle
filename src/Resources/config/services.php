<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Spiriit\Bundle\CommitHistoryBundle\Adapter\SymfonyHttpClientAdapter;
use Spiriit\Bundle\CommitHistoryBundle\Command\ClearCacheCommand;
use Spiriit\Bundle\CommitHistoryBundle\Command\RefreshCacheCommand;
use Spiriit\Bundle\CommitHistoryBundle\Controller\DependenciesChangesController;
use Spiriit\Bundle\CommitHistoryBundle\Controller\TimelineController;
use Spiriit\Bundle\CommitHistoryBundle\Service\CachingDependencyDetectionService;
use Spiriit\Bundle\CommitHistoryBundle\Service\CachingFeedFetcher;
use Spiriit\Bundle\CommitHistoryBundle\Service\CachingFeedFetcherInterface;
use Spiriit\CommitHistory\Contract\HttpClientInterface;
use Spiriit\CommitHistory\DiffParser\ComposerDiffParser;
use Spiriit\CommitHistory\DiffParser\DiffParserRegistry;
use Spiriit\CommitHistory\DiffParser\PackageDiffParser;
use Spiriit\CommitHistory\Provider\Github\CommitParser as GithubCommitParser;
use Spiriit\CommitHistory\Provider\Github\GithubProvider;
use Spiriit\CommitHistory\Provider\Gitlab\CommitParser as GitlabCommitParser;
use Spiriit\CommitHistory\Provider\Gitlab\GitlabProvider;
use Spiriit\CommitHistory\Service\FeedFetcher;
use Spiriit\CommitHistory\Service\FeedFetcherInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // HTTP Client Adapter (bridges Symfony to library contract)
    $services->set('spiriit_commit_history.http_client_adapter', SymfonyHttpClientAdapter::class)
        ->args([
            service('http_client'),
        ]);

    $services->alias(HttpClientInterface::class, 'spiriit_commit_history.http_client_adapter');

    // GitLab services
    $services->set('spiriit_commit_history.gitlab.parser', GitlabCommitParser::class);

    $services->set('spiriit_commit_history.gitlab.provider', GitlabProvider::class)
        ->args([
            service('spiriit_commit_history.http_client_adapter'),
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
            service('spiriit_commit_history.http_client_adapter'),
            service('spiriit_commit_history.github.parser'),
            param('spiriit_commit_history.github.base_url'),
            param('spiriit_commit_history.github.owner'),
            param('spiriit_commit_history.github.repo'),
            param('spiriit_commit_history.github.token'),
            param('spiriit_commit_history.github.ref'),
        ]);

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

    // Caching Dependency Detection Service (extends library's service with caching)
    $services->set('spiriit_commit_history.dependency_detection', CachingDependencyDetectionService::class)
        ->args([
            service('spiriit_commit_history.provider'),
            param('spiriit_commit_history.dependency_files'),
            param('spiriit_commit_history.track_dependency_changes'),
            service('cache.app'),
        ]);

    // Library's FeedFetcher (inner, no caching of commits list)
    $services->set('spiriit_commit_history.feed_fetcher.inner', FeedFetcher::class)
        ->args([
            service('spiriit_commit_history.provider'),
            param('spiriit_commit_history.available_years_count'),
            service('spiriit_commit_history.dependency_detection'),
        ]);

    // Caching FeedFetcher (decorator that adds commits list caching)
    $services->set('spiriit_commit_history.feed_fetcher', CachingFeedFetcher::class)
        ->args([
            service('spiriit_commit_history.feed_fetcher.inner'),
            service('cache.app'),
            param('spiriit_commit_history.cache_ttl'),
            param('spiriit_commit_history.provider_hash'),
        ]);

    $services->alias(FeedFetcherInterface::class, 'spiriit_commit_history.feed_fetcher');
    $services->alias(CachingFeedFetcherInterface::class, 'spiriit_commit_history.feed_fetcher');

    // Controller
    $services->set('spiriit_commit_history.controller.timeline', TimelineController::class)
        ->args([
            service('spiriit_commit_history.feed_fetcher'),
            service('twig'),
            param('spiriit_commit_history.feed_name'),
        ])
        ->tag('controller.service_arguments');

    // Dependencies Changes Controller (has its own caching for dependency changes)
    $services->set('spiriit_commit_history.controller.dependencies_changes', DependenciesChangesController::class)
        ->args([
            service('spiriit_commit_history.provider'),
            service('spiriit_commit_history.diff_parser_registry'),
            service('cache.app'),
            param('spiriit_commit_history.dependency_files'),
            param('spiriit_commit_history.track_dependency_changes'),
        ])
        ->tag('controller.service_arguments');

    // Commands
    $services->set('spiriit_commit_history.command.refresh_cache', RefreshCacheCommand::class)
        ->args([
            service(CachingFeedFetcherInterface::class),
            service('cache.app'),
        ])
        ->tag('console.command');

    $services->set('spiriit_commit_history.command.clear_cache', ClearCacheCommand::class)
        ->args([
            service('cache.app'),
            service(CachingFeedFetcherInterface::class),
        ])
        ->tag('console.command');
};
