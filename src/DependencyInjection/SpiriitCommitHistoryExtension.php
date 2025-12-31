<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\DependencyInjection;

use Spiriit\Bundle\CommitHistoryBundle\Provider\ProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class SpiriitCommitHistoryExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Set general parameters
        $container->setParameter('spiriit_commit_history.provider', $config['provider']);
        $container->setParameter('spiriit_commit_history.feed_name', $config['feed_name']);
        $container->setParameter('spiriit_commit_history.cache_ttl', $config['cache_ttl']);
        $container->setParameter('spiriit_commit_history.available_years_count', $config['available_years_count']);

        // Set GitLab parameters
        $gitlab = $config['gitlab'] ?? [];
        $container->setParameter('spiriit_commit_history.gitlab.project_id', $gitlab['project_id'] ?? '');
        $container->setParameter('spiriit_commit_history.gitlab.base_url', $gitlab['base_url'] ?? 'https://gitlab.com');
        $container->setParameter('spiriit_commit_history.gitlab.token', $gitlab['token'] ?? null);
        $container->setParameter('spiriit_commit_history.gitlab.ref', $gitlab['ref'] ?? null);

        // Set GitHub parameters
        $github = $config['github'] ?? [];
        $container->setParameter('spiriit_commit_history.github.owner', $github['owner'] ?? '');
        $container->setParameter('spiriit_commit_history.github.repo', $github['repo'] ?? '');
        $container->setParameter('spiriit_commit_history.github.base_url', $github['base_url'] ?? 'https://api.github.com');
        $container->setParameter('spiriit_commit_history.github.token', $github['token'] ?? null);
        $container->setParameter('spiriit_commit_history.github.ref', $github['ref'] ?? null);

        // Load services
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.php');

        // Wire the correct provider based on configuration
        $this->configureProvider($container, $config['provider']);
    }

    private function configureProvider(ContainerBuilder $container, string $provider): void
    {
        $providerServiceId = match ($provider) {
            'gitlab' => 'spiriit_commit_history.gitlab.provider',
            'github' => 'spiriit_commit_history.github.provider',
            default => throw new \InvalidArgumentException(\sprintf('Unknown provider "%s"', $provider)),
        };

        $container->setAlias('spiriit_commit_history.provider', $providerServiceId);
        $container->setAlias(ProviderInterface::class, $providerServiceId);
    }
}
