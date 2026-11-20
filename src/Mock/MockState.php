<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Mock;

/**
 * State of the mock backend. `MockState` is the single owner of everything the fake API remembers: balance,
 * operations, rents, the profile's webhook URL and its delivery log, scheduled SMS, the failure queue and the
 * request log. Handlers (`Handlers`) read and change it only through this object.
 *
 * Deterministic: ids and numbers come from a counter, timestamps from a fixed epoch plus that counter.
 * Money is kept in integer thousandths of a USD ("mills") so sums never drift.
 *
 * @internal used by `MockOnlineSim`; not part of the public API
 *
 * @phpstan-type MockPrices array<int, array<string, float|int>>
 * @phpstan-type MockRentTariff array{days: array<int, float|int>, extend: int}
 * @phpstan-type MockSms array{service: string, text: string, code: string|null, at: int}
 * @phpstan-type MockOperation array{
 *     tzid: int,
 *     service: string,
 *     country: int,
 *     number: string,
 *     sum: float,
 *     status: 'TZ_NUM_WAIT'|'TZ_NUM_ANSWER'|'TZ_OVER_OK',
 *     messages: list<MockSms>,
 *     cursor: int,
 * }
 * @phpstan-type MockRentSms array{service: string, text: string, code: string|null, at: int, id: int, created_at: string}
 * @phpstan-type MockRent array{
 *     tzid: int,
 *     country: int,
 *     number: string,
 *     days: int,
 *     initialDays: int,
 *     status: 0|1,
 *     sum: float,
 *     extension: bool,
 *     messages: list<MockRentSms>,
 * }
 * @phpstan-type MockRequest array{method: string, path: string, query: array<array-key, string>, body: mixed}
 * @phpstan-type MockWebhookLog array{
 *     id: int,
 *     user_id: int,
 *     webhook_url: string,
 *     type: 'receiving_sms'|'rent_sms',
 *     params: array<string, string|int>,
 *     status: 'success',
 *     error: null,
 *     created_at: string,
 *     updated_at: string,
 * }
 * @phpstan-type MockFailure array{endpoint: string|null, code: string|null, status: int}
 * @phpstan-type ScheduledSms array{text: string, service: string|null, polls: int}
 */
final class MockState
{
    public const DEFAULT_PRICES = [
        7 => ['vkcom' => 0.5, 'telegram' => 1.2, 'google' => 0.3],
        44 => ['google' => 0.2, 'vkcom' => 0.36],
    ];

    public const DEFAULT_RENT_TARIFFS = [
        7 => ['days' => [1 => 1.0, 3 => 2.5, 7 => 5.0], 'extend' => 1],
    ];

    public const USER_ID = 1;

    /** Seconds an operation stays open, as reported in `time`. The mock has no clock, so it never runs down. */
    public const OPERATION_TIME = 1200;

    /** Minutes an unconfirmed rent waits for its first SMS (`stop_time` = start + this). */
    public const RENT_FIRST_SMS_MINUTES = 20;

    /** Sender of a rent SMS from a prohibited sender: it gets the integer `code` `0` and does not confirm the rent. */
    public const NOTIFY = 'notify';

    /** 2026-01-01 00:00:00 UTC. */
    private const EPOCH = 1_767_225_600;

    /** Total balance incl. the frozen part, mills. */
    public int $balance = 0;
    /** Frozen by open operations and unconfirmed rents, mills. */
    public int $frozen = 0;
    /** @var array<int, MockOperation> tzid → operation, oldest first */
    public array $operations = [];
    /** @var array<int, MockRent> tzid → rent, oldest first */
    public array $rents = [];
    public ?string $webhookUrl = null;
    /** @var list<MockWebhookLog> */
    public array $webhookLogs = [];
    /** @var list<MockRequest> */
    public array $requests = [];
    /** @var list<MockFailure> */
    public array $failures = [];
    /** @var array<int, list<ScheduledSms>> */
    public array $scheduled = [];
    /** Source of tzids, numbers, message ids and timestamps. */
    public int $seq = 0;

    private readonly int $startBalance;

    /**
     * @param MockPrices                 $prices
     * @param array<int, MockRentTariff> $rentTariffs
     */
    public function __construct(
        public readonly string $apiKey,
        float $balance,
        public readonly array $prices,
        public readonly array $rentTariffs,
        public readonly int $guardInterval,
    ) {
        $this->startBalance = self::toMills($balance);
        $this->reset();
    }

    public function reset(): void
    {
        $this->balance = $this->startBalance;
        $this->frozen = 0;
        $this->operations = [];
        $this->rents = [];
        $this->webhookUrl = null;
        $this->webhookLogs = [];
        $this->requests = [];
        $this->failures = [];
        $this->scheduled = [];
        $this->seq = 0;
    }

    public static function toMills(float|int $usd): int
    {
        return (int) round($usd * 1000);
    }

    public static function fromMills(int $mills): float
    {
        return $mills / 1000;
    }

    /** `YYYY-MM-DD HH:MM:SS` at `$seconds` after the fixed mock epoch. */
    public static function stamp(int $seconds = 0): string
    {
        return gmdate('Y-m-d H:i:s', self::EPOCH + $seconds);
    }

