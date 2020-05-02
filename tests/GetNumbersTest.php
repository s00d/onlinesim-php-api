<?php

namespace Tests;

use s00d\OnlineSimApi\Apis\GetNumbers;
use PHPUnit\Framework\TestCase;
use s00d\OnlineSimApi\OnlineSimApi;

class GetNumbersTest extends TestCase
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

    public function testRepeat()
    {

    }

    public function testStateOne()
    {
//        $data = $this->request->numbers()->stateOne()->toArray();
//        $this->assertInternalType('array', $data);
    }

    public function testState()
    {
//        $data = $this->request->numbers()->state()->toArray();
//        $this->assertInternalType('array', $data);
    }

    public function testGet()
    {

    }

    public function testService()
    {

    }

    public function testServiceNumber()
    {

    }

    public function testNext()
    {
        $data = $this->request->numbers()->next(1);
    }

    public function testClose()
    {

    }

    public function testTariffs()
    {
        $data = $this->request->numbers()->tariffs();
        $this->assertInternalType('array', $data->toArray());
        $this->assertArrayHasKey('slug', $data->country(7)->service('VKcom')->toArray());
        $this->assertTrue($data->country(7)->service('VKcom')->get('slug') === 'VKcom');

    }
}
