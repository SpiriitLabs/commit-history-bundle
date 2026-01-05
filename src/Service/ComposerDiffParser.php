<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Service;

use Spiriit\Bundle\CommitHistoryBundle\DTO\ComposerChange;

class ComposerDiffParser implements ComposerDiffParserInterface
{
    public function parse(string $diffContent): array
    {
        $changes = [];
        $lines = explode("\n", $diffContent);
        $lineCount = \count($lines);

        $removedPackages = [];
        $addedPackages = [];

        for ($i = 0; $i < $lineCount; ++$i) {
            $line = $lines[$i];

            // Look for package name lines
            if (preg_match('/^[-+]?\s*"name":\s*"([^"]+)"/', $line, $nameMatch)) {
                $packageName = $nameMatch[1];
                $isRemoved = str_starts_with(trim($line), '-');
                $isAdded = str_starts_with(trim($line), '+');

                // Look for version in nearby lines (within 10 lines)
                $version = $this->findVersionNearby($lines, $i, $isRemoved ? '-' : ($isAdded ? '+' : ' '));

                if ($isRemoved && $version) {
                    $removedPackages[$packageName] = $version;
                } elseif ($isAdded && $version) {
                    $addedPackages[$packageName] = $version;
                }
            }

            // Also look for version changes where name is on a neutral line
            if (preg_match('/^\s*"name":\s*"([^"]+)"/', $line, $nameMatch)) {
                $packageName = $nameMatch[1];

                // Check if there's a version change nearby
                $oldVersion = $this->findVersionNearby($lines, $i, '-');
                $newVersion = $this->findVersionNearby($lines, $i, '+');

                if ($oldVersion && $newVersion && $oldVersion !== $newVersion) {
                    if (!isset($removedPackages[$packageName])) {
                        $removedPackages[$packageName] = $oldVersion;
                    }
                    if (!isset($addedPackages[$packageName])) {
                        $addedPackages[$packageName] = $newVersion;
                    }
                }
            }
        }

        // Build changes array
        $allPackages = array_unique(array_merge(array_keys($removedPackages), array_keys($addedPackages)));
        sort($allPackages);

        foreach ($allPackages as $package) {
            $fromVersion = $removedPackages[$package] ?? null;
            $toVersion = $addedPackages[$package] ?? null;

            if ($fromVersion && $toVersion) {
                if ($fromVersion !== $toVersion) {
                    $changes[] = new ComposerChange($package, $fromVersion, $toVersion, ComposerChange::TYPE_UPDATED);
                }
            } elseif ($toVersion) {
                $changes[] = new ComposerChange($package, null, $toVersion, ComposerChange::TYPE_ADDED);
            } elseif ($fromVersion) {
                $changes[] = new ComposerChange($package, $fromVersion, null, ComposerChange::TYPE_REMOVED);
            }
        }

        return $changes;
    }

    /**
     * @param string[] $lines
     */
    private function findVersionNearby(array $lines, int $currentIndex, string $prefix): ?string
    {
        $searchRange = 15;
        $start = max(0, $currentIndex - $searchRange);
        $end = min(\count($lines), $currentIndex + $searchRange);

        for ($i = $start; $i < $end; ++$i) {
            $line = $lines[$i];

            // Match version line with specific prefix
            $pattern = '/^' . preg_quote($prefix, '/') . '\s*"version":\s*"([^"]+)"/';
            if (preg_match($pattern, $line, $match)) {
                return $match[1];
            }
        }

        return null;
    }
}
