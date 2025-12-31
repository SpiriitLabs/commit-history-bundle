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
        $url = rtrim($this->baseUrl, '/').'/repos/'.$this->owner.'/'.$this->repo.'/commits';
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
            $options = [
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                ],
                'query' => $params,
            ];

            if (null !== $this->token) {
                $options['headers']['Authorization'] = 'Bearer '.$this->token;
            }

            $response = $this->httpClient->request('GET', $url, $options);
            $data = $response->toArray();

            foreach ($data as $item) {
                $commits[] = $this->parser->parse($item);
            }

            // Parse Link header for next page
            $headers = $response->getHeaders();
            $linkHeader = $headers['link'][0] ?? '';
            $url = $this->extractNextUrl($linkHeader);
            $params = []; // URL already contains query params
        } while (null !== $url && !empty($data));

        return $commits;
    }

    private function extractNextUrl(string $linkHeader): ?string
    {
        if (preg_match('/<([^>]+)>;\s*rel="next"/', $linkHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
