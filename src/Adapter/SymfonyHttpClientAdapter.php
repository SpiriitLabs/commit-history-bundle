<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Adapter;

use Spiriit\CommitHistory\Contract\HttpClientInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface as SymfonyHttpClientInterface;

final class SymfonyHttpClientAdapter implements HttpClientInterface
{
    public function __construct(
        private readonly SymfonyHttpClientInterface $httpClient,
    ) {
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, headers: array<string, list<string>>, body: string}
     */
    public function request(string $method, string $url, array $headers = []): array
    {
        $response = $this->httpClient->request($method, $url, [
            'headers' => $headers,
        ]);

        return [
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => $response->getContent(),
        ];
    }
}
