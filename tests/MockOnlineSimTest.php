<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Tests;

use s00d\OnlineSimApi\Exception\ApiError;
use s00d\OnlineSimApi\Exception\NoNumberError;
use s00d\OnlineSimApi\Exception\TransportError;
use s00d\OnlineSimApi\Mock\MockOnlineSim;
use s00d\OnlineSimApi\OnlineSim;
use s00d\OnlineSimApi\OrderBy;

/** MockOnlineSim through the real client: every endpoint, every control helper. */
final class MockOnlineSimTest extends TestCase
{
    private static function noSleep(): \Closure
    {
        return static function (float $seconds): void {
        };
    }

    private function apiError(callable $fn, string $code): ApiError
    {
        $e = $this->caught($fn);
        self::assertInstanceOf(ApiError::class, $e);
        self::assertSame($code, $e->errorCode);

        return $e;
    }

    // ----- auth and transport -----

    public function testWrongOrMissingKeyIsHttp456WhichTheClientMapsToApiError(): void
    {
        $mock = new MockOnlineSim();
        foreach (['nope', ''] as $apiKey) {
            $this->apiError(static fn () => $mock->client(apiKey: $apiKey)->user->balance(), 'ERROR_WRONG_KEY');
        }
        $raw = $mock->send('GET', 'https://x.test/api/getBalance.php', [], null, 1.0);
        self::assertSame(456, $raw->status);
    }

    public function testTariffsAndFreeNumbersNeedNoKey(): void
    {
        $client = (new MockOnlineSim())->client(apiKey: '');
        self::assertSame(44, $client->numbers->tariffsOne(44)['code']);
        self::assertSame(7, $client->rent->tariffsOne(7)['code']);
        self::assertSame(44, $client->free->list()['messages']['country']);
    }

    public function testWorksAsTheTransportOfAClientBuiltByHandWithAnyBaseUrl(): void
    {
        $mock = new MockOnlineSim(apiKey: 'k', balance: 5);
        $client = new OnlineSim(apiKey: 'k', transport: $mock->transport);
        self::assertSame(['balance' => 5.0, 'zbalance' => 0.0, 'income' => 0.0], $client->user->balance());
        self::assertSame([['method' => 'GET', 'path' => 'getBalance.php', 'query' => ['income' => '1', 'lang' => 'en'], 'body' => null]], $mock->requests());
    }

    public function testAnUnknownEndpointIsANonJson404(): void
    {
        $res = (new MockOnlineSim())->send('GET', 'https://x.test/api/nope.php', [], null, 1.0);
        self::assertSame(404, $res->status);
        self::assertStringStartsWith('<html>', $res->body);
    }

    // ----- numbers -----

    public function testPriceNullForUnknownServiceUndefinedCountryForUnknownCountry(): void
    {
        $client = (new MockOnlineSim())->client();
        self::assertSame(1.2, $client->numbers->price('telegram', 7));
        self::assertNull($client->numbers->price('nope', 7));
        $this->apiError(static fn () => $client->numbers->price('google', 999), 'UNDEFINED_COUNTRY');
    }

    public function testGetFreezesThePriceNumbersAreDeterministicGetWithNumberReturnsTheOrder(): void
    {
        $mock = new MockOnlineSim();
        $client = $mock->client();
        self::assertSame(1, $client->numbers->get('google', 44));
        self::assertSame(
            ['tzid' => 2, 'number' => '+7900000002', 'country' => 7, 'time' => 1200, 'service' => 'vkcom'],
            $client->numbers->getWithNumber('vkcom'),
        );
        self::assertSame([null, '1'], array_map(static fn (array $r) => $r['query']['number'] ?? null, $mock->requests()));
        self::assertSame(['balance' => 100.0, 'zbalance' => 0.7, 'income' => 0.0], $client->user->balance());
        self::assertSame(
            [[1, '+44900000001', 'TZ_NUM_WAIT'], [2, '+7900000002', 'TZ_NUM_WAIT']],
            array_map(static fn (array $o) => [$o['tzid'], $o['number'], $o['status']], $mock->operations()),
        );
    }

