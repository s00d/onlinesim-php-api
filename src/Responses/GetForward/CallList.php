<?php

namespace s00d\OnlineSimApi\Responses\GetForward;

use s00d\OnlineSimApi\Responses\Base;

class CallList extends Base
{
    public $data = [];

    public function __construct($properties = []) {
        foreach ($properties as $key => $value) {
            $this->data[$value['id']] = new CallListOne($value);
        }
    }

    /**
     * @return CallListOne
     */
    public function first() {
        return array_shift($this->data);
    }

    /**
     * @param $code
     * @return CallListOne
     */
    public function get($tzid) {
        if(!isset($this->data[$tzid])) {
            throw new \Exception('Bad country');
        }
        return $this->data[$tzid];
    }

    public function toArray() {
        $result = [];
        foreach ($this->data as $key => $item) {
            if($item instanceof CallListOne) {
                $result[$key] = $item->toArray();
            }
        }
        unset($result['other']);
        return $result;
    }
}
