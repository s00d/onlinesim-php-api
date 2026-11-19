<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Mock;

use s00d\OnlineSimApi\Normalize;

/**
 * The mock's routes: endpoint path (relative to `/api/`) → handler. A handler gets the state and the request and
 * returns the JSON body the real backend would send (HTTP 200). Shapes follow the OpenAPI spec and the live
 * captures: `response` as int or string per endpoint, decimal-string money, `[]` for empty maps.
 * Auth, queued failures, 456 and 404 are the transport's job (`MockOnlineSim`), not the handlers'.
 *
 * @internal used by `MockOnlineSim`; not part of the public API
 *
 * @phpstan-import-type MockOperation from MockState
 * @phpstan-import-type MockRent from MockState
 * @phpstan-import-type MockRequest from MockState
 * @phpstan-import-type MockSms from MockState
 *
 * @phpstan-type Json array<array-key, mixed>
 * @phpstan-type Handler \Closure(MockState, MockRequest): Json
 */
final class Handlers
{
    /** Endpoints that answer without a key. */
    public const PUBLIC_PATHS = ['getNumbersStats.php', 'rent/tariffsRent.php', 'getFreeList'];

    private const COUNTRY_NAMES = [
        7 => ['russia', 'Russia'],
        44 => ['united_kingdom', 'United Kingdom'],
    ];

    /** Free numbers: country → [name, text, short number → list of [sender, text]]. */
    private const FREE = [
        7 => ['russia', 'Russia', ['9001234567' => [['Telegram', 'Telegram code: 51423']]]],
        44 => ['united_kingdom', 'United Kingdom', ['7459608320' => [
            ['Amazon', 'Amazon: Use 195895 to reset your password.'],
            ['Microsoft', 'Microsoft access code: 2991'],
        ]]],
    ];

    /** @return Handler|null */
    public static function route(string $path): ?\Closure
    {
        return match ($path) {
            'getPrice.php' => self::getPrice(...),
            'getNum.php' => self::getNum(...),
            'getState.php' => self::getState(...),
            'setOperationRevise.php' => self::setOperationRevise(...),
            'setOperationOk.php' => self::setOperationOk(...),
            'getNumbersStats.php' => self::getNumbersStats(...),
            'rent/getRentNum.php' => self::getRentNum(...),
            'rent/getRentState.php' => self::getRentState(...),
            'rent/extendRentState.php' => self::extendRentState(...),
            'rent/closeRentNum.php' => self::closeRentNum(...),
            'rent/tariffsRent.php' => self::tariffsRent(...),
            'getBalance.php' => self::getBalance(...),
            'getProfile.php' => self::getProfile(...),
            'profile.php' => self::profile(...),
            'webhook-logs' => self::webhookLogs(...),
            'getPaymentHistory.php' => self::getPaymentHistory(...),
            'getFreeList' => self::getFreeList(...),
            default => null,
        };
    }

    // ----- helpers -----

    /** @return Json */
    private static function error(string $code): array
    {
        return ['response' => $code];
    }

    /** @param MockRequest $req */
    private static function flag(array $req, string $key): bool
    {
        return isset($req['query'][$key]) && $req['query'][$key] !== '0';
    }

    /** @param MockRequest $req */
    private static function int(array $req, string $key, int $fallback): int
    {
        return Normalize::int($req['query'][$key] ?? null) ?? $fallback;
    }

    /** USD with `$digits` decimals, as the backend sends `balance` and rent `sum`. */
    private static function decimal(int $mills, int $digits = 3): string
    {
        return number_format(MockState::fromMills($mills), $digits, '.', '');
    }

    /** @return array{string, string} */
    private static function countryName(int $code): array
    {
        return self::COUNTRY_NAMES[$code] ?? ["country_{$code}", "Country {$code}"];
    }

    /**
     * Laravel paginator over `$items`.
     *
     * @param list<mixed> $items
     *
     * @return Json
     */
    private static function paginate(array $items, int $page, int $perPage, string $path): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $total = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $data = array_slice($items, ($page - 1) * $perPage, $perPage);
        $url = static fn (int $p): string => "https://onlinesim.host/api/{$path}?page={$p}";

