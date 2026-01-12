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
use Spiriit\CommitHistory\Contract\HttpClientInterface;
use Spiriit\CommitHistory\DTO\Commit;
use Spiriit\CommitHistory\Provider\Gitlab\CommitParser;
use Spiriit\CommitHistory\Provider\Gitlab\GitlabProvider;

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

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => $json,
            ]);

        $provider = new GitlabProvider(
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
        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->stringContains('/api/v4/projects/'),
                $this->callback(function (array $headers): bool {
                    return isset($headers['PRIVATE-TOKEN'])
                        && 'glpat-xxxx' === $headers['PRIVATE-TOKEN'];
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => '[]',
            ]);

        $provider = new GitlabProvider(
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
        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->stringContains('ref_name=develop'),
                $this->anything()
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => '[]',
            ]);

        $provider = new GitlabProvider(
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

        $this->httpClient
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                ['status' => 200, 'headers' => [], 'body' => json_encode($commits)],
                ['status' => 200, 'headers' => [], 'body' => '[]']
            );

        $provider = new GitlabProvider(
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

        $provider = new GitlabProvider(
            $this->httpClient,
            $this->parser,
            'https://gitlab.example.com',
            '123',
        );

        $provider->getCommits($since, $until);
    }

    public function testGetCommitFileNames(): void
    {
        $diffResponse = [
            ['new_path' => 'composer.json', 'old_path' => 'composer.json', 'diff' => 'some diff'],
            ['new_path' => 'src/Controller.php', 'old_path' => 'src/Controller.php', 'diff' => 'another diff'],
        ];

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->stringContains('/repository/commits/abc123/diff'),
                $this->anything()
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode($diffResponse),
            ]);

        $provider = new GitlabProvider(
            $this->httpClient,
            $this->parser,
            'https://gitlab.example.com',
            '123',
        );

        $files = $provider->getCommitFileNames('abc123');

        $this->assertCount(2, $files);
        $this->assertContains('composer.json', $files);
        $this->assertContains('src/Controller.php', $files);
    }

    public function testGetCommitFileNamesWithToken(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->anything(),
                $this->callback(function (array $headers): bool {
                    return isset($headers['PRIVATE-TOKEN'])
                        && 'glpat-xxxx' === $headers['PRIVATE-TOKEN'];
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => '[]',
            ]);

        $provider = new GitlabProvider(
            $this->httpClient,
            $this->parser,
            'https://gitlab.example.com',
            '123',
            'glpat-xxxx',
        );

        $provider->getCommitFileNames('abc123');
    }

    public function testGetCommitDiff(): void
    {
        $diffContent = '@@ -1,3 +1,4 @@\n+new line';
        $diffResponse = [
            ['new_path' => 'composer.json', 'old_path' => 'composer.json', 'diff' => $diffContent],
            ['new_path' => 'README.md', 'old_path' => 'README.md', 'diff' => 'readme diff'],
        ];

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode($diffResponse),
            ]);

        $provider = new GitlabProvider(
            $this->httpClient,
            $this->parser,
            'https://gitlab.example.com',
            '123',
        );

        $diffs = $provider->getCommitDiff('abc123');

        $this->assertCount(2, $diffs);
        $this->assertArrayHasKey('composer.json', $diffs);
        $this->assertArrayHasKey('README.md', $diffs);
        $this->assertSame($diffContent, $diffs['composer.json']);
    }

    public function testGetCommitDiffUsesNewPathOverOldPath(): void
    {
        $diffResponse = [
            ['new_path' => 'renamed.json', 'old_path' => 'original.json', 'diff' => 'diff content'],
        ];

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode($diffResponse),
            ]);

        $provider = new GitlabProvider(
            $this->httpClient,
            $this->parser,
            'https://gitlab.example.com',
            '123',
        );

        $diffs = $provider->getCommitDiff('abc123');

        $this->assertArrayHasKey('renamed.json', $diffs);
        $this->assertArrayNotHasKey('original.json', $diffs);
    }
}
