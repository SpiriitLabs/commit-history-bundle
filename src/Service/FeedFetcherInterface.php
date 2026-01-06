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

interface FeedFetcherInterface
{
    /**
     * @return Commit[]
     */
    public function fetch(?int $year = null): array;

    /**
     * Force refresh the cache and return commits.
     *
     * @return Commit[]
     */
    public function refresh(?int $year = null): array;

    /**
     * Get available years for filtering.
     *
     * @return int[]
     */
    public function getAvailableYears(): array;

    /**
     * Get the cache key for a specific year.
     * Useful for cache clearing commands.
     */
    public function getCacheKey(int $year): string;
}
