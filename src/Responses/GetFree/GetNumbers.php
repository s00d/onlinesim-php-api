<?php


namespace s00d\OnlineSimApi\Responses\GetFree;

use s00d\OnlineSimApi\Responses\Base;

class GetNumbers extends Base
{
    public $data = [];

    public function __construct($properties = []) {
        foreach ($properties as $key => $value) {
            $this->data[$value['number']] = new GetNumbersOne($value);
        }
    }

    /**
     * @return GetNumbersOne
     */
    public function first() {
        return array_shift($this->data);
    }

    /**
     * @param $code
     * @return GetNumbersOne
     */
    public function country($code) {
        if(!isset($this->data[$code])) {
            throw new \Exception('Bad country');
        }
        return $this->data[$code];
    }

    public function toArray() {
        $result = [];
        foreach ($this->data as $key => $item) {
            if($item instanceof GetNumbers) {
                $result[$key] = $item->toArray();
            }
        }
        unset($result['other']);
        return $result;
    }

}
