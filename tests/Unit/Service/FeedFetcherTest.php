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
use Spiriit\Bundle\CommitHistoryBundle\Tests\Mock\ArrayCacheAdapter;
use Spiriit\CommitHistory\DTO\Commit;
use Spiriit\CommitHistory\Provider\ProviderInterface;
use Spiriit\CommitHistory\Service\FeedFetcher;

class FeedFetcherTest extends TestCase
{
    private ArrayCacheAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayCacheAdapter();
    }

    public function testFetchReturnsCommits(): void
    {
        $commits = [
            new Commit('abc123', 'Test commit', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('getCommits')
            ->willReturn($commits);

        $fetcher = new FeedFetcher($provider, $this->cache, 3600);

        $result = $fetcher->fetch();

        $this->assertCount(1, $result);
        $this->assertContainsOnlyInstancesOf(Commit::class, $result);
    }

    public function testFetchUsesCacheOnSecondCall(): void
    {
        $commits = [
            new Commit('abc123', 'Test commit', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('getCommits')
            ->willReturn($commits);

        $fetcher = new FeedFetcher($provider, $this->cache, 3600);

        // First call - fetches from provider
        $result1 = $fetcher->fetch();

        // Second call - should use cache
        $result2 = $fetcher->fetch();

        $this->assertEquals($result1, $result2);
    }

    public function testRefreshReturnsCommits(): void
    {
        $commits = [
            new Commit('abc123', 'Test commit', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('getCommits')
            ->willReturn($commits);

        $fetcher = new FeedFetcher($provider, $this->cache, 3600);

        $result = $fetcher->refresh();

        $this->assertCount(1, $result);
        $this->assertContainsOnlyInstancesOf(Commit::class, $result);
    }

    public function testRefreshInvalidatesCache(): void
    {
        $commits1 = [
            new Commit('abc123', 'Test commit', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];
        $commits2 = [
            new Commit('def456', 'New commit', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->exactly(2))
            ->method('getCommits')
            ->willReturnOnConsecutiveCalls($commits1, $commits2);

        $fetcher = new FeedFetcher($provider, $this->cache, 3600);

        // First call - populates cache
        $result1 = $fetcher->fetch();
        $this->assertSame('abc123', $result1[0]->id);

        // Refresh - should invalidate cache and fetch new data
        $result2 = $fetcher->refresh();
        $this->assertSame('def456', $result2[0]->id);
    }

    public function testFetchWithYearPassesDateRangeToProvider(): void
    {
        $commits = [
            new Commit('abc123', 'Test commit', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('getCommits')
            ->with(
                $this->callback(function (\DateTimeImmutable $since): bool {
                    return '2024-01-01' === $since->format('Y-m-d');
                }),
                $this->callback(function (\DateTimeImmutable $until): bool {
                    return '2024-12-31' === $until->format('Y-m-d');
                })
            )
            ->willReturn($commits);

        $fetcher = new FeedFetcher($provider, $this->cache, 3600);

        $result = $fetcher->fetch(2024);

        $this->assertCount(1, $result);
    }

    public function testFetchCachesPerYear(): void
    {
        $commits2024 = [
            new Commit('abc123', 'Commit 2024', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];
        $commits2025 = [
            new Commit('def456', 'Commit 2025', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->exactly(2))
            ->method('getCommits')
            ->willReturnOnConsecutiveCalls($commits2024, $commits2025);

        $fetcher = new FeedFetcher($provider, $this->cache, 3600);

        // Fetch 2024
        $result2024 = $fetcher->fetch(2024);
        $this->assertSame('abc123', $result2024[0]->id);

        // Fetch 2025 - should call provider again (different year = different cache key)
        $result2025 = $fetcher->fetch(2025);
        $this->assertSame('def456', $result2025[0]->id);

        // Fetch 2024 again - should use cache (provider not called)
        $result2024Again = $fetcher->fetch(2024);
        $this->assertSame('abc123', $result2024Again[0]->id);
    }

    public function testGetAvailableYearsReturnsLastSixYearsByDefault(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $fetcher = new FeedFetcher($provider, $this->cache, 3600);

        $years = $fetcher->getAvailableYears();
        $currentYear = (int) date('Y');

        $this->assertCount(6, $years);
        $this->assertSame($currentYear, $years[0]);
        $this->assertSame($currentYear - 5, $years[5]);
    }

    public function testGetAvailableYearsWithCustomCount(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $fetcher = new FeedFetcher($provider, $this->cache, 3600, 10);

        $years = $fetcher->getAvailableYears();
        $currentYear = (int) date('Y');

        $this->assertCount(10, $years);
        $this->assertSame($currentYear, $years[0]);
        $this->assertSame($currentYear - 9, $years[9]);
    }

    public function testRefreshWithYearRefreshesOnlyThatYear(): void
    {
        $commits = [
            new Commit('abc123', 'Test commit', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('getCommits')
            ->with(
                $this->callback(function (\DateTimeImmutable $since): bool {
                    return '2024-01-01' === $since->format('Y-m-d');
                }),
                $this->callback(function (\DateTimeImmutable $until): bool {
                    return '2024-12-31' === $until->format('Y-m-d');
                })
            )
            ->willReturn($commits);

        $fetcher = new FeedFetcher($provider, $this->cache, 3600);

        $result = $fetcher->refresh(2024);

        $this->assertCount(1, $result);
    }
}
