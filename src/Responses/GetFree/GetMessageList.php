<?php


namespace s00d\OnlineSimApi\Responses\GetFree;

use s00d\OnlineSimApi\Responses\Base;

class GetMessageList extends Base
{
    public $data = [];

    public function __construct($properties = []){
        foreach ($properties as $key => $value) {
            $this->data[] = new GetMessageListOne($value);
        }
    }

    /**
     * @return GetMessageListOne
     */
    public function first() {
        return array_shift($this->data);
    }

    public function toArray() {
        $result = [];
        foreach ($this->data as $key => $item) {
            if($item instanceof GetMessageListOne) {
                $result[$key] = $item->toArray();
            }
        }
        unset($result['other']);
        return $result;
    }
}
