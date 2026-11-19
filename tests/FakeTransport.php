<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Tests;

use s00d\OnlineSimApi\Transport\Response;
use s00d\OnlineSimApi\Transport\Transport;

/**
 * Answers canned replies in order and records every request.
 *
 * A reply is `['json' => mixed, 'status' => int]`, `['raw' => string, 'status' => int]`, or a Throwable to throw.
 *
 * @phpstan-type Reply array{json?: mixed, raw?: string, status?: int}|\Throwable
 * @phpstan-type Call array{method: string, url: string, path: string, query: array<string, string>, headers: array<string, string>, body: mixed, timeout: float}
 */
final class FakeTransport implements Transport
{
    /** @var list<Call> */
    public array $calls = [];

    /** @param list<Reply> $replies */
    public function __construct(private array $replies)
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): Response
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);
        /** @var array<string, string> $query */
        $this->calls[] = [
            'method' => $method,
            'url' => $url,
            'path' => $parts['path'] ?? '',
            'query' => $query,
            'headers' => $headers,
            'body' => $body === null ? null : json_decode($body, true, 512, JSON_THROW_ON_ERROR),
            'timeout' => $timeout,
        ];
        $reply = array_shift($this->replies) ?? throw new \LogicException("unexpected request {$url}");
        if ($reply instanceof \Throwable) {
            throw $reply;
        }

        return new Response($reply['status'] ?? 200, $reply['raw'] ?? json_encode($reply['json'] ?? null, JSON_THROW_ON_ERROR));
    }
}