    /**
     * The code rule shared by every SDK mock: dashes/spaces between digits are removed, then the first run of
     * 4–8 digits (not part of a longer run) is the code; `null` when there is none.
     */
    public static function extractCode(string $text): ?string
    {
        $joined = (string) preg_replace('/(\d)[\s-]+(?=\d)/', '$1', $text);

        return preg_match('/(?<!\d)\d{4,8}(?!\d)/', $joined, $m) === 1 ? $m[0] : null;
    }

    /** Short (country-less) number for an id: deterministic, 9 digits. */
    public static function shortNumber(int $id): string
    {
        return (string) (900_000_000 + $id);
    }

    /** Available balance (total minus frozen), mills. */
    public function available(): int
    {
        return $this->balance - $this->frozen;
    }

    /** The id the next operation/rent will get. */
    public function peekId(): int
    {
        return $this->seq + 1;
    }

    public function nextId(): int
    {
        return ++$this->seq;
    }

    /**
     * Remove and return the first queued failure that applies to `$path`.
     *
     * @return MockFailure|null
     */
    public function takeFailure(string $path): ?array
    {
        foreach ($this->failures as $i => $failure) {
            if ($failure['endpoint'] === null || $failure['endpoint'] === $path) {
                array_splice($this->failures, $i, 1);

                return $failure;
            }
        }

        return null;
    }

    /** An SMS arrives at operation or rent `$tzid` now. Returns `false` when there is no open one. */
    public function deliver(int $tzid, string $text, ?string $service = null): bool
    {
        $code = self::extractCode($text);
        $op = $this->operations[$tzid] ?? null;
        if ($op !== null && $op['status'] !== 'TZ_OVER_OK') {
            $sms = ['service' => $service ?? $op['service'], 'text' => $text, 'code' => $code, 'at' => $this->seq];
            $op['messages'][] = $sms;
            $op['status'] = 'TZ_NUM_ANSWER';
            $this->operations[$tzid] = $op;
            // Activation: `+<digits>`, seconds left, the order time.
            $this->webhook('receiving_sms', $tzid, $op['country'], $op['number'], $sms, self::OPERATION_TIME, self::stamp($op['tzid']));

            return true;
        }
        $rent = $this->rents[$tzid] ?? null;
        if ($rent !== null) {
            $id = $this->nextId();
            $sms = ['service' => $service ?? 'other', 'text' => $text, 'code' => $code, 'at' => $id];
            $rent['messages'][] = $sms + ['id' => $id, 'created_at' => self::stamp($id)];
            if ($rent['status'] === 0 && $sms['service'] !== self::NOTIFY) {
                // The first real SMS confirms the rent: the reservation is charged.
                $cost = self::toMills($rent['sum']);
                $this->frozen -= $cost;
                $this->balance -= $cost;
                $rent['status'] = 1;
            }
            $this->rents[$tzid] = $rent;
            // Rent: full number in digits without `+`, minutes left, the SMS time.
            $minutesLeft = $rent['status'] === 1 ? $rent['days'] * 24 * 60 : self::RENT_FIRST_SMS_MINUTES;
            $this->webhook('rent_sms', $tzid, $rent['country'], "{$rent['country']}{$rent['number']}", $sms, $minutesLeft, self::stamp($id));

            return true;
        }

        return false;
    }

    public function schedule(int $tzid, string $text, int $polls, ?string $service = null): void
    {
        $this->scheduled[$tzid][] = ['text' => $text, 'service' => $service, 'polls' => $polls];
    }

    /** One state poll of `$tzid`: delivers the scheduled SMS whose polls ran out, counts down the rest. */
    public function poll(int $tzid): void
    {
        $list = $this->scheduled[$tzid] ?? [];
        $due = [];
        $rest = [];
        foreach ($list as $sms) {
            if ($sms['polls'] <= 0) {
                $due[] = $sms;
            } else {
                --$sms['polls'];
                $rest[] = $sms;
            }
        }
        if ($rest !== []) {
            $this->scheduled[$tzid] = $rest;
        } else {
            unset($this->scheduled[$tzid]);
        }
        // Dropped silently when the operation was closed meanwhile: a real SMS would not arrive either.
        foreach ($due as $sms) {
            $this->deliver($tzid, $sms['text'], $sms['service']);
        }
    }

    /**
     * No HTTP call: the mock only records what the backend would have sent.
     *
     * @param 'receiving_sms'|'rent_sms' $type
     * @param MockSms                    $sms
     */
    private function webhook(string $type, int $tzid, int $country, string $number, array $sms, int $timeLeft, string $timeStart): void
    {
        if ($this->webhookUrl === null) {
            return;
        }
        $time = self::stamp($sms['at']);
        $this->webhookLogs[] = [
            'id' => count($this->webhookLogs) + 1,
            'user_id' => self::USER_ID,
            'webhook_url' => $this->webhookUrl,
            'type' => $type,
            'params' => [
                'webhook_type' => $type,
                'operation_id' => $tzid,
                'user_id' => self::USER_ID,
                'country_code' => $country,
                'number' => $number,
                'sender' => $sms['service'],
                'message' => $sms['text'],
                'code' => $sms['code'] ?? '',
                'time_start' => $timeStart,
                'time_left' => $timeLeft,
            ],
            'status' => 'success',
            'error' => null,
            'created_at' => $time,
            'updated_at' => $time,
        ];
    }
}