    public function testGetErrorsUnknownCountryOrServiceLowBalanceReject(): void
    {
        $mock = new MockOnlineSim(balance: 0.25);
        $client = $mock->client();
        $this->apiError(static fn () => $client->numbers->get('google', 999), 'UNDEFINED_COUNTRY');
        $this->apiError(static fn () => $client->numbers->get('nope', 7), 'ERROR_NO_SERVICE');
        $this->apiError(static fn () => $client->numbers->get('google', 7), 'WARNING_LOW_BALANCE');
        self::assertInstanceOf(NoNumberError::class, $this->caught(static fn () => $client->numbers->get('google', 44, ['4490', '7'])));
        $requests = $mock->requests();
        self::assertSame('4490,7', end($requests)['query']['reject'] ?? null);
        // a non-matching reject passes; the rejected attempt did not consume a number
        self::assertSame('+44900000001', $client->numbers->getWithNumber('google', 44, ['4480'])['number']);
        // 0.25 - 0.2 frozen = 0.05 available
        $this->apiError(static fn () => $client->numbers->get('google', 44), 'WARNING_LOW_BALANCE');
    }

    public function testStateListAndOneWaitAnswerOver(): void
    {
        $mock = new MockOnlineSim();
        $client = $mock->client();
        self::assertSame([], $client->numbers->state());
        $a = $client->numbers->get('google', 44);
        $b = $client->numbers->get('vkcom', 7);
        $one = $client->numbers->stateOne($a);
        self::assertSame([$a, 'TZ_NUM_WAIT', '+44900000001', 0.2, 0], [$one['tzid'], $one['response'], $one['number'] ?? null, $one['sum'] ?? null, $one['guard_interval_remaining_seconds'] ?? null]);
        self::assertArrayNotHasKey('msg', $one);

        $mock->addSms($a, 'Your Google code is G-123 456. Do not share it.');
        $one = $client->numbers->stateOne($a);
        self::assertSame(['TZ_NUM_ANSWER', '123456'], [$one['response'], $one['msg'] ?? null]);
        self::assertSame('Your Google code is G-123 456. Do not share it.', $client->numbers->stateOne($a, messageToCode: false)['msg'] ?? null);
        self::assertSame([$b, $a], array_column($client->numbers->state(orderBy: OrderBy::Desc), 'tzid'));

        $client->numbers->close($a);
        $one = $client->numbers->stateOne($a);
        self::assertSame(['TZ_OVER_OK', 0], [$one['response'], $one['time'] ?? null]);
        $this->apiError(static fn () => $client->numbers->stateOne(999), 'ERROR_NO_OPERATIONS');
    }

    public function testTextWithoutCodeGivesFullTextNextSwitchesMsgListListsAll(): void
    {
        $mock = new MockOnlineSim();
        $client = $mock->client();
        $tzid = $client->numbers->get('telegram');
        $mock->addSms($tzid, 'Welcome!', service: 'telegram');
        self::assertSame('Welcome!', $client->numbers->stateOne($tzid)['msg'] ?? null);
        $client->numbers->next($tzid);
        $one = $client->numbers->stateOne($tzid);
        self::assertSame('TZ_NUM_WAIT', $one['response']);
        self::assertArrayNotHasKey('msg', $one);
        $mock->addSms($tzid, 'code 98765');
        self::assertSame('98765', $client->numbers->stateOne($tzid)['msg'] ?? null);
        self::assertSame(
            [['service' => 'telegram', 'msg' => 'Welcome!'], ['service' => 'telegram', 'msg' => '98765']],
            $client->numbers->stateOne($tzid, msgList: true)['msg'] ?? null,
        );
        self::assertTrue($client->numbers->next(424242)); // always succeeds, like the backend
    }

    public function testCloseAfterSmsSpendsWithoutRefundsBanAfterSmsAnswers0(): void
    {
        $mock = new MockOnlineSim(balance: 10);
        $client = $mock->client();
        $paid = $client->numbers->get('telegram');
        $empty = $client->numbers->get('telegram');
        $mock->addSms($paid, '1111');
        $this->apiError(static fn () => $client->numbers->ban($paid), '0');
        $client->numbers->close($paid);
        $client->numbers->close($empty);
        self::assertSame(['balance' => 8.8, 'zbalance' => 0.0, 'income' => 0.0], $client->user->balance());
        self::assertSame(8.8, $mock->balance());
        $this->apiError(static fn () => $client->numbers->close($paid), 'ERROR_WRONG_TZID');
        $this->apiError(static fn () => $client->numbers->close(999), 'ERROR_WRONG_TZID');
        $banned = $client->numbers->get('google');
        self::assertTrue($client->numbers->ban($banned));
        self::assertSame('TZ_OVER_OK', $mock->operations()[2]['status']);
    }

