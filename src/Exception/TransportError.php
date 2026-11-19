<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Exception;

/** Network failure, request timeout, or a response that is not JSON. */
class TransportError extends OnlineSimError
{
    /**
     * @param int|null $status HTTP status, `null` when no response arrived (network error, timeout)
     * @param string   $body   start of the response body (up to 500 characters), empty when none
     */
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly string $body = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
