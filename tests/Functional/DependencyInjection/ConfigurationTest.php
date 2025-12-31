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
use Spiriit\Bundle\CommitHistoryBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    private Processor $processor;
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->processor = new Processor();
        $this->configuration = new Configuration();
    }

    public function testMinimalGitlabConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            'spiriit_commit_history' => [
                'provider' => 'gitlab',
                'gitlab' => [
                    'project_id' => '123',
                ],
            ],
        ]);

        $this->assertSame('gitlab', $config['provider']);
        $this->assertSame('123', $config['gitlab']['project_id']);
        $this->assertSame('https://gitlab.com', $config['gitlab']['base_url']);
        $this->assertNull($config['gitlab']['token']);
        $this->assertSame('Commits', $config['feed_name']);
        $this->assertSame(3600, $config['cache_ttl']);
        $this->assertSame(6, $config['available_years_count']);
    }

    public function testMinimalGithubConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            'spiriit_commit_history' => [
                'provider' => 'github',
                'github' => [
                    'owner' => 'myorg',
                    'repo' => 'myproject',
                ],
            ],
        ]);

        $this->assertSame('github', $config['provider']);
        $this->assertSame('myorg', $config['github']['owner']);
        $this->assertSame('myproject', $config['github']['repo']);
        $this->assertSame('https://api.github.com', $config['github']['base_url']);
        $this->assertNull($config['github']['token']);
    }

    public function testFullGitlabConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            'spiriit_commit_history' => [
                'provider' => 'gitlab',
                'feed_name' => 'My Project',
                'cache_ttl' => 7200,
                'available_years_count' => 10,
                'gitlab' => [
                    'project_id' => '456',
                    'base_url' => 'https://gitlab.mycompany.com',
                    'token' => 'glpat-xxxx',
                    'ref' => 'develop',
                ],
            ],
        ]);

        $this->assertSame('gitlab', $config['provider']);
        $this->assertSame('My Project', $config['feed_name']);
        $this->assertSame(7200, $config['cache_ttl']);
        $this->assertSame(10, $config['available_years_count']);
        $this->assertSame('456', $config['gitlab']['project_id']);
        $this->assertSame('https://gitlab.mycompany.com', $config['gitlab']['base_url']);
        $this->assertSame('glpat-xxxx', $config['gitlab']['token']);
        $this->assertSame('develop', $config['gitlab']['ref']);
    }

    public function testFullGithubConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            'spiriit_commit_history' => [
                'provider' => 'github',
                'feed_name' => 'My Project',
                'cache_ttl' => 1800,
                'github' => [
                    'owner' => 'myorg',
                    'repo' => 'myproject',
                    'base_url' => 'https://github.mycompany.com/api/v3',
                    'token' => 'ghp_xxxx',
                    'ref' => 'main',
                ],
            ],
        ]);

        $this->assertSame('github', $config['provider']);
        $this->assertSame('https://github.mycompany.com/api/v3', $config['github']['base_url']);
        $this->assertSame('ghp_xxxx', $config['github']['token']);
        $this->assertSame('main', $config['github']['ref']);
    }

    public function testProviderIsRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->configuration, [
            'spiriit_commit_history' => [
                'gitlab' => [
                    'project_id' => '123',
                ],
            ],
        ]);
    }

    public function testGitlabConfigRequiredWhenGitlabProvider(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('gitlab configuration is missing');

        $this->processor->processConfiguration($this->configuration, [
            'spiriit_commit_history' => [
                'provider' => 'gitlab',
            ],
        ]);
    }

    public function testGithubConfigRequiredWhenGithubProvider(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('github configuration is missing');

        $this->processor->processConfiguration($this->configuration, [
            'spiriit_commit_history' => [
                'provider' => 'github',
            ],
        ]);
    }

    public function testCacheTtlCannotBeNegative(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->configuration, [
            'spiriit_commit_history' => [
                'provider' => 'gitlab',
                'cache_ttl' => -1,
                'gitlab' => [
                    'project_id' => '123',
                ],
            ],
        ]);
    }

    public function testCacheTtlCanBeZero(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            'spiriit_commit_history' => [
                'provider' => 'gitlab',
                'cache_ttl' => 0,
                'gitlab' => [
                    'project_id' => '123',
                ],
            ],
        ]);

        $this->assertSame(0, $config['cache_ttl']);
    }

    public function testAvailableYearsCountCannotBeZero(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->configuration, [
            'spiriit_commit_history' => [
                'provider' => 'gitlab',
                'available_years_count' => 0,
                'gitlab' => [
                    'project_id' => '123',
                ],
            ],
        ]);
    }
}
