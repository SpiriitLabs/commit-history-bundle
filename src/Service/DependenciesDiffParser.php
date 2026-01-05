<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Service;

use Spiriit\Bundle\CommitHistoryBundle\DTO\DependencyChange;

class DependenciesDiffParser implements DependenciesDiffParserInterface
{
    /**
     * Keys that are not package names in dependency files.
     */
    private const EXCLUDED_KEYS = [
        'name',
        'version',
        'type',
        'source',
        'dist',
        'require',
        'require-dev',
        'autoload',
        'description',
        'license',
        'keywords',
        'authors',
        'homepage',
        'support',
        'funding',
        'time',
        'extra',
        'scripts',
        'config',
        'minimum-stability',
        'prefer-stable',
        'repositories',
        'bin',
        'archive',
        'abandoned',
        'non-feature-branches',
        'dependencies',
        'devDependencies',
        'peerDependencies',
        'optionalDependencies',
        'bundledDependencies',
        'engines',
        'main',
        'module',
        'browser',
        'types',
        'typings',
        'exports',
        'files',
        'private',
        'publishConfig',
        'workspaces',
    ];

    public function parse(string $diffContent): array
    {
        $removedPackages = [];
        $addedPackages = [];

        $this->parseDiff($diffContent, $removedPackages, $addedPackages);

        // Build changes array
        $changes = [];
        $allPackages = array_unique(array_merge(array_keys($removedPackages), array_keys($addedPackages)));
        sort($allPackages);

        foreach ($allPackages as $package) {
            $fromVersion = $removedPackages[$package] ?? null;
            $toVersion = $addedPackages[$package] ?? null;

            if ($fromVersion && $toVersion) {
                if ($fromVersion !== $toVersion) {
                    $changes[] = new DependencyChange($package, $fromVersion, $toVersion, DependencyChange::TYPE_UPDATED);
                }
            } elseif ($toVersion) {
                $changes[] = new DependencyChange($package, null, $toVersion, DependencyChange::TYPE_ADDED);
            } elseif ($fromVersion) {
                $changes[] = new DependencyChange($package, $fromVersion, null, DependencyChange::TYPE_REMOVED);
            }
        }

        return $changes;
    }

    /**
     * Parse diff content for dependency changes.
     * Supports both composer.lock format (separate name/version lines) and package.json format (inline).
     *
     * @param array<string, string> $removedPackages
     * @param array<string, string> $addedPackages
     */
    private function parseDiff(string $diffContent, array &$removedPackages, array &$addedPackages): void
    {
        $lines = explode("\n", $diffContent);
        $lineCount = \count($lines);

        for ($i = 0; $i < $lineCount; ++$i) {
            $line = $lines[$i];
            $trimmedLine = trim($line);

            // Pattern 1a: composer.lock format - added/removed name line with version on separate line
            if (preg_match('/^([+-])\s*"name":\s*"([^"]+)"/', $line, $nameMatch)) {
                $prefix = $nameMatch[1];
                $packageName = $nameMatch[2];

                $version = $this->findVersionNearby($lines, $i, $prefix);

                if ('-' === $prefix && $version) {
                    $removedPackages[$packageName] = $version;
                } elseif ('+' === $prefix && $version) {
                    $addedPackages[$packageName] = $version;
                }

                continue;
            }

            // Pattern 1b: composer.lock format - neutral name line with version changes nearby
            if (!str_starts_with($trimmedLine, '-') && !str_starts_with($trimmedLine, '+')
                && preg_match('/^\s*"name":\s*"([^"]+)"/', $line, $nameMatch)) {
                $packageName = $nameMatch[1];
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

                continue;
            }

            // Pattern 2: package.json format - "package-name": "version" on same line
            if (preg_match('/^([+-])\s*"([^"]+)":\s*"([^"]+)"/', $line, $match)) {
                $prefix = $match[1];
                $packageName = $match[2];
                $version = $match[3];

                // Skip excluded keys (not package names)
                if (\in_array($packageName, self::EXCLUDED_KEYS, true)) {
                    continue;
                }

                // Version must look like semver (starts with digit, ^, ~, >=, <, *, workspace:, etc.)
                if (!preg_match('/^[\d^~>=<*]|^workspace:/', $version)) {
                    continue;
                }

                if ('-' === $prefix) {
                    $removedPackages[$packageName] = $version;
                } else {
                    $addedPackages[$packageName] = $version;
                }
            }
        }
    }

    /**
     * Find version string near a given line index.
     *
     * @param string[] $lines
     */
    private function findVersionNearby(array $lines, int $currentIndex, string $prefix): ?string
    {
        $searchRange = 15;
        $start = max(0, $currentIndex - $searchRange);
        $end = min(\count($lines), $currentIndex + $searchRange);

        for ($i = $start; $i < $end; ++$i) {
            $pattern = '/^'.preg_quote($prefix, '/').'\s*"version":\s*"([^"]+)"/';
            if (preg_match($pattern, $lines[$i], $match)) {
                return $match[1];
            }
        }

        return null;
    }
}
