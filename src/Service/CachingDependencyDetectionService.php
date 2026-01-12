<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Service;

use Spiriit\CommitHistory\Provider\ProviderInterface;
use Spiriit\CommitHistory\Service\DependencyDetectionService;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Decorator that adds caching to the library's DependencyDetectionService.
 * Caches dependency detection results per-commit with infinite TTL (commit IDs are immutable).
 */
final class CachingDependencyDetectionService extends DependencyDetectionService
{
    private const CACHE_KEY_PREFIX = 'spiriit_commit_history_has_deps_';

    private CacheInterface $cache;

    /**
     * @param string[] $dependencyFiles
     */
    public function __construct(
        ProviderInterface $provider,
        array $dependencyFiles,
        bool $trackDependencyChanges,
        CacheInterface $cache,
    ) {
        parent::__construct($provider, $dependencyFiles, $trackDependencyChanges);
        $this->cache = $cache;
    }

    /**
     * Check if a commit has dependency changes.
     * Result is cached per commit ID (forever, since commit content is immutable).
     */
    public function hasDependencyChanges(string $commitId): bool
    {
        $cacheKey = self::CACHE_KEY_PREFIX.$commitId;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($commitId): bool {
            // Cache forever (null TTL) since commit IDs are immutable
            $item->expiresAfter(null);

            return parent::hasDependencyChanges($commitId);
        });
    }

    /**
     * Get the cache key prefix for dependency detection.
     * Useful for cache clearing commands.
     */
    public static function getCacheKeyPrefix(): string
    {
        return self::CACHE_KEY_PREFIX;
    }
}
