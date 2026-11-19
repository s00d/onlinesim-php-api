<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Transport;

use s00d\OnlineSimApi\Exception\TransportError;

/** Default transport on ext-curl. */
final class CurlTransport implements Transport
{
    /**
     * @param array<int, mixed> $curlOptions extra `CURLOPT_*` options applied to every request
     *                                       (a proxy, a CA bundle, …); they override the defaults
     */
    public function __construct(private readonly array $curlOptions = [])
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): Response
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT_MS => (int) ceil($timeout * 1000),
            CURLOPT_NOSIGNAL => true,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new TransportError("Network error: cannot initialise curl for {$url}");
        }
        // No curl_close(): the handle is freed with the object (the call is a deprecated no-op since PHP 8.0).
        curl_setopt_array($handle, $this->curlOptions + $options);
        $text = curl_exec($handle);
        if (!is_string($text)) {
            throw new TransportError(curl_errno($handle) === CURLE_OPERATION_TIMEDOUT
                ? "Request timed out after {$timeout} s"
                : 'Network error: ' . curl_error($handle));
        }

        return new Response((int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $text);
    }
}
