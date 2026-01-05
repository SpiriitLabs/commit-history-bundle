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
    private const DIFF_LINE_ADDED = '+';
    private const DIFF_LINE_REMOVED = '-';
    private const VERSION_SEARCH_RANGE = 15;

    /**
     * Regex patterns for parsing diff content.
     */
    private const PATTERN_COMPOSER_LOCK_NAME_WITH_PREFIX = '/^([+-])\s*"name":\s*"([^"]+)"/';
    private const PATTERN_COMPOSER_LOCK_NAME_NEUTRAL = '/^\s*"name":\s*"([^"]+)"/';
    private const PATTERN_PACKAGE_JSON_DEPENDENCY = '/^([+-])\s*"([^"]+)":\s*"([^"]+)"/';
    private const PATTERN_SEMVER_VERSION = '/^[\d^~>=<*]|^workspace:/';

    /**
     * Keys that are not package names in dependency files (composer.json, package.json).
     */
    private const EXCLUDED_KEYS = [
        // composer.json / composer.lock keys
        'name', 'version', 'type', 'source', 'dist', 'require', 'require-dev',
        'autoload', 'description', 'license', 'keywords', 'authors', 'homepage',
        'support', 'funding', 'time', 'extra', 'scripts', 'config',
        'minimum-stability', 'prefer-stable', 'repositories', 'bin', 'archive',
        'abandoned', 'non-feature-branches',
        // package.json keys
        'dependencies', 'devDependencies', 'peerDependencies', 'optionalDependencies',
        'bundledDependencies', 'engines', 'main', 'module', 'browser', 'types',
        'typings', 'exports', 'files', 'private', 'publishConfig', 'workspaces',
    ];

    public function parse(string $diffContent): array
    {
        $removedPackages = [];
        $addedPackages = [];

        $this->extractPackagesFromDiff($diffContent, $removedPackages, $addedPackages);

        return $this->buildDependencyChanges($removedPackages, $addedPackages);
    }

    /**
     * Extract added and removed packages from diff content.
     *
     * @param array<string, string> $removedPackages
     * @param array<string, string> $addedPackages
     */
    private function extractPackagesFromDiff(string $diffContent, array &$removedPackages, array &$addedPackages): void
    {
        $lines = explode("\n", $diffContent);
        $lineCount = \count($lines);

        for ($lineIndex = 0; $lineIndex < $lineCount; ++$lineIndex) {
            $line = $lines[$lineIndex];

            if ($this->tryParseComposerLockAddedOrRemovedPackage($lines, $lineIndex, $removedPackages, $addedPackages)) {
                continue;
            }

            if ($this->tryParseComposerLockUpdatedPackage($lines, $lineIndex, $removedPackages, $addedPackages)) {
                continue;
            }

            $this->tryParsePackageJsonDependency($line, $removedPackages, $addedPackages);
        }
    }

    /**
     * Try to parse a composer.lock format line where the package is added or removed.
     * Format: +/- "name": "vendor/package" with "version": "x.x.x" on a nearby line.
     *
     * @param string[]              $lines
     * @param array<string, string> $removedPackages
     * @param array<string, string> $addedPackages
     */
    private function tryParseComposerLockAddedOrRemovedPackage(
        array $lines,
        int $lineIndex,
        array &$removedPackages,
        array &$addedPackages,
    ): bool {
        if (!preg_match(self::PATTERN_COMPOSER_LOCK_NAME_WITH_PREFIX, $lines[$lineIndex], $matches)) {
            return false;
        }

        $diffPrefix = $matches[1];
        $packageName = $matches[2];
        $version = $this->findVersionNearLine($lines, $lineIndex, $diffPrefix);

        if (null === $version) {
            return true;
        }

        if (self::DIFF_LINE_REMOVED === $diffPrefix) {
            $removedPackages[$packageName] = $version;
        } else {
            $addedPackages[$packageName] = $version;
        }

        return true;
    }

    /**
     * Try to parse a composer.lock format line where only the version changed.
     * Format: "name": "vendor/package" (unchanged) with -/+ "version": "x.x.x" nearby.
     *
     * @param string[]              $lines
     * @param array<string, string> $removedPackages
     * @param array<string, string> $addedPackages
     */
    private function tryParseComposerLockUpdatedPackage(
        array $lines,
        int $lineIndex,
        array &$removedPackages,
        array &$addedPackages,
    ): bool {
        $line = $lines[$lineIndex];

        if ($this->isAddedOrRemovedLine($line)) {
            return false;
        }

        if (!preg_match(self::PATTERN_COMPOSER_LOCK_NAME_NEUTRAL, $line, $matches)) {
            return false;
        }

        $packageName = $matches[1];
        $oldVersion = $this->findVersionNearLine($lines, $lineIndex, self::DIFF_LINE_REMOVED);
        $newVersion = $this->findVersionNearLine($lines, $lineIndex, self::DIFF_LINE_ADDED);

        if (null === $oldVersion || null === $newVersion || $oldVersion === $newVersion) {
            return true;
        }

        $removedPackages[$packageName] ??= $oldVersion;
        $addedPackages[$packageName] ??= $newVersion;

        return true;
    }

    /**
     * Try to parse a package.json format dependency line.
     * Format: +/- "package-name": "^1.0.0".
     *
     * @param array<string, string> $removedPackages
     * @param array<string, string> $addedPackages
     */
    private function tryParsePackageJsonDependency(string $line, array &$removedPackages, array &$addedPackages): bool
    {
        if (!preg_match(self::PATTERN_PACKAGE_JSON_DEPENDENCY, $line, $matches)) {
            return false;
        }

        $diffPrefix = $matches[1];
        $key = $matches[2];
        $value = $matches[3];

        if (!$this->isValidPackageName($key)) {
            return false;
        }

        if (!$this->isValidVersionString($value)) {
            return false;
        }

        if (self::DIFF_LINE_REMOVED === $diffPrefix) {
            $removedPackages[$key] = $value;
        } else {
            $addedPackages[$key] = $value;
        }

        return true;
    }

    /**
     * Build DependencyChange objects from the extracted package data.
     *
     * @param array<string, string> $removedPackages
     * @param array<string, string> $addedPackages
     *
     * @return DependencyChange[]
     */
    private function buildDependencyChanges(array $removedPackages, array $addedPackages): array
    {
        /** @var string[] $allPackageNames */
        $allPackageNames = array_unique(array_merge(array_keys($removedPackages), array_keys($addedPackages)));
        sort($allPackageNames);

        /** @var DependencyChange[] $changes */
        $changes = [];

        foreach ($allPackageNames as $packageName) {
            $oldVersion = $removedPackages[$packageName] ?? null;
            $newVersion = $addedPackages[$packageName] ?? null;

            $change = $this->createDependencyChange($packageName, $oldVersion, $newVersion);

            if (null !== $change) {
                $changes[] = $change;
            }
        }

        return $changes;
    }

    /**
     * Create a DependencyChange object based on version changes.
     */
    private function createDependencyChange(string $packageName, ?string $oldVersion, ?string $newVersion): ?DependencyChange
    {
        $hasOldVersion = null !== $oldVersion;
        $hasNewVersion = null !== $newVersion;

        if ($hasOldVersion && $hasNewVersion) {
            return $oldVersion !== $newVersion
                ? new DependencyChange($packageName, $oldVersion, $newVersion, DependencyChange::TYPE_UPDATED)
                : null;
        }

        if ($hasNewVersion) {
            return new DependencyChange($packageName, null, $newVersion, DependencyChange::TYPE_ADDED);
        }

        if ($hasOldVersion) {
            return new DependencyChange($packageName, $oldVersion, null, DependencyChange::TYPE_REMOVED);
        }

        return null;
    }

    /**
     * Find a version string near the given line index with the specified diff prefix.
     *
     * @param string[] $lines
     */
    private function findVersionNearLine(array $lines, int $lineIndex, string $diffPrefix): ?string
    {
        $startIndex = max(0, $lineIndex - self::VERSION_SEARCH_RANGE);
        $endIndex = min(\count($lines), $lineIndex + self::VERSION_SEARCH_RANGE);

        $pattern = '/^'.preg_quote($diffPrefix, '/').'\s*"version":\s*"([^"]+)"/';

        for ($i = $startIndex; $i < $endIndex; ++$i) {
            if (preg_match($pattern, $lines[$i], $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Check if the line starts with a diff add (+) or remove (-) prefix.
     */
    private function isAddedOrRemovedLine(string $line): bool
    {
        $trimmedLine = trim($line);

        return str_starts_with($trimmedLine, self::DIFF_LINE_ADDED)
            || str_starts_with($trimmedLine, self::DIFF_LINE_REMOVED);
    }

    /**
     * Check if the key is a valid package name (not a reserved key).
     */
    private function isValidPackageName(string $key): bool
    {
        return !\in_array($key, self::EXCLUDED_KEYS, true);
    }

    /**
     * Check if the value looks like a valid version string.
     */
    private function isValidVersionString(string $value): bool
    {
        return 1 === preg_match(self::PATTERN_SEMVER_VERSION, $value);
    }
}
