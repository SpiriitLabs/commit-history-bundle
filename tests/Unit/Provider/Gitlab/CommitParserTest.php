<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Tests\Unit\Provider\Gitlab;

use PHPUnit\Framework\TestCase;
use Spiriit\Bundle\CommitHistoryBundle\DTO\Commit;
use Spiriit\Bundle\CommitHistoryBundle\Provider\Gitlab\CommitParser;

class CommitParserTest extends TestCase
{
    private CommitParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CommitParser();
    }

    public function testParseReturnsCommit(): void
    {
        $data = [
            'id' => '9668d5f494b695b3ae657e32cb32a651c440f297',
            'title' => 'fix(return): Send slack message',
            'author_name' => 'Romain MILLAN',
            'author_email' => 'rmillan@spiriit.com',
            'created_at' => '2025-12-18T07:19:04+01:00',
            'web_url' => 'https://gitlab.example.com/-/commit/9668d5f494b695b3ae657e32cb32a651c440f297',
        ];

        $commit = $this->parser->parse($data);

        $this->assertInstanceOf(Commit::class, $commit);
        $this->assertSame('9668d5f4', $commit->id);
        $this->assertSame('fix(return): Send slack message', $commit->title);
        $this->assertSame('Romain MILLAN', $commit->author);
        $this->assertSame('rmillan@spiriit.com', $commit->authorEmail);
        $this->assertSame('https://gitlab.example.com/-/commit/9668d5f494b695b3ae657e32cb32a651c440f297', $commit->url);
        $this->assertEquals(new \DateTimeImmutable('2025-12-18T07:19:04+01:00'), $commit->date);
    }

    public function testParseWithNullEmail(): void
    {
        $data = [
            'id' => 'abc123def456789012345678901234567890abcd',
            'title' => 'feat(api): Add endpoint',
            'author_name' => 'Jane Smith',
            'created_at' => '2025-12-16T14:00:00+01:00',
            'web_url' => 'https://gitlab.example.com/-/commit/abc123def456789012345678901234567890abcd',
        ];

        $commit = $this->parser->parse($data);

        $this->assertNull($commit->authorEmail);
    }

    public function testParseShortensId(): void
    {
        $data = [
            'id' => '1234567890abcdef1234567890abcdef12345678',
            'title' => 'test',
            'author_name' => 'Test',
            'created_at' => '2025-01-01T00:00:00+00:00',
            'web_url' => 'https://example.com',
        ];

        $commit = $this->parser->parse($data);

        $this->assertSame('12345678', $commit->id);
    }
}
