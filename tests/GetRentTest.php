<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use s00d\OnlineSimApi\OnlineSimApi;

class GetRentTest extends TestCase
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
        $data = $this->request->rent()->state();
        $this->assertInternalType('array', $data->toArray());
        $first = $data->first();
        $this->assertInternalType('array', $first->toArray());
        $this->assertInternalType('array', $first->toArray()['messages']);
//        $this->assertArrayHasKey('tzid', $data->toArray());
    }


    public function testStateOne()
    {
        $data = $this->request->rent()->stateOne(
            $data = $this->request->rent()->state()->first()->tzid
        );
        $this->assertInternalType('array', $data->toArray());
    }

    public function testClose()
    {

    }

    public function testGet()
    {
        $data = $this->request->rent()->get();
        $this->assertInternalType('array', $data->toArray());
    }

    public function testTariffs()
    {
        $data = $this->request->rent()->tariffs();
        $this->assertInternalType('array', $data->toArray());
        $this->assertArrayHasKey('code', $data->first()->toArray());
    }

    public function testExtend()
    {

    }

    public function testPortReload()
    {

    }
}
