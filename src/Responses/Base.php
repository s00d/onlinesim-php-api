<?php

namespace s00d\OnlineSimApi\Responses;

use s00d\OnlineSimApi\Responses\GetUser\Payment;

class Base {
    public function _init() {}
    private $other = [];

    public function __construct($properties = []){
        $this->_init();
        foreach ($properties as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function __isset($property){
        return property_exists($this, $property);
    }

    public function __set($property, $value){
        if(!isset($this->{$property})) {
            return $this->other[$property] = $value;
        } else {
            return $this->$property = $value;
        }
    }

    public function __get($property) {
        return property_exists($this, $property) ? $this->$property : null;
    }

    public function get($key) {
        return $this->{$key};
    }

    /**
     * @return array
     */
    public function toArray() {
        $properties = get_object_vars($this);
        foreach ($properties as $key => $item) {
            if($item instanceof Base) {
                $properties[$key] = $item->toArray();
            }
        }
        unset($properties['other']);
        if(!empty($this->other)) {
            $properties += $this->other;
        }
        return $properties;
    }
}
