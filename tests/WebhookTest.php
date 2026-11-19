<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Tests;

use s00d\OnlineSimApi\Exception\OnlineSimError;

use function s00d\OnlineSimApi\parseWebhook;

final class WebhookTest extends TestCase
{
    public function testParsesAReceivingSmsJsonString(): void
    {
        $payload = [
            'webhook_type' => 'receiving_sms',
            'operation_id' => 1520,
            'user_id' => 2,
            'country_code' => 7,
            'number' => '+79001234567',
            'sender' => 'VK',
            'message' => 'VK: 195895 is your code',
            'code' => '195895',
            'time_start' => '2026-11-19 10:00:00',
            'time_left' => 1100,
        ];
        self::assertSame($payload, parseWebhook(json_encode($payload, JSON_THROW_ON_ERROR)));
    }

    public function testIsLenientNumericStringsMissingFieldsUnknownFieldsAndTypes(): void
    {
        $parsed = parseWebhook(['webhook_type' => 'rent_sms', 'operation_id' => '430', 'code' => 2991, 'time_left' => 'soon', 'extra' => true]);
        self::assertSame([
            'webhook_type' => 'rent_sms',
            'operation_id' => 430,
            'user_id' => null,
            'country_code' => null,
            'number' => null,
            'sender' => null,
            'message' => null,
            'code' => '2991',
            'time_start' => null,
            'time_left' => null,
        ], $parsed);
        self::assertNull(parseWebhook(['webhook_type' => 'other'])['webhook_type']);
        self::assertNull(parseWebhook('{}')['operation_id']);
    }

    public function testThrowsOnlineSimErrorOnlyForInputThatIsNotAJsonObject(): void
    {
        foreach (['not json' => 'not valid JSON', '[1,2]' => 'not a JSON object', 'null' => 'not a JSON object'] as $input => $message) {
            $error = $this->caught(static fn () => parseWebhook($input));
            self::assertInstanceOf(OnlineSimError::class, $error);
            self::assertStringContainsString($message, $error->getMessage());
        }
    }
}
