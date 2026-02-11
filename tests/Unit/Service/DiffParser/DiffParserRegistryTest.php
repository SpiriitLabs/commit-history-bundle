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
use Spiriit\CommitHistory\DiffParser\ComposerDiffParser;
use Spiriit\CommitHistory\DiffParser\DiffParserRegistry;
use Spiriit\CommitHistory\DiffParser\PackageDiffParser;
use Spiriit\CommitHistory\DTO\DependencyChange;

class DiffParserRegistryTest extends TestCase
{
    private DiffParserRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new DiffParserRegistry([
            new ComposerDiffParser(),
            new PackageDiffParser(),
        ]);
    }

    public function testSupportsComposerFiles(): void
    {
        $this->assertTrue($this->registry->supports('composer.json'));
        $this->assertTrue($this->registry->supports('composer.lock'));
    }

    public function testSupportsPackageFiles(): void
    {
        $this->assertTrue($this->registry->supports('package.json'));
        $this->assertTrue($this->registry->supports('package-lock.json'));
    }

    public function testDoesNotSupportUnknownFiles(): void
    {
        $this->assertFalse($this->registry->supports('unknown.txt'));
        $this->assertFalse($this->registry->supports('README.md'));
    }

    public function testParseUsesCorrectParser(): void
    {
        $composerDiff = '+        "symfony/http-client": "^7.0",';
        $packageDiff = '+    "lodash": "^4.17.21",';

        $composerChanges = $this->registry->parse($composerDiff, 'composer.json');
        $packageChanges = $this->registry->parse($packageDiff, 'package.json');

        $this->assertCount(1, $composerChanges);
        $this->assertSame('symfony/http-client', $composerChanges[0]->name);

        $this->assertCount(1, $packageChanges);
        $this->assertSame('lodash', $packageChanges[0]->name);
    }

    public function testParseReturnsEmptyArrayForUnsupportedFile(): void
    {
        $changes = $this->registry->parse('some content', 'unknown.txt');

        $this->assertCount(0, $changes);
    }

    public function testParseAllParsesMultipleFiles(): void
    {
        $diffs = [
            'composer.json' => '+        "symfony/http-client": "^7.0",',
            'package.json' => '+    "lodash": "^4.17.21",',
        ];

        $changes = $this->registry->parseAll($diffs);

        $this->assertCount(2, $changes);
        $this->assertContainsOnlyInstancesOf(DependencyChange::class, $changes);

        $names = array_map(fn ($c) => $c->name, $changes);
        $this->assertContains('symfony/http-client', $names);
        $this->assertContains('lodash', $names);
    }

    public function testParseAllSkipsUnsupportedFiles(): void
    {
        $diffs = [
            'composer.json' => '+        "symfony/http-client": "^7.0",',
            'README.md' => '+ Some text',
            'unknown.txt' => '+ More text',
        ];

        $changes = $this->registry->parseAll($diffs);

        $this->assertCount(1, $changes);
        $this->assertSame('symfony/http-client', $changes[0]->name);
    }

    public function testParseAllWithEmptyDiffs(): void
    {
        $changes = $this->registry->parseAll([]);

        $this->assertCount(0, $changes);
    }

    public function testRegistryWithNoParsers(): void
    {
        $emptyRegistry = new DiffParserRegistry([]);

        $this->assertFalse($emptyRegistry->supports('composer.json'));
        $this->assertCount(0, $emptyRegistry->parse('some diff', 'composer.json'));
        $this->assertCount(0, $emptyRegistry->parseAll(['composer.json' => 'diff']));
    }
}
