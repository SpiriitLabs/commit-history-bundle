<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Tests;

use Spiriit\Bundle\CommitHistoryBundle\SpiriitCommitHistoryBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new SpiriitCommitHistoryBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function ($container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
                'http_client' => [
                    'scoped_clients' => [],
                ],
            ]);

            $container->loadFromExtension('twig', [
                'default_path' => __DIR__.'/templates',
            ]);

            $container->loadFromExtension('spiriit_commit_history', [
                'provider' => 'gitlab',
                'feed_name' => 'Test Project',
                'gitlab' => [
                    'project_id' => '123',
                    'base_url' => 'https://gitlab.example.com',
                ],
            ]);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/spiriit_commit_history_bundle/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/spiriit_commit_history_bundle/logs';
    }
}
