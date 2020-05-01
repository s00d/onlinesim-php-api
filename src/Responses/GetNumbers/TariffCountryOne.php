<?php


namespace s00d\OnlineSimApi\Responses\GetNumbers;


use s00d\OnlineSimApi\Responses\Base;

class TariffCountryOne extends Base
{
    public $name = [];
    public $position = [];
    public $code = [];
    public $other = [];
    public $new = [];
    public $enabled = [];
    public $services = [];

    public function __construct($properties = []){
        foreach ($properties as $key => $value) {
            if(property_exists($this, $key)) {
                if($key === 'services') {
                    foreach ($value as $service) {
                        $this->services[$service['slug']] = new TariffServiceOne($service);
                    }
                    continue;
                }
                $this->{$key} = $value;
            }
        }
    }

    /**
     * @return TariffServiceOne
     */
    public function first() {
        return array_shift($this->services);
    }

    /**
     * @param $code
     * @return TariffServiceOne
     */
    public function service($slug) {
        if(!isset($this->services[$slug])) {
            throw new \Exception('Bad service');
        }
        return $this->services[$slug];
    }

    public function toArray() {
        $result = [];
        foreach ($this->services as $key => $item) {
            if($item instanceof TariffServiceOne) {
                $result[$item->slug] = $item->toArray();
            }
        }
        unset($result['other']);
        return $result;
    }
}
