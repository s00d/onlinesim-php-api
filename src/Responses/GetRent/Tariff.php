<?php

namespace s00d\OnlineSimApi\Responses\GetRent;

use s00d\OnlineSimApi\Responses\Base;

class Tariff extends Base
{
    public $code;
    public $enabled;
    public $name;
    public $new;
    public $position;
    public $count;

    public function getCount($days) {
        if(!isset($this->count[$days])) {
            throw new \RuntimeException('bad days');
        }
        return $this->count[$days];
    }
}
