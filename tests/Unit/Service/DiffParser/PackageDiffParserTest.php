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
use Spiriit\Bundle\CommitHistoryBundle\Service\DiffParser\PackageDiffParser;

class PackageDiffParserTest extends TestCase
{
    private PackageDiffParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PackageDiffParser();
    }

    public function testSupportsPackageJson(): void
    {
        $this->assertTrue($this->parser->supports('package.json'));
        $this->assertTrue($this->parser->supports('/path/to/package.json'));
    }

    public function testSupportsPackageLockJson(): void
    {
        $this->assertTrue($this->parser->supports('package-lock.json'));
        $this->assertTrue($this->parser->supports('/path/to/package-lock.json'));
    }

    public function testDoesNotSupportOtherFiles(): void
    {
        $this->assertFalse($this->parser->supports('composer.json'));
        $this->assertFalse($this->parser->supports('composer.lock'));
        $this->assertFalse($this->parser->supports('package.txt'));
    }

    public function testParsePackageJsonAddedPackage(): void
    {
        $diff = <<<'DIFF'
@@ -10,6 +10,7 @@
   "dependencies": {
     "express": "^4.18.0",
+    "lodash": "^4.17.21",
     "react": "^18.0.0"
   },
DIFF;

        $changes = $this->parser->parse($diff, 'package.json');

        $this->assertCount(1, $changes);
        $this->assertSame('lodash', $changes[0]->name);
        $this->assertSame(DependencyChange::TYPE_ADDED, $changes[0]->type);
        $this->assertNull($changes[0]->oldVersion);
        $this->assertSame('^4.17.21', $changes[0]->newVersion);
    }

    public function testParsePackageJsonRemovedPackage(): void
    {
        $diff = <<<'DIFF'
@@ -10,7 +10,6 @@
   "dependencies": {
     "express": "^4.18.0",
-    "lodash": "^4.17.21",
     "react": "^18.0.0"
   },
DIFF;

        $changes = $this->parser->parse($diff, 'package.json');

        $this->assertCount(1, $changes);
        $this->assertSame('lodash', $changes[0]->name);
        $this->assertSame(DependencyChange::TYPE_REMOVED, $changes[0]->type);
        $this->assertSame('^4.17.21', $changes[0]->oldVersion);
        $this->assertNull($changes[0]->newVersion);
    }

    public function testParsePackageJsonUpdatedPackage(): void
    {
        $diff = <<<'DIFF'
@@ -10,7 +10,7 @@
   "dependencies": {
     "express": "^4.18.0",
-    "react": "^17.0.0",
+    "react": "^18.0.0",
     "lodash": "^4.17.21"
   },
DIFF;

        $changes = $this->parser->parse($diff, 'package.json');

        $this->assertCount(1, $changes);
        $this->assertSame('react', $changes[0]->name);
        $this->assertSame(DependencyChange::TYPE_UPDATED, $changes[0]->type);
        $this->assertSame('^17.0.0', $changes[0]->oldVersion);
        $this->assertSame('^18.0.0', $changes[0]->newVersion);
    }

    public function testParsePackageJsonSkipsNonDependencyKeys(): void
    {
        $diff = <<<'DIFF'
@@ -1,5 +1,5 @@
 {
-  "name": "old-name",
+  "name": "new-name",
-  "version": "1.0.0",
+  "version": "2.0.0",
-  "description": "Old description",
+  "description": "New description"
 }
DIFF;

        $changes = $this->parser->parse($diff, 'package.json');

        $this->assertCount(0, $changes);
    }

    public function testParsePackageJsonSkipsUrlVersions(): void
    {
        $diff = <<<'DIFF'
@@ -10,6 +10,7 @@
   "dependencies": {
+    "my-package": "https://github.com/user/repo.git",
+    "local-package": "file:../local",
+    "git-package": "git+ssh://git@github.com/user/repo.git"
   },
DIFF;

        $changes = $this->parser->parse($diff, 'package.json');

        $this->assertCount(0, $changes);
    }

    public function testParsePackageLockAddedPackage(): void
    {
        $diff = <<<'DIFF'
@@ -100,6 +100,12 @@
+    "node_modules/lodash": {
+      "version": "4.17.21",
+      "resolved": "https://registry.npmjs.org/lodash/-/lodash-4.17.21.tgz"
+    },
DIFF;

        $changes = $this->parser->parse($diff, 'package-lock.json');

        $this->assertCount(1, $changes);
        $this->assertSame('lodash', $changes[0]->name);
        $this->assertSame(DependencyChange::TYPE_ADDED, $changes[0]->type);
        $this->assertSame('4.17.21', $changes[0]->newVersion);
    }

    public function testParsePackageLockUpdatedPackage(): void
    {
        $diff = <<<'DIFF'
@@ -100,7 +100,7 @@
-    "node_modules/lodash": {
-      "version": "4.17.20",
+    "node_modules/lodash": {
+      "version": "4.17.21",
       "resolved": "https://registry.npmjs.org/lodash/-/lodash.tgz"
DIFF;

        $changes = $this->parser->parse($diff, 'package-lock.json');

        $this->assertCount(1, $changes);
        $this->assertSame('lodash', $changes[0]->name);
        $this->assertSame(DependencyChange::TYPE_UPDATED, $changes[0]->type);
        $this->assertSame('4.17.20', $changes[0]->oldVersion);
        $this->assertSame('4.17.21', $changes[0]->newVersion);
    }

    public function testParseMultipleChanges(): void
    {
        $diff = <<<'DIFF'
@@ -10,9 +10,10 @@
   "dependencies": {
+    "lodash": "^4.17.21",
-    "old-package": "^1.0.0",
-    "react": "^17.0.0",
+    "react": "^18.0.0",
     "express": "^4.18.0"
   },
DIFF;

        $changes = $this->parser->parse($diff, 'package.json');

        $this->assertCount(3, $changes);

        $changesByName = [];
        foreach ($changes as $change) {
            $changesByName[$change->name] = $change;
        }

        $this->assertSame(DependencyChange::TYPE_ADDED, $changesByName['lodash']->type);
        $this->assertSame(DependencyChange::TYPE_REMOVED, $changesByName['old-package']->type);
        $this->assertSame(DependencyChange::TYPE_UPDATED, $changesByName['react']->type);
    }

    public function testParseEmptyDiff(): void
    {
        $changes = $this->parser->parse('', 'package.json');

        $this->assertCount(0, $changes);
    }

    public function testSourceFileIsPreserved(): void
    {
        $diff = <<<'DIFF'
+    "lodash": "^4.17.21",
DIFF;

        $changes = $this->parser->parse($diff, '/path/to/package.json');

        $this->assertCount(1, $changes);
        $this->assertSame('/path/to/package.json', $changes[0]->sourceFile);
    }
}