    public function testGuardIntervalAnOperationWithoutSmsCannotBeClosed(): void
    {
        $mock = new MockOnlineSim(guardInterval: 120);
        $client = $mock->client();
        $tzid = $client->numbers->get('google');
        self::assertSame(120, $client->numbers->stateOne($tzid)['guard_interval_remaining_seconds'] ?? null);
        $this->apiError(static fn () => $client->numbers->close($tzid), 'NO_COMPLETE_TZID');
        $mock->addSms($tzid, '5555');
        self::assertSame(0, $client->numbers->stateOne($tzid)['guard_interval_remaining_seconds'] ?? null);
        self::assertTrue($client->numbers->close($tzid));
    }

    public function testWaitCodeWithAddSmsAfterAndZeroIntervalNoClockNoRealWaiting(): void
    {
        $mock = new MockOnlineSim();
        $client = $mock->client();
        $tzid = $client->numbers->get('google', 44);
        $mock->addSmsAfter($tzid, 'Your code: 4321', 2);
        self::assertSame('4321', $client->numbers->waitCode($tzid, interval: 0));
        self::assertSame(
            ['getNum.php', 'getState.php', 'getState.php', 'getState.php', 'setOperationOk.php'],
            array_column($mock->requests(), 'path'),
        );
        self::assertSame('TZ_OVER_OK', $mock->operations()[0]['status']);
    }

    public function testWaitCodeKeepOpenAndSinceWaitsForTheNextSmsListPollsCountToo(): void
    {
        $mock = new MockOnlineSim();
        $client = $mock->client();
        $tzid = $client->numbers->get('google', 44);
        $mock->addSmsAfter($tzid, '1111', 0);
        $first = $client->numbers->waitCode($tzid, interval: 0, keepOpen: true, sleep: self::noSleep());
        $mock->addSmsAfter($tzid, '2222', 1);
        $client->numbers->state(); // one poll of every operation
        self::assertSame('2222', $client->numbers->waitCode($tzid, interval: 0, since: $first, sleep: self::noSleep()));
    }

    public function testTariffsFromThePricesNoCountryForUnknown(): void
    {
        $client = (new MockOnlineSim())->client();
        $all = $client->numbers->tariffs();
        self::assertSame([7, 44], array_keys($all));
        self::assertSame(
            ['count' => 100, 'popular' => false, 'code' => 44, 'price' => 0.36, 'id' => 2, 'service' => 'vkcom', 'slug' => 'vkcom'],
            $all[44]['services']['service_vkcom'],
        );
        $this->apiError(static fn () => $client->numbers->tariffsOne(1), 'NO_COUNTRY');
        $empty = (new MockOnlineSim(prices: [1 => []]))->client();
        self::assertSame([], $empty->numbers->tariffsOne(1)['services']); // `[]` on the wire
    }

    // ----- rent -----

    public function testRentGetStateExtendCloseNonExtendableRefused(): void
    {
        $mock = new MockOnlineSim(balance: 10);
        $client = $mock->client();
        $rent = $client->rent->get(7, 3);
        self::assertSame(
            [1, '900000001', 2.5, 3, 3 * 1440, 3 * 86400, 1, []],
            [$rent['tzid'], $rent['number'], $rent['sum'], $rent['days'], $rent['time'], $rent['time_seconds'] ?? null, $rent['extension'], $rent['messages']],
        );
        self::assertSame([1 => 1.0, 3 => 2.5, 7 => 5.0], $rent['extend']);
        self::assertSame(7.5, $mock->balance());

        $mock->addSms($rent['tzid'], 'Bank: 777 888', service: 'bank');
        $mock->addSms($rent['tzid'], 'no code here');
        $state = $client->rent->stateOne($rent['tzid']);
        self::assertSame([['other', ''], ['bank', '777888']], array_map(static fn (array $m) => [$m['service'], $m['code']], $state['messages'])); // newest first

        $extended = $client->rent->extend($rent['tzid'], 1);
        self::assertSame([4, 3.5], [$extended['days'], $extended['sum']]);
        self::assertSame(6.5, $mock->balance());

        $fixed = $client->rent->get(7, 1, false);
        self::assertSame([0, []], [$fixed['extension'], $fixed['extend']]);
        $this->apiError(static fn () => $client->rent->extend($fixed['tzid']), 'TRY_AGAIN_LATER');
        self::assertSame([$rent['tzid'], $fixed['tzid']], array_column($client->rent->state(), 'tzid'));

        $client->rent->close($fixed['tzid']);
        self::assertSame([$rent['tzid']], array_column($mock->rents(), 'tzid'));
        $this->apiError(static fn () => $client->rent->stateOne($fixed['tzid']), 'ERROR_NO_OPERATIONS');
        $this->apiError(static fn () => $client->rent->close($fixed['tzid']), 'ERROR_WRONG_TZID');
    }

