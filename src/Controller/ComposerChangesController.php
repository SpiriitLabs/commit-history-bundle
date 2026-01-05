<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Controller;

use Spiriit\Bundle\CommitHistoryBundle\Provider\ProviderInterface;
use Spiriit\Bundle\CommitHistoryBundle\Service\ComposerDiffParserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class ComposerChangesController
{
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly ComposerDiffParserInterface $composerDiffParser,
    ) {
    }

    public function __invoke(string $commitId): JsonResponse
    {
        $diff = $this->provider->getCommitDiff($commitId);

        if (null === $diff) {
            return new JsonResponse([
                'commitId' => $commitId,
                'hasComposerChanges' => false,
                'changes' => [],
            ]);
        }

        $changes = $this->composerDiffParser->parse($diff);

        return new JsonResponse([
            'commitId' => $commitId,
            'hasComposerChanges' => \count($changes) > 0,
            'changes' => array_map(fn ($change) => [
                'package' => $change->package,
                'from' => $change->fromVersion,
                'to' => $change->toVersion,
                'type' => $change->type,
            ], $changes),
        ]);
    }
}
