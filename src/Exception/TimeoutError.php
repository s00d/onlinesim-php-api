<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Exception;

/** `waitCode` gave up: no code arrived within its `timeout`. */
class TimeoutError extends OnlineSimError
{
}
