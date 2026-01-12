<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Tests\Functional\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Spiriit\Bundle\CommitHistoryBundle\Controller\DependenciesChangesController;
use Spiriit\CommitHistory\DiffParser\ComposerDiffParser;
use Spiriit\CommitHistory\DiffParser\DiffParserRegistry;
use Spiriit\CommitHistory\DiffParser\PackageDiffParser;
use Spiriit\CommitHistory\Provider\ProviderInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DependenciesChangesControllerTest extends TestCase
{
    private ArrayAdapter $cache;
    private ProviderInterface&MockObject $provider;
    private DiffParserRegistry $registry;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
        $this->provider = $this->createMock(ProviderInterface::class);
        $this->registry = new DiffParserRegistry([
            new ComposerDiffParser(),
            new PackageDiffParser(),
        ]);
    }

    public function testInvokeReturnsJsonResponse(): void
    {
        $diff = <<<'DIFF'
+        "symfony/http-client": "^7.0",
-        "old/package": "^1.0",
DIFF;

        $this->provider
            ->expects($this->once())
            ->method('getCommitDiff')
            ->with('abc123def456789012345678901234567890abcd')
            ->willReturn(['composer.json' => $diff]);

        $controller = new DependenciesChangesController(
            $this->provider,
            $this->registry,
            $this->cache,
            ['composer.json', 'composer.lock', 'package.json', 'package-lock.json'],
            true,
        );

        $response = $controller('abc123def456789012345678901234567890abcd');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('abc123def456789012345678901234567890abcd', $data['commitId']);
        $this->assertCount(2, $data['changes']);
    }

    public function testInvokeWithShortCommitId(): void
    {
        $this->provider
            ->expects($this->once())
            ->method('getCommitDiff')
            ->with('abc123d')
            ->willReturn(['composer.json' => '+        "symfony/http-client": "^7.0",']);

        $controller = new DependenciesChangesController(
            $this->provider,
            $this->registry,
            $this->cache,
            ['composer.json'],
            true,
        );

        $response = $controller('abc123d');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testInvokeReturnsNotFoundWhenTrackingDisabled(): void
    {
        $this->provider
            ->expects($this->never())
            ->method('getCommitDiff');

        $controller = new DependenciesChangesController(
            $this->provider,
            $this->registry,
            $this->cache,
            ['composer.json'],
            false,
        );

        $response = $controller('abc123d');

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Dependency tracking is disabled', $data['error']);
    }

    public function testInvokeReturnsBadRequestForInvalidCommitId(): void
    {
        $controller = new DependenciesChangesController(
            $this->provider,
            $this->registry,
            $this->cache,
            ['composer.json'],
            true,
        );

        // Too short
        $response = $controller('abc');
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        // Invalid characters
        $response = $controller('abc123g');
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        // Contains non-hex characters
        $response = $controller('xyz1234');
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testInvokeReturnsServerErrorOnProviderFailure(): void
    {
        $this->provider
            ->expects($this->once())
            ->method('getCommitDiff')
            ->willThrowException(new \RuntimeException('API error'));

        $controller = new DependenciesChangesController(
            $this->provider,
            $this->registry,
            $this->cache,
            ['composer.json'],
            true,
        );

        $response = $controller('abc123d');

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Failed to fetch dependency changes', $data['error']);
    }

    public function testInvokeCachesResult(): void
    {
        $diff = '+        "symfony/http-client": "^7.0",';

        $this->provider
            ->expects($this->once())
            ->method('getCommitDiff')
            ->with('abc123d')
            ->willReturn(['composer.json' => $diff]);

        $controller = new DependenciesChangesController(
            $this->provider,
            $this->registry,
            $this->cache,
            ['composer.json'],
            true,
        );

        // First call - fetches from provider
        $response1 = $controller('abc123d');

        // Second call - should use cache
        $response2 = $controller('abc123d');

        $this->assertSame($response1->getContent(), $response2->getContent());
    }

    public function testInvokeFiltersNonDependencyFiles(): void
    {
        $this->provider
            ->expects($this->once())
            ->method('getCommitDiff')
            ->willReturn([
                'composer.json' => '+        "symfony/http-client": "^7.0",',
                'README.md' => '+ Some text',
                'src/Controller.php' => '+ class Foo {}',
            ]);

        $controller = new DependenciesChangesController(
            $this->provider,
            $this->registry,
            $this->cache,
            ['composer.json', 'package.json'],
            true,
        );

        $response = $controller('abc123d');

        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['changes']);
        $this->assertSame('symfony/http-client', $data['changes'][0]['name']);
    }

    public function testInvokeReturnsEmptyChangesWhenNoDependencyFiles(): void
    {
        $this->provider
            ->expects($this->once())
            ->method('getCommitDiff')
            ->willReturn(['README.md' => '+ Some text']);

        $controller = new DependenciesChangesController(
            $this->provider,
            $this->registry,
            $this->cache,
            ['composer.json'],
            true,
        );

        $response = $controller('abc123d');

        $data = json_decode($response->getContent(), true);
        $this->assertCount(0, $data['changes']);
    }

    public function testInvokeResponseStructure(): void
    {
        $diff = <<<'DIFF'
-        "symfony/http-client": "^6.4",
+        "symfony/http-client": "^7.0",
DIFF;

        $this->provider
            ->expects($this->once())
            ->method('getCommitDiff')
            ->willReturn(['path/to/composer.json' => $diff]);

        $controller = new DependenciesChangesController(
            $this->provider,
            $this->registry,
            $this->cache,
            ['composer.json'],
            true,
        );

        $response = $controller('abc123d');

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('commitId', $data);
        $this->assertArrayHasKey('changes', $data);
        $this->assertCount(1, $data['changes']);

        $change = $data['changes'][0];
        $this->assertArrayHasKey('name', $change);
        $this->assertArrayHasKey('type', $change);
        $this->assertArrayHasKey('oldVersion', $change);
        $this->assertArrayHasKey('newVersion', $change);
        $this->assertArrayHasKey('sourceFile', $change);

        $this->assertSame('symfony/http-client', $change['name']);
        $this->assertSame('updated', $change['type']);
        $this->assertSame('^6.4', $change['oldVersion']);
        $this->assertSame('^7.0', $change['newVersion']);
        $this->assertSame('path/to/composer.json', $change['sourceFile']);
    }

    public function testGetCacheKeyPrefix(): void
    {
        $prefix = DependenciesChangesController::getCacheKeyPrefix();

        $this->assertSame('spiriit_commit_history_deps_', $prefix);
    }
}
