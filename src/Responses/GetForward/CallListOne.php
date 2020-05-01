<?php


namespace s00d\OnlineSimApi\Responses\GetForward;


use s00d\OnlineSimApi\Responses\Base;

class CallListOne extends Base
{
    public $id;
    public $number_forward_client;
    public $number_forward;
    public $payment_minutes;
    public $start_at;
    public $stop_at;
    public $created_at;
}
