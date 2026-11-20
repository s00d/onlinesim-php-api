<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Tests;

use s00d\OnlineSimApi\Exception\ApiError;
use s00d\OnlineSimApi\OrderBy;

final class NumbersTest extends TestCase
{
    public function testPrice(): void
    {
        $client = $this->client([['json' => ['response' => '1', 'price' => 0.36]]]);
        self::assertSame(0.36, $client->numbers->price('vkcom', 44));
        self::assertSame('/api/getPrice.php', $this->last()['path']);
        self::assertSame(['service' => 'vkcom', 'country' => '44'], $this->query());
    }

    public function testPriceIsNullWhenTheServerHasNone(): void
    {
        $client = $this->client([['json' => ['response' => '1', 'price' => null]]]);
        self::assertNull($client->numbers->price('vkcom'));
    }

    public function testGetSendsNoNumberFlagAndRejectAsACommaList(): void
    {
        $client = $this->client([['json' => ['response' => 1, 'tzid' => 1520]]]);
        self::assertSame(1520, $client->numbers->get('google', 44, reject: ['4470', 4471]));
        self::assertSame('/api/getNum.php', $this->last()['path']);
        self::assertSame(['service' => 'google', 'country' => '44', 'reject' => '4470,4471'], $this->query());
    }

    public function testGetDefaultsToCountry7AndOmitsAnEmptyReject(): void
    {
        $client = $this->client([['json' => ['response' => 1, 'tzid' => 1]]]);
        $client->numbers->get('google', reject: []);
        self::assertSame(['service' => 'google', 'country' => '7'], $this->query());
    }

    public function testGetWithNumberSendsNumber1AndReturnsTheOrder(): void
    {
        $order = ['tzid' => 1520, 'number' => '+447459608320', 'country' => 44, 'time' => 1200, 'service' => 'google'];
        $client = $this->client([['json' => ['response' => 1] + $order]]);
        self::assertSame($order, $client->numbers->getWithNumber('google', 44));
        self::assertSame(['service' => 'google', 'country' => '44', 'number' => '1'], $this->query());
    }

    public function testStateSendsBooleansAs10LowercaseOrderbyAndTheNumberFlag(): void
    {
        $ops = self::fixtureArray('getState');
        $client = $this->client([['json' => $ops]]);
        $result = $client->numbers->state(orderBy: OrderBy::Desc, msgList: true);
        self::assertSame($ops, $result);
        self::assertSame('195895', $result[0]['msg'] ?? null);
        self::assertSame('/api/getState.php', $this->last()['path']);
        self::assertSame(['message_to_code' => '1', 'orderby' => 'desc', 'msg_list' => '1', 'number' => '1'], $this->query());
    }

    public function testStateDefaults(): void
    {
        $client = $this->client([['json' => []]]);
        $client->numbers->state();
        self::assertSame(['message_to_code' => '1', 'orderby' => 'asc', 'msg_list' => '0', 'number' => '1'], $this->query());
    }

    public function testStateReturnsAnEmptyListOnErrorNoOperations(): void
    {
        $client = $this->client([['json' => ['response' => 'ERROR_NO_OPERATIONS']]]);
        self::assertSame([], $client->numbers->state());
    }

    public function testStateOneReturnsTheOperation(): void
    {
        $ops = self::fixtureArray('getState');
        $client = $this->client([['json' => [$ops[0]]]]);
        self::assertSame($ops[0], $client->numbers->stateOne(594, messageToCode: false));
        self::assertSame(['tzid' => '594', 'message_to_code' => '0', 'msg_list' => '0', 'number' => '1'], $this->query());
    }

    public function testStateOneRaisesErrorNoOperations(): void
    {
        $client = $this->client([['json' => ['response' => 'ERROR_NO_OPERATIONS']], ['json' => []]]);
        foreach ([1, 2] as $_) {
            $error = $this->caught(static fn () => $client->numbers->stateOne(1));
            self::assertInstanceOf(ApiError::class, $error);
            self::assertSame('ERROR_NO_OPERATIONS', $error->errorCode);
        }
    }

