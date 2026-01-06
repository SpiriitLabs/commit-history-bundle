<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Tests\Unit\DTO;

use PHPUnit\Framework\TestCase;
use Spiriit\Bundle\CommitHistoryBundle\DTO\Commit;

class CommitTest extends TestCase
{
    public function testCommitCreation(): void
    {
        $date = new \DateTimeImmutable('2025-12-18T07:19:04+01:00');

        $commit = new Commit(
            id: '9668d5f4',
            title: 'fix(return): Send slack message',
            date: $date,
            author: 'Romain MILLAN',
            url: 'https://gitlab.example.com/-/commit/9668d5f4',
            authorEmail: 'rmillan@spiriit.com',
        );

        $this->assertSame('9668d5f4', $commit->id);
        $this->assertSame('fix(return): Send slack message', $commit->title);
        $this->assertSame($date, $commit->date);
        $this->assertSame('Romain MILLAN', $commit->author);
        $this->assertSame('https://gitlab.example.com/-/commit/9668d5f4', $commit->url);
        $this->assertSame('rmillan@spiriit.com', $commit->authorEmail);
    }

    public function testCommitWithNullEmail(): void
    {
        $commit = new Commit(
            id: '9668d5f4',
            title: 'fix(return): Send slack message',
            date: new \DateTimeImmutable(),
            author: 'Romain MILLAN',
            url: 'https://gitlab.example.com/-/commit/9668d5f4',
        );

        $this->assertNull($commit->authorEmail);
    }

    public function testCommitHasDependenciesChangesDefaultsToFalse(): void
    {
        $commit = new Commit(
            id: '9668d5f4',
            title: 'fix(return): Send slack message',
            date: new \DateTimeImmutable(),
            author: 'Romain MILLAN',
            url: 'https://gitlab.example.com/-/commit/9668d5f4',
        );

        $this->assertFalse($commit->hasDependenciesChanges);
    }

    public function testCommitWithHasDependenciesChanges(): void
    {
        $commit = new Commit(
            id: '9668d5f4',
            title: 'fix(return): Send slack message',
            date: new \DateTimeImmutable(),
            author: 'Romain MILLAN',
            url: 'https://gitlab.example.com/-/commit/9668d5f4',
            hasDependenciesChanges: true,
        );

        $this->assertTrue($commit->hasDependenciesChanges);
    }

    public function testWithHasDependenciesChangesReturnsNewInstance(): void
    {
        $date = new \DateTimeImmutable('2025-12-18T07:19:04+01:00');

        $commit = new Commit(
            id: '9668d5f4',
            title: 'fix(return): Send slack message',
            date: $date,
            author: 'Romain MILLAN',
            url: 'https://gitlab.example.com/-/commit/9668d5f4',
            authorEmail: 'rmillan@spiriit.com',
            hasDependenciesChanges: false,
        );

        $newCommit = $commit->withHasDependenciesChanges(true);

        // Original should be unchanged
        $this->assertFalse($commit->hasDependenciesChanges);

        // New instance should have updated value
        $this->assertTrue($newCommit->hasDependenciesChanges);

        // Other properties should be preserved
        $this->assertSame($commit->id, $newCommit->id);
        $this->assertSame($commit->title, $newCommit->title);
        $this->assertSame($commit->date, $newCommit->date);
        $this->assertSame($commit->author, $newCommit->author);
        $this->assertSame($commit->url, $newCommit->url);
        $this->assertSame($commit->authorEmail, $newCommit->authorEmail);
    }
}
