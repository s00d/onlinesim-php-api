<?php

namespace s00d\OnlineSimApi\Responses\GetUser;

use Dotenv\Store\File\Paths;
use s00d\OnlineSimApi\Responses\Base;

class Profile extends Base
{
    public $id;
    public $hash_id;
    public $name;
    public $username;
    public $email;
    public $active;
    public $apikey;
    public $api_access;
    public $locale;
    public $number_region;
    public $number_country;
    public $number_reject;
    public $ugroup;
    public $verify;
    public $block;
    public $resetCount;
    public $created_at;
    public $notification_at;
    public $reset_at;
    public $sub;
    public $disable;
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
