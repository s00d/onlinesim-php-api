<?php

namespace s00d\OnlineSimApi\Responses\GetNumbers;

use s00d\OnlineSimApi\Responses\Base;

class State
{
    public $data = [];

    public function __construct($properties = []){
        foreach ($properties as $key => $value) {
            $this->data[$key] = new StateOne($value);
        }
    }

    public function toArray() {
        $result = [];
        foreach ($this->data as $key => $item) {
            if($item instanceof StateOne) {
                $result[] = $item->toArray();
            }
        }
        return $result;
    }
}
