<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi;

use s00d\OnlineSimApi\Exception\ApiError;
use s00d\OnlineSimApi\Exception\TimeoutError;
use s00d\OnlineSimApi\Exception\TransportError;

/**
 * Numbers for receiving SMS: `$client->numbers`.
 *
 * Results are arrays with the server's snake_case keys, described by these shapes:
 *
 * @phpstan-type OperationSms array{service: string, msg?: string, text?: string, code?: string}
 * @phpstan-type Operation array{
 *     tzid: int,
 *     response: 'TZ_INPOOL'|'TZ_NUM_WAIT'|'TZ_NUM_ANSWER'|'TZ_OVER_OK'|'ERROR_NO_ITEMS',
 *     number?: string,
 *     service?: string,
 *     country?: int,
 *     sum?: float|int,
 *     time?: int,
 *     form?: string,
 *     subscription_package_id?: int|null,
 *     webhook_url?: string|null,
 *     msg?: string|list<OperationSms>|false,
 *     extend?: int,
 *     guard_interval_remaining_seconds?: int,
 * }
 * @phpstan-type NumberOrder array{
 *     tzid: int,
 *     number: string,
 *     country: int,
 *     time: int,
 *     service: string,
 *     title?: string,
 *     guard_interval_remaining_seconds?: int,
 * }
 * @phpstan-type ServiceStat array{
 *     count: int|string,
 *     popular: bool,
 *     code: int,
 *     price: float|int,
 *     id: int,
 *     service: string,
 *     slug: string,
 * }
 * @phpstan-type CountryStat array{
 *     name: string,
 *     locale_name?: string,
 *     position: int,
 *     code: int,
 *     other?: float|int|false,
 *     new: bool,
 *     enabled: bool,
 *     services: array<string, ServiceStat>,
 * }
 */
final class Numbers
{
    /** @internal created by `OnlineSim` */
    public function __construct(private readonly Requester $requester)
    {
    }

    /**
     * Price (USD) of one number of `$service` in `$country`; `null` when the country has no price for it.
     *
     * @throws ApiError|TransportError
     */
    public function price(string $service, int $country = 7): ?float
    {
        $res = $this->requester->call('getPrice.php', ['service' => $service, 'country' => $country]);

        return Normalize::float($res['price'] ?? null);
    }

    /**
     * Order a number. Returns the operation id (`tzid`).
     *
     * @param list<string|int> $reject number prefixes to skip, e.g. `['4470', '4471']`
     *
     * @throws ApiError|TransportError
     */
    public function get(string $service, int $country = 7, array $reject = []): int
    {
        $res = $this->requester->call('getNum.php', ['service' => $service, 'country' => $country, 'reject' => $reject]);

        return Normalize::int($res['tzid'] ?? null) ?? throw new TransportError('getNum: no tzid in the answer');
    }

    /**
     * Order a number and get it right away together with the operation id.
     *
     * @param list<string|int> $reject number prefixes to skip
     *
     * @return NumberOrder
     *
     * @throws ApiError|TransportError
     */
    public function getWithNumber(string $service, int $country = 7, array $reject = []): array
    {
        // `number` is a presence flag on getNum: sent here only.
        /** @var NumberOrder */
        return $this->requester->call('getNum.php', ['service' => $service, 'country' => $country, 'reject' => $reject, 'number' => 1]);
    }

    /**
     * All operations of the account; `[]` when there are none.
     *
     * @param bool $messageToCode `true`: `msg` is the code only; `false`: the full SMS text
     * @param bool $msgList       `true`: `msg` is a list of all SMS
     *
     * @return list<Operation>
     *
     * @throws ApiError|TransportError
     */
    public function state(bool $messageToCode = true, OrderBy $orderBy = OrderBy::Asc, bool $msgList = false): array
    {
        try {
            /** @var list<Operation> */
            return $this->requester->call('getState.php', [
                'message_to_code' => $messageToCode,
                'orderby' => $orderBy,
                'msg_list' => $msgList,
                'number' => 1,
            ]);
        } catch (ApiError $e) {
            if ($e->errorCode === 'ERROR_NO_OPERATIONS') {
                return [];
            }

            throw $e;
        }
    }

    /**
     * One operation. Throws `ApiError('ERROR_NO_OPERATIONS')` when it does not exist (any more).
     *
     * @return Operation
     *
     * @throws ApiError|TransportError
     */
    public function stateOne(int $tzid, bool $messageToCode = true, bool $msgList = false): array
    {
        /** @var list<Operation> $list */
        $list = $this->requester->call('getState.php', [
            'tzid' => $tzid,
            'message_to_code' => $messageToCode,
            'msg_list' => $msgList,
            'number' => 1,
        ]);

        return $list[0] ?? throw new ApiError('ERROR_NO_OPERATIONS');
    }