    public function testRentErrorsUnknownCountryUnknownDaysLowBalance(): void
    {
        $client = (new MockOnlineSim(balance: 1.5))->client();
        $this->apiError(static fn () => $client->rent->get(44), 'NO_COUNTRY');
        $this->apiError(static fn () => $client->rent->get(7, 2), 'UNDEFINED_DAYS');
        $this->apiError(static fn () => $client->rent->get(7, 3), 'WARNING_LOW_BALANCE');
    }

    public function testAddSmsAfterWorksForRentsTariffsUnknownCountry(): void
    {
        $mock = new MockOnlineSim();
        $client = $mock->client();
        $tzid = $client->rent->get()['tzid'];
        $mock->addSmsAfter($tzid, '6060', 1);
        self::assertSame([], $client->rent->stateOne($tzid)['messages']);
        $message = $client->rent->state()[0]['messages'][0] ?? null;
        self::assertSame(['6060', 'other'], [$message['code'] ?? null, $message['service'] ?? null]);
        $count = [1 => 100, 3 => 100, 7 => 100];
        self::assertSame(
            [7 => ['code' => 7, 'enabled' => true, 'name' => 'russia', 'new' => false, 'position' => 7, 'count' => $count, 'days' => [1 => 1.0, 3 => 2.5, 7 => 5.0], 'extend' => 1, 'confirm' => false, 'ext_counts' => $count]],
            $client->rent->tariffs(),
        );
        $this->apiError(static fn () => $client->rent->tariffsOne(44), 'NO_COUNTRY');
    }

    // ----- user and free -----

    public function testProfileWebhookSetValidateClearLogsRecordedWhenAnSmsArrives(): void
    {
        $mock = new MockOnlineSim(apiKey: 'secret');
        $client = $mock->client();
        $profile = $client->user->profile();
        self::assertSame([1, 'secret'], [$profile['id'], $profile['apikey'] ?? null]);
        self::assertNull(array_key_exists('webhook_url', $profile) ? $profile['webhook_url'] : 'missing');
        $e = $this->apiError(static fn () => $client->user->setWebhookUrl('ftp://x'), 'VALIDATION_ERROR');
        self::assertSame('The webhook url must be a valid URL.', $e->getMessage());
        $client->user->setWebhookUrl('https://example.com/hook');
        $requests = $mock->requests();
        $last = end($requests);
        self::assertSame(['POST', 'profile.php', ['profile' => ['webhook_url' => 'https://example.com/hook']]], [$last['method'] ?? null, $last['path'] ?? null, $last['body'] ?? null]);
        self::assertSame('https://example.com/hook', $client->user->profile()['webhook_url'] ?? null);

        $tzid = $client->numbers->get('google', 44);
        $mock->addSms($tzid, 'code 1234');
        $rent = $client->rent->get();
        $mock->addSms($rent['tzid'], 'code 5678');
        $logs = $client->user->webhookLogs();
        self::assertSame([2, 1, 10], [$logs['total'], $logs['current_page'], $logs['per_page']]);
        self::assertSame(
            [['rent_sms', $rent['tzid'], '5678'], ['receiving_sms', $tzid, '1234']],
            array_map(static fn (array $l) => [$l['type'], $l['params']['operation_id'] ?? null, $l['params']['code'] ?? null], $logs['data']),
        );

        $client->user->clearWebhookUrl();
        self::assertNull($client->user->profile()['webhook_url'] ?? null);
    }

    public function testBalanceIncludesTheFrozenPartPaymentHistoryIsEmpty(): void
    {
        $mock = new MockOnlineSim(balance: 3);
        $client = $mock->client();
        $client->numbers->get('telegram');
        self::assertSame(['balance' => 3.0, 'zbalance' => 1.2, 'income' => 0.0], $client->user->balance());
        $history = $client->user->paymentHistory();
        self::assertSame([], $history['paylist']);
        self::assertSame([[], 0, 1], [$history['orders']['data'] ?? null, $history['orders']['total'] ?? null, $history['orders']['current_page'] ?? null]);
    }

