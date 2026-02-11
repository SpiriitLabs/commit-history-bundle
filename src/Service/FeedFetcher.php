<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Service;

use Spiriit\CommitHistory\DTO\Commit;
use Spiriit\CommitHistory\Service\FeedFetcherInterface as BaseFeedFetcherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Decorator that adds per-year caching to the library's FeedFetcher.
 */
final class FeedFetcher implements FeedFetcherInterface
{
    private const CACHE_KEY_PREFIX = 'spiriit_commit_history_feed_';

    public function __construct(
        private readonly BaseFeedFetcherInterface $inner,
        private readonly CacheInterface $cache,
        private readonly int $cacheTtl,
        private readonly string $providerHash,
    ) {
    }

    /**
     * @return Commit[]
     */
    public function fetch(?int $year = null): array
    {
        $year = $year ?? (int) date('Y');
        $cacheKey = $this->getCacheKey($year);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($year): array {
            $commits = $this->inner->fetch($year);

            // Don't cache empty results for long
            if (empty($commits)) {
                $item->expiresAfter(60); // Cache empty results for 1 minute
            } else {
                $item->expiresAfter($this->cacheTtl);
            }

            return $commits;
        });
    }

    /**
     * @return int[]
     */
    public function getAvailableYears(): array
    {
        return $this->inner->getAvailableYears();
    }

    public static function getCacheKeyPrefix(): string
    {
        return self::CACHE_KEY_PREFIX;
    }

    private function getCacheKey(int $year): string
    {
        return self::CACHE_KEY_PREFIX.$this->providerHash.'_'.$year;
    }
}
