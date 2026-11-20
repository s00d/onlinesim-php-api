<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Tests;

use s00d\OnlineSimApi\Exception\ApiError;

final class RentTest extends TestCase
{
    private const DETAIL = ['pagination' => '0', 'number' => '1'];

    /** @return list<array<string, mixed>> */
    private static function list(): array
    {
        /** @var array{list: list<array<string, mixed>>} $state */
        $state = self::fixtureArray('rent_getRentState');

        return $state['list'];
    }

    public function testGetSendsExtensionExplicitlyPaginationAndNumberAndParsesSum(): void
    {
        $client = $this->client([['json' => ['response' => 1, 'item' => self::list()[0]]]]);
        $rent = $client->rent->get(7, 3);
        self::assertSame('/api/rent/getRentNum.php', $this->last()['path']);
        self::assertSame(['country' => '7', 'days' => '3', 'extension' => '1'] + self::DETAIL, $this->query());
        self::assertSame(5.14, $rent['sum']);
        self::assertSame([1 => 14.7, 3 => 25.3, 7 => 51], $rent['extend']);
        self::assertSame('195895', $rent['messages'][0]['code'] ?? null);
    }

    public function testDecodesTheSpecShapesSumNumberOrStringCode0ForANotifySms(): void
    {
        $item = [
            'status' => 0,
            'checked' => false,
            'sum' => 1.5,
            'messages' => [
                ['id' => 2, 'service' => 'notify', 'text' => 'Messages from this sender are not accepted', 'code' => 0, 'created_at' => '2026-11-19 10:01:12'],
                ['id' => 1, 'service' => 'Google', 'text' => 'G-070051', 'code' => '070051', 'created_at' => '2026-11-19 10:01:00'],
            ],
        ] + self::list()[0];
        $client = $this->client([['json' => ['response' => 1, 'item' => $item]], ['json' => ['response' => 1, 'list' => [['sum' => '2.40'] + $item]]]]);
        $rent = $client->rent->get(7, 3);
        self::assertSame([0, false, 1.5], [$rent['status'], $rent['checked'] ?? null, $rent['sum']]);
        self::assertSame([0, '070051'], array_column($rent['messages'], 'code'));
        self::assertSame(2.4, $client->rent->stateOne($rent['tzid'])['sum']);
    }

    public function testGetWithExtensionFalseSendsExtension0(): void
    {
        $client = $this->client([['json' => ['response' => 1, 'item' => self::list()[1]]]]);
        $client->rent->get(7, 1, false);
        self::assertSame('0', $this->query()['extension']);
    }

    public function testStateNormalisesSumAndTheEmptyExtendMap(): void
    {
        $client = $this->client([['json' => ['response' => 1, 'list' => self::list()]]]);
        $list = $client->rent->state();
        self::assertSame('/api/rent/getRentState.php', $this->last()['path']);
        self::assertSame(self::DETAIL, $this->query());
        self::assertSame([5.14, 0.0], array_column($list, 'sum'));
        self::assertSame([], $list[1]['extend']);
    }

    public function testStateOne(): void
    {
        $client = $this->client([['json' => ['response' => 1, 'list' => [self::list()[0]]]]]);
        $rent = $client->rent->stateOne(430);
        self::assertSame([430, 5.14], [$rent['tzid'], $rent['sum']]);
        self::assertSame(['tzid' => '430'] + self::DETAIL, $this->query());
    }

    public function testStateOneRaisesErrorNoOperationsForAnEmptyList(): void
    {
        $client = $this->client([['json' => ['response' => 1, 'list' => []]]]);
        $error = $this->caught(static fn () => $client->rent->stateOne(1));
        self::assertInstanceOf(ApiError::class, $error);
        self::assertSame('ERROR_NO_OPERATIONS', $error->errorCode);
    }

    public function testExtend(): void
    {
        $client = $this->client([['json' => ['response' => 1, 'item' => self::list()[0]]]]);
        self::assertSame(430, $client->rent->extend(430, 3)['tzid']);
        self::assertSame('/api/rent/extendRentState.php', $this->last()['path']);
        self::assertSame(['tzid' => '430', 'days' => '3'] + self::DETAIL, $this->query());
    }

    public function testTariffs(): void
    {
        $tariffs = self::fixtureArray('rent_tariffsRent');
        $client = $this->client([['json' => $tariffs]]);
        self::assertSame($tariffs, $client->rent->tariffs());
        self::assertSame('/api/rent/tariffsRent.php', $this->last()['path']);
        self::assertSame([], $this->query());
    }

    public function testTariffsOne(): void
    {
        $ru = self::fixtureArray('rent_tariffsRent')[7];
        $client = $this->client([['json' => $ru]]);
        self::assertSame($ru, $client->rent->tariffsOne());
        self::assertSame(['country' => '7'], $this->query());
    }

    public function testTariffsOneRaisesNoCountryForTheEmptyAnswer(): void
    {
        $client = $this->client([['json' => []]]);
        $error = $this->caught(static fn () => $client->rent->tariffsOne(999));
        self::assertInstanceOf(ApiError::class, $error);
        self::assertSame('NO_COUNTRY', $error->errorCode);
    }

    public function testClose(): void
    {
        $client = $this->client([['json' => ['response' => '1']]]);
        self::assertTrue($client->rent->close(430));
        self::assertSame('/api/rent/closeRentNum.php', $this->last()['path']);
        self::assertSame(['tzid' => '430'], $this->query());
    }

    public function testCloseSurfacesNoCloseRentUs(): void
    {
        $client = $this->client([['json' => ['response' => 'NO_CLOSE_RENT_US']]]);
        $error = $this->caught(static fn () => $client->rent->close(1));
        self::assertInstanceOf(ApiError::class, $error);
        self::assertSame('NO_CLOSE_RENT_US', $error->errorCode);
    }
}
