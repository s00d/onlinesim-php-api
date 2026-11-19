<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Transport;

/** Raw HTTP answer returned by a `Transport`. */
final class Response
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
    ) {
    }
}
