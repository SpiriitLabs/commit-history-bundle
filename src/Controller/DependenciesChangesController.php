<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Controller;

use Spiriit\Bundle\CommitHistoryBundle\DTO\DependencyChange;
use Spiriit\Bundle\CommitHistoryBundle\Provider\ProviderInterface;
use Spiriit\Bundle\CommitHistoryBundle\Service\DiffParser\DiffParserRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class DependenciesChangesController
{
    private const CACHE_KEY_PREFIX = 'spiriit_commit_history_deps_';

    /**
     * @param string[] $dependencyFiles
     */
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly DiffParserRegistry $diffParserRegistry,
        private readonly CacheInterface $cache,
        private readonly array $dependencyFiles,
        private readonly bool $trackDependencyChanges,
    ) {
    }

    public function __invoke(string $commitId): Response
    {
        if (!$this->trackDependencyChanges) {
            return new JsonResponse(
                ['error' => 'Dependency tracking is disabled'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Validate commit ID format (should be 7-40 hex characters)
        if (!preg_match('/^[a-f0-9]{7,40}$/i', $commitId)) {
            return new JsonResponse(
                ['error' => 'Invalid commit ID'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $changes = $this->getDependencyChanges($commitId);
        } catch (\Throwable $e) {
            return new JsonResponse(
                ['error' => 'Failed to fetch dependency changes'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return new JsonResponse([
            'commitId' => $commitId,
            'changes' => array_map(
                fn (DependencyChange $change) => [
                    'name' => $change->name,
                    'type' => $change->type,
                    'oldVersion' => $change->oldVersion,
                    'newVersion' => $change->newVersion,
                    'sourceFile' => $change->sourceFile,
                ],
                $changes
            ),
        ]);
    }

    /**
     * @return DependencyChange[]
     */
    private function getDependencyChanges(string $commitId): array
    {
        $cacheKey = self::CACHE_KEY_PREFIX.$commitId;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($commitId): array {
            // Cache forever (no TTL) since commit ID is immutable
            $item->expiresAfter(null);

            return $this->fetchAndParseDependencyChanges($commitId);
        });
    }

    /**
     * @return DependencyChange[]
     */
    private function fetchAndParseDependencyChanges(string $commitId): array
    {
        $diffs = $this->provider->getCommitDiff($commitId);

        // Filter to only include dependency files
        $dependencyDiffs = [];
        foreach ($diffs as $filename => $diff) {
            $baseName = basename($filename);
            if (\in_array($baseName, $this->dependencyFiles, true)) {
                $dependencyDiffs[$filename] = $diff;
            }
        }

        return $this->diffParserRegistry->parseAll($dependencyDiffs);
    }

    /**
     * Get the cache key prefix for dependency changes.
     * Useful for cache clearing commands.
     */
    public static function getCacheKeyPrefix(): string
    {
        return self::CACHE_KEY_PREFIX;
    }
}
