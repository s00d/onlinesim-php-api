<?php

namespace s00d\OnlineSimApi\Responses\GetRent;

use s00d\OnlineSimApi\Responses\Base;

class Tariffs extends Base
{
    public $data = [];

    public function __construct($properties = []){
        foreach ($properties as $key => $value) {
            $this->data[$key] = new Tariff($value);
        }
    }

    /**
     * @return Tariff
     */
    public function first() {
        return array_shift($this->data);
    }

    /**
     * @param int $country
     * @return Tariff
     */
    public function get($country) {
        if(!isset($this->data[$country])) {
            throw new \RuntimeException('Bad country');
        }
        return $this->data[$country];
    }

    public function toArray() {
        $result = [];
        foreach ($this->data as $key => $item) {
            if($item instanceof Tariff) {
                $result[$key] = $item->toArray();
            }
        }
        unset($result['other']);
        return $result;
    }
}
