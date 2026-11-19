<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi\Exception;

/** No number is available right now (`NO_NUMBER`, `NO_NUMBER_FOR_FORWARD`). Retry later or pick another country. */
class NoNumberError extends ApiError
{
}
