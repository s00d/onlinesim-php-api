<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Tests;

final class FreeTest extends TestCase
{
    public function testListHasNoPhpSuffixAndSendsOnlyTheSetParameters(): void
    {
        $client = $this->client([['json' => self::fixture('getFreeList')]]);
        $list = $client->free->list(country: 44, number: '7459608320', count: 20);
        self::assertSame('/api/getFreeList', $this->last()['path']);
        self::assertSame(['country' => '44', 'number' => '7459608320', 'count' => '20'], $this->query());
        self::assertSame(['countries', 'numbers', 'messages'], array_keys($list));
        self::assertSame('447459608320', $list['numbers'][7459608320]['full_number'] ?? null);
        self::assertSame('195895', $list['messages']['data'][0]['code'] ?? null);
    }

    public function testListAcceptsNullCountryTextAndCountryOriginal(): void
    {
        $country = ['country' => 999, 'country_text' => null, 'country_original' => null];
        $client = $this->client([['json' => ['response' => 1, 'countries' => [$country], 'numbers' => [], 'messages' => ['data' => []]]]]);
        self::assertSame([$country], $client->free->list()['countries']);
    }

    public function testListNormalisesEmptyNumbers(): void
    {
        $client = $this->client([['json' => ['response' => 1, 'countries' => [], 'numbers' => [], 'messages' => ['data' => []]]]]);
        self::assertSame([], $client->free->list()['numbers']);
        self::assertSame([], $this->query());
    }
}