    public function testNext(): void
    {
        $client = $this->client([['json' => ['response' => '1', 'tzid' => '1520']]]);
        self::assertTrue($client->numbers->next(1520));
        self::assertSame('/api/setOperationRevise.php', $this->last()['path']);
        self::assertSame(['tzid' => '1520'], $this->query());
    }

    public function testBanAfterAnSmsSurfaces0AsApiErrorWithAReadableMessage(): void
    {
        $client = $this->client([['json' => ['response' => '0']]]);
        $error = $this->caught(static fn () => $client->numbers->ban(1520));
        self::assertInstanceOf(ApiError::class, $error);
        self::assertSame(['0', 'Ban not applied: the operation already has an SMS'], [$error->errorCode, $error->getMessage()]);
    }

    public function testNextAcceptsTheTzidStringAnswerErrorWrongTzidForAForeignTzid(): void
    {
        $client = $this->client([['json' => ['tzid' => '1520', 'response' => '1']], ['json' => ['response' => 'ERROR_WRONG_TZID']]]);
        self::assertTrue($client->numbers->next(1520));
        $error = $this->caught(static fn () => $client->numbers->next(1));
        self::assertInstanceOf(ApiError::class, $error);
        self::assertSame('ERROR_WRONG_TZID', $error->errorCode);
    }

    public function testStateOneAcceptsAnOperationWithoutResponse(): void
    {
        $client = $this->client([['json' => [['tzid' => 5, 'country' => 7, 'service' => 'vkcom', 'sum' => 0.5, 'time' => 900, 'form' => 'index']]]]);
        $op = $client->numbers->stateOne(5);
        self::assertSame(5, $op['tzid']);
        self::assertArrayNotHasKey('response', $op);
    }

    public function testClose(): void
    {
        $client = $this->client([['json' => ['response' => 1, 'tzid' => 1520]]]);
        self::assertTrue($client->numbers->close(1520));
        self::assertSame('/api/setOperationOk.php', $this->last()['path']);
        self::assertSame(['tzid' => '1520'], $this->query());
    }

    public function testBan(): void
    {
        $client = $this->client([['json' => ['response' => 1, 'tzid' => 1520]]]);
        self::assertTrue($client->numbers->ban(1520));
        self::assertSame('/api/setOperationOk.php', $this->last()['path']);
        self::assertSame(['tzid' => '1520', 'ban' => '1'], $this->query());
    }

    public function testCloseSurfacesNoCompleteTzid(): void
    {
        $client = $this->client([['json' => ['response' => 'NO_COMPLETE_TZID']]]);
        $error = $this->caught(static fn () => $client->numbers->close(1));
        self::assertInstanceOf(ApiError::class, $error);
        self::assertSame('NO_COMPLETE_TZID', $error->errorCode);
    }

    public function testTariffsRequestsCountryAllAndNormalisesEmptyServices(): void
    {
        $uk = self::fixtureArray('getNumbersStats_44');
        $client = $this->client([['json' => [44 => $uk, 7 => ['code' => 7, 'services' => []] + $uk]]]);
        $all = $client->numbers->tariffs();
        self::assertSame('/api/getNumbersStats.php', $this->last()['path']);
        self::assertSame(['country' => 'all'], $this->query());
        self::assertSame('vkcom', $all[44]['services']['service_vkcom']['slug'] ?? null);
        self::assertSame([], $all[7]['services'] ?? null);
    }

    public function testTariffsOne(): void
    {
        $uk = self::fixtureArray('getNumbersStats_44');
        $client = $this->client([['json' => $uk]]);
        self::assertSame($uk, $client->numbers->tariffsOne(44));
        self::assertSame(['country' => '44'], $this->query());
    }

    public function testTariffsOneRaisesNoCountry(): void
    {
        $client = $this->client([['json' => ['response' => 'NO_COUNTRY']]]);
        $error = $this->caught(static fn () => $client->numbers->tariffsOne(999));
        self::assertInstanceOf(ApiError::class, $error);
        self::assertSame('NO_COUNTRY', $error->errorCode);
    }
}
