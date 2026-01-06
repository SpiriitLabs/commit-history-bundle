<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Service;

use Spiriit\Bundle\CommitHistoryBundle\DTO\Commit;
use Spiriit\Bundle\CommitHistoryBundle\Provider\ProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class DependencyDetectionService
{
    private const CACHE_KEY_PREFIX = 'spiriit_commit_history_has_deps_';

    /**
     * @param string[] $dependencyFiles
     */
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly CacheInterface $cache,
        private readonly array $dependencyFiles,
        private readonly bool $trackDependencyChanges,
    ) {
    }

    /**
     * Detect dependency changes for a list of commits.
     * Uses per-commit caching to avoid re-fetching file names.
     *
     * @param Commit[] $commits
     *
     * @return Commit[]
     */
    public function detectForCommits(array $commits): array
    {
        if (!$this->trackDependencyChanges) {
            return $commits;
        }

        $result = [];
        foreach ($commits as $commit) {
            $hasDeps = $this->hasDependencyChanges($commit->id);
            $result[] = $commit->withHasDependenciesChanges($hasDeps);
        }

        return $result;
    }

    /**
     * Check if a commit has dependency changes.
     * Result is cached per commit ID (never invalidates since commit ID is immutable).
     */
    public function hasDependencyChanges(string $commitId): bool
    {
        if (!$this->trackDependencyChanges) {
            return false;
        }

        $cacheKey = self::CACHE_KEY_PREFIX.$commitId;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($commitId): bool {
            // Cache forever (no TTL) since commit ID is immutable
            $item->expiresAfter(null);

            return $this->checkCommitForDependencyFiles($commitId);
        });
    }

    /**
     * Clear the dependency detection cache for a specific commit.
     */
    public function clearCache(string $commitId): void
    {
        $cacheKey = self::CACHE_KEY_PREFIX.$commitId;
        $this->cache->delete($cacheKey);
    }

    /**
     * Check if any of the changed files in the commit are dependency files.
     */
    private function checkCommitForDependencyFiles(string $commitId): bool
    {
        try {
            $fileNames = $this->provider->getCommitFileNames($commitId);
        } catch (\Throwable) {
            return false;
        }

        foreach ($fileNames as $fileName) {
            $baseName = basename($fileName);
            if (\in_array($baseName, $this->dependencyFiles, true)) {
                return true;
            }
        }

        return false;
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
