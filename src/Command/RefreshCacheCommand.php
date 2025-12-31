<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Command;

use Spiriit\Bundle\CommitHistoryBundle\Service\FeedFetcherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'spiriit:commit-history:refresh',
    description: 'Refresh the commit history cache',
)]
class RefreshCacheCommand extends Command
{
    public function __construct(
        private readonly FeedFetcherInterface $feedFetcher,
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
                $commits = $this->feedFetcher->refresh($year);
                $count = \count($commits);
                $totalCommits += $count;
                $io->text(\sprintf('Year %d: %d commits fetched.', $year, $count));
            }

            $io->success(\sprintf('Cache refreshed for all %d years. %d total commits fetched.', \count($years), $totalCommits));
        } else {
            $year = null !== $yearArg ? (int) $yearArg : null;
            $commits = $this->feedFetcher->refresh($year);
            $displayYear = $year ?? (int) date('Y');

            $io->success(\sprintf('Cache refreshed for year %d. %d commits fetched.', $displayYear, \count($commits)));
        }

        return Command::SUCCESS;
    }
}