    public function testFreeListCountriesNumbersKeyedByShortNumberMessagesOfTheSelectedNumber(): void
    {
        $client = (new MockOnlineSim())->client();
        $uk = $client->free->list();
        self::assertSame([7, 44], array_column($uk['countries'], 'country'));
        self::assertSame(['7459608320'], array_map('strval', array_keys($uk['numbers'])));
        self::assertSame(['7459608320', 44, 2], [$uk['messages']['number'] ?? null, $uk['messages']['country'] ?? null, $uk['messages']['total'] ?? null]);
        self::assertSame('195895', $uk['messages']['data'][0]['code'] ?? null);
        $ru = $client->free->list(country: 7);
        self::assertSame(['51423', '9001234567'], [$ru['messages']['data'][0]['code'] ?? null, $ru['messages']['data'][0]['my_number'] ?? null]);
    }

    // ----- control API -----

    public function testFailNextAnswersAnErrorCodeOnceOptionallyOnlyForOneEndpoint(): void
    {
        $mock = new MockOnlineSim();
        $client = $mock->client();
        $mock->failNext('NO_NUMBER', endpoint: 'getNum.php');
        self::assertSame(100.0, $client->user->balance()['balance']); // other endpoint: untouched
        self::assertInstanceOf(NoNumberError::class, $this->caught(static fn () => $client->numbers->get('google')));
        self::assertSame(1, $client->numbers->get('google'));
        $mock->failNext('TRY_AGAIN_LATER');
        $this->apiError(static fn () => $client->user->profile(), 'TRY_AGAIN_LATER');
    }

    public function testFailNextTransportFailsOnceWithANonJsonBodyWaitCodeRetriesIt(): void
    {
        $mock = new MockOnlineSim();
        $client = $mock->client();
        $mock->failNextTransport();
        $e = $this->caught(static fn () => $client->user->balance());
        self::assertInstanceOf(TransportError::class, $e);
        self::assertSame(502, $e->status);

        $tzid = $client->numbers->get('google');
        $mock->addSms($tzid, '3030');
        $mock->failNextTransport(status: 503, endpoint: 'getState.php');
        self::assertSame('3030', $client->numbers->waitCode($tzid, interval: 0, sleep: self::noSleep()));
    }

    public function testAddSmsRejectsUnknownIdsSetBalanceRequestsLogReset(): void
    {
        $mock = new MockOnlineSim(balance: 1);
        $client = $mock->client(lang: 'ru');
        $e = $this->caught(static fn () => $mock->addSms(5, 'x'));
        self::assertInstanceOf(\InvalidArgumentException::class, $e);
        self::assertStringContainsString('no open operation or rent 5', $e->getMessage());
        $e = $this->caught(static fn () => $mock->addSmsAfter(5, 'x', 1));
        self::assertStringContainsString('no operation or rent 5', $e->getMessage());
        $mock->setBalance(50);
        self::assertSame(50.0, $mock->balance());
        $tzid = $client->numbers->get('telegram');
        $client->numbers->close($tzid);
        self::assertInstanceOf(\InvalidArgumentException::class, $this->caught(static fn () => $mock->addSms($tzid, 'late'))); // closed
        self::assertSame(['method' => 'GET', 'path' => 'getNum.php', 'query' => ['service' => 'telegram', 'country' => '7', 'lang' => 'ru'], 'body' => null], $mock->requests()[0]);

        $mock->failNext('NO_NUMBER');
        $mock->reset();
        self::assertSame(1.0, $mock->balance());
        self::assertSame([], $mock->operations());
        self::assertSame([], $mock->rents());
        self::assertSame([], $mock->requests());
        self::assertSame(1, $client->numbers->get('google', 44)); // ids restart, failure queue cleared
    }

    public function testOperationsReturnsCopies(): void
    {
        $mock = new MockOnlineSim();
        $mock->client()->numbers->get('google');
        $ops = $mock->operations();
        $ops[0]['status'] = 'TZ_OVER_OK';
        self::assertSame('TZ_NUM_WAIT', $mock->operations()[0]['status']);
    }

    public function testExtractCodeRule(): void
    {
        $mock = new MockOnlineSim();
        $client = $mock->client();
        $cases = [
            'G-123 456' => '123456',
            'code 123456789 here' => 'code 123456789 here', // a longer run is not a code
            'pin 12 34' => '1234',
            'abc' => 'abc',
            'first 1111 then 2222' => '1111',
        ];
        foreach ($cases as $text => $expected) {
            $tzid = $client->numbers->get('google', 44);
            $mock->addSms($tzid, $text);
            self::assertSame($expected, $client->numbers->stateOne($tzid)['msg'] ?? null, $text);
        }
    }
}
