<?php


namespace s00d\OnlineSimApi;

use s00d\OnlineSimApi\Apis\GetForward;
use s00d\OnlineSimApi\Apis\GetFree;
use s00d\OnlineSimApi\Apis\GetNumbers;
use s00d\OnlineSimApi\Apis\GetProxy;
use s00d\OnlineSimApi\Apis\GetRent;
use s00d\OnlineSimApi\Apis\GetUser;

class OnlineSimApi
{
    protected $request;

    /**
     * OnlineSimApi constructor.
     * @param string $apiKey
     * @param null|string $locale - null|ru|en
     * @param null|string $dev_id
     */
    public function __construct($apiKey, $locale = null, $dev_id = null) {
        $this->request = new Request($apiKey, $locale, $dev_id);
    }

    /**
     * @return GetUser
     */
    public function user() {
        return new GetUser($this->request);
    }

    /**
     * @return GetRent
     */
    public function rent() {
        return new GetRent($this->request);
    }

    /**
     * @return GetNumbers
     */
    public function numbers() {
        return new GetNumbers($this->request);
    }

    /**
     * @return GetForward
     */
    public function forward() {
        return new GetForward($this->request);
    }

    /**
     * @return GetProxy
     */
    public function proxy() {
        return new GetProxy($this->request);
    }

    /**
     * @return GetFree
     */
    public function free() {
        return new GetFree($this->request);
    }
}
