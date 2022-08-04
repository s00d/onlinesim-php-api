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

    public function setUp(): void {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__.'/..');
        try {
            $dotenv->load();
        } catch (\Exception $e) {}
        $this->request = new OnlineSimApi(getenv('ON_APIKEY'));
    }

    public function testBalance()
    {
        $data = $this->request->user()->balance()->toArray();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('balance', $data);
        $this->assertArrayHasKey('zbalance', $data);
        $this->assertArrayHasKey('income', $data);
    }

    public function testProfile()
    {
        $data = $this->request->user()->profile()->toArray();
        $this->assertIsArray($data);
        $this->assertIsInt( $data['id']);
        $this->assertArrayHasKey('apikey', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertIsArray($data['payment']);
        $this->assertIsInt($data['payment']['id']);
    }
}
