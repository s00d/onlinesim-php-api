<?php


namespace s00d\OnlineSimApi\Responses\GetProxy;


use s00d\OnlineSimApi\Responses\Base;

class State extends Base
{
    public $data = [];

    public function __construct($properties = []){
        foreach ($properties as $key => $value) {
            $this->data[$value['tzid']] = new Get($value);
        }
    }

    /**
     * @return Get
     */
    public function first() {
        return array_shift($this->data);
    }

    /**
     * @param $code
     * @return Get
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
            if($item instanceof Get) {
                $result[$key] = $item->toArray();
            }
        }
        unset($result['other']);
        return $result;
    }
}
