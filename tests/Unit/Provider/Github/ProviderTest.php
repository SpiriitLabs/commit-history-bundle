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
use Spiriit\CommitHistory\Contract\HttpClientInterface;
use Spiriit\CommitHistory\DTO\Commit;
use Spiriit\CommitHistory\Provider\Github\CommitParser;
use Spiriit\CommitHistory\Provider\Github\GithubProvider;

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

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => $json,
            ]);

        $provider = new GithubProvider(
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
        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->stringContains('/repos/'),
                $this->callback(function (array $headers): bool {
                    return isset($headers['Authorization'])
                        && 'Bearer ghp_xxxx' === $headers['Authorization'];
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => '[]',
            ]);

        $provider = new GithubProvider(
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
        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->stringContains('sha=develop'),
                $this->anything()
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => '[]',
            ]);

        $provider = new GithubProvider(
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

        $this->httpClient
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                [
                    'status' => 200,
                    'headers' => ['link' => ['<https://api.github.com/repos/example/project/commits?page=2>; rel="next"']],
                    'body' => json_encode($commits),
                ],
                [
                    'status' => 200,
                    'headers' => [],
                    'body' => json_encode($commits),
                ]
            );

        $provider = new GithubProvider(
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
        $since = new \DateTimeImmutable('2025-01-01T00:00:00+00:00');
        $until = new \DateTimeImmutable('2025-12-31T23:59:59+00:00');

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->callback(function (string $url) use ($since, $until): bool {
                    return str_contains($url, 'since='.urlencode($since->format('c')))
                        && str_contains($url, 'until='.urlencode($until->format('c')));
                }),
                $this->anything()
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => '[]',
            ]);

        $provider = new GithubProvider(
            $this->httpClient,
            $this->parser,
            'https://api.github.com',
            'example',
            'project',
        );

        $provider->getCommits($since, $until);
    }

    public function testGetCommitFileNames(): void
    {
        $commitResponse = [
            'sha' => 'abc123',
            'files' => [
                ['filename' => 'composer.json', 'patch' => 'some diff'],
                ['filename' => 'src/Controller.php', 'patch' => 'another diff'],
            ],
        ];

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->stringContains('/commits/abc123'),
                $this->anything()
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode($commitResponse),
            ]);

        $provider = new GithubProvider(
            $this->httpClient,
            $this->parser,
            'https://api.github.com',
            'example',
            'project',
        );

        $files = $provider->getCommitFileNames('abc123');

        $this->assertCount(2, $files);
        $this->assertContains('composer.json', $files);
        $this->assertContains('src/Controller.php', $files);
    }

    public function testGetCommitFileNamesWithToken(): void
    {
        $commitResponse = ['sha' => 'abc123', 'files' => []];

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->anything(),
                $this->callback(function (array $headers): bool {
                    return isset($headers['Authorization'])
                        && 'Bearer ghp_xxxx' === $headers['Authorization'];
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode($commitResponse),
            ]);

        $provider = new GithubProvider(
            $this->httpClient,
            $this->parser,
            'https://api.github.com',
            'example',
            'project',
            'ghp_xxxx',
        );

        $provider->getCommitFileNames('abc123');
    }

    public function testGetCommitDiff(): void
    {
        $patchContent = '@@ -1,3 +1,4 @@\n+new line';
        $commitResponse = [
            'sha' => 'abc123',
            'files' => [
                ['filename' => 'composer.json', 'patch' => $patchContent],
                ['filename' => 'README.md', 'patch' => 'readme diff'],
            ],
        ];

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode($commitResponse),
            ]);

        $provider = new GithubProvider(
            $this->httpClient,
            $this->parser,
            'https://api.github.com',
            'example',
            'project',
        );

        $diffs = $provider->getCommitDiff('abc123');

        $this->assertCount(2, $diffs);
        $this->assertArrayHasKey('composer.json', $diffs);
        $this->assertArrayHasKey('README.md', $diffs);
        $this->assertSame($patchContent, $diffs['composer.json']);
    }

    public function testGetCommitDiffExcludesFilesWithoutPatch(): void
    {
        $commitResponse = [
            'sha' => 'abc123',
            'files' => [
                ['filename' => 'composer.json', 'patch' => 'diff content'],
                ['filename' => 'binary.png'], // No patch for binary files
            ],
        ];

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode($commitResponse),
            ]);

        $provider = new GithubProvider(
            $this->httpClient,
            $this->parser,
            'https://api.github.com',
            'example',
            'project',
        );

        $diffs = $provider->getCommitDiff('abc123');

        $this->assertCount(1, $diffs);
        $this->assertArrayHasKey('composer.json', $diffs);
        $this->assertArrayNotHasKey('binary.png', $diffs);
    }
}
