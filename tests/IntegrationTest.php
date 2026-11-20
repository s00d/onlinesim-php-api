<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Tests;

use PHPUnit\Framework\Attributes\Group;
use s00d\OnlineSimApi\Exception\ApiError;
use s00d\OnlineSimApi\Exception\NoNumberError;
use s00d\OnlineSimApi\OnlineSim;
use s00d\OnlineSimApi\Transport\CurlTransport;

/**
 * Opt-in integration test against a LOCAL backend. Skipped unless both are set:
 *   ONLINESIM_IT_BASE=https://on.test/api/  ONLINESIM_IT_KEY=<apikey>
 * With ONLINESIM_IT_MOCK=1 it also runs the number lifecycle on the provider-free `test` driver
 * (`spec/verify/backend_mock.php setup` first, a key of a user without webhook_url, teardown after).
 */
#[Group('integration')]
final class IntegrationTest extends \PHPUnit\Framework\TestCase
{
    private const COUNTRY = 44;
    private const SERVICE = 'google';

    private static function base(): string
    {
        $base = (string) getenv('ONLINESIM_IT_BASE');
        $key = (string) getenv('ONLINESIM_IT_KEY');
        if ($base === '' || $key === '') {
            self::markTestSkipped('set ONLINESIM_IT_BASE and ONLINESIM_IT_KEY to run');
        }
        $host = (string) parse_url($base, PHP_URL_HOST);
        if (!in_array($host, ['localhost', '127.0.0.1'], true) && !str_ends_with($host, '.test') && !str_ends_with($host, '.localhost')) {
            throw new \RuntimeException("refusing non-local integration host {$host}");
        }

        return rtrim($base, '/') . '/';
    }

    /** Test-only transport: local backends use a self-signed certificate. Not the SDK default. */
    private static function insecure(): CurlTransport
    {
        return new CurlTransport([CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]);
    }

    private static function client(?string $key = null): OnlineSim
    {
        $base = self::base();

        return new OnlineSim(apiKey: $key ?? (string) getenv('ONLINESIM_IT_KEY'), baseUrl: $base, transport: self::insecure());
    }

    private static function lifecycle(): OnlineSim
    {
        $client = self::client();
        if (getenv('ONLINESIM_IT_MOCK') !== '1') {
            self::markTestSkipped('set ONLINESIM_IT_MOCK=1 (after backend_mock.php setup) to run the lifecycle');
        }

        return $client;
    }

    // ----- read-only -----

    public function testBalance(): void
    {
        $balance = self::client()->user->balance();
        self::assertIsFloat($balance['balance']);
        self::assertIsFloat($balance['zbalance']);
    }

    public function testProfile(): void
    {
        self::assertIsInt(self::client()->user->profile()['id']);
    }

    public function testNumbersTariffsOne44(): void
    {
        $uk = self::client()->numbers->tariffsOne(44);
        self::assertSame(44, $uk['code']);
        self::assertIsArray($uk['services']);
    }

    public function testNumbersState(): void
    {
        self::assertTrue(array_is_list(self::client()->numbers->state()));
    }

    public function testRentTariffsOne7(): void
    {
        self::assertSame(7, self::client()->rent->tariffsOne(7)['code']);
    }

    public function testRentState(): void
    {
        foreach (self::client()->rent->state() as $rent) {
            self::assertIsFloat($rent['sum']);
            self::assertTrue(array_is_list($rent['messages']));
            self::assertIsArray($rent['extend']);
        }
        $this->addToAssertionCount(1);
    }

    public function testPaymentHistory(): void
    {
        $history = self::client()->user->paymentHistory();
        self::assertTrue(array_is_list($history['orders']['data']));
        self::assertIsArray($history['paylist']);
    }

    public function testWebhookLogs(): void
    {
        self::assertTrue(array_is_list(self::client()->user->webhookLogs()['data']));
    }

    public function testFreeList(): void
    {
        $free = self::client()->free->list();
        self::assertTrue(array_is_list($free['countries']));
        self::assertIsArray($free['numbers']);
    }

    public function testWrongKeyIsApiErrorWrongKey(): void
    {
        try {
            self::client('wrong-key')->user->balance();
            self::fail('expected ApiError');
        } catch (ApiError $e) {
            self::assertSame('ERROR_WRONG_KEY', $e->errorCode);
        }
    }

    // ----- number lifecycle on the test driver (ONLINESIM_IT_MOCK=1) -----

    public function testNumberLifecycle(): void
    {
        $client = self::lifecycle();
        self::assertIsFloat($client->numbers->price(self::SERVICE, self::COUNTRY));

        $tzid = $client->numbers->get(self::SERVICE, self::COUNTRY);
        $order = $client->numbers->getWithNumber(self::SERVICE, self::COUNTRY);
        self::assertMatchesRegularExpression('/^\+?44\d+$/', $order['number']);
        self::assertSame(self::COUNTRY, $order['country']);

        $ids = array_column($client->numbers->state(), 'tzid');
        self::assertContains($tzid, $ids);
        self::assertContains($order['tzid'], $ids);
        self::assertStringStartsWith('TZ_', $client->numbers->stateOne($tzid)['response'] ?? '');

        // The test driver answers NO_NUMBER to any reject list: proves `reject` is sent.
        try {
            $client->numbers->get(self::SERVICE, self::COUNTRY, reject: ['4470']);
            self::fail('expected NoNumberError');
        } catch (NoNumberError) {
        }

        // No SMS yet: the guard interval keeps it open.
        try {
            $client->numbers->close($order['tzid']);
            self::fail('expected NO_COMPLETE_TZID');
        } catch (ApiError $e) {
            self::assertSame('NO_COMPLETE_TZID', $e->errorCode);
        }

        self::injectSms($tzid, 'VK: 195895 is your code');
        self::assertSame('195895', $client->numbers->waitCode($tzid, interval: 1.0, timeout: 30.0));
    }

    public function testWebhookUrlSetValidateClear(): void
    {
        $client = self::lifecycle();
        self::assertTrue($client->user->setWebhookUrl('https://example.com/hook'));
        self::assertSame('https://example.com/hook', $client->user->profile()['webhook_url'] ?? null);
        try {
            $client->user->setWebhookUrl('not a url');
            self::fail('expected VALIDATION_ERROR');
        } catch (ApiError $e) {
            self::assertSame('VALIDATION_ERROR', $e->errorCode);
        }
        self::assertTrue($client->user->clearWebhookUrl());
        self::assertNull($client->user->profile()['webhook_url'] ?? null);
    }

    /** Inject an SMS through the test driver callback (local backends only, no auth). */
    private static function injectSms(int $tzid, string $text): void
    {
        $response = self::insecure()->send(
            'POST',
            self::base() . 'sms_in_getaways/test',
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            json_encode(['id' => $tzid, 'phoneFrom' => 'VK', 'text' => $text], JSON_THROW_ON_ERROR),
            30.0,
        );
        self::assertSame(200, $response->status);
    }
}
