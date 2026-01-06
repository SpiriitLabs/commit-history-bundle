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
use Spiriit\Bundle\CommitHistoryBundle\DTO\DependencyChange;

class DependencyChangeTest extends TestCase
{
    public function testDependencyChangeCreation(): void
    {
        $change = new DependencyChange(
            name: 'symfony/http-client',
            type: DependencyChange::TYPE_ADDED,
            oldVersion: null,
            newVersion: '^7.0',
            sourceFile: 'composer.json',
        );

        $this->assertSame('symfony/http-client', $change->name);
        $this->assertSame(DependencyChange::TYPE_ADDED, $change->type);
        $this->assertNull($change->oldVersion);
        $this->assertSame('^7.0', $change->newVersion);
        $this->assertSame('composer.json', $change->sourceFile);
    }

    public function testDependencyChangeWithUpdate(): void
    {
        $change = new DependencyChange(
            name: 'symfony/http-client',
            type: DependencyChange::TYPE_UPDATED,
            oldVersion: '^6.4',
            newVersion: '^7.0',
            sourceFile: 'composer.json',
        );

        $this->assertSame(DependencyChange::TYPE_UPDATED, $change->type);
        $this->assertSame('^6.4', $change->oldVersion);
        $this->assertSame('^7.0', $change->newVersion);
    }

    public function testDependencyChangeWithRemoved(): void
    {
        $change = new DependencyChange(
            name: 'old/package',
            type: DependencyChange::TYPE_REMOVED,
            oldVersion: '^1.0',
        );

        $this->assertSame(DependencyChange::TYPE_REMOVED, $change->type);
        $this->assertSame('^1.0', $change->oldVersion);
        $this->assertNull($change->newVersion);
        $this->assertNull($change->sourceFile);
    }

    public function testTypeConstants(): void
    {
        $this->assertSame('added', DependencyChange::TYPE_ADDED);
        $this->assertSame('updated', DependencyChange::TYPE_UPDATED);
        $this->assertSame('removed', DependencyChange::TYPE_REMOVED);
    }
}
