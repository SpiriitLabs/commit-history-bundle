<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Command;

use Spiriit\CommitHistory\Service\DependencyDetectionService;
use Spiriit\CommitHistory\Service\FeedFetcherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Cache\CacheInterface;

#[AsCommand(
    name: 'spiriit:commit-history:refresh',
    description: 'Refresh the commit history cache and dependency detection',
)]
class RefreshCacheCommand extends Command
{
    public function __construct(
        private readonly FeedFetcherInterface $feedFetcher,
        private readonly CacheInterface $cache,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('year', InputArgument::OPTIONAL, 'The year to refresh (defaults to current year)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Refresh all available years');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $all = $input->getOption('all');
        $yearArg = $input->getArgument('year');

        if ($all) {
            $years = $this->feedFetcher->getAvailableYears();
            $totalCommits = 0;

            foreach ($years as $year) {
                $commits = $this->refreshYear($year);
                $count = \count($commits);
                $totalCommits += $count;
                $io->text(\sprintf('Year %d: %d commits fetched.', $year, $count));
            }

            $io->success(\sprintf('Cache refreshed for all %d years. %d total commits fetched.', \count($years), $totalCommits));
        } else {
            $year = null !== $yearArg ? (int) $yearArg : (int) date('Y');
            $commits = $this->refreshYear($year);

            $io->success(\sprintf('Cache refreshed for year %d. %d commits fetched.', $year, \count($commits)));
        }

        return Command::SUCCESS;
    }

    /**
     * @return \Spiriit\CommitHistory\DTO\Commit[]
     */
    private function refreshYear(int $year): array
    {
        // Get existing commits to clear their dependency detection cache
        $existingCommits = $this->feedFetcher->fetch($year);

        // Clear dependency detection cache for existing commits
        foreach ($existingCommits as $commit) {
            $hasDepsKey = DependencyDetectionService::getCacheKeyPrefix().$commit->id;
            $this->cache->delete($hasDepsKey);
        }

        // Refresh commits (clears commits cache, re-fetches, and re-detects dependencies)
        return $this->feedFetcher->refresh($year);
    }
}
