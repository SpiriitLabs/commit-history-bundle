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

class PackageDiffParser implements DiffParserInterface
{
    private const SUPPORTED_FILES = ['package.json', 'package-lock.json'];

    private const NON_DEPENDENCY_KEYS = [
        'name',
        'version',
        'description',
        'main',
        'scripts',
        'repository',
        'keywords',
        'author',
        'license',
        'bugs',
        'homepage',
        'private',
        'engines',
        'browserslist',
    ];

    public function supports(string $filename): bool
    {
        $baseName = basename($filename);

        return \in_array($baseName, self::SUPPORTED_FILES, true);
    }

    /**
     * @return DependencyChange[]
     */
    public function parse(string $diff, string $filename): array
    {
        $baseName = basename($filename);

        if ('package-lock.json' === $baseName) {
            return $this->parsePackageLock($diff, $filename);
        }

        return $this->parsePackageJson($diff, $filename);
    }

    /**
     * @return DependencyChange[]
     */
    private function parsePackageJson(string $diff, string $filename): array
    {
        $changes = [];
        $lines = explode("\n", $diff);

        foreach ($lines as $line) {
            // Match lines like: +    "react": "^18.0.0",
            // or              -    "react": "^17.0.0",
            if (preg_match('/^([+-])\s*"([^"]+)":\s*"([^"]+)"/', $line, $matches)) {
                $operation = $matches[1];
                $package = $matches[2];
                $version = $matches[3];

                // Skip non-dependency keys
                if (\in_array($package, self::NON_DEPENDENCY_KEYS, true)) {
                    continue;
                }

                // Skip if it looks like a URL or path
                if (str_starts_with($version, 'http') || str_starts_with($version, 'file:') || str_starts_with($version, 'git')) {
                    continue;
                }

                if ('+' === $operation) {
                    if (!isset($changes[$package])) {
                        $changes[$package] = ['name' => $package, 'sourceFile' => $filename];
                    }
                    $changes[$package]['newVersion'] = $version;
                } else {
                    if (!isset($changes[$package])) {
                        $changes[$package] = ['name' => $package, 'sourceFile' => $filename];
                    }
                    $changes[$package]['oldVersion'] = $version;
                }
            }
        }

        return $this->buildDependencyChanges($changes);
    }

    /**
     * @return DependencyChange[]
     */
    private function parsePackageLock(string $diff, string $filename): array
    {
        $changes = [];
        $lines = explode("\n", $diff);
        $currentPackage = null;

        foreach ($lines as $line) {
            // Match package entry: +    "node_modules/lodash": {
            // or:                  -    "lodash": {
            if (preg_match('/^([+-])\s*"(?:node_modules\/)?([^"]+)":\s*\{/', $line, $matches)) {
                $package = $matches[2];

                // Skip nested node_modules paths
                if (str_contains($package, '/node_modules/')) {
                    continue;
                }

                $currentPackage = $package;
                if (!isset($changes[$currentPackage])) {
                    $changes[$currentPackage] = ['name' => $currentPackage, 'sourceFile' => $filename];
                }
            }
            // Match version in lock file
            elseif (null !== $currentPackage && preg_match('/^([+-])\s*"version":\s*"([^"]+)"/', $line, $matches)) {
                $operation = $matches[1];
                $version = $matches[2];

                if ('+' === $operation) {
                    $changes[$currentPackage]['newVersion'] = $version;
                } else {
                    $changes[$currentPackage]['oldVersion'] = $version;
                }
            }
            // Reset on chunk boundaries
            elseif (str_starts_with($line, '@@')) {
                $currentPackage = null;
            }
        }

        return $this->buildDependencyChanges($changes);
    }

    /**
     * @param array<string, array{name: string, sourceFile: string, oldVersion?: string, newVersion?: string}> $changes
     *
     * @return DependencyChange[]
     */
    private function buildDependencyChanges(array $changes): array
    {
        $result = [];

        foreach ($changes as $change) {
            $hasOld = !empty($change['oldVersion']);
            $hasNew = !empty($change['newVersion']);

            if (!$hasOld && !$hasNew) {
                continue;
            }

            $type = match (true) {
                $hasOld && $hasNew => DependencyChange::TYPE_UPDATED,
                $hasNew => DependencyChange::TYPE_ADDED,
                default => DependencyChange::TYPE_REMOVED,
            };

            $result[] = new DependencyChange(
                name: $change['name'],
                type: $type,
                oldVersion: $change['oldVersion'] ?? null,
                newVersion: $change['newVersion'] ?? null,
                sourceFile: $change['sourceFile'],
            );
        }

        return $result;
    }
}
