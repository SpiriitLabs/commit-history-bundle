<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Command;

use Spiriit\Bundle\CommitHistoryBundle\Controller\DependenciesChangesController;
use Spiriit\Bundle\CommitHistoryBundle\Service\DependencyDetectionService;
use Spiriit\Bundle\CommitHistoryBundle\Service\FeedFetcherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Cache\CacheInterface;

#[AsCommand(
    name: 'spiriit:commit-history:clear',
    description: 'Clear all commit history caches (commits list, dependency detection, and dependency changes)',
)]
class ClearCacheCommand extends Command
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly FeedFetcherInterface $feedFetcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('year', InputArgument::OPTIONAL, 'The year to clear cache for')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Clear cache for all years');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $yearArg = $input->getArgument('year') ?? (new \DateTimeImmutable('now'))->format('Y');
        $clearAll = $input->getOption('all');

        if ($clearAll) {
            return $this->clearAllYears($io);
        }

        return $this->clearYear((int) $yearArg, $io);
    }

    private function clearYear(int $year, SymfonyStyle $io): int
    {
        $io->text(\sprintf('Clearing cache for year %d...', $year));

        $commitCount = $this->doClearYear($year);

        $io->success(\sprintf('Cache cleared for year %d (%d commits).', $year, $commitCount));

        return Command::SUCCESS;
    }

    private function clearAllYears(SymfonyStyle $io): int
    {
        $years = $this->feedFetcher->getAvailableYears();
        $totalCommits = 0;

        $io->text('Clearing cache for all years...');

        foreach ($years as $year) {
            $totalCommits += $this->doClearYear($year);
            $io->text(\sprintf('  Year %d: cleared.', $year));
        }

        $io->success(\sprintf('Cache cleared for %d years (%d total commits).', \count($years), $totalCommits));

        return Command::SUCCESS;
    }

    private function doClearYear(int $year): int
    {
        $commits = $this->feedFetcher->fetch($year);

        foreach ($commits as $commit) {
            $this->clearDependencyCaches($commit->id);
        }

        $this->cache->delete($this->feedFetcher->getCacheKey($year));

        return \count($commits);
    }

    private function clearDependencyCaches(string $commitId): void
    {
        // Clear dependency detection cache (badge)
        $hasDepsKey = DependencyDetectionService::getCacheKeyPrefix().$commitId;
        $this->cache->delete($hasDepsKey);

        // Clear dependency changes cache (list)
        $depsKey = DependenciesChangesController::getCacheKeyPrefix().$commitId;
        $this->cache->delete($depsKey);
    }
}
