<?php


namespace s00d\OnlineSimApi;

use s00d\OnlineSimApi\Apis\GetForward;
use s00d\OnlineSimApi\Apis\GetFree;
use s00d\OnlineSimApi\Apis\GetNumbers;
use s00d\OnlineSimApi\Apis\GetProxy;
use s00d\OnlineSimApi\Apis\GetRent;

class OnlineSimApi
{
    protected $request;
    private $apiKey;
    private $dev_id;
    private $locale;

    /**
     * OnlineSimApi constructor.
     * @param string $apiKey
     * @param null|string $locale - null|ru|en
     * @param null|string $dev_id
     */
    public function __construct($apiKey, $locale = null, $dev_id = null) {
        $this->request = new Request($apiKey, $locale, $dev_id);
        $this->apiKey = $apiKey;
        $this->dev_id = $dev_id;
        $this->locale = $locale;
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getbalance
     * @return array|null
     * @throws RequestError
     */
    public function balance() {
        return $this->request->send('getBalance', [], 'GET');
    }

    /**
     * @return GetRent
     */
    public function rent() {
        return new GetRent($this->apiKey, $this->locale, $this->dev_id);
    }

    /**
     * @return GetNumbers
     */
    public function numbers() {
        return new GetNumbers($this->apiKey, $this->locale, $this->dev_id);
    }

    /**
     * @return GetForward
     */
    public function forward() {
        return new GetForward($this->apiKey, $this->locale, $this->dev_id);
    }

    /**
     * @return GetProxy
     */
    public function proxy() {
        return new GetProxy($this->apiKey, $this->locale, $this->dev_id);
    }

    /**
     * @return GetFree
     */
    public function free() {
        return new GetFree($this->apiKey, $this->locale, $this->dev_id);
    }
}
