<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use s00d\OnlineSimApi\OnlineSimApi;

class IoTest extends TestCase
{
    /**
     * @var OnlineSimApi
     */
    private $request;

    public function setUp():void
    {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        try {
            $dotenv->load();
        } catch (\Exception $e) {
        }
        $this->request = new OnlineSimApi(getenv('ON_APIKEY'));
    }

    public function testIsIo()
    {
        $this->request->setIo();
        $data = $this->request->user()->balance()->toArray();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('balance', $data);
        $this->assertArrayHasKey('zbalance', $data);
        $this->assertArrayHasKey('income', $data);
//        $this->assertTrue($data['balance'] > 0);
    }
}
