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

    public function setUp(): void {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__.'/..');
        try {
            $dotenv->load();
        } catch (\Exception $e) {}
        $this->request = new OnlineSimApi(getenv('ON_APIKEY'));
    }

    public function testRepeat()
    {

    }

    public function testStateOne()
    {
//        $data = $this->request->numbers()->stateOne()->toArray();
//        $this->assertIsArray($data);
    }

    public function testState()
    {
//        $data = $this->request->numbers()->state()->toArray();
//        $this->assertIsArray($data);
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
        $this->assertIsArray($data->toArray());
        $this->assertArrayHasKey('slug', $data->country(7)->service('VKcom')->toArray());
        $this->assertSame($data->country(7)->service('VKcom')->get('slug'), 'VKcom');

    }
}
