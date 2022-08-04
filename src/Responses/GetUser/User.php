<?php

namespace s00d\OnlineSimApi\Responses\GetUser;

use s00d\OnlineSimApi\Responses\Base;

class User extends Base
{
    public $id;
    public $username;
    public $name;
    public $email;
    public $ugroup;
    public $apikey;
    public $locale;
    public $domain;
    public $hash_id;
    public $abilities;
    public $first_login;
    /** @var Payment */
    public $payment;


    public function __construct($properties = []){
        $this->payment = new Payment();
        foreach ($properties as $key => $value) {
            if(property_exists($this, $key)) {
                if($key === 'payment') {
                    $this->{$key} = new Payment($value);
                    continue;
                }
                $this->{$key} = $value;
            }
        }
    }

}
