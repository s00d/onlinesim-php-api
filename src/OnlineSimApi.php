<?php


namespace s00d\OnlineSimApi;

use s00d\OnlineSimApi\Apis\GetFree;
use s00d\OnlineSimApi\Apis\GetNumbers;
use s00d\OnlineSimApi\Apis\GetProxy;
use s00d\OnlineSimApi\Apis\GetRent;
use s00d\OnlineSimApi\Apis\GetUser;
use s00d\OnlineSimApi\Exceptions\NoAuthException;

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

    public function init() {
        $profile = $this->user()->me();
        if(!$profile->auth) {
            throw new NoAuthException();
        }
        $this->request->setDomain($profile->user->domain);
        return $this;
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
