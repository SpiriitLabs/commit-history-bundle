<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Tests\Functional\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Spiriit\Bundle\CommitHistoryBundle\DependencyInjection\SpiriitCommitHistoryExtension;
use Spiriit\Bundle\CommitHistoryBundle\Provider\ProviderInterface;
use Spiriit\Bundle\CommitHistoryBundle\Service\FeedFetcherInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SpiriitCommitHistoryExtensionTest extends TestCase
{
    private ContainerBuilder $container;
    private SpiriitCommitHistoryExtension $extension;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->extension = new SpiriitCommitHistoryExtension();
    }

    public function testLoadSetsGitlabParameters(): void
    {
        $this->extension->load([
            'spiriit_commit_history' => [
                'provider' => 'gitlab',
                'feed_name' => 'My Project',
                'cache_ttl' => 7200,
                'gitlab' => [
                    'project_id' => '123',
                    'base_url' => 'https://gitlab.mycompany.com',
                    'token' => 'glpat-xxxx',
                    'ref' => 'develop',
                ],
            ],
        ], $this->container);

        $this->assertSame('gitlab', $this->container->getParameter('spiriit_commit_history.provider'));
        $this->assertSame('My Project', $this->container->getParameter('spiriit_commit_history.feed_name'));
        $this->assertSame(7200, $this->container->getParameter('spiriit_commit_history.cache_ttl'));
        $this->assertSame('123', $this->container->getParameter('spiriit_commit_history.gitlab.project_id'));
        $this->assertSame('https://gitlab.mycompany.com', $this->container->getParameter('spiriit_commit_history.gitlab.base_url'));
        $this->assertSame('glpat-xxxx', $this->container->getParameter('spiriit_commit_history.gitlab.token'));
        $this->assertSame('develop', $this->container->getParameter('spiriit_commit_history.gitlab.ref'));
    }

    public function testLoadSetsGithubParameters(): void
    {
        $this->extension->load([
            'spiriit_commit_history' => [
                'provider' => 'github',
                'github' => [
                    'owner' => 'myorg',
                    'repo' => 'myproject',
                    'base_url' => 'https://api.github.com',
                    'token' => 'ghp_xxxx',
                ],
            ],
        ], $this->container);

        $this->assertSame('github', $this->container->getParameter('spiriit_commit_history.provider'));
        $this->assertSame('myorg', $this->container->getParameter('spiriit_commit_history.github.owner'));
        $this->assertSame('myproject', $this->container->getParameter('spiriit_commit_history.github.repo'));
        $this->assertSame('https://api.github.com', $this->container->getParameter('spiriit_commit_history.github.base_url'));
        $this->assertSame('ghp_xxxx', $this->container->getParameter('spiriit_commit_history.github.token'));
    }

    public function testLoadRegistersGitlabServices(): void
    {
        $this->extension->load([
            'spiriit_commit_history' => [
                'provider' => 'gitlab',
                'gitlab' => [
                    'project_id' => '123',
                ],
            ],
        ], $this->container);

        $this->assertTrue($this->container->hasDefinition('spiriit_commit_history.gitlab.parser'));
        $this->assertTrue($this->container->hasDefinition('spiriit_commit_history.gitlab.provider'));
        $this->assertTrue($this->container->hasDefinition('spiriit_commit_history.feed_fetcher'));
        $this->assertTrue($this->container->hasDefinition('spiriit_commit_history.controller.timeline'));
    }

    public function testLoadRegistersGithubServices(): void
    {
        $this->extension->load([
            'spiriit_commit_history' => [
                'provider' => 'github',
                'github' => [
                    'owner' => 'myorg',
                    'repo' => 'myproject',
                ],
            ],
        ], $this->container);

        $this->assertTrue($this->container->hasDefinition('spiriit_commit_history.github.parser'));
        $this->assertTrue($this->container->hasDefinition('spiriit_commit_history.github.provider'));
    }

    public function testFeedFetcherInterfaceAlias(): void
    {
        $this->extension->load([
            'spiriit_commit_history' => [
                'provider' => 'gitlab',
                'gitlab' => [
                    'project_id' => '123',
                ],
            ],
        ], $this->container);

        $this->assertTrue($this->container->hasAlias(FeedFetcherInterface::class));
    }

    public function testProviderInterfaceAliasForGitlab(): void
    {
        $this->extension->load([
            'spiriit_commit_history' => [
                'provider' => 'gitlab',
                'gitlab' => [
                    'project_id' => '123',
                ],
            ],
        ], $this->container);

        $this->assertTrue($this->container->hasAlias(ProviderInterface::class));
        $this->assertSame('spiriit_commit_history.gitlab.provider', (string) $this->container->getAlias(ProviderInterface::class));
    }

    public function testProviderInterfaceAliasForGithub(): void
    {
        $this->extension->load([
            'spiriit_commit_history' => [
                'provider' => 'github',
                'github' => [
                    'owner' => 'myorg',
                    'repo' => 'myproject',
                ],
            ],
        ], $this->container);

        $this->assertTrue($this->container->hasAlias(ProviderInterface::class));
        $this->assertSame('spiriit_commit_history.github.provider', (string) $this->container->getAlias(ProviderInterface::class));
    }
}
