<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use s00d\OnlineSimApi\OnlineSimApi;

class GetUserTest extends TestCase
{
    /**
     * @var OnlineSimApi
     */
    private $request;

    public function setUp() {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__.'/..');
        $dotenv->load();
        $this->request = new OnlineSimApi(getenv('APIKEY'));
    }

    public function testBalance()
    {
        $data = $this->request->user()->balance()->toArray();
        $this->assertInternalType('array', $data);
        $this->assertArrayHasKey('balance', $data);
        $this->assertArrayHasKey('zbalance', $data);
        $this->assertArrayHasKey('income', $data);
    }

    public function testProfile()
    {
        $data = $this->request->user()->profile()->toArray();
        $this->assertInternalType('array', $data);
        $this->assertInternalType('int', $data['id']);
        $this->assertArrayHasKey('apikey', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertInternalType('array', $data['payment']);
        $this->assertInternalType('int', $data['payment']['id']);
    }
}
