<?php

namespace s00d\OnlineSimApi\Responses\GetRent;

use s00d\OnlineSimApi\Responses\Base;

class Message extends Base
{
    public $id;
    public $service;
    public $text;
    public $code;
    public $created_at;
}
