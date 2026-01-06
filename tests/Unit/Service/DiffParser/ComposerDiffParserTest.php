<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Tests\Unit\Service\DiffParser;

use PHPUnit\Framework\TestCase;
use Spiriit\Bundle\CommitHistoryBundle\DTO\DependencyChange;
use Spiriit\Bundle\CommitHistoryBundle\Service\DiffParser\ComposerDiffParser;

class ComposerDiffParserTest extends TestCase
{
    private ComposerDiffParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ComposerDiffParser();
    }

    public function testSupportsComposerJson(): void
    {
        $this->assertTrue($this->parser->supports('composer.json'));
        $this->assertTrue($this->parser->supports('/path/to/composer.json'));
    }

    public function testSupportsComposerLock(): void
    {
        $this->assertTrue($this->parser->supports('composer.lock'));
        $this->assertTrue($this->parser->supports('/path/to/composer.lock'));
    }

    public function testDoesNotSupportOtherFiles(): void
    {
        $this->assertFalse($this->parser->supports('package.json'));
        $this->assertFalse($this->parser->supports('package-lock.json'));
        $this->assertFalse($this->parser->supports('composer.txt'));
    }

    public function testParseComposerJsonAddedPackage(): void
    {
        $diff = <<<'DIFF'
@@ -10,6 +10,7 @@
     "require": {
         "php": "^8.2",
+        "symfony/http-client": "^7.0",
         "symfony/framework-bundle": "^7.0"
     },
DIFF;

        $changes = $this->parser->parse($diff, 'composer.json');

        $this->assertCount(1, $changes);
        $this->assertSame('symfony/http-client', $changes[0]->name);
        $this->assertSame(DependencyChange::TYPE_ADDED, $changes[0]->type);
        $this->assertNull($changes[0]->oldVersion);
        $this->assertSame('^7.0', $changes[0]->newVersion);
    }

    public function testParseComposerJsonRemovedPackage(): void
    {
        $diff = <<<'DIFF'
@@ -10,7 +10,6 @@
     "require": {
         "php": "^8.2",
-        "old/package": "^1.0",
         "symfony/framework-bundle": "^7.0"
     },
DIFF;

        $changes = $this->parser->parse($diff, 'composer.json');

        $this->assertCount(1, $changes);
        $this->assertSame('old/package', $changes[0]->name);
        $this->assertSame(DependencyChange::TYPE_REMOVED, $changes[0]->type);
        $this->assertSame('^1.0', $changes[0]->oldVersion);
        $this->assertNull($changes[0]->newVersion);
    }

    public function testParseComposerJsonUpdatedPackage(): void
    {
        $diff = <<<'DIFF'
@@ -10,7 +10,7 @@
     "require": {
         "php": "^8.2",
-        "symfony/http-client": "^6.4",
+        "symfony/http-client": "^7.0",
         "symfony/framework-bundle": "^7.0"
     },
DIFF;

        $changes = $this->parser->parse($diff, 'composer.json');

        $this->assertCount(1, $changes);
        $this->assertSame('symfony/http-client', $changes[0]->name);
        $this->assertSame(DependencyChange::TYPE_UPDATED, $changes[0]->type);
        $this->assertSame('^6.4', $changes[0]->oldVersion);
        $this->assertSame('^7.0', $changes[0]->newVersion);
    }

    public function testParseComposerJsonSkipsNonDependencyKeys(): void
    {
        $diff = <<<'DIFF'
@@ -1,5 +1,5 @@
 {
-    "name": "old/name",
+    "name": "new/name",
-    "description": "Old description",
+    "description": "New description",
     "require": {}
 }
DIFF;

        $changes = $this->parser->parse($diff, 'composer.json');

        $this->assertCount(0, $changes);
    }

    public function testParseComposerJsonSkipsNonPackageNames(): void
    {
        $diff = <<<'DIFF'
@@ -1,5 +1,5 @@
     "require": {
-        "php": "^8.1",
+        "php": "^8.2",
     }
DIFF;

        $changes = $this->parser->parse($diff, 'composer.json');

        // "php" doesn't contain "/" so it's skipped
        $this->assertCount(0, $changes);
    }

    public function testParseComposerLockAddedPackage(): void
    {
        $diff = <<<'DIFF'
@@ -100,6 +100,20 @@
+            "name": "symfony/http-client",
+            "version": "v7.0.0",
+            "source": {
DIFF;

        $changes = $this->parser->parse($diff, 'composer.lock');

        $this->assertCount(1, $changes);
        $this->assertSame('symfony/http-client', $changes[0]->name);
        $this->assertSame(DependencyChange::TYPE_ADDED, $changes[0]->type);
        $this->assertSame('v7.0.0', $changes[0]->newVersion);
    }

    public function testParseComposerLockUpdatedPackage(): void
    {
        $diff = <<<'DIFF'
@@ -100,7 +100,7 @@
-            "name": "symfony/http-client",
-            "version": "v6.4.0",
+            "name": "symfony/http-client",
+            "version": "v7.0.0",
             "source": {
DIFF;

        $changes = $this->parser->parse($diff, 'composer.lock');

        $this->assertCount(1, $changes);
        $this->assertSame('symfony/http-client', $changes[0]->name);
        $this->assertSame(DependencyChange::TYPE_UPDATED, $changes[0]->type);
        $this->assertSame('v6.4.0', $changes[0]->oldVersion);
        $this->assertSame('v7.0.0', $changes[0]->newVersion);
    }

    public function testParseMultipleChanges(): void
    {
        $diff = <<<'DIFF'
@@ -10,9 +10,10 @@
     "require": {
         "php": "^8.2",
+        "symfony/http-client": "^7.0",
-        "old/package": "^1.0",
-        "another/package": "^2.0",
+        "another/package": "^3.0",
         "symfony/framework-bundle": "^7.0"
     },
DIFF;

        $changes = $this->parser->parse($diff, 'composer.json');

        $this->assertCount(3, $changes);

        $changesByName = [];
        foreach ($changes as $change) {
            $changesByName[$change->name] = $change;
        }

        $this->assertSame(DependencyChange::TYPE_ADDED, $changesByName['symfony/http-client']->type);
        $this->assertSame(DependencyChange::TYPE_REMOVED, $changesByName['old/package']->type);
        $this->assertSame(DependencyChange::TYPE_UPDATED, $changesByName['another/package']->type);
    }

    public function testParseEmptyDiff(): void
    {
        $changes = $this->parser->parse('', 'composer.json');

        $this->assertCount(0, $changes);
    }

    public function testSourceFileIsPreserved(): void
    {
        $diff = <<<'DIFF'
+        "symfony/http-client": "^7.0",
DIFF;

        $changes = $this->parser->parse($diff, '/path/to/composer.json');

        $this->assertCount(1, $changes);
        $this->assertSame('/path/to/composer.json', $changes[0]->sourceFile);
    }
}
