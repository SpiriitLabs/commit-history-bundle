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

class FeedFetcher implements FeedFetcherInterface
{
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly CacheInterface $cache,
        private readonly int $cacheTtl = 3600,
        private readonly int $availableYearsCount = 6,
        private readonly ?DependencyDetectionService $dependencyDetectionService = null,
    ) {
    }

    /**
     * @return Commit[]
     */
    public function fetch(?int $year = null): array
    {
        $year = $year ?? (int) date('Y');
        [$since, $until] = $this->getYearDateRange($year);

        $commits = $this->cache->get($this->getCacheKey($year), function (ItemInterface $item) use ($since, $until): array {
            $commits = $this->provider->getCommits($since, $until);

            if (empty($commits)) {
                $item->expiresAfter(0);
            } else {
                $item->expiresAfter($this->cacheTtl);
            }

            return $commits;
        });

        // Detect dependency changes for each commit (uses per-commit caching)
        if (null !== $this->dependencyDetectionService) {
            $commits = $this->dependencyDetectionService->detectForCommits($commits);
        }

        return $commits;
    }

    /**
     * @return Commit[]
     */
    public function refresh(?int $year = null): array
    {
        $year = $year ?? (int) date('Y');
        $this->cache->delete($this->getCacheKey($year));

        return $this->fetch($year);
    }

    /**
     * @return int[]
     */
    public function getAvailableYears(): array
    {
        $currentYear = (int) date('Y');
        $years = [];

        for ($i = 0; $i < $this->availableYearsCount; ++$i) {
            $years[] = $currentYear - $i;
        }

        return $years;
    }

    public function getCacheKey(int $year): string
    {
        return 'spiriit_commit_history_feed_'.md5(\get_class($this->provider)).'_'.$year;
    }

    /**
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    private function getYearDateRange(int $year): array
    {
        $since = new \DateTimeImmutable(\sprintf('%d-01-01T00:00:00+00:00', $year));
        $until = new \DateTimeImmutable(\sprintf('%d-12-31T23:59:59+00:00', $year));

        return [$since, $until];
    }
}
