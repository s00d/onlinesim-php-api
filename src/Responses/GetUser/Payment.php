<?php

namespace s00d\OnlineSimApi\Responses\GetUser;

use s00d\OnlineSimApi\Responses\Base;

class Payment extends Base
{
    public $id;
    public $id_user;
    public $payment;
    public $spent;
    public $now;
    public $income;
    public $sms_count;
    public $created_at;
    public $updated_at;
}
