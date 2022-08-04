<?php

namespace s00d\OnlineSimApi\Responses\GetUser;

use s00d\OnlineSimApi\Responses\Base;

class Me extends Base
{
    public $id;
    public $notifications;
    public $_replace_domain;
    public $_replace_currency;
    public $_timestamp;
    public $_timeatom;
    public $auth;
    public $locale;

    public $user;


    public function __construct($properties = []){
        $this->user = new User();
        foreach ($properties as $key => $value) {
            if(property_exists($this, $key)) {
                if($key === 'user') {
                    $this->{$key} = new User($value);
                    continue;
                }
                $this->{$key} = $value;
            }
        }
    }

}
