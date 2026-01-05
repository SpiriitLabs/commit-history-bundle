<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Provider\Github;

use Spiriit\Bundle\CommitHistoryBundle\DTO\Commit;
use Spiriit\Bundle\CommitHistoryBundle\Provider\CommitParserInterface;
use Spiriit\Bundle\CommitHistoryBundle\Provider\ProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Provider implements ProviderInterface
{
    private const COMPOSER_FILES = ['composer.lock', 'composer.json'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CommitParserInterface $parser,
        private readonly string $baseUrl,
        private readonly string $owner,
        private readonly string $repo,
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
        $url = $this->buildApiUrl('/commits');
        $params = ['per_page' => 100];

        if (null !== $this->ref) {
            $params['sha'] = $this->ref;
        }

        if (null !== $since) {
            $params['since'] = $since->format('c');
        }

        if (null !== $until) {
            $params['until'] = $until->format('c');
        }

        do {
            $options = $this->buildRequestOptions($params);

            $response = $this->httpClient->request('GET', $url, $options);
            $data = $response->toArray();

            foreach ($data as $item) {
                $commit = $this->parser->parse($item);
                $hasComposerChanges = $this->hasComposerFileChanges($item['sha']);
                $commits[] = $commit->withHasComposerChanges($hasComposerChanges);
            }

            // Parse Link header for next page
            $headers = $response->getHeaders();
            $linkHeader = $headers['link'][0] ?? '';
            $url = $this->extractNextUrl($linkHeader);
            $params = []; // URL already contains query params
        } while (null !== $url && !empty($data));

        return $commits;
    }

    /**
     * @return string[]
     */
    public function getCommitFileNames(string $commitId): array
    {
        $url = $this->buildApiUrl('/commits/'.$commitId);
        $options = $this->buildRequestOptions();

        $response = $this->httpClient->request('GET', $url, $options);
        $data = $response->toArray();

        $fileNames = [];
        foreach ($data['files'] ?? [] as $file) {
            if (isset($file['filename'])) {
                $fileNames[] = $file['filename'];
            }
        }

        return $fileNames;
    }

    public function getCommitDiff(string $commitId): ?string
    {
        $url = $this->buildApiUrl('/commits/'.$commitId);
        $options = $this->buildRequestOptions();

        $response = $this->httpClient->request('GET', $url, $options);
        $data = $response->toArray();

        $diffContent = '';
        foreach ($data['files'] ?? [] as $file) {
            if (isset($file['patch'])) {
                $filename = $file['filename'] ?? 'unknown';
                $diffContent .= "--- a/{$filename}\n";
                $diffContent .= "+++ b/{$filename}\n";
                $diffContent .= $file['patch']."\n";
            }
        }

        return '' !== $diffContent ? $diffContent : null;
    }

    private function hasComposerFileChanges(string $commitId): bool
    {
        $fileNames = $this->getCommitFileNames($commitId);

        foreach ($fileNames as $fileName) {
            if (\in_array(basename($fileName), self::COMPOSER_FILES, true)) {
                return true;
            }
        }

        return false;
    }

    private function buildApiUrl(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/repos/'.$this->owner.'/'.$this->repo.$path;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function buildRequestOptions(array $params = []): array
    {
        $options = [
            'headers' => [
                'Accept' => 'application/vnd.github+json',
            ],
        ];

        if (!empty($params)) {
            $options['query'] = $params;
        }

        if (null !== $this->token) {
            $options['headers']['Authorization'] = 'Bearer '.$this->token;
        }

        return $options;
    }

    private function extractNextUrl(string $linkHeader): ?string
    {
        if (preg_match('/<([^>]+)>;\s*rel="next"/', $linkHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
