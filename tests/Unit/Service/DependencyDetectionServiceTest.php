<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Tests\Unit\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Spiriit\Bundle\CommitHistoryBundle\Service\CachingDependencyDetectionService;
use Spiriit\CommitHistory\DTO\Commit;
use Spiriit\CommitHistory\Provider\ProviderInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class DependencyDetectionServiceTest extends TestCase
{
    private ArrayAdapter $cache;
    private ProviderInterface&MockObject $provider;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
        $this->provider = $this->createMock(ProviderInterface::class);
    }

    public function testHasDependencyChangesReturnsTrueWhenDependencyFileModified(): void
    {
        $this->provider
            ->expects($this->once())
            ->method('getCommitFileNames')
            ->with('abc123')
            ->willReturn(['src/Controller.php', 'composer.json', 'README.md']);

        $service = new CachingDependencyDetectionService(
            $this->provider,
            ['composer.json', 'composer.lock'],
            true,
            $this->cache,
        );

        $result = $service->hasDependencyChanges('abc123');

        $this->assertTrue($result);
    }

    public function testHasDependencyChangesReturnsFalseWhenNoDependencyFileModified(): void
    {
        $this->provider
            ->expects($this->once())
            ->method('getCommitFileNames')
            ->with('abc123')
            ->willReturn(['src/Controller.php', 'README.md']);

        $service = new CachingDependencyDetectionService(
            $this->provider,
            ['composer.json', 'composer.lock'],
            true,
            $this->cache,
        );

        $result = $service->hasDependencyChanges('abc123');

        $this->assertFalse($result);
    }

    public function testHasDependencyChangesHandlesPathsWithDirectories(): void
    {
        $this->provider
            ->expects($this->once())
            ->method('getCommitFileNames')
            ->with('abc123')
            ->willReturn(['src/Controller.php', 'packages/my-package/composer.json']);

        $service = new CachingDependencyDetectionService(
            $this->provider,
            ['composer.json', 'composer.lock'],
            true,
            $this->cache,
        );

        $result = $service->hasDependencyChanges('abc123');

        $this->assertTrue($result);
    }

    public function testHasDependencyChangesReturnsFalseWhenTrackingDisabled(): void
    {
        $this->provider
            ->expects($this->never())
            ->method('getCommitFileNames');

        $service = new CachingDependencyDetectionService(
            $this->provider,
            ['composer.json'],
            false,
            $this->cache,
        );

        $result = $service->hasDependencyChanges('abc123');

        $this->assertFalse($result);
    }

    public function testHasDependencyChangesCachesResult(): void
    {
        $this->provider
            ->expects($this->once())
            ->method('getCommitFileNames')
            ->with('abc123')
            ->willReturn(['composer.json']);

        $service = new CachingDependencyDetectionService(
            $this->provider,
            ['composer.json'],
            true,
            $this->cache,
        );

        // First call - fetches from provider
        $result1 = $service->hasDependencyChanges('abc123');

        // Second call - should use cache
        $result2 = $service->hasDependencyChanges('abc123');

        $this->assertTrue($result1);
        $this->assertTrue($result2);
    }

    public function testHasDependencyChangesReturnsFalseOnProviderError(): void
    {
        $this->provider
            ->expects($this->once())
            ->method('getCommitFileNames')
            ->willThrowException(new \RuntimeException('API error'));

        $service = new CachingDependencyDetectionService(
            $this->provider,
            ['composer.json'],
            true,
            $this->cache,
        );

        $result = $service->hasDependencyChanges('abc123');

        $this->assertFalse($result);
    }

    public function testDetectForCommitsUpdatesAllCommits(): void
    {
        $commits = [
            new Commit('abc123', 'Commit 1', new \DateTimeImmutable(), 'Author', 'https://example.com'),
            new Commit('def456', 'Commit 2', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];

        $this->provider
            ->expects($this->exactly(2))
            ->method('getCommitFileNames')
            ->willReturnCallback(function (string $commitId) {
                return 'abc123' === $commitId ? ['composer.json'] : ['README.md'];
            });

        $service = new CachingDependencyDetectionService(
            $this->provider,
            ['composer.json'],
            true,
            $this->cache,
        );

        $result = $service->detectForCommits($commits);

        $this->assertCount(2, $result);
        $this->assertTrue($result[0]->hasDependenciesChanges);
        $this->assertFalse($result[1]->hasDependenciesChanges);
    }

    public function testDetectForCommitsReturnsUnmodifiedCommitsWhenDisabled(): void
    {
        $commits = [
            new Commit('abc123', 'Commit 1', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];

        $this->provider
            ->expects($this->never())
            ->method('getCommitFileNames');

        $service = new CachingDependencyDetectionService(
            $this->provider,
            ['composer.json'],
            false,
            $this->cache,
        );

        $result = $service->detectForCommits($commits);

        $this->assertCount(1, $result);
        $this->assertFalse($result[0]->hasDependenciesChanges);
    }

    public function testGetCacheKeyPrefix(): void
    {
        $prefix = CachingDependencyDetectionService::getCacheKeyPrefix();

        $this->assertSame('spiriit_commit_history_has_deps_', $prefix);
    }

    public function testSupportsMultipleDependencyFileTypes(): void
    {
        $this->provider
            ->method('getCommitFileNames')
            ->willReturnOnConsecutiveCalls(
                ['package.json'],
                ['package-lock.json'],
                ['composer.lock'],
            );

        $service = new CachingDependencyDetectionService(
            $this->provider,
            ['composer.json', 'composer.lock', 'package.json', 'package-lock.json'],
            true,
            $this->cache,
        );

        $this->assertTrue($service->hasDependencyChanges('commit1'));
        $this->assertTrue($service->hasDependencyChanges('commit2'));
        $this->assertTrue($service->hasDependencyChanges('commit3'));
    }
}
