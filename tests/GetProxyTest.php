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

    public function setUp(): void {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__.'/..');
        try {
            $dotenv->load();
        } catch (\Exception $e) {}
        $this->request = new OnlineSimApi(getenv('ON_APIKEY'));
    }
    public function testState()
    {
        $data = $this->request->proxy()->state();
        $this->assertIsArray($data->toArray());
        $first = $data->first();
        $this->assertIsArray($first->toArray());
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
