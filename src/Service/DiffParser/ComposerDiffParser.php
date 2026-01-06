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

class ComposerDiffParser implements DiffParserInterface
{
    private const SUPPORTED_FILES = ['composer.json', 'composer.lock'];

    private const NON_DEPENDENCY_KEYS = [
        'name',
        'description',
        'type',
        'license',
        'minimum-stability',
        'prefer-stable',
        'autoload',
        'autoload-dev',
        'scripts',
        'config',
        'extra',
        'repositories',
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

        if ('composer.lock' === $baseName) {
            return $this->parseComposerLock($diff, $filename);
        }

        return $this->parseComposerJson($diff, $filename);
    }

    /**
     * @return DependencyChange[]
     */
    private function parseComposerJson(string $diff, string $filename): array
    {
        $changes = [];
        $lines = explode("\n", $diff);

        foreach ($lines as $line) {
            // Match lines like: +        "symfony/http-client": "^7.0",
            // or              -        "symfony/http-client": "^6.4",
            if (preg_match('/^([+-])\s*"([^"]+)":\s*"([^"]+)"/', $line, $matches)) {
                $operation = $matches[1];
                $package = $matches[2];
                $version = $matches[3];

                // Skip non-dependency keys
                if (\in_array($package, self::NON_DEPENDENCY_KEYS, true)) {
                    continue;
                }

                // Skip if it doesn't look like a package name (should contain /)
                if (!str_contains($package, '/')) {
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
    private function parseComposerLock(string $diff, string $filename): array
    {
        $changes = [];
        $lines = explode("\n", $diff);
        $currentPackage = null;
        $currentOperation = null;

        foreach ($lines as $line) {
            // Match package name: +            "name": "symfony/http-client",
            if (preg_match('/^([+-])\s*"name":\s*"([^"]+)"/', $line, $matches)) {
                $currentOperation = $matches[1];
                $currentPackage = $matches[2];

                if (!isset($changes[$currentPackage])) {
                    $changes[$currentPackage] = ['name' => $currentPackage, 'sourceFile' => $filename];
                }
            }
            // Match version: +            "version": "v7.0.0",
            elseif (null !== $currentPackage && preg_match('/^([+-])\s*"version":\s*"([^"]+)"/', $line, $matches)) {
                $operation = $matches[1];
                $version = $matches[2];

                if ('+' === $operation) {
                    $changes[$currentPackage]['newVersion'] = $version;
                } else {
                    $changes[$currentPackage]['oldVersion'] = $version;
                }
            }
            // Reset on chunk boundaries or closing braces that indicate package block end
            elseif (str_starts_with($line, '@@') || preg_match('/^\s*\},?\s*$/', $line)) {
                $currentPackage = null;
                $currentOperation = null;
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
