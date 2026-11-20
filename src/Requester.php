<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi;

use s00d\OnlineSimApi\Exception\ApiError;
use s00d\OnlineSimApi\Exception\NoNumberError;
use s00d\OnlineSimApi\Exception\TransportError;
use s00d\OnlineSimApi\Transport\Transport;

/**
 * The one request function every section calls: owns the client settings and the transport,
 * encodes the request and maps the answer (see CONVENTIONS "Response handling").
 *
 * @internal created by `OnlineSim`; not part of the public API
 */
final class Requester
{
    private const BODY_EXCERPT = 500;

    public function __construct(
        private readonly Transport $transport,
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly string $lang,
        private readonly ?string $devId,
        private readonly float $timeout,
    ) {
    }

    /**
     * @param string                $endpoint relative to the base URL, suffix included (`getNum.php`)
     * @param array<string, mixed>  $query    `null`, `''` and `[]` are omitted, booleans become `1`/`0`, lists a comma list
     * @param array<string, mixed>|null $json body; switches the request to POST
     *
     * @return array<array-key, mixed> the payload without `response`, or the whole body when it has none
     *
     * @throws ApiError|TransportError
     */
    public function call(string $endpoint, array $query = [], ?array $json = null): array
    {
        $query += ['lang' => $this->lang, 'dev_id' => $this->devId];
        $params = [];
        foreach ($query as $key => $value) {
            $encoded = self::encode($value);
            if ($encoded !== null) {
                $params[$key] = $encoded;
            }
        }
        $url = $this->baseUrl . $endpoint . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $headers = ['Accept' => 'application/json'];
        if ($this->apiKey !== null) {
            $headers['Authorization'] = "Bearer {$this->apiKey}";
        }
        $body = null;
        if ($json !== null) {
            $headers['Content-Type'] = 'application/json';
            $body = json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $response = $this->transport->send($json === null ? 'GET' : 'POST', $url, $headers, $body, $this->timeout);
        $status = $response->status;
        $text = $response->body;

        try {
            $data = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new TransportError("HTTP {$status}: response is not JSON", $status, self::excerpt($text), $e);
        }
        // The body is checked before the status: a wrong key is HTTP 456 with {"response":"ERROR_WRONG_KEY"},
        // the rate limit HTTP 429 with {"response":"TRY_AGAIN_LATER"}.
        if (is_array($data) && !array_is_list($data) && array_key_exists('response', $data)) {
            return self::unwrap($data);
        }
        if ($status < 200 || $status >= 300) {
            throw new TransportError("HTTP {$status}", $status, self::excerpt($text));
        }
        if (!is_array($data)) {
            throw new TransportError("HTTP {$status}: unexpected JSON value", $status, self::excerpt($text));
        }

        return $data;
    }

    /**
     * Success: strip `response`, return the rest. Error code: throw.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function unwrap(array $data): array
    {
        $response = $data['response'];
        unset($data['response']);
        if ($response === 1 || $response === '1') {
            return $data;
        }
        if (is_array($response)) {
            // profile.php validation errors: {"response":{"webhook_url":["The webhook url must be a valid URL."]}}
            $messages = [];
            array_walk_recursive($response, static function (mixed $m) use (&$messages): void {
                $messages[] = is_scalar($m) ? (string) $m : '';
            });

            throw new ApiError('VALIDATION_ERROR', implode('; ', array_filter($messages, static fn (string $m) => $m !== '')));
        }
        $code = is_scalar($response) ? (string) $response : 'UNKNOWN_ERROR';
        // Debug backends echo the code as `message`; prefer the readable fallback then.
        $message = isset($data['message']) && is_string($data['message']) && $data['message'] !== $code ? $data['message'] : null;
        if ($code === 'NO_NUMBER') {
            throw new NoNumberError($code, $message);
        }

        throw new ApiError($code, $message);
    }

    private static function encode(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            $value = implode(',', array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $value));
        }
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }
        if ($value === null || $value === '' || !is_scalar($value)) {
            return null;
        }

        return (string) $value;
    }

    private static function excerpt(string $text): string
    {
        // Cut on a UTF-8 character boundary when the body is valid UTF-8.
        return preg_match('/^.{0,' . self::BODY_EXCERPT . '}/su', $text, $m) === 1 ? $m[0] : substr($text, 0, self::BODY_EXCERPT);
    }
}
