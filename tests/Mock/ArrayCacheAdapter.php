<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Tests\Mock;

use Spiriit\CommitHistory\Contract\CacheInterface;

/**
 * Simple in-memory cache adapter for testing.
 */
class ArrayCacheAdapter implements CacheInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $cache = [];

    public function get(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if (!\array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $callback();
        }

        return $this->cache[$key];
    }

    public function delete(string $key): bool
    {
        if (\array_key_exists($key, $this->cache)) {
            unset($this->cache[$key]);

            return true;
        }

        return false;
    }

    public function clear(): void
    {
        $this->cache = [];
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->cache);
    }
}
