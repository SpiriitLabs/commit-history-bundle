<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Spiriit\Bundle\CommitHistoryBundle\DTO\ComposerChange;
use Spiriit\Bundle\CommitHistoryBundle\Service\ComposerDiffParser;

class ComposerDiffParserTest extends TestCase
{
    private ComposerDiffParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ComposerDiffParser();
    }

    public function testParseDetectsUpdatedPackages(): void
    {
        $diff = <<<'DIFF'
            "name": "symfony/console",
-            "version": "v6.4.0",
+            "version": "v6.4.1",
DIFF;

        $changes = $this->parser->parse($diff);

        $this->assertCount(1, $changes);
        $this->assertSame('symfony/console', $changes[0]->package);
        $this->assertSame('v6.4.0', $changes[0]->fromVersion);
        $this->assertSame('v6.4.1', $changes[0]->toVersion);
        $this->assertSame(ComposerChange::TYPE_UPDATED, $changes[0]->type);
    }

    public function testParseDetectsAddedPackages(): void
    {
        $diff = <<<'DIFF'
+        {
+            "name": "monolog/monolog",
+            "version": "3.0.0",
+        },
DIFF;

        $changes = $this->parser->parse($diff);

        $this->assertCount(1, $changes);
        $this->assertSame('monolog/monolog', $changes[0]->package);
        $this->assertNull($changes[0]->fromVersion);
        $this->assertSame('3.0.0', $changes[0]->toVersion);
        $this->assertSame(ComposerChange::TYPE_ADDED, $changes[0]->type);
    }

    public function testParseDetectsRemovedPackages(): void
    {
        $diff = <<<'DIFF'
-        {
-            "name": "old/package",
-            "version": "1.0.0",
-        },
DIFF;

        $changes = $this->parser->parse($diff);

        $this->assertCount(1, $changes);
        $this->assertSame('old/package', $changes[0]->package);
        $this->assertSame('1.0.0', $changes[0]->fromVersion);
        $this->assertNull($changes[0]->toVersion);
        $this->assertSame(ComposerChange::TYPE_REMOVED, $changes[0]->type);
    }

    public function testParseReturnsEmptyArrayForNonComposerDiff(): void
    {
        $diff = <<<'DIFF'
-function oldFunction() {}
+function newFunction() {}
DIFF;

        $changes = $this->parser->parse($diff);

        $this->assertEmpty($changes);
    }

    public function testParseFromFixtureFile(): void
    {
        $diff = file_get_contents(__DIR__ . '/../../Fixtures/composer_lock_diff.txt');

        $changes = $this->parser->parse($diff);

        $this->assertNotEmpty($changes);

        $packageNames = array_map(fn (ComposerChange $c) => $c->package, $changes);

        $this->assertContains('symfony/console', $packageNames);
        $this->assertContains('symfony/http-kernel', $packageNames);
    }
}
