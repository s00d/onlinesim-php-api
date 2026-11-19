<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Mock;

use s00d\OnlineSimApi\OnlineSim;
use s00d\OnlineSimApi\Transport\Response;
use s00d\OnlineSimApi\Transport\Transport;

/**
 * In-process fake OnlineSim API for tests: no network, no key, no money, no clock.
 *
 * It is a `Transport` that answers like the real backend (`$mock->transport` is the mock itself); `$mock->client()`
 * returns a normal `OnlineSim` wired to it. Test helpers add SMS, queue failures and expose the state.
 *
 * ```php
 * $mock = new MockOnlineSim(balance: 10);
 * $client = $mock->client();
 * $tzid = $client->numbers->get('google', 44);
 * $mock->addSmsAfter($tzid, 'Your code: 123-456', 2);
 * $client->numbers->waitCode($tzid, interval: 0); // '123456'
 * ```
 *
 * @phpstan-import-type MockPrices from MockState
 * @phpstan-import-type MockRentTariff from MockState
 * @phpstan-import-type MockOperation from MockState
 * @phpstan-import-type MockRent from MockState
 * @phpstan-import-type MockRequest from MockState
 */
final class MockOnlineSim implements Transport
{
    /** Base URL of clients made by `client()`. Never contacted: the mock answers in-process. */
    public const MOCK_BASE_URL = 'https://mock.onlinesim.invalid/api/';

    /** The fake transport (this object); pass it as `transport:` when you build the client yourself. Any base URL works. */
    public readonly Transport $transport;

    private readonly MockState $state;

    /**
     * @param string                          $apiKey        key the mock accepts as `Authorization: Bearer <apiKey>`
     * @param float                           $balance       starting balance, USD
     * @param MockPrices|null                 $prices        number prices, country → service → USD; replaces the defaults
     * @param array<int, MockRentTariff>|null $rentTariffs   rent tariffs, country → `['days' => [days => USD], 'extend' => 1|0]`; replaces the defaults
     * @param int                             $guardInterval seconds; `> 0`: an operation without SMS cannot be closed (`NO_COMPLETE_TZID`)
     */
    public function __construct(
        string $apiKey = 'mock-key',
        float $balance = 100,
        ?array $prices = null,
        ?array $rentTariffs = null,
        int $guardInterval = 0,
    ) {
        $this->state = new MockState(
            $apiKey,
            $balance,
            $prices ?? MockState::DEFAULT_PRICES,
            $rentTariffs ?? MockState::DEFAULT_RENT_TARIFFS,
            $guardInterval,
        );
        $this->transport = $this;
    }

    /**
     * A normal client wired to the mock. `$apiKey` defaults to the mock's key; pass another one (or `''` for none)
     * to test a wrong key. The other arguments are those of `OnlineSim`.
     */
    public function client(
        ?string $apiKey = null,
        string $baseUrl = self::MOCK_BASE_URL,
        string $lang = 'en',
        string|int|null $devId = null,
        float $timeout = 30.0,
    ): OnlineSim {
        return new OnlineSim(
            apiKey: $apiKey ?? $this->state->apiKey,
            baseUrl: $baseUrl,
            lang: $lang,
            devId: $devId,
            timeout: $timeout,
            transport: $this,
        );
    }

    /**
     * An SMS arrives now at number operation or rent `$tzid`.
     *
     * @throws \InvalidArgumentException when there is no open operation or rent with that id
     */
    public function addSms(int $tzid, string $text, ?string $service = null): void
    {
        if (!$this->state->deliver($tzid, $text, $service)) {
            throw new \InvalidArgumentException("MockOnlineSim: no open operation or rent {$tzid}");
        }
    }

    /**
     * The SMS arrives after `$polls` state requests for `$tzid` (getState / rent getRentState, listing it alone or
     * with others): with `$polls = 2` the third poll sees it. Lets `waitCode` run with `interval: 0`.
     *
     * @throws \InvalidArgumentException when there is no operation or rent with that id
     */
    public function addSmsAfter(int $tzid, string $text, int $polls, ?string $service = null): void
    {
        if (!isset($this->state->operations[$tzid]) && !isset($this->state->rents[$tzid])) {
            throw new \InvalidArgumentException("MockOnlineSim: no operation or rent {$tzid}");
        }
        $this->state->schedule($tzid, $text, $polls, $service);
    }

    /** The next request (to `$endpoint` only, e.g. `getNum.php`, when given) answers `{"response": $code}`. */
    public function failNext(string $code, ?string $endpoint = null): void
    {
        $this->state->failures[] = ['endpoint' => $endpoint, 'code' => $code, 'status' => 200];
    }

    /** The next request (to `$endpoint` only, when given) fails at transport level: a non-JSON body with HTTP `$status`. */
    public function failNextTransport(int $status = 502, ?string $endpoint = null): void
    {
        $this->state->failures[] = ['endpoint' => $endpoint, 'code' => null, 'status' => $status];
    }

    /** Set the total balance, USD. */
    public function setBalance(float $usd): void
    {
        $this->state->balance = MockState::toMills($usd);
    }

    /** Total balance, USD, including the part frozen by open operations (as `getBalance` reports it). */
    public function balance(): float
    {
        return MockState::fromMills($this->state->balance);
    }

    /**
     * Number operations, oldest first (copies).
     *
     * @return list<MockOperation>
     */
    public function operations(): array
    {
        return array_values($this->state->operations);
    }

    /**
     * Open rents, oldest first (copies).
     *
     * @return list<MockRent>
     */
    public function rents(): array
    {
        return array_values($this->state->rents);
    }

    /**
     * Every request received, in order: `method`, `path` (relative to `/api/`), `query`, `body` (decoded JSON, `null` for GET).
     *
     * @return list<MockRequest>
     */
    public function requests(): array
    {
        return $this->state->requests;
    }

    /** Back to the constructor's state: balance, no operations, rents, logs, schedules or failures. */
    public function reset(): void
    {
        $this->state->reset();
    }

    /** The transport: log request → failure queue → unknown path 404 → wrong key 456 → handler. */
    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): Response
    {
        $parts = parse_url($url);
        $path = is_array($parts) ? ($parts['path'] ?? '') : '';
        $at = strpos($path, '/api/');
        parse_str(is_array($parts) ? ($parts['query'] ?? '') : '', $rawQuery);
        $req = [
            'method' => strtoupper($method),
            'path' => $at === false ? ltrim($path, '/') : substr($path, $at + 5),
            'query' => array_filter($rawQuery, 'is_string'),
            'body' => $body === null ? null : json_decode($body, true),
        ];
        $state = $this->state;
        $state->requests[] = $req;

        $failure = $state->takeFailure($req['path']);
        if ($failure !== null) {
            return $failure['code'] !== null ? self::json(['response' => $failure['code']]) : self::text($failure['status'], 'Mock transport failure');
        }

        $handler = Handlers::route($req['path']);
        if ($handler === null) {
            return self::text(404, 'Not Found');
        }
        if (!in_array($req['path'], Handlers::PUBLIC_PATHS, true) && self::header($headers, 'Authorization') !== "Bearer {$state->apiKey}") {
            return self::json(['response' => 'ERROR_WRONG_KEY'], 456);
        }

        return self::json($handler($state, $req));
    }

    /** @param array<string, string> $headers */
    private static function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    private static function json(mixed $body, int $status = 200): Response
    {
        return new Response($status, json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private static function text(int $status, string $body): Response
    {
        return new Response($status, "<html><body>{$status} {$body}</body></html>");
    }
}
