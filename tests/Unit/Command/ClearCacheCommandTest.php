<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Tests\Unit\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Spiriit\Bundle\CommitHistoryBundle\Command\ClearCacheCommand;
use Spiriit\CommitHistory\DTO\Commit;
use Spiriit\CommitHistory\Service\FeedFetcherInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ClearCacheCommandTest extends TestCase
{
    private ArrayAdapter $cache;
    private FeedFetcherInterface&MockObject $feedFetcher;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
        $this->feedFetcher = $this->createMock(FeedFetcherInterface::class);
    }

    public function testExecuteClearsAllYearsWithAllOption(): void
    {
        $commits = [
            new Commit('abc123', 'Commit 1', new \DateTimeImmutable(), 'Author', 'https://example.com'),
            new Commit('def456', 'Commit 2', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];

        $currentYear = (int) date('Y');
        $availableYears = [$currentYear, $currentYear - 1];

        $this->feedFetcher
            ->expects($this->once())
            ->method('getAvailableYears')
            ->willReturn($availableYears);

        $this->feedFetcher
            ->expects($this->exactly(2))
            ->method('fetch')
            ->willReturn($commits);

        $this->feedFetcher
            ->expects($this->exactly(2))
            ->method('getCacheKey')
            ->willReturnCallback(fn (int $year) => 'cache_key_'.$year);

        $command = new ClearCacheCommand($this->cache, $this->feedFetcher);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--all' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Cache cleared for 2 years (4 total commits)', $commandTester->getDisplay());
    }

    public function testExecuteClearsCurrentYearByDefault(): void
    {
        $commits = [
            new Commit('abc123', 'Commit 1', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];

        $currentYear = (int) date('Y');

        $this->feedFetcher
            ->expects($this->once())
            ->method('fetch')
            ->with($currentYear)
            ->willReturn($commits);

        $this->feedFetcher
            ->expects($this->once())
            ->method('getCacheKey')
            ->with($currentYear)
            ->willReturn('cache_key_'.$currentYear);

        $command = new ClearCacheCommand($this->cache, $this->feedFetcher);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString(\sprintf('Cache cleared for year %d (1 commits)', $currentYear), $commandTester->getDisplay());
    }

    public function testExecuteClearsSpecificYear(): void
    {
        $commits = [
            new Commit('abc123', 'Commit 1', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];

        $this->feedFetcher
            ->expects($this->once())
            ->method('fetch')
            ->with(2024)
            ->willReturn($commits);

        $this->feedFetcher
            ->expects($this->once())
            ->method('getCacheKey')
            ->with(2024)
            ->willReturn('cache_key_2024');

        $command = new ClearCacheCommand($this->cache, $this->feedFetcher);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['year' => '2024']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Cache cleared for year 2024 (1 commits)', $commandTester->getDisplay());
    }

    public function testCommandName(): void
    {
        $command = new ClearCacheCommand($this->cache, $this->feedFetcher);

        $this->assertSame('spiriit:commit-history:clear', $command->getName());
    }

    public function testCommandDescription(): void
    {
        $command = new ClearCacheCommand($this->cache, $this->feedFetcher);

        $this->assertStringContainsString('Clear all commit history caches', $command->getDescription());
    }
}
