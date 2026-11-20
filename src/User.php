<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi;

use s00d\OnlineSimApi\Exception\ApiError;
use s00d\OnlineSimApi\Exception\TransportError;

/**
 * Account: `$client->user`.
 *
 * @phpstan-type Balance array{balance: float, zbalance: float, income?: float}
 * @phpstan-type Profile array{
 *     id: int,
 *     hash_id?: string|null,
 *     name?: string|null,
 *     username?: string|null,
 *     email?: string|null,
 *     email_verified_at?: string|null,
 *     apikey?: string,
 *     api_access?: bool|array<string, mixed>,
 *     locale?: string,
 *     currency?: string,
 *     number_country?: int|null,
 *     number_reject?: array<array-key, string>|null,
 *     webhook_url?: string|null,
 *     number?: int|null,
 *     country?: string|null,
 *     number_region?: int|null,
 *     is_api?: bool,
 *     active?: string,
 *     block?: int,
 *     disable?: bool,
 *     verify?: int,
 *     ugroup?: int,
 *     sub?: int,
 *     first_login?: bool,
 *     is_project_subscription?: bool,
 *     resetCount?: int|null,
 *     created_at?: string,
 *     notification_at?: string|null,
 * }
 * @phpstan-type Paginator array{
 *     current_page: int,
 *     data: list<array<string, mixed>>,
 *     from: int|null,
 *     to: int|null,
 *     last_page: int,
 *     per_page: int,
 *     total: int,
 *     first_page_url?: string|null,
 *     last_page_url?: string|null,
 *     next_page_url?: string|null,
 *     prev_page_url?: string|null,
 *     path?: string,
 *     links?: list<array<string, mixed>>,
 * }
 * @phpstan-type PaymentOrder array{
 *     id: int,
 *     sum: string,
 *     currency: string|null,
 *     stat: int,
 *     form_text?: string|null,
 *     status_text?: string,
 *     created_at: string,
 *     ...
 * }
 * @phpstan-type PaymentHistory array{
 *     orders: array{current_page: int, data: list<PaymentOrder>, from: int|null, to: int|null, last_page: int, per_page: int, total: int, ...},
 *     paylist: array<array-key, mixed>,
 *     domains?: list<string>,
 *     forms?: array<string, string>,
 *     currency?: array<string, int|float>,
 *     buttons?: array<string, list<int>>,
 * }
 * @phpstan-type WebhookLog array{
 *     id: int,
 *     user_id: int,
 *     webhook_url: string,
 *     type: 'receiving_sms'|'rent_sms',
 *     params: array<string, mixed>,
 *     status: 'success'|'fail'|'pending',
 *     error: string|null,
 *     created_at: string,
 *     updated_at: string,
 * }
 * @phpstan-type WebhookLogPage array{
 *     current_page: int,
 *     data: list<WebhookLog>,
 *     from: int|null,
 *     to: int|null,
 *     last_page: int,
 *     per_page: int,
 *     total: int,
 *     ...
 * }
 */
final class User
{
    /** @internal created by `OnlineSim` */
    public function __construct(private readonly Requester $requester)
    {
    }

    /**
     * Balance in USD. `balance` includes the frozen `zbalance` (numbers and unconfirmed rents in progress; may be
     * negative): available = balance − max(zbalance, 0).
     *
     * @return Balance
     *
     * @throws ApiError|TransportError
     */
    public function balance(): array
    {
        $res = $this->requester->call('getBalance.php', ['income' => 1]);
        $out = [
            'balance' => Normalize::float($res['balance'] ?? null) ?? 0.0,
            'zbalance' => Normalize::float($res['zbalance'] ?? null) ?? 0.0,
        ];
        $income = Normalize::float($res['income'] ?? null);
        if ($income !== null) {
            $out['income'] = $income;
        }

        return $out;
    }

    /**
     * Account profile.
     *
     * @return Profile
     *
     * @throws ApiError|TransportError
     */
    public function profile(): array
    {
        $res = $this->requester->call('getProfile.php');

        /** @var Profile */
        return Normalize::map($res['profile'] ?? null);
    }

    /**
     * Top-up orders (paginated, newest first) and the available payment methods. `paylist` maps a group id to its
     * methods; it is a list when the ids are 0, 1, 2… (the server sends a JSON array then). Order `currency` and
     * `form_text` may be `null`.
     *
     * @return PaymentHistory
     *
     * @throws ApiError|TransportError
     */
    public function paymentHistory(int $page = 1): array
    {
        $res = $this->requester->call('getPaymentHistory.php', ['page' => $page]);
        // A JSON array `paylist` (ids 0..n) is kept as the list it decodes to; only a missing value becomes `[]`.
        $res['paylist'] = Normalize::map($res['paylist'] ?? null);

        /** @var PaymentHistory $res */
        return $res;
    }

    /**
     * Set the URL that receives SMS webhooks. Throws `ApiError('VALIDATION_ERROR')` for an invalid URL.
     *
     * @return true
     *
     * @throws ApiError|TransportError
     */
    public function setWebhookUrl(string $url): bool
    {
        $this->requester->call('profile.php', json: ['profile' => ['webhook_url' => $url]]);

        return true;
    }

    /**
     * Remove the webhook URL (also from operations in progress).
     *
     * @return true
     *
     * @throws ApiError|TransportError
     */
    public function clearWebhookUrl(): bool
    {
        $this->requester->call('profile.php', json: ['profile' => ['webhook_url' => null]]);

        return true;
    }

    /**
     * Webhook delivery log, newest first, 10 per page.
     *
     * @return WebhookLogPage
     *
     * @throws ApiError|TransportError
     */
    public function webhookLogs(int $page = 1): array
    {
        $res = $this->requester->call('webhook-logs', ['page' => $page]);

        /** @var WebhookLogPage */
        return Normalize::map($res['data'] ?? null);
    }
}
