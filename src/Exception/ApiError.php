<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Exception;

/**
 * The API answered with an error code in `response`.
 *
 * The raw code is `$errorCode` (PHP's own `getCode()` is an integer and stays 0).
 */
class ApiError extends OnlineSimError
{
    /** Fallback messages for error codes the server sends without a `message`. */
    public const MESSAGES = [
        'ACCOUNT_BLOCKED' => 'Account is blocked',
        'ERROR_WRONG_KEY' => 'Wrong API key',
        'ERROR_NO_KEY' => 'API key is missing',
        'ERROR_NO_SERVICE' => 'Service is not specified',
        'API_ACCESS_DISABLED' => 'API access is disabled in the profile',
        'API_ACCESS_IP' => 'API access from this IP is not allowed in the profile',
        'ERROR_NO_TZID' => 'tzid is not specified',
        'ERROR_WRONG_TZID' => 'Wrong tzid',
        'ERROR_NO_OPERATIONS' => 'No operations',
        'NO_NUMBER' => 'No numbers available for this service right now',
        'NO_NUMBER_FOR_FORWARD' => 'No numbers available for forwarding',
        'NO_COUNTRY' => 'Country not found',
        'UNDEFINED_COUNTRY' => 'Unknown country',
        'WARNING_LOW_BALANCE' => 'Not enough balance',
        'EXCEEDED_CONCURRENT_OPERATIONS' => 'Too many concurrent operations for this account',
        'INTERVAL_CONCURRENT_REQUESTS_ERROR' => 'Too many concurrent requests, try again in a few seconds',
        'TRY_AGAIN_LATER' => 'Temporarily unable to perform the request, try again later',
        'ACCOUNT_IDENTIFICATION_REQUIRED' => 'Account identification is required',
        'ERROR_PARAMS' => 'Wrong parameters',
        'API_DISABLED' => 'API is disabled',
        'NO_COMPLETE_TZID' => 'The operation cannot be closed yet',
        'NO_CLOSE_RENT_US' => 'US rent numbers cannot be closed early',
        'UNDEFINED_DAYS' => 'Unknown rent period',
        'VALIDATION_ERROR' => 'Validation failed',
        'TZ_OVER_OK' => 'Operation finished without a new code',
        'ERROR_NO_ITEMS' => 'Operation failed',
    ];

    /**
     * @param string      $errorCode raw error code, e.g. `ERROR_WRONG_KEY`
     * @param string|null $message   server text; default: `MESSAGES[$errorCode]`, else the code itself
     */
    public function __construct(public readonly string $errorCode, ?string $message = null)
    {
        parent::__construct($message !== null && $message !== '' ? $message : (self::MESSAGES[$errorCode] ?? $errorCode));
    }
}
