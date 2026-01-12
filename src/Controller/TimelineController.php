<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Controller;

use Spiriit\CommitHistory\Service\FeedFetcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class TimelineController
{
    public function __construct(
        private readonly FeedFetcherInterface $feedFetcher,
        private readonly Environment $twig,
        private readonly string $feedName,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $year = $request->query->getInt('year') ?: null;
        $selectedYear = $year ?? (int) date('Y');

        $commits = $this->feedFetcher->fetch($selectedYear);
        $availableYears = $this->feedFetcher->getAvailableYears();

        $content = $this->twig->render('@SpiriitCommitHistory/timeline.html.twig', [
            'commits' => $commits,
            'feed_name' => $this->feedName,
            'available_years' => $availableYears,
            'selected_year' => $selectedYear,
        ]);

        return new Response($content);
    }
}
