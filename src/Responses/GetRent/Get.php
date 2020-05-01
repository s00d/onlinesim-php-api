<?php

namespace s00d\OnlineSimApi\Responses\GetRent;

use s00d\OnlineSimApi\Responses\Base;

class Get extends Base
{
    public $tzid;
    public $status;
    public $messages = [];
    public $country;
    public $rent;
    public $extension;
    public $sum;
    public $number;
    public $time;
    public $hours;
    public $extend;
    public $checked;
    public $reload;

    public function __construct($properties = []){
        foreach ($properties as $key => $value) {
            if ($key === 'messages') {
                foreach ($value as $id => $data) {
                    $this->messages[$id] = new Message($data);
                }
            } else {
                $this->{$key} = $value;
            }
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
        $properties = get_object_vars($this);
        foreach ($properties as $key => $item) {
            if($key === 'messages') {
                foreach ($item as $id => $message) {
                    /** @var Message $message */
                    $properties['messages'][$id] = $message->toArray();
                }
            }
        }
        unset($properties['other']);
        return $properties;
    }
}
