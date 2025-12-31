<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Tests\Functional\Controller;

use PHPUnit\Framework\TestCase;
use Spiriit\Bundle\CommitHistoryBundle\Controller\TimelineController;
use Spiriit\Bundle\CommitHistoryBundle\DTO\Commit;
use Spiriit\Bundle\CommitHistoryBundle\Service\FeedFetcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class TimelineControllerTest extends TestCase
{
    public function testInvokeReturnsResponse(): void
    {
        $commits = [
            new Commit(
                id: '9668d5f4',
                title: 'Test commit',
                date: new \DateTimeImmutable(),
                author: 'Test Author',
                url: 'https://example.com/commit/1',
            ),
        ];

        $feedFetcher = $this->createMock(FeedFetcherInterface::class);
        $feedFetcher->method('fetch')->willReturn($commits);
        $feedFetcher->method('getAvailableYears')->willReturn([2025, 2024, 2023]);

        $twig = new Environment(new ArrayLoader([
            '@SpiriitCommitHistory/timeline.html.twig' => '{{ feed_name }} - {{ commits|length }} commits',
        ]));

        $controller = new TimelineController($feedFetcher, $twig, 'Test Project');

        $response = $controller(new Request());

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Test Project', $response->getContent());
        $this->assertStringContainsString('1 commits', $response->getContent());
    }

    public function testInvokeWithEmptyCommits(): void
    {
        $feedFetcher = $this->createMock(FeedFetcherInterface::class);
        $feedFetcher->method('fetch')->willReturn([]);
        $feedFetcher->method('getAvailableYears')->willReturn([2025]);

        $twig = new Environment(new ArrayLoader([
            '@SpiriitCommitHistory/timeline.html.twig' => '{% if commits is empty %}No commits{% endif %}',
        ]));

        $controller = new TimelineController($feedFetcher, $twig, 'Empty Project');

        $response = $controller(new Request());

        $this->assertStringContainsString('No commits', $response->getContent());
    }

    public function testInvokeWithYearParameter(): void
    {
        $commits = [
            new Commit(
                id: '9668d5f4',
                title: 'Test commit 2024',
                date: new \DateTimeImmutable('2024-06-15'),
                author: 'Test Author',
                url: 'https://example.com/commit/1',
            ),
        ];

        $feedFetcher = $this->createMock(FeedFetcherInterface::class);
        $feedFetcher->expects($this->once())
            ->method('fetch')
            ->with(2024)
            ->willReturn($commits);
        $feedFetcher->method('getAvailableYears')->willReturn([2025, 2024, 2023]);

        $twig = new Environment(new ArrayLoader([
            '@SpiriitCommitHistory/timeline.html.twig' => 'Year: {{ selected_year }} - {{ commits|length }} commits',
        ]));

        $controller = new TimelineController($feedFetcher, $twig, 'Test Project');
        $request = new Request(['year' => '2024']);

        $response = $controller($request);

        $this->assertStringContainsString('Year: 2024', $response->getContent());
        $this->assertStringContainsString('1 commits', $response->getContent());
    }

    public function testInvokePassesAvailableYearsToTemplate(): void
    {
        $feedFetcher = $this->createMock(FeedFetcherInterface::class);
        $feedFetcher->method('fetch')->willReturn([]);
        $feedFetcher->method('getAvailableYears')->willReturn([2025, 2024, 2023]);

        $twig = new Environment(new ArrayLoader([
            '@SpiriitCommitHistory/timeline.html.twig' => 'Years: {{ available_years|join(", ") }}',
        ]));

        $controller = new TimelineController($feedFetcher, $twig, 'Test Project');

        $response = $controller(new Request());

        $this->assertStringContainsString('Years: 2025, 2024, 2023', $response->getContent());
    }

    public function testInvokeDefaultsToCurrentYear(): void
    {
        $currentYear = (int) date('Y');

        $feedFetcher = $this->createMock(FeedFetcherInterface::class);
        $feedFetcher->expects($this->once())
            ->method('fetch')
            ->with($currentYear)
            ->willReturn([]);
        $feedFetcher->method('getAvailableYears')->willReturn([$currentYear]);

        $twig = new Environment(new ArrayLoader([
            '@SpiriitCommitHistory/timeline.html.twig' => 'Selected: {{ selected_year }}',
        ]));

        $controller = new TimelineController($feedFetcher, $twig, 'Test Project');

        $response = $controller(new Request());

        $this->assertStringContainsString('Selected: '.$currentYear, $response->getContent());
    }
}
