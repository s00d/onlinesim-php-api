<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi;

use s00d\OnlineSimApi\Exception\ApiError;
use s00d\OnlineSimApi\Exception\TransportError;

/**
 * Public free numbers: `$client->free`. No key needed.
 *
 * @phpstan-type FreeCountry array{country: int, country_text: string|null, country_original: string|null}
 * @phpstan-type FreeNumber array{country: int, country_original: string|null, full_number: string, data_humans: string, is_archive: bool}
 * @phpstan-type FreeMessage array{
 *     id: int,
 *     text: string,
 *     in_number: string,
 *     my_number: string,
 *     code: string,
 *     created_at: string,
 *     data_humans: string,
 * }
 * @phpstan-type FreeList array{
 *     countries: list<FreeCountry>,
 *     numbers: array<int|string, FreeNumber>,
 *     messages: array{
 *         current_page: int,
 *         data: list<FreeMessage>,
 *         from: int|null,
 *         to: int|null,
 *         last_page: int,
 *         per_page: int,
 *         total: int,
 *         number: string|null,
 *         country: int,
 *         ...
 *     },
 * }
 */
final class Free
{
    /** @internal created by `OnlineSim` */
    public function __construct(private readonly Requester $requester)
    {
    }

    /**
     * Countries, the numbers of the selected country and the SMS of the selected number.
     *
     * @param int|null    $country country code; server default 44
     * @param string|int|null $number number without the country code, as the keys of `numbers`
     * @param int|null    $page    page of `messages`
     * @param int|null    $count   SMS per page, 10–500
     *
     * @return FreeList
     *
     * @throws ApiError|TransportError
     */
    public function list(?int $country = null, string|int|null $number = null, ?int $page = null, ?int $count = null): array
    {
        $res = $this->requester->call('getFreeList', ['country' => $country, 'number' => $number, 'page' => $page, 'count' => $count]);

        /** @var FreeList */
        return [
            'countries' => Normalize::map($res['countries'] ?? null),
            'numbers' => Normalize::map($res['numbers'] ?? null),
            'messages' => Normalize::map($res['messages'] ?? null),
        ];
    }
}
