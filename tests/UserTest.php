<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Tests;

final class UserTest extends TestCase
{
    public function testBalanceSendsIncome1AndParsesTheDecimalString(): void
    {
        $client = $this->client([['json' => self::fixture('getBalance')]]);
        self::assertSame(['balance' => 1442.33, 'zbalance' => 1023.41, 'income' => 9.78], $client->user->balance());
        self::assertSame('/api/getBalance.php', $this->last()['path']);
        self::assertSame(['income' => '1'], $this->query());
    }

    public function testBalanceWithoutIncome(): void
    {
        $client = $this->client([['json' => ['response' => '1', 'balance' => '0.000', 'zbalance' => 0]]]);
        self::assertSame(['balance' => 0.0, 'zbalance' => 0.0], $client->user->balance());
    }

    public function testProfile(): void
    {
        $client = $this->client([['json' => self::fixture('getProfile')]]);
        $profile = $client->user->profile();
        self::assertSame('/api/getProfile.php', $this->last()['path']);
        self::assertSame(1, $profile['id']);
        self::assertSame('https://example.com/hook', $profile['webhook_url'] ?? null);
        self::assertSame(['333'], $profile['number_reject'] ?? null);
    }

    public function testPaymentHistoryNormalisesTheEmptyPaylist(): void
    {
        $client = $this->client([['json' => self::fixture('getPaymentHistory')]]);
        $history = $client->user->paymentHistory(2);
        self::assertSame('/api/getPaymentHistory.php', $this->last()['path']);
        self::assertSame(['page' => '2'], $this->query());
        self::assertSame([], $history['paylist']);
        self::assertSame([9462, '89.00'], [$history['orders']['data'][0]['id'], $history['orders']['data'][0]['sum']]);
    }

    public function testSetWebhookUrlPostsJson(): void
    {
        $client = $this->client([['json' => ['response' => '1', 'user' => ['id' => 2]]]]);
        self::assertTrue($client->user->setWebhookUrl('https://example.com/hook'));
        $call = $this->last();
        self::assertSame('POST', $call['method']);
        self::assertSame('/api/profile.php', $call['path']);
        self::assertSame('application/json', $call['headers']['Content-Type']);
        self::assertSame('Bearer test-key', $call['headers']['Authorization']);
        self::assertSame(['profile' => ['webhook_url' => 'https://example.com/hook']], $call['body']);
    }

    public function testClearWebhookUrlPostsWebhookUrlNull(): void
    {
        $client = $this->client([['json' => ['response' => '1', 'user' => ['id' => 2]]]]);
        self::assertTrue($client->user->clearWebhookUrl());
        self::assertSame('POST', $this->last()['method']);
        self::assertSame(['profile' => ['webhook_url' => null]], $this->last()['body']);
    }

    public function testWebhookLogsReturnsThePaginator(): void
    {
        $page = [
            'current_page' => 1,
            'data' => [[
                'id' => 7,
                'user_id' => 2,
                'webhook_url' => 'https://example.com/hook',
                'type' => 'receiving_sms',
                'params' => ['webhook_type' => 'receiving_sms', 'operation_id' => 1520, 'code' => '195895'],
                'status' => 'success',
                'error' => null,
                'created_at' => '2026-11-19 10:00:01',
                'updated_at' => '2026-11-19 10:00:01',
            ]],
            'from' => 1,
            'to' => 1,
            'last_page' => 1,
            'per_page' => 10,
            'total' => 1,
        ];
        $client = $this->client([['json' => ['response' => '1', 'data' => $page]]]);
        self::assertSame($page, $client->user->webhookLogs());
        self::assertSame('/api/webhook-logs', $this->last()['path']);
        self::assertSame(['page' => '1'], $this->query());
    }
}
