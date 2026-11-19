<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Transport;

use s00d\OnlineSimApi\Exception\TransportError;

/**
 * Sends one HTTP request. The default is `CurlTransport`; tests pass a fake.
 * Any HTTP status is a `Response`; only a failure without a response (network error, timeout) throws.
 */
interface Transport
{
    /**
     * @param 'GET'|'POST'          $method
     * @param array<string, string> $headers
     * @param float                 $timeout seconds
     *
     * @throws TransportError
     */
    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): Response;
}
