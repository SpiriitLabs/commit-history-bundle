<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Service\DiffParser;

use Spiriit\Bundle\CommitHistoryBundle\DTO\DependencyChange;

interface DiffParserInterface
{
    /**
     * Check if this parser supports the given filename.
     */
    public function supports(string $filename): bool;

    /**
     * Parse diff content and return dependency changes.
     *
     * @return DependencyChange[]
     */
    public function parse(string $diff, string $filename): array;
}