        return [
            'current_page' => $page,
            'data' => $data,
            'first_page_url' => $url(1),
            'from' => $data !== [] ? ($page - 1) * $perPage + 1 : null,
            'last_page' => $lastPage,
            'last_page_url' => $url($lastPage),
            'links' => [],
            'next_page_url' => $page < $lastPage ? $url($page + 1) : null,
            'path' => "https://onlinesim.host/api/{$path}",
            'per_page' => $perPage,
            'prev_page_url' => $page > 1 ? $url($page - 1) : null,
            'to' => $data !== [] ? ($page - 1) * $perPage + count($data) : null,
            'total' => $total,
        ];
    }

    // ----- numbers -----

    /** @param MockSms $sms */
    private static function msgOf(array $sms, bool $toCode): string
    {
        return $toCode ? ($sms['code'] ?? $sms['text']) : $sms['text'];
    }

    /**
     * @param MockOperation $op
     * @param MockRequest   $req
     *
     * @return Json
     */
    private static function operationView(MockState $state, array $op, array $req): array
    {
        $toCode = self::flag($req, 'message_to_code');
        $open = $op['status'] !== 'TZ_OVER_OK';
        $view = [
            'country' => $op['country'],
            'sum' => $op['sum'],
            'service' => $op['service'],
            'number' => $op['number'],
            'subscription_package_id' => null,
            'webhook_url' => $state->webhookUrl,
            'response' => $op['status'],
            'tzid' => $op['tzid'],
            'time' => $open ? MockState::OPERATION_TIME : 0,
            'form' => 'index',
        ];
        if (self::flag($req, 'msg_list')) {
            if ($op['messages'] !== []) {
                $view['msg'] = array_map(static fn (array $s) => ['service' => $s['service'], 'msg' => self::msgOf($s, $toCode)], $op['messages']);
            }
        } elseif (isset($op['messages'][$op['cursor']])) {
            $view['msg'] = self::msgOf($op['messages'][$op['cursor']], $toCode);
        }
        if (isset($req['query']['number'])) {
            $view['guard_interval_remaining_seconds'] = $open && $op['messages'] === [] ? $state->guardInterval : 0;
        }

        return $view;
    }

    /**
     * @param MockRequest $req
     *
     * @return Json
     */
    private static function getPrice(MockState $state, array $req): array
    {
        $prices = $state->prices[self::int($req, 'country', 7)] ?? null;
        if ($prices === null) {
            return self::error('UNDEFINED_COUNTRY');
        }

        return ['response' => '1', 'price' => $prices[$req['query']['service'] ?? ''] ?? null];
    }

    /**
     * @param MockRequest $req
     *
     * @return Json
     */
    private static function getNum(MockState $state, array $req): array
    {
        $country = self::int($req, 'country', 7);
        $service = $req['query']['service'] ?? '';
        $prices = $state->prices[$country] ?? null;
        if ($prices === null) {
            return self::error('UNDEFINED_COUNTRY');
        }
        $price = $prices[$service] ?? null;
        if ($price === null) {
            return self::error('ERROR_NO_SERVICE');
        }
        $cost = MockState::toMills($price);
        if ($state->available() < $cost) {
            return self::error('WARNING_LOW_BALANCE');
        }

        $number = "+{$country}" . MockState::shortNumber($state->peekId());
        foreach (explode(',', $req['query']['reject'] ?? '') as $prefix) {
            if ($prefix !== '' && str_starts_with(substr($number, 1), $prefix)) {
                return self::error('NO_NUMBER');
            }
        }

        $tzid = $state->nextId();
        $state->frozen += $cost;
        $state->operations[$tzid] = [
            'tzid' => $tzid,
            'service' => $service,
            'country' => $country,
            'number' => $number,
            'sum' => (float) $price,
            'status' => 'TZ_NUM_WAIT',
            'messages' => [],
            'cursor' => 0,
        ];
        if (!isset($req['query']['number'])) {
            return ['response' => 1, 'tzid' => $tzid];
        }

        return ['response' => 1, 'tzid' => $tzid, 'number' => $number, 'country' => $country, 'time' => MockState::OPERATION_TIME, 'service' => $service];
    }

    /**
     * @param MockRequest $req
     *
     * @return Json
     */
    private static function getState(MockState $state, array $req): array
    {
        if (isset($req['query']['tzid'])) {
            $tzid = self::int($req, 'tzid', -1);
            $ids = isset($state->operations[$tzid]) ? [$tzid] : [];
        } else {
            $ids = array_keys($state->operations);
        }
        foreach ($ids as $tzid) {
            $state->poll($tzid);
        }
        if ($ids === []) {
            return self::error('ERROR_NO_OPERATIONS');
        }
        sort($ids);
        if (($req['query']['orderby'] ?? '') === 'desc') {
            $ids = array_reverse($ids);
        }

        return array_map(static fn (int $tzid) => self::operationView($state, $state->operations[$tzid], $req), $ids);
    }

    /**
     * @param MockRequest $req
     *
     * @return Json
     */
    private static function setOperationRevise(MockState $state, array $req): array
    {
        $tzid = self::int($req, 'tzid', 0);
        $op = $state->operations[$tzid] ?? null;
        // Always succeeds, even for unknown tzids, like the backend.
        if ($op !== null && $op['status'] !== 'TZ_OVER_OK') {
            $op['cursor'] = count($op['messages']);
            $op['status'] = 'TZ_NUM_WAIT';
            $state->operations[$tzid] = $op;
        }

        return ['response' => 1, 'tzid' => $tzid];
    }

    /**
     * @param MockRequest $req
     *
     * @return Json
     */
    private static function setOperationOk(MockState $state, array $req): array
    {
        $tzid = self::int($req, 'tzid', 0);
        $op = $state->operations[$tzid] ?? null;
        if ($op === null || $op['status'] === 'TZ_OVER_OK') {
            return self::error('ERROR_WRONG_TZID');
        }
        $received = $op['messages'] !== [];
        if (self::flag($req, 'ban') && $received) {
            return ['response' => '0'];
        }
        if (!$received && $state->guardInterval > 0) {
            return self::error('NO_COMPLETE_TZID');
        }
        $cost = MockState::toMills($op['sum']);
        $state->frozen -= $cost;
        if ($received) {
            $state->balance -= $cost;
        }
        $state->operations[$tzid]['status'] = 'TZ_OVER_OK';

        return ['response' => 1, 'tzid' => $tzid];
    }

    /** @return Json */
    private static function countryStat(MockState $state, int $code): array
    {
        [$name, $locale] = self::countryName($code);
        $services = [];
        $id = 0;
        foreach ($state->prices[$code] ?? [] as $slug => $price) {
            $services["service_{$slug}"] = ['count' => 100, 'popular' => false, 'code' => $code, 'price' => $price, 'id' => ++$id, 'service' => $slug, 'slug' => $slug];
        }

        return [
            'name' => $name,
            'position' => $code,
            'code' => $code,
            'other' => false,
            'new' => false,
            'enabled' => true,
            'services' => $services, // `[]` when empty, like the backend
            'locale_name' => $locale,
        ];
    }

    /**
     * @param MockRequest $req
     *
     * @return Json
     */
    private static function getNumbersStats(MockState $state, array $req): array
    {
        if (($req['query']['country'] ?? null) === 'all') {
            $all = [];
            foreach (array_keys($state->prices) as $code) {
                $all[$code] = self::countryStat($state, $code);
            }

            return $all;
        }
        $code = self::int($req, 'country', 7);

        return isset($state->prices[$code]) ? self::countryStat($state, $code) : self::error('NO_COUNTRY');
    }

    // ----- rent -----

    /**
     * @param MockRent    $rent
     * @param MockRequest $req
     *
     * @return Json
     */
    private static function rentItem(MockState $state, array $rent, array $req): array
    {
        $tariff = $state->rentTariffs[$rent['country']] ?? null;
        $extendable = $rent['extension'] && $tariff !== null && $tariff['extend'] === 1;
        $start = $rent['tzid'];
        $item = [
            'status' => 1,
            'extension' => $rent['extension'] ? 1 : 0,
            'messages' => array_map(static fn (array $m) => [
                'id' => $m['id'],
                'service' => $m['service'],
                'text' => $m['text'],
                'code' => $m['code'] ?? '',
                'created_at' => $m['created_at'],
            ], array_reverse($rent['messages'])),
            'webhook_url' => $state->webhookUrl,
            'sum' => self::decimal(MockState::toMills($rent['sum']), 2),
            'country' => $rent['country'],
            'start_time' => MockState::stamp($start),
            'stop_time' => MockState::stamp($start + $rent['days'] * 86_400),
            'checked_time' => MockState::stamp($start),
            'number' => $rent['number'],
            'days_autoextend' => 0,
            'rent' => 1,
            'tzid' => $rent['tzid'],
            'time' => $rent['days'] * 24 * 60,
            'days' => $rent['days'],
            'hours' => $rent['days'] * 24,
            'extend' => $extendable ? $tariff['days'] : [],
            'checked' => true,
            'reload' => null,
            'day_extend' => $extendable ? ($tariff['days'][1] ?? 0) : 0,
            'm_ext' => false,
            'freeze' => false,
        ];
        if (isset($req['query']['number'])) {
            $item['time_seconds'] = $rent['days'] * 86_400;
        }

        return $item;
    }

    /**
     * @param MockRequest $req
     *
     * @return Json
     */
    private static function getRentNum(MockState $state, array $req): array
    {
        $country = self::int($req, 'country', 7);
        $tariff = $state->rentTariffs[$country] ?? null;
        if ($tariff === null) {
            return self::error('NO_COUNTRY');
        }
        $days = self::int($req, 'days', 1);
        $price = $tariff['days'][$days] ?? null;
        if ($price === null) {
            return self::error('UNDEFINED_DAYS');
        }
        $cost = MockState::toMills($price);
        if ($state->available() < $cost) {
            return self::error('WARNING_LOW_BALANCE');
        }
        $tzid = $state->nextId();
        $state->balance -= $cost;
        $rent = [
            'tzid' => $tzid,
            'country' => $country,
            'number' => MockState::shortNumber($tzid),
            'days' => $days,
            'sum' => (float) $price,
            // `extension` defaults to 1 on the server; `0` makes the rent non-extendable for good.
            'extension' => ($req['query']['extension'] ?? null) !== '0',
            'messages' => [],
        ];
        $state->rents[$tzid] = $rent;

        return ['response' => 1, 'item' => self::rentItem($state, $rent, $req)];
    }

    /**
     * @param MockRequest $req
     *
     * @return Json
     */
    private static function getRentState(MockState $state, array $req): array
    {
        if (isset($req['query']['tzid'])) {
            $tzid = self::int($req, 'tzid', -1);
            $ids = isset($state->rents[$tzid]) ? [$tzid] : [];
        } else {
            $ids = array_keys($state->rents);
        }
        foreach ($ids as $tzid) {
            $state->poll($tzid);
        }

        return ['response' => 1, 'list' => array_map(static fn (int $tzid) => self::rentItem($state, $state->rents[$tzid], $req), $ids)];
    }

    /**
     * @param MockRequest $req
     *
     * @return Json
     */
    private static function extendRentState(MockState $state, array $req): array
    {
        $tzid = self::int($req, 'tzid', 0);
        $rent = $state->rents[$tzid] ?? null;
        if ($rent === null) {
            return self::error('ERROR_WRONG_TZID');
        }
        $tariff = $state->rentTariffs[$rent['country']] ?? null;
        if (!$rent['extension'] || $tariff === null || $tariff['extend'] !== 1) {
            return self::error('TRY_AGAIN_LATER');
        }
        $days = self::int($req, 'days', 1);
        $price = $tariff['days'][$days] ?? null;
        if ($price === null) {
            return self::error('UNDEFINED_DAYS');
        }
        $cost = MockState::toMills($price);
        if ($state->available() < $cost) {
            return self::error('WARNING_LOW_BALANCE');
        }
        $state->balance -= $cost;
        $rent['days'] += $days;
        $rent['sum'] = MockState::fromMills(MockState::toMills($rent['sum']) + $cost);
        $state->rents[$tzid] = $rent;

        return ['response' => 1, 'item' => self::rentItem($state, $rent, $req)];
    }

    /**
     * @param MockRequest $req
     *
     * @return Json
     */
    private static function closeRentNum(MockState $state, array $req): array
    {
        $tzid = self::int($req, 'tzid', 0);
        if (!isset($state->rents[$tzid])) {
            return self::error('ERROR_WRONG_TZID');
        }
        unset($state->rents[$tzid], $state->scheduled[$tzid]);

        return ['response' => '1'];
    }

    /**
     * @param MockRequest $req
     *
     * @return Json
     */
    private static function tariffsRent(MockState $state, array $req): array
    {
        $tariff = static function (int $code) use ($state): array {
            $t = $state->rentTariffs[$code] ?? null;
            if ($t === null) {
                return [];
            }
            $count = array_map(static fn () => 100, $t['days']);

            return [
                'code' => $code,
                'enabled' => true,
                'name' => self::countryName($code)[0],
                'new' => false,
                'position' => $code,
                'count' => $count,
                'days' => $t['days'],
                'extend' => $t['extend'],
                'confirm' => false,
                'ext_counts' => $count,
            ];
        };
        if (isset($req['query']['country'])) {
            return $tariff(self::int($req, 'country', 0));
        }
        $all = [];
        foreach (array_keys($state->rentTariffs) as $code) {
            $all[$code] = $tariff($code);
        }

        return $all;
    }

    // ----- user -----

    /**
     * @param MockRequest $req
     *
     * @return Json
     */
    private static function getBalance(MockState $state, array $req): array
    {
        $res = ['response' => '1', 'balance' => self::decimal($state->balance), 'zbalance' => MockState::fromMills($state->frozen)];
        if (isset($req['query']['income'])) {
            $res += ['income' => 0, 'income_usd' => '0.000'];
        }

        return $res;
    }

    /**
     * @param MockRequest $req
     *
     * @return Json
     */
    private static function getProfile(MockState $state, array $req): array
    {
        return [
            'response' => '1',
            'profile' => [
                'id' => MockState::USER_ID,
                'hash_id' => 'mock',
                'name' => 'mock@example.com',
                'username' => 'mock@example.com',
                'email' => 'mock@example.com',
                'email_verified_at' => MockState::stamp(),
                'active' => 'true',
                'apikey' => $state->apiKey,
                'api_access' => true,
                'is_api' => true,
                'number' => null,
                'country' => null,
                'locale' => 'en',
                'currency' => 'USD',
                'number_region' => null,
                'number_country' => null,
                'number_reject' => [],
                'ugroup' => 1,
                'verify' => 0,
                'block' => 0,
                'resetCount' => null,
                'created_at' => MockState::stamp(),
                'notification_at' => null,
                'first_login' => false,
                'sub' => 0,
                'disable' => false,
                'webhook_url' => $state->webhookUrl,
                'is_project_subscription' => false,
            ],
        ];
    }

    private static function isHttpUrl(string $value): bool
    {
        $url = parse_url($value);

        return is_array($url) && in_array(strtolower($url['scheme'] ?? ''), ['http', 'https'], true) && ($url['host'] ?? '') !== '';
    }

    /**
     * @param MockRequest $req
     *
     * @return Json
     */
    private static function profile(MockState $state, array $req): array
    {
        $body = $req['body'];
        if ($req['method'] !== 'POST' || !is_array($body) || !isset($body['profile']) || !is_array($body['profile'])) {
            return self::error('ERROR_PARAMS');
        }
        if (array_key_exists('webhook_url', $body['profile'])) {
            $url = $body['profile']['webhook_url'];
            if ($url !== null && (!is_string($url) || !self::isHttpUrl($url))) {
                return ['response' => ['webhook_url' => ['The webhook url must be a valid URL.']]];
            }
            $state->webhookUrl = $url;
        }

        return ['response' => '1', 'user' => ['id' => MockState::USER_ID, 'webhook_url' => $state->webhookUrl]];
    }

    /**
     * @param MockRequest $req
     *
     * @return Json
     */
    private static function webhookLogs(MockState $state, array $req): array
    {
        return ['response' => '1', 'data' => self::paginate(array_reverse($state->webhookLogs), self::int($req, 'page', 1), 10, 'webhook-logs')];
    }

    /**
     * @param MockRequest $req
     *
     * @return Json
     */
    private static function getPaymentHistory(MockState $state, array $req): array
    {
        return [
            'response' => '1',
            'orders' => self::paginate([], self::int($req, 'page', 1), 10, 'getPaymentHistory.php'),
            'paylist' => [],
            'domains' => [],
            'currency' => ['USD' => 1],
        ];
    }

    // ----- free -----

    /**
     * @param MockRequest $req
     *
     * @return Json
     */
    private static function getFreeList(MockState $state, array $req): array
    {
        $wanted = self::int($req, 'country', 44);
        $country = isset(self::FREE[$wanted]) ? $wanted : array_key_first(self::FREE);
        [$name, , $list] = self::FREE[$country];
        $numbers = [];
        foreach (array_keys($list) as $n) {
            $numbers[$n] = ['country' => $country, 'country_original' => $name, 'data_humans' => '1 day ago', 'full_number' => "{$country}{$n}", 'is_archive' => false];
        }
        $wantedNumber = $req['query']['number'] ?? '';
        $number = isset($list[$wantedNumber]) ? $wantedNumber : (string) array_key_first($list);
        $messages = [];
        foreach ($list[$number] ?? [] as $i => [$sender, $text]) {
            $messages[] = [
                'id' => $i + 1,
                'text' => $text,
                'in_number' => $sender,
                'my_number' => $number,
                'created_at' => MockState::stamp($i),
                'data_humans' => '1 day ago',
                'code' => MockState::extractCode($text) ?? '-',
            ];
        }
        $countries = [];
        foreach (self::FREE as $code => [$cName, $cText]) {
            $countries[] = ['country' => $code, 'country_text' => $cText, 'country_original' => $cName];
        }

        return [
            'response' => 1,
            'countries' => $countries,
            'numbers' => $numbers,
            'messages' => self::paginate($messages, self::int($req, 'page', 1), self::int($req, 'count', 10), 'getFreeList') + ['number' => $number, 'country' => $country],
        ];
    }
}
