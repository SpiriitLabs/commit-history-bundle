<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Provider\Github;

use Spiriit\Bundle\CommitHistoryBundle\DTO\Commit;
use Spiriit\Bundle\CommitHistoryBundle\Provider\CommitParserInterface;

class CommitParser implements CommitParserInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function parse(array $data): Commit
    {
        $commitData = $data['commit'] ?? [];
        $authorData = $commitData['author'] ?? [];

        return new Commit(
            id: substr((string) $data['sha'], 0, 8),
            title: $this->extractTitle((string) ($commitData['message'] ?? '')),
            date: new \DateTimeImmutable((string) ($authorData['date'] ?? 'now')),
            author: (string) ($authorData['name'] ?? ''),
            url: (string) ($data['html_url'] ?? ''),
            authorEmail: $authorData['email'] ?? null,
        );
    }

    private function extractTitle(string $message): string
    {
        $lines = explode("\n", $message);

        return trim($lines[0]);
    }
}
