<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Tests\Unit\Provider\Github;

use PHPUnit\Framework\TestCase;
use Spiriit\Bundle\CommitHistoryBundle\DTO\Commit;
use Spiriit\Bundle\CommitHistoryBundle\Provider\Github\CommitParser;

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
            'sha' => '9668d5f494b695b3ae657e32cb32a651c440f297',
            'html_url' => 'https://github.com/example/project/commit/9668d5f494b695b3ae657e32cb32a651c440f297',
            'commit' => [
                'message' => 'fix(return): Send slack message',
                'author' => [
                    'name' => 'Romain MILLAN',
                    'email' => 'rmillan@spiriit.com',
                    'date' => '2025-12-18T06:19:04Z',
                ],
            ],
        ];

        $commit = $this->parser->parse($data);

        $this->assertInstanceOf(Commit::class, $commit);
        $this->assertSame('9668d5f4', $commit->id);
        $this->assertSame('fix(return): Send slack message', $commit->title);
        $this->assertSame('Romain MILLAN', $commit->author);
        $this->assertSame('rmillan@spiriit.com', $commit->authorEmail);
        $this->assertSame('https://github.com/example/project/commit/9668d5f494b695b3ae657e32cb32a651c440f297', $commit->url);
    }

    public function testParseExtractsFirstLineAsTitle(): void
    {
        $data = [
            'sha' => 'abc123def456789012345678901234567890abcd',
            'html_url' => 'https://github.com/example/project/commit/abc123def456789012345678901234567890abcd',
            'commit' => [
                'message' => "First line title\n\nDetailed description\nMore details",
                'author' => [
                    'name' => 'Test',
                    'email' => 'test@example.com',
                    'date' => '2025-01-01T00:00:00Z',
                ],
            ],
        ];

        $commit = $this->parser->parse($data);

        $this->assertSame('First line title', $commit->title);
    }

    public function testParseShortensId(): void
    {
        $data = [
            'sha' => '1234567890abcdef1234567890abcdef12345678',
            'html_url' => 'https://example.com',
            'commit' => [
                'message' => 'test',
                'author' => [
                    'name' => 'Test',
                    'email' => 'test@example.com',
                    'date' => '2025-01-01T00:00:00Z',
                ],
            ],
        ];

        $commit = $this->parser->parse($data);

        $this->assertSame('12345678', $commit->id);
    }
}
