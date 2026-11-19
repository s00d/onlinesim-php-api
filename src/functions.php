<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi;

use s00d\OnlineSimApi\Exception\OnlineSimError;

/**
 * Parse an inbound webhook body (`receiving_sms` or `rent_sms`) from a JSON string or an already decoded array.
 *
 * Lenient: numeric fields may arrive as strings, missing or malformed fields become `null`, unknown fields are
 * dropped. Throws `OnlineSimError` only when the input is not a JSON object at all.
 *
 * @param string|array<array-key, mixed> $payload
 *
 * @return array{
 *     webhook_type: 'receiving_sms'|'rent_sms'|null,
 *     operation_id: int|null,
 *     user_id: int|null,
 *     country_code: int|null,
 *     number: string|null,
 *     sender: string|null,
 *     message: string|null,
 *     code: string|null,
 *     time_start: string|null,
 *     time_left: int|null,
 * }
 *
 * @throws OnlineSimError
 */
function parseWebhook(string|array $payload): array
{
    $data = $payload;
    if (is_string($payload)) {
        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new OnlineSimError('Webhook payload is not valid JSON', 0, $e);
        }
    }
    // `{}` decodes to `[]`, which is a list too: only a non-empty list is rejected.
    if (!is_array($data) || ($data !== [] && array_is_list($data))) {
        throw new OnlineSimError('Webhook payload is not a JSON object');
    }
    $type = $data['webhook_type'] ?? null;

    return [
        'webhook_type' => $type === 'receiving_sms' || $type === 'rent_sms' ? $type : null,
        'operation_id' => Normalize::int($data['operation_id'] ?? null),
        'user_id' => Normalize::int($data['user_id'] ?? null),
        'country_code' => Normalize::int($data['country_code'] ?? null),
        'number' => Normalize::string($data['number'] ?? null),
        'sender' => Normalize::string($data['sender'] ?? null),
        'message' => Normalize::string($data['message'] ?? null),
        'code' => Normalize::string($data['code'] ?? null),
        'time_start' => Normalize::string($data['time_start'] ?? null),
        'time_left' => Normalize::int($data['time_left'] ?? null),
    ];
}
