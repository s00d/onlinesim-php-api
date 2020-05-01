<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use s00d\OnlineSimApi\OnlineSimApi;

class GetProxyTest extends TestCase
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
    public function testState()
    {
        $data = $this->request->proxy()->state();
        $this->assertInternalType('array', $data->toArray());
        $first = $data->first();
        $this->assertInternalType('array', $first->toArray());
        $this->assertArrayHasKey('tzid', $first->toArray());
    }

    public function testGet()
    {

    }

    public function testChangeIp()
    {

    }

    public function testChangeType()
    {

    }

    public function testSetComment()
    {

    }

    public function testStateOne()
    {

    }
}
