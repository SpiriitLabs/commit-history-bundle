<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Provider;

use Spiriit\Bundle\CommitHistoryBundle\DTO\Commit;

interface ProviderInterface
{
    /**
     * @return Commit[]
     */
    public function getCommits(?\DateTimeImmutable $since = null, ?\DateTimeImmutable $until = null): array;

    /**
     * Get the list of file names changed in a commit.
     *
     * @return string[]
     */
    public function getCommitFileNames(string $commitId): array;

    /**
     * Get the diff content for a specific commit.
     */
    public function getCommitDiff(string $commitId): ?string;
}
