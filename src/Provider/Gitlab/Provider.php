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
            $url = rtrim($this->baseUrl, '/').'/api/v4/projects/'.urlencode($this->projectId).'/repository/commits';

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

            if (null !== $this->token) {
                $options['headers'] = ['PRIVATE-TOKEN' => $this->token];
            }

            $response = $this->httpClient->request('GET', $url, $options);
            $data = $response->toArray();

            if (empty($data)) {
                break;
            }

            foreach ($data as $item) {
                $commits[] = $this->parser->parse($item);
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
        $diffs = $this->fetchCommitDiff($commitId);

        $files = [];
        foreach ($diffs as $diff) {
            if (!empty($diff['new_path'])) {
                $files[] = $diff['new_path'];
            } elseif (!empty($diff['old_path'])) {
                $files[] = $diff['old_path'];
            }
        }

        return array_unique($files);
    }

    /**
     * @return array<string, string>
     */
    public function getCommitDiff(string $commitId): array
    {
        $diffs = $this->fetchCommitDiff($commitId);

        $result = [];
        foreach ($diffs as $diff) {
            $filename = $diff['new_path'] ?? $diff['old_path'] ?? '';
            if (!empty($filename) && isset($diff['diff'])) {
                $result[$filename] = $diff['diff'];
            }
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchCommitDiff(string $commitId): array
    {
        $url = rtrim($this->baseUrl, '/').'/api/v4/projects/'.urlencode($this->projectId).'/repository/commits/'.$commitId.'/diff';

        $options = [];
        if (null !== $this->token) {
            $options['headers'] = ['PRIVATE-TOKEN' => $this->token];
        }

        $response = $this->httpClient->request('GET', $url, $options);

        return $response->toArray();
    }
}
