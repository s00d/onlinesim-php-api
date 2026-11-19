<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi;

/** Sort order of `Numbers::state()`. The server accepts only lowercase values. */
enum OrderBy: string
{
    case Asc = 'asc';
    case Desc = 'desc';
}
