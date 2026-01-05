<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\DTO;

readonly class DependencyChange
{
    public const TYPE_UPDATED = 'updated';
    public const TYPE_ADDED = 'added';
    public const TYPE_REMOVED = 'removed';

    public function __construct(
        public string $package,
        public ?string $fromVersion,
        public ?string $toVersion,
        public string $type,
    ) {
    }
}
