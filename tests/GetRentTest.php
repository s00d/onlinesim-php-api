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

    public function setUp(): void {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__.'/..');
        try {
            $dotenv->load();
        } catch (\Exception $e) {}
        $this->request = new OnlineSimApi(getenv('ON_APIKEY'));
    }
    public function testState()
    {
        $data = $this->request->rent()->state();
        $this->assertIsArray($data->toArray());
        $first = $data->first();
        $this->assertIsArray($first->toArray());
        $this->assertIsArray($first->toArray()['messages']);
//        $this->assertArrayHasKey('tzid', $data->toArray());
    }


    public function testStateOne()
    {
        $data = $this->request->rent()->stateOne(
            $data = $this->request->rent()->state()->first()->tzid
        );
        $this->assertIsArray($data->toArray());
    }

    public function testClose()
    {

    }

    public function testGet()
    {
        $data = $this->request->rent()->get();
        $this->assertIsArray($data->toArray());
    }

    public function testTariffs()
    {
        $data = $this->request->rent()->tariffs();
        $this->assertIsArray($data->toArray());
        $this->assertArrayHasKey('code', $data->first()->toArray());
    }

    public function testExtend()
    {

    }

    public function testPortReload()
    {

    }
}
