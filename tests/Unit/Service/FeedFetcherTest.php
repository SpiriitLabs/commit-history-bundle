<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Spiriit\Bundle\CommitHistoryBundle\Service\FeedFetcher;
use Spiriit\CommitHistory\DTO\Commit;
use Spiriit\CommitHistory\Service\FeedFetcherInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class FeedFetcherTest extends TestCase
{
    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
    }

    public function testFetchReturnsCommits(): void
    {
        $commits = [
            new Commit('abc123', 'Test commit', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];

        $innerFetcher = $this->createMock(FeedFetcherInterface::class);
        $innerFetcher->expects($this->once())
            ->method('fetch')
            ->willReturn($commits);

        $fetcher = new FeedFetcher($innerFetcher, $this->cache, 3600, 'test_provider_hash');

        $result = $fetcher->fetch();

        $this->assertCount(1, $result);
        $this->assertContainsOnlyInstancesOf(Commit::class, $result);
    }

    public function testFetchUsesCacheOnSecondCall(): void
    {
        $commits = [
            new Commit('abc123', 'Test commit', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];

        $innerFetcher = $this->createMock(FeedFetcherInterface::class);
        $innerFetcher->expects($this->once())
            ->method('fetch')
            ->willReturn($commits);

        $fetcher = new FeedFetcher($innerFetcher, $this->cache, 3600, 'test_provider_hash');

        // First call - fetches from inner fetcher
        $result1 = $fetcher->fetch();

        // Second call - should use cache
        $result2 = $fetcher->fetch();

        $this->assertEquals($result1, $result2);
    }

    public function testFetchCachesPerYear(): void
    {
        $commits2024 = [
            new Commit('abc123', 'Commit 2024', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];
        $commits2025 = [
            new Commit('def456', 'Commit 2025', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];

        $innerFetcher = $this->createMock(FeedFetcherInterface::class);
        $innerFetcher->expects($this->exactly(2))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($commits2024, $commits2025);

        $fetcher = new FeedFetcher($innerFetcher, $this->cache, 3600, 'test_provider_hash');

        // Fetch 2024
        $result2024 = $fetcher->fetch(2024);
        $this->assertSame('abc123', $result2024[0]->id);

        // Fetch 2025 - should call inner fetcher again (different year = different cache key)
        $result2025 = $fetcher->fetch(2025);
        $this->assertSame('def456', $result2025[0]->id);

        // Fetch 2024 again - should use cache (inner fetcher not called)
        $result2024Again = $fetcher->fetch(2024);
        $this->assertSame('abc123', $result2024Again[0]->id);
    }

    public function testGetAvailableYearsDelegatesToInner(): void
    {
        $currentYear = (int) date('Y');
        $expectedYears = [$currentYear, $currentYear - 1, $currentYear - 2];

        $innerFetcher = $this->createMock(FeedFetcherInterface::class);
        $innerFetcher->expects($this->once())
            ->method('getAvailableYears')
            ->willReturn($expectedYears);

        $fetcher = new FeedFetcher($innerFetcher, $this->cache, 3600, 'test_provider_hash');

        $years = $fetcher->getAvailableYears();

        $this->assertSame($expectedYears, $years);
    }

    public function testGetCacheKeyPrefixReturnsExpectedPrefix(): void
    {
        $this->assertSame('spiriit_commit_history_feed_', FeedFetcher::getCacheKeyPrefix());
    }
}
