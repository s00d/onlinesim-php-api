<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi;

use s00d\OnlineSimApi\Transport\CurlTransport;
use s00d\OnlineSimApi\Transport\Transport;

/**
 * OnlineSim API client.
 *
 * ```php
 * $client = new OnlineSim(apiKey: getenv('ONLINESIM_KEY') ?: null);
 * $balance = $client->user->balance()['balance'];
 * ```
 */
final class OnlineSim
{
    public const DEFAULT_BASE_URL = 'https://onlinesim.host/api/';

    public readonly Numbers $numbers;
    public readonly Rent $rent;
    public readonly User $user;
    public readonly Free $free;

    /**
     * @param string|null     $apiKey    sent as `Authorization: Bearer <apiKey>`; not needed for tariffs and free numbers
     * @param string          $baseUrl   full API base; trailing slash optional
     * @param string          $lang      language of server texts (`lang` query parameter)
     * @param string|int|null $devId     partner developer id (`dev_id` query parameter), sent only when set
     * @param float           $timeout   per-request timeout, seconds
     * @param Transport|null  $transport default: `CurlTransport`
     */
    public function __construct(
        ?string $apiKey = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        string $lang = 'en',
        string|int|null $devId = null,
        float $timeout = 30.0,
        ?Transport $transport = null,
    ) {
        $requester = new Requester(
            transport: $transport ?? new CurlTransport(),
            baseUrl: str_ends_with($baseUrl, '/') ? $baseUrl : "{$baseUrl}/",
            apiKey: $apiKey === '' ? null : $apiKey,
            lang: $lang,
            devId: $devId === null || $devId === '' ? null : (string) $devId,
            timeout: $timeout,
        );
        $this->numbers = new Numbers($requester);
        $this->rent = new Rent($requester);
        $this->user = new User($requester);
        $this->free = new Free($requester);
    }
}
