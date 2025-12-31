<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Spiriit\Bundle\CommitHistoryBundle\Command\RefreshCacheCommand;
use Spiriit\Bundle\CommitHistoryBundle\DTO\Commit;
use Spiriit\Bundle\CommitHistoryBundle\Service\FeedFetcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class RefreshCacheCommandTest extends TestCase
{
    public function testExecuteRefreshesCurrentYearByDefault(): void
    {
        $commits = [
            new Commit('abc123', 'Test commit 1', new \DateTimeImmutable(), 'Author', 'https://example.com'),
            new Commit('def456', 'Test commit 2', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];

        $feedFetcher = $this->createMock(FeedFetcherInterface::class);
        $feedFetcher->expects($this->once())
            ->method('refresh')
            ->with(null)
            ->willReturn($commits);

        $command = new RefreshCacheCommand($feedFetcher);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('2 commits fetched', $commandTester->getDisplay());
    }

    public function testExecuteRefreshesSpecificYear(): void
    {
        $commits = [
            new Commit('abc123', 'Test commit', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];

        $feedFetcher = $this->createMock(FeedFetcherInterface::class);
        $feedFetcher->expects($this->once())
            ->method('refresh')
            ->with(2024)
            ->willReturn($commits);

        $command = new RefreshCacheCommand($feedFetcher);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['year' => '2024']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('year 2024', $commandTester->getDisplay());
        $this->assertStringContainsString('1 commits fetched', $commandTester->getDisplay());
    }

    public function testExecuteRefreshesAllYears(): void
    {
        $commits = [
            new Commit('abc123', 'Test commit', new \DateTimeImmutable(), 'Author', 'https://example.com'),
        ];

        $currentYear = (int) date('Y');
        $availableYears = [$currentYear, $currentYear - 1, $currentYear - 2];

        $feedFetcher = $this->createMock(FeedFetcherInterface::class);
        $feedFetcher->expects($this->once())
            ->method('getAvailableYears')
            ->willReturn($availableYears);
        $feedFetcher->expects($this->exactly(3))
            ->method('refresh')
            ->willReturn($commits);

        $command = new RefreshCacheCommand($feedFetcher);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--all' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('all 3 years', $commandTester->getDisplay());
        $this->assertStringContainsString('3 total commits fetched', $commandTester->getDisplay());
    }

    public function testExecuteRefreshesAllYearsWithShortOption(): void
    {
        $commits = [];

        $currentYear = (int) date('Y');
        $availableYears = [$currentYear, $currentYear - 1];

        $feedFetcher = $this->createMock(FeedFetcherInterface::class);
        $feedFetcher->expects($this->once())
            ->method('getAvailableYears')
            ->willReturn($availableYears);
        $feedFetcher->expects($this->exactly(2))
            ->method('refresh')
            ->willReturn($commits);

        $command = new RefreshCacheCommand($feedFetcher);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['-a' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('all 2 years', $commandTester->getDisplay());
    }

    public function testCommandName(): void
    {
        $feedFetcher = $this->createMock(FeedFetcherInterface::class);
        $command = new RefreshCacheCommand($feedFetcher);

        $this->assertSame('spiriit:commit-history:refresh', $command->getName());
    }
}
