<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Tests;

use s00d\OnlineSimApi\OnlineSim;

/**
 * @phpstan-import-type Reply from FakeTransport
 * @phpstan-import-type Call from FakeTransport
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected FakeTransport $transport;

    /**
     * Client on a fake transport that answers `$replies` in order.
     *
     * @param list<Reply> $replies
     */
    protected function client(array $replies, ?string $apiKey = 'test-key', string $baseUrl = 'https://api.test/api/', string $lang = 'en', string|int|null $devId = null): OnlineSim
    {
        $this->transport = new FakeTransport($replies);

        return new OnlineSim(apiKey: $apiKey, baseUrl: $baseUrl, lang: $lang, devId: $devId, transport: $this->transport);
    }

    /**
     * The only request made; fails when there were more or none.
     *
     * @return Call
     */
    protected function last(): array
    {
        self::assertCount(1, $this->transport->calls, 'expected exactly 1 request');

        return $this->transport->calls[0];
    }

    /**
     * Query of the only request, without the `lang` every request carries.
     *
     * @return array<string, string>
     */
    protected function query(): array
    {
        $query = $this->last()['query'];
        unset($query['lang']);

        return $query;
    }

    protected static function fixture(string $name): mixed
    {
        return json_decode((string) file_get_contents(__DIR__ . "/fixtures/{$name}.json"), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<array-key, mixed> */
    protected static function fixtureArray(string $name): array
    {
        $data = self::fixture($name);
        self::assertIsArray($data);

        return $data;
    }

    /** Runs `$fn` and returns what it threw; fails when it did not throw. */
    protected function caught(callable $fn): \Throwable
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            return $e;
        }
        self::fail('expected an exception');
    }
}