    /**
     * Switch the operation to its next SMS and keep it open.
     *
     * @return true
     *
     * @throws ApiError|TransportError
     */
    public function next(int $tzid): bool
    {
        $this->requester->call('setOperationRevise.php', ['tzid' => $tzid]);

        return true;
    }

    /**
     * Finish the operation.
     *
     * @return true
     *
     * @throws ApiError|TransportError
     */
    public function close(int $tzid): bool
    {
        $this->requester->call('setOperationOk.php', ['tzid' => $tzid]);

        return true;
    }

    /**
     * Finish the operation and report the number as bad (only before any SMS arrived).
     *
     * @return true
     *
     * @throws ApiError|TransportError
     */
    public function ban(int $tzid): bool
    {
        $this->requester->call('setOperationOk.php', ['tzid' => $tzid, 'ban' => true]);

        return true;
    }

    /**
     * Services, prices and stock of every country, keyed by country code. No key needed.
     *
     * @return array<int, CountryStat>
     *
     * @throws ApiError|TransportError
     */
    public function tariffs(): array
    {
        /** @var array<int, CountryStat> $all */
        $all = $this->requester->call('getNumbersStats.php', ['country' => 'all']);

        return array_map(self::country(...), $all);
    }

    /**
     * Services, prices and stock of one country. No key needed.
     *
     * @return CountryStat
     *
     * @throws ApiError|TransportError
     */
    public function tariffsOne(int $country = 7): array
    {
        /** @var CountryStat $res */
        $res = $this->requester->call('getNumbersStats.php', ['country' => $country]);

        return self::country($res);
    }

    /**
     * Poll the operation until an SMS code arrives, then close it (or `next` with `$keepOpen`) and return the code.
     *
     * Polls `stateOne($tzid)` every `$interval` seconds until a code different from `$since` arrives. A finished
     * operation (TZ_OVER_OK, ERROR_NO_ITEMS) without a new code throws `ApiError` with that state as the code;
     * running out of `$timeout` throws `TimeoutError`. `TransportError` is retried until the timeout; any `ApiError`
     * is thrown at once.
     *
     * @param float                       $interval    poll interval, seconds
     * @param float                       $timeout     give up after, seconds
     * @param bool                        $keepOpen    call `next($tzid)` instead of `close($tzid)`, to wait for another SMS
     * @param bool                        $fullMessage return the full SMS text instead of the code
     * @param string|null                 $since       ignore this value (the previous code)
     * @param (callable(float): void)|null $sleep      injectable sleep in seconds (tests); default `usleep`
     * @param (callable(): float)|null     $now        injectable monotonic clock in seconds (tests); default `hrtime`
     *
     * @throws ApiError|TimeoutError|TransportError
     */
    public function waitCode(
        int $tzid,
        float $interval = 5.0,
        float $timeout = 600.0,
        bool $keepOpen = false,
        bool $fullMessage = false,
        ?string $since = null,
        ?callable $sleep = null,
        ?callable $now = null,
    ): string {
        $sleep ??= static function (float $seconds): void {
            usleep((int) round($seconds * 1_000_000));
        };
        $now ??= static fn (): float => hrtime(true) / 1e9;

        $deadline = $now() + $timeout;
        for (;;) {
            $op = null;
            try {
                $op = $this->stateOne($tzid, messageToCode: !$fullMessage);
            } catch (TransportError) {
                // transient: retried until the timeout
            }

            if ($op !== null) {
                $msg = $op['msg'] ?? null;
                if (is_string($msg) && $msg !== '' && $msg !== $since) {
                    $keepOpen ? $this->next($tzid) : $this->close($tzid);

                    return $msg;
                }
                if ($op['response'] === 'TZ_OVER_OK' || $op['response'] === 'ERROR_NO_ITEMS') {
                    throw new ApiError($op['response']);
                }
            }

            $left = $deadline - $now();
            if ($left <= 0) {
                throw new TimeoutError("No code for operation {$tzid} within {$timeout} s");
            }
            $sleep(min($interval, $left));
        }
    }

    /**
     * @param CountryStat $country
     *
     * @return CountryStat
     */
    private static function country(array $country): array
    {
        /** @var array<string, ServiceStat> $services */
        $services = Normalize::map($country['services'] ?? null);
        $country['services'] = $services;

        return $country;
    }
}
