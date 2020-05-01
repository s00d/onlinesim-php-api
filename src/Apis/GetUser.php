<?php

namespace s00d\OnlineSimApi\Apis;

use s00d\OnlineSimApi\Exceptions\RequestException;
use s00d\OnlineSimApi\Request;
use s00d\OnlineSimApi\Responses\GetUser\Balance;
use s00d\OnlineSimApi\Responses\GetUser\Profile;

class GetUser
{
    /** @var Request */
    protected $request;

    /**
     * @param Request $request
     */
    public function __construct($request) {
        $this->request = $request;
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getbalance
     * @return Balance
     * @throws RequestException
     */
    public function balance() {
        return new Balance($this->request->send('getBalance', [
            'income' => true
        ], 'GET'));
    }

    /**
     * @return Profile
     * @throws RequestException
     */
    public function profile() {
        return new Profile($this->request->send('getProfile', [], 'GET')['profile']);
    }

}
