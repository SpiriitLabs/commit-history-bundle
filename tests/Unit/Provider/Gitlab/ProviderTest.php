<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Tests\Unit\Provider\Gitlab;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Spiriit\Bundle\CommitHistoryBundle\DTO\Commit;
use Spiriit\Bundle\CommitHistoryBundle\Provider\Gitlab\CommitParser;
use Spiriit\Bundle\CommitHistoryBundle\Provider\Gitlab\Provider;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ProviderTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private CommitParser $parser;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->parser = new CommitParser();
    }

    public function testGetCommitsReturnsCommits(): void
    {
        $json = file_get_contents(__DIR__.'/../../../Fixtures/gitlab_commits.json');
        $commitsData = json_decode($json, true);

        $commitsResponse = $this->createMock(ResponseInterface::class);
        $commitsResponse->method('toArray')->willReturn($commitsData);

        $diffResponse = $this->createMock(ResponseInterface::class);
        $diffResponse->method('toArray')->willReturn([]);

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function (string $method, string $url) use ($commitsResponse, $diffResponse): MockObject {
                if (str_contains($url, '/diff')) {
                    return $diffResponse;
                }

                return $commitsResponse;
            });

        $provider = new Provider(
            $this->httpClient,
            $this->parser,
            'https://gitlab.example.com',
            '123',
        );

        $commits = $provider->getCommits();

        $this->assertCount(3, $commits);
        $this->assertContainsOnlyInstancesOf(Commit::class, $commits);
    }

    public function testGetCommitsWithToken(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->stringContains('/api/v4/projects/'),
                $this->callback(function (array $options): bool {
                    return isset($options['headers']['PRIVATE-TOKEN'])
                        && 'glpat-xxxx' === $options['headers']['PRIVATE-TOKEN'];
                })
            )
            ->willReturn($response);

        $provider = new Provider(
            $this->httpClient,
            $this->parser,
            'https://gitlab.example.com',
            '123',
            'glpat-xxxx',
        );

        $provider->getCommits();
    }

    public function testGetCommitsWithRef(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->anything(),
                $this->callback(function (array $options): bool {
                    return isset($options['query']['ref_name'])
                        && 'develop' === $options['query']['ref_name'];
                })
            )
            ->willReturn($response);

        $provider = new Provider(
            $this->httpClient,
            $this->parser,
            'https://gitlab.example.com',
            '123',
            null,
            'develop',
        );

        $provider->getCommits();
    }

    public function testGetCommitsPaginates(): void
    {
        // Simulate 100 commits per page to trigger pagination
        $commits = array_fill(0, 100, [
            'id' => 'abc123def456789012345678901234567890abcd',
            'short_id' => 'abc123de',
            'title' => 'test',
            'author_name' => 'Test',
            'author_email' => 'test@test.com',
            'created_at' => '2025-01-01T00:00:00Z',
            'web_url' => 'https://example.com',
        ]);

        $response1 = $this->createMock(ResponseInterface::class);
        $response1->method('toArray')->willReturn($commits);

        $response2 = $this->createMock(ResponseInterface::class);
        $response2->method('toArray')->willReturn([]);

        $diffResponse = $this->createMock(ResponseInterface::class);
        $diffResponse->method('toArray')->willReturn([]);

        $callCount = 0;
        $this->httpClient
            ->method('request')
            ->willReturnCallback(function (string $method, string $url) use ($response1, $response2, $diffResponse, &$callCount): MockObject {
                if (str_contains($url, '/diff')) {
                    return $diffResponse;
                }
                ++$callCount;

                return 1 === $callCount ? $response1 : $response2;
            });

        $provider = new Provider(
            $this->httpClient,
            $this->parser,
            'https://gitlab.example.com',
            '123',
        );

        $result = $provider->getCommits();

        $this->assertCount(100, $result);
    }

    public function testGetCommitsWithDateRange(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([]);

        $since = new \DateTimeImmutable('2025-01-01T00:00:00+00:00');
        $until = new \DateTimeImmutable('2025-12-31T23:59:59+00:00');

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->anything(),
                $this->callback(function (array $options) use ($since, $until): bool {
                    return isset($options['query']['since'])
                        && $options['query']['since'] === $since->format('c')
                        && isset($options['query']['until'])
                        && $options['query']['until'] === $until->format('c');
                })
            )
            ->willReturn($response);

        $provider = new Provider(
            $this->httpClient,
            $this->parser,
            'https://gitlab.example.com',
            '123',
        );

        $provider->getCommits($since, $until);
    }
}
