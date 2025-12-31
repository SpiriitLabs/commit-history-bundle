<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Provider\Gitlab;

use Spiriit\Bundle\CommitHistoryBundle\DTO\Commit;
use Spiriit\Bundle\CommitHistoryBundle\Provider\CommitParserInterface;

class CommitParser implements CommitParserInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function parse(array $data): Commit
    {
        return new Commit(
            id: substr((string) $data['id'], 0, 8),
            title: (string) $data['title'],
            date: new \DateTimeImmutable((string) $data['created_at']),
            author: (string) $data['author_name'],
            url: (string) $data['web_url'],
            authorEmail: $data['author_email'] ?? null,
        );
    }
}
