<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Adapter;

use Spiriit\CommitHistory\Contract\CacheInterface;
use Symfony\Contracts\Cache\CacheInterface as SymfonyCacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class SymfonyCacheAdapter implements CacheInterface
{
    public function __construct(
        private readonly SymfonyCacheInterface $cache,
    ) {
    }

    public function get(string $key, callable $callback, ?int $ttl = null): mixed
    {
        return $this->cache->get($key, function (ItemInterface $item) use ($callback, $ttl): mixed {
            if (null !== $ttl) {
                $item->expiresAfter($ttl);
            }

            return $callback();
        });
    }

    public function delete(string $key): bool
    {
        return $this->cache->delete($key);
    }
}
