<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Tests\Unit\Provider\Github;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Spiriit\Bundle\CommitHistoryBundle\DTO\Commit;
use Spiriit\Bundle\CommitHistoryBundle\Provider\Github\CommitParser;
use Spiriit\Bundle\CommitHistoryBundle\Provider\Github\Provider;
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
        $json = file_get_contents(__DIR__.'/../../../Fixtures/github_commits.json');
        $commitsData = json_decode($json, true);

        $commitsResponse = $this->createMock(ResponseInterface::class);
        $commitsResponse->method('toArray')->willReturn($commitsData);
        $commitsResponse->method('getHeaders')->willReturn([]);

        $commitDetailResponse = $this->createMock(ResponseInterface::class);
        $commitDetailResponse->method('toArray')->willReturn(['files' => []]);

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function (string $method, string $url) use ($commitsResponse, $commitDetailResponse) {
                // Individual commit endpoint (for file check) vs list endpoint
                if (preg_match('#/commits/[a-f0-9]+$#', $url)) {
                    return $commitDetailResponse;
                }

                return $commitsResponse;
            });

        $provider = new Provider(
            $this->httpClient,
            $this->parser,
            'https://api.github.com',
            'example',
            'project',
        );

        $commits = $provider->getCommits();

        $this->assertCount(3, $commits);
        $this->assertContainsOnlyInstancesOf(Commit::class, $commits);
    }

    public function testGetCommitsWithToken(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([]);
        $response->method('getHeaders')->willReturn([]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->stringContains('/repos/'),
                $this->callback(function (array $options): bool {
                    return isset($options['headers']['Authorization'])
                        && 'Bearer ghp_xxxx' === $options['headers']['Authorization'];
                })
            )
            ->willReturn($response);

        $provider = new Provider(
            $this->httpClient,
            $this->parser,
            'https://api.github.com',
            'example',
            'project',
            'ghp_xxxx',
        );

        $provider->getCommits();
    }

    public function testGetCommitsWithRef(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([]);
        $response->method('getHeaders')->willReturn([]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->anything(),
                $this->callback(function (array $options): bool {
                    return isset($options['query']['sha'])
                        && 'develop' === $options['query']['sha'];
                })
            )
            ->willReturn($response);

        $provider = new Provider(
            $this->httpClient,
            $this->parser,
            'https://api.github.com',
            'example',
            'project',
            null,
            'develop',
        );

        $provider->getCommits();
    }

    public function testGetCommitsPaginates(): void
    {
        $commits = [
            ['sha' => 'abc123', 'html_url' => 'https://example.com', 'commit' => ['message' => 'test', 'author' => ['name' => 'Test', 'email' => 'test@test.com', 'date' => '2025-01-01T00:00:00Z']]],
        ];

        $response1 = $this->createMock(ResponseInterface::class);
        $response1->method('toArray')->willReturn($commits);
        $response1->method('getHeaders')->willReturn([
            'link' => ['<https://api.github.com/repos/example/project/commits?page=2>; rel="next"'],
        ]);

        $response2 = $this->createMock(ResponseInterface::class);
        $response2->method('toArray')->willReturn($commits);
        $response2->method('getHeaders')->willReturn([]);

        $commitDetailResponse = $this->createMock(ResponseInterface::class);
        $commitDetailResponse->method('toArray')->willReturn(['files' => []]);

        $listCallCount = 0;
        $this->httpClient
            ->method('request')
            ->willReturnCallback(function (string $method, string $url) use ($response1, $response2, $commitDetailResponse, &$listCallCount) {
                // Individual commit endpoint (for file check) vs list endpoint
                if (preg_match('#/commits/[a-f0-9]+$#', $url)) {
                    return $commitDetailResponse;
                }
                ++$listCallCount;

                return $listCallCount === 1 ? $response1 : $response2;
            });

        $provider = new Provider(
            $this->httpClient,
            $this->parser,
            'https://api.github.com',
            'example',
            'project',
        );

        $result = $provider->getCommits();

        $this->assertCount(2, $result);
    }

    public function testGetCommitsWithDateRange(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([]);
        $response->method('getHeaders')->willReturn([]);

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
            'https://api.github.com',
            'example',
            'project',
        );

        $provider->getCommits($since, $until);
    }
}
