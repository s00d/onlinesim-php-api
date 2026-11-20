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

    public function testAWrongKeyIsHttp456AMissingKeyHttp200BothMapToApiError(): void
    {
        $mock = new MockOnlineSim();
        foreach (['nope', ''] as $apiKey) {
            $this->apiError(static fn () => $mock->client(apiKey: $apiKey)->user->balance(), 'ERROR_WRONG_KEY');
        }
        $missing = $mock->send('GET', 'https://x.test/api/getBalance.php', [], null, 1.0);
        self::assertSame([200, '{"response":"ERROR_WRONG_KEY"}'], [$missing->status, $missing->body]);
        $wrong = $mock->send('GET', 'https://x.test/api/getBalance.php', ['Authorization' => 'Bearer nope'], null, 1.0);
        self::assertSame(456, $wrong->status);
    }

    public function testTariffsAndFreeNumbersNeedNoKeyButASentWrongKeyIsStillHttp456(): void
    {
        $mock = new MockOnlineSim();
        $client = $mock->client(apiKey: '');
        self::assertSame(44, $client->numbers->tariffsOne(44)['code']);
        self::assertSame(7, $client->rent->tariffsOne(7)['code']);
        self::assertSame(44, $client->free->list()['messages']['country']);
        $this->apiError(static fn () => $mock->client(apiKey: 'nope')->free->list(), 'ERROR_WRONG_KEY');
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
            ['tzid' => 2, 'number' => '+7900000002', 'country' => 7, 'time' => 1200, 'service' => 'vkcom', 'title' => 'vkcom', 'response_text' => 'Waiting for SMS', 'guard_interval_remaining_seconds' => 0],
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
        self::assertSame([$a, 'TZ_NUM_WAIT', '+44900000001', 0.2, 0], [$one['tzid'], $one['response'] ?? null, $one['number'] ?? null, $one['sum'] ?? null, $one['guard_interval_remaining_seconds'] ?? null]);
        self::assertArrayNotHasKey('msg', $one);

        $mock->addSms($a, 'Your Google code is G-123 456. Do not share it.');
        $one = $client->numbers->stateOne($a);
        self::assertSame(['TZ_NUM_ANSWER', '123456'], [$one['response'] ?? null, $one['msg'] ?? null]);
        self::assertSame('Your Google code is G-123 456. Do not share it.', $client->numbers->stateOne($a, messageToCode: false)['msg'] ?? null);
        self::assertSame([$b, $a], array_column($client->numbers->state(orderBy: OrderBy::Desc), 'tzid'));

        $client->numbers->close($a);
        $one = $client->numbers->stateOne($a);
        self::assertSame(['TZ_OVER_OK', 0], [$one['response'] ?? null, $one['time'] ?? null]);
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
        self::assertSame('TZ_NUM_WAIT', $one['response'] ?? null);
        self::assertArrayNotHasKey('msg', $one);
        $mock->addSms($tzid, 'code 98765');
        self::assertSame('98765', $client->numbers->stateOne($tzid)['msg'] ?? null);
        self::assertSame(
            [['service' => 'telegram', 'msg' => 'Welcome!'], ['service' => 'telegram', 'msg' => '98765']],
            $client->numbers->stateOne($tzid, msgList: true)['msg'] ?? null,
        );
        self::assertCount(1, array_filter($mock->requests(), static fn (array $r) => $r['path'] === 'setOperationRevise.php'));
    }

    public function testNextAnswersTzidAsStringErrorWrongTzidForUnknownErrorNoTzidFor0(): void
    {
        $mock = new MockOnlineSim();
        $client = $mock->client();
        $tzid = $client->numbers->get('google', 44);
        $res = $mock->send('GET', "https://x.test/api/setOperationRevise.php?tzid={$tzid}", ['Authorization' => 'Bearer mock-key'], null, 1.0);
        self::assertSame(['tzid' => (string) $tzid, 'response' => '1'], json_decode($res->body, true));
        $this->apiError(static fn () => $client->numbers->next(424242), 'ERROR_WRONG_TZID');
        $this->apiError(static fn () => $client->numbers->next(0), 'ERROR_NO_TZID');
        $this->apiError(static fn () => $client->numbers->close(0), 'ERROR_NO_TZID');
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

    public function testRentGetReservesTheFirstSmsConfirmsAndChargesExtendChargesAtOnceClose(): void
    {
        $mock = new MockOnlineSim(balance: 10);
        $client = $mock->client();
        $rent = $client->rent->get(7, 3);
        self::assertSame(
            [1, '900000001', 0, false, 2.5, 0, 20, 1200, 3, 3, 1, 0, []],
            [$rent['tzid'], $rent['number'], $rent['status'], $rent['checked'] ?? null, $rent['sum'], $rent['days'], $rent['time'], $rent['time_seconds'] ?? null, $rent['rent'] ?? null, $rent['days_autoextend'] ?? null, $rent['extension'], $rent['guard_interval_remaining_seconds'] ?? null, $rent['messages']],
        );
        self::assertSame([1 => 1.0, 3 => 2.5, 7 => 5.0], $rent['extend']);
        // reserved: the total balance stays, the price is frozen
        self::assertSame(10.0, $mock->balance());
        self::assertSame(['balance' => 10.0, 'zbalance' => 2.5, 'income' => 0.0], $client->user->balance());
        $this->apiError(static fn () => $client->rent->extend($rent['tzid']), 'TRY_AGAIN_LATER'); // not confirmed yet

        $mock->addSms($rent['tzid'], 'Bank: 777 888', service: 'bank');
        $mock->addSms($rent['tzid'], 'no code here');
        $state = $client->rent->stateOne($rent['tzid']);
        self::assertSame([1, true, 3, 3 * 1440, 3 * 86400], [$state['status'], $state['checked'] ?? null, $state['days'], $state['time'], $state['time_seconds'] ?? null]);
        self::assertSame([['other', ''], ['bank', '777888']], array_map(static fn (array $m) => [$m['service'], $m['code']], $state['messages'])); // newest first
        self::assertSame(7.5, $mock->balance());
        self::assertSame(['balance' => 7.5, 'zbalance' => 0.0, 'income' => 0.0], $client->user->balance());

        $extended = $client->rent->extend($rent['tzid'], 1);
        self::assertSame([4, 4, 3, 3.5], [$extended['days'], $extended['rent'] ?? null, $extended['days_autoextend'] ?? null, $extended['sum']]);
        self::assertSame(6.5, $mock->balance());
        $this->apiError(static fn () => $client->rent->extend($rent['tzid'], 2), 'TRY_AGAIN_LATER'); // unknown period
        $this->apiError(static fn () => $client->rent->extend(999), 'TRY_AGAIN_LATER'); // unknown tzid

        $fixed = $client->rent->get(7, 1, false);
        $mock->addSms($fixed['tzid'], '1234');
        $fixedState = $client->rent->stateOne($fixed['tzid']);
        self::assertSame([0, []], [$fixedState['extension'], $fixedState['extend']]);
        $this->apiError(static fn () => $client->rent->extend($fixed['tzid']), 'TRY_AGAIN_LATER');
        self::assertSame([$rent['tzid'], $fixed['tzid']], array_column($client->rent->state(), 'tzid'));

        $client->rent->close($fixed['tzid']); // confirmed: no refund
        self::assertSame(5.5, $mock->balance());
        self::assertSame([$rent['tzid']], array_column($mock->rents(), 'tzid'));
        $this->apiError(static fn () => $client->rent->stateOne($fixed['tzid']), 'ERROR_NO_OPERATIONS');
        $this->apiError(static fn () => $client->rent->close($fixed['tzid']), 'ERROR_NO_OPERATIONS');
    }

    public function testRentSumIsANumberInGetRentNumAndADecimalStringInGetRentStateOnTheWire(): void
    {
        $mock = new MockOnlineSim();
        $auth = ['Authorization' => 'Bearer mock-key'];
        /** @var array{item: array{sum: mixed}} $got */
        $got = json_decode($mock->send('GET', 'https://x.test/api/rent/getRentNum.php?days=3', $auth, null, 1.0)->body, true);
        self::assertSame(2.5, $got['item']['sum']);
        /** @var array{list: list<array{sum: mixed}>} $listed */
        $listed = json_decode($mock->send('GET', 'https://x.test/api/rent/getRentState.php', $auth, null, 1.0)->body, true);
        self::assertSame('2.50', $listed['list'][0]['sum']);
    }

    public function testAnUnconfirmedRentCloseReleasesTheReservationNotifySmsHasCode0AndConfirmsNothing(): void
    {
        $mock = new MockOnlineSim(balance: 10, guardInterval: 120);
        $client = $mock->client();
        $rent = $client->rent->get(7, 3);
        self::assertSame(120, $rent['guard_interval_remaining_seconds'] ?? null);
        $this->apiError(static fn () => $client->rent->close($rent['tzid']), 'NO_COMPLETE_TZID');
        $mock->addSms($rent['tzid'], 'Messages from this sender are not accepted', service: 'notify');
        $state = $client->rent->stateOne($rent['tzid']);
        self::assertSame([0, false, 0], [$state['status'], $state['checked'] ?? null, $state['guard_interval_remaining_seconds'] ?? null]);
        self::assertSame(['notify', 0], [$state['messages'][0]['service'] ?? null, $state['messages'][0]['code'] ?? null]);
        self::assertTrue($client->rent->close($rent['tzid']));
        self::assertSame(10.0, $mock->balance()); // released, nothing charged
        self::assertSame(['balance' => 10.0, 'zbalance' => 0.0, 'income' => 0.0], $client->user->balance());
    }

    public function testRentErrorsUnknownCountryUnknownDaysLowBalance(): void
    {
        $client = (new MockOnlineSim(balance: 1.5))->client();
        $this->apiError(static fn () => $client->rent->get(44), 'UNDEFINED_COUNTRY');
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
        $listed = $client->rent->state()[0];
        $message = $listed['messages'][0] ?? null;
        self::assertSame([1, '6060', 'other'], [$listed['status'], $message['code'] ?? null, $message['service'] ?? null]);
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
        $e = $this->apiError(static fn () => $client->user->setWebhookUrl('not a url'), 'VALIDATION_ERROR');
        self::assertSame('The webhook url must be a valid URL.', $e->getMessage());
        self::assertTrue($client->user->setWebhookUrl('ftp://x')); // any scheme://… URL
        $client->user->setWebhookUrl('https://example.com/hook');
        $requests = $mock->requests();
        $last = end($requests);
        self::assertSame(['POST', 'profile.php', ['profile' => ['webhook_url' => 'https://example.com/hook']]], [$last['method'] ?? null, $last['path'] ?? null, $last['body'] ?? null]);
        self::assertSame('https://example.com/hook', $client->user->profile()['webhook_url'] ?? null);

        $tzid = $client->numbers->get('google', 44);
        $mock->addSms($tzid, 'code 1234');
        $rent = $client->rent->get();
        $mock->addSms($rent['tzid'], 'code 5678');
        $mock->addSms($rent['tzid'], 'no code');
        $logs = $client->user->webhookLogs();
        self::assertSame([3, 1, 10], [$logs['total'], $logs['current_page'], $logs['per_page']]);
        self::assertSame(
            [['rent_sms', $rent['tzid'], ''], ['rent_sms', $rent['tzid'], '5678'], ['receiving_sms', $tzid, '1234']],
            array_map(static fn (array $l) => [$l['type'], $l['params']['operation_id'] ?? null, $l['params']['code'] ?? null], $logs['data']),
        );
        // activation: +number, seconds left, order time; rent: digits without +, minutes left, SMS time
        $param = static fn (int $i, string $key): mixed => $logs['data'][$i]['params'][$key] ?? null;
        self::assertSame(['+44900000001', 1200, '2026-01-01 00:00:01'], [$param(2, 'number'), $param(2, 'time_left'), $param(2, 'time_start')]);
        self::assertSame(['7900000002', 1440, '2026-01-01 00:00:03'], [$param(1, 'number'), $param(1, 'time_left'), $param(1, 'time_start')]);

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
        self::assertSame([[1 => 'Card'], ['USD' => [5, 10, 20]]], [$history['forms'] ?? null, $history['buttons'] ?? null]);
        self::assertSame([[], 0, 1], [$history['orders']['data'] ?? null, $history['orders']['total'] ?? null, $history['orders']['current_page'] ?? null]);
    }

    public function testFreeListCountriesNumbersKeyedByShortNumberMessagesOfTheSelectedNumber(): void
    {
        $client = (new MockOnlineSim())->client();
        $uk = $client->free->list();
        self::assertSame([44, 7], array_column($uk['countries'], 'country'));
        self::assertSame(['7459608320'], array_map('strval', array_keys($uk['numbers'])));
        self::assertSame(['7459608320', 44, 2, 10], [$uk['messages']['number'] ?? null, $uk['messages']['country'] ?? null, $uk['messages']['total'] ?? null, $uk['messages']['per_page'] ?? null]);
        self::assertSame(['195895', '2026-01-01T00:00:00.000000Z'], [$uk['messages']['data'][0]['code'] ?? null, $uk['messages']['data'][0]['created_at'] ?? null]);
        self::assertSame(10, $client->free->list(count: 0)['messages']['per_page']); // clamped to 10..500
        self::assertSame(500, $client->free->list(count: 9999)['messages']['per_page']);
        self::assertSame(44, $client->free->list(country: 999)['messages']['country']); // unknown → the first country
        $raw = json_decode((new MockOnlineSim())->send('GET', 'https://x.test/api/getFreeList', [], null, 1.0)->body, true);
        self::assertIsArray($raw);
        self::assertSame([1, null], [$raw['response'] ?? 'missing', array_key_exists('content', $raw) ? $raw['content'] : 'missing']);
        self::assertIsString($raw['ignore'] ?? null);
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
