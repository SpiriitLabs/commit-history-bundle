<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Service\DiffParser;

use Spiriit\Bundle\CommitHistoryBundle\DTO\DependencyChange;

class DiffParserRegistry
{
    /**
     * @var iterable<DiffParserInterface>
     */
    private readonly iterable $parsers;

    /**
     * @param iterable<DiffParserInterface> $parsers
     */
    public function __construct(iterable $parsers)
    {
        $this->parsers = $parsers;
    }

    /**
     * Parse diffs from multiple files and return all dependency changes.
     *
     * @param array<string, string> $diffs Map of filename => diff content
     *
     * @return DependencyChange[]
     */
    public function parseAll(array $diffs): array
    {
        $changes = [];

        foreach ($diffs as $filename => $diff) {
            $fileChanges = $this->parse($diff, $filename);
            foreach ($fileChanges as $change) {
                $changes[] = $change;
            }
        }

        return $changes;
    }

    /**
     * Parse a single file's diff content.
     *
     * @return DependencyChange[]
     */
    public function parse(string $diff, string $filename): array
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($filename)) {
                return $parser->parse($diff, $filename);
            }
        }

        return [];
    }

    /**
     * Check if any parser supports the given filename.
     */
    public function supports(string $filename): bool
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($filename)) {
                return true;
            }
        }

        return false;
    }
}
