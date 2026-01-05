<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Provider\Gitlab;

use Spiriit\Bundle\CommitHistoryBundle\DTO\Commit;
use Spiriit\Bundle\CommitHistoryBundle\Provider\CommitParserInterface;
use Spiriit\Bundle\CommitHistoryBundle\Provider\ProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Provider implements ProviderInterface
{
    private const DEPENDENCY_FILES = [
        'composer.lock',
        'composer.json',
        'package.json',
        'package-lock.json',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CommitParserInterface $parser,
        private readonly string $baseUrl,
        private readonly string $projectId,
        private readonly ?string $token = null,
        private readonly ?string $ref = null,
    ) {
    }

    /**
     * @return Commit[]
     */
    public function getCommits(?\DateTimeImmutable $since = null, ?\DateTimeImmutable $until = null): array
    {
        $commits = [];
        $page = 1;
        $perPage = 100;

        do {
            $url = $this->buildApiUrl('/repository/commits');

            $options = [
                'query' => [
                    'page' => $page,
                    'per_page' => $perPage,
                ],
            ];

            if (null !== $this->ref) {
                $options['query']['ref_name'] = $this->ref;
            }

            if (null !== $since) {
                $options['query']['since'] = $since->format('c');
            }

            if (null !== $until) {
                $options['query']['until'] = $until->format('c');
            }

            $this->addAuthHeaders($options);

            $response = $this->httpClient->request('GET', $url, $options);
            $data = $response->toArray();

            if (empty($data)) {
                break;
            }

            foreach ($data as $item) {
                $commit = $this->parser->parse($item);
                $hasDependencyChanges = $this->hasDependencyFileChanges($item['id']);
                $commits[] = $commit->withHasDependenciesChanges($hasDependencyChanges);
            }

            ++$page;
        } while (\count($data) === $perPage);

        return $commits;
    }

    /**
     * @return string[]
     */
    public function getCommitFileNames(string $commitId): array
    {
        $url = $this->buildApiUrl('/repository/commits/'.$commitId.'/diff');
        $options = [];
        $this->addAuthHeaders($options);

        $response = $this->httpClient->request('GET', $url, $options);
        $data = $response->toArray();

        $fileNames = [];
        foreach ($data as $diff) {
            if (isset($diff['new_path'])) {
                $fileNames[] = $diff['new_path'];
            }
            if (isset($diff['old_path']) && $diff['old_path'] !== ($diff['new_path'] ?? null)) {
                $fileNames[] = $diff['old_path'];
            }
        }

        return array_unique($fileNames);
    }

    public function getCommitDiff(string $commitId): ?string
    {
        $url = $this->buildApiUrl('/repository/commits/'.$commitId.'/diff');
        $options = [];
        $this->addAuthHeaders($options);

        $response = $this->httpClient->request('GET', $url, $options);
        $data = $response->toArray();

        $diffContent = '';
        foreach ($data as $diff) {
            if (isset($diff['diff'])) {
                $diffContent .= "--- a/{$diff['old_path']}\n";
                $diffContent .= "+++ b/{$diff['new_path']}\n";
                $diffContent .= $diff['diff']."\n";
            }
        }

        return '' !== $diffContent ? $diffContent : null;
    }

    private function hasDependencyFileChanges(string $commitId): bool
    {
        $fileNames = $this->getCommitFileNames($commitId);

        foreach ($fileNames as $fileName) {
            if (\in_array(basename($fileName), self::DEPENDENCY_FILES, true)) {
                return true;
            }
        }

        return false;
    }

    private function buildApiUrl(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/api/v4/projects/'.urlencode($this->projectId).$path;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function addAuthHeaders(array &$options): void
    {
        if (null !== $this->token) {
            $options['headers'] = ['PRIVATE-TOKEN' => $this->token];
        }
    }
}
