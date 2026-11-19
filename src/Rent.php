<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi;

use s00d\OnlineSimApi\Exception\ApiError;
use s00d\OnlineSimApi\Exception\NoNumberError;
use s00d\OnlineSimApi\Exception\TransportError;

/**
 * Rented numbers: `$client->rent`.
 *
 * @phpstan-type RentSms array{id: int, service: string, text: string, code: string, created_at: string}
 * @phpstan-type RentItem array{
 *     tzid: int,
 *     status: int,
 *     number: string,
 *     country: int,
 *     sum: float,
 *     days: int,
 *     hours: int,
 *     time: int,
 *     time_seconds?: int,
 *     guard_interval_remaining_seconds?: int,
 *     extension: int,
 *     days_autoextend?: int,
 *     rent?: int,
 *     start_time: string,
 *     stop_time: string,
 *     checked_time?: string|null,
 *     checked?: bool,
 *     freeze?: bool,
 *     reload?: int|null,
 *     m_ext?: bool,
 *     day_extend?: float|int,
 *     extend: array<int|string, float|int>,
 *     webhook_url?: string|null,
 *     messages: list<RentSms>,
 * }
 * @phpstan-type RentTariff array{
 *     code: int,
 *     name: string,
 *     enabled: bool,
 *     new: bool,
 *     position: int,
 *     extend: int,
 *     confirm?: bool,
 *     days: array<int|string, float|int>,
 *     count: array<int|string, int>,
 *     ext_counts?: array<int|string, int>,
 * }
 */
final class Rent
{
    /** Always sent: a flat `messages` list and `time_seconds`. */
    private const DETAIL = ['pagination' => 0, 'number' => 1];

    /** @internal created by `OnlineSim` */
    public function __construct(private readonly Requester $requester)
    {
    }

    /**
     * Rent a number for `$days` days. `$extension = false` makes the rent non-extendable for good.
     *
     * @return RentItem
     *
     * @throws NoNumberError|ApiError|TransportError
     */
    public function get(int $country = 7, int $days = 1, bool $extension = true): array
    {
        $res = $this->requester->call('rent/getRentNum.php', ['country' => $country, 'days' => $days, 'extension' => $extension] + self::DETAIL);

        return self::item($res['item'] ?? null);
    }

    /**
     * All rents of the account.
     *
     * @return list<RentItem>
     *
     * @throws ApiError|TransportError
     */
    public function state(): array
    {
        $res = $this->requester->call('rent/getRentState.php', self::DETAIL);

        return array_values(array_map(self::item(...), Normalize::map($res['list'] ?? null)));
    }

    /**
     * One rent. Throws `ApiError('ERROR_NO_OPERATIONS')` when it does not exist.
     *
     * @return RentItem
     *
     * @throws ApiError|TransportError
     */
    public function stateOne(int $tzid): array
    {
        $res = $this->requester->call('rent/getRentState.php', ['tzid' => $tzid] + self::DETAIL);
        $list = Normalize::map($res['list'] ?? null);

        return array_key_exists(0, $list) ? self::item($list[0]) : throw new ApiError('ERROR_NO_OPERATIONS');
    }

    /**
     * Extend a rent by `$days` days (only rents created with `extension: true`).
     *
     * @return RentItem
     *
     * @throws ApiError|TransportError
     */
    public function extend(int $tzid, int $days = 1): array
    {
        $res = $this->requester->call('rent/extendRentState.php', ['tzid' => $tzid, 'days' => $days] + self::DETAIL);

        return self::item($res['item'] ?? null);
    }

    /**
     * Rent prices of every country, keyed by country code. No key needed.
     *
     * @return array<int, RentTariff>
     *
     * @throws ApiError|TransportError
     */
    public function tariffs(): array
    {
        /** @var array<int, RentTariff> */
        return $this->requester->call('rent/tariffsRent.php');
    }

    /**
     * Rent prices of one country. Throws `ApiError('NO_COUNTRY')` for a country without rent. No key needed.
     *
     * @return RentTariff
     *
     * @throws ApiError|TransportError
     */
    public function tariffsOne(int $country = 7): array
    {
        $res = $this->requester->call('rent/tariffsRent.php', ['country' => $country]);
        // An unknown country answers `[]`.
        if (!array_key_exists('code', $res)) {
            throw new ApiError('NO_COUNTRY');
        }

        /** @var RentTariff $res */
        return $res;
    }

    /**
     * Return the number before the rent period ends.
     *
     * @return true
     *
     * @throws ApiError|TransportError
     */
    public function close(int $tzid): bool
    {
        $this->requester->call('rent/closeRentNum.php', ['tzid' => $tzid]);

        return true;
    }

    /**
     * Server shape → `RentItem`: `sum` is a decimal string, `extend` may be `[]`, `messages` must be a list.
     *
     * @return RentItem
     */
    private static function item(mixed $raw): array
    {
        $item = Normalize::map($raw);
        $item['sum'] = Normalize::float($item['sum'] ?? null) ?? 0.0;
        $item['extend'] = Normalize::map($item['extend'] ?? null);
        $item['messages'] = isset($item['messages']) && is_array($item['messages']) && array_is_list($item['messages']) ? $item['messages'] : [];

        /** @var RentItem $item */
        return $item;
    }
}
