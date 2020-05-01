<?php

namespace Tests;

use s00d\OnlineSimApi\Apis\GetFree;
use PHPUnit\Framework\TestCase;
use s00d\OnlineSimApi\OnlineSimApi;

class GetFreeTest extends TestCase
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

    public function testGetList()
    {
        $countries = $this->request->free()->getList();
        $this->assertInternalType('array', $countries->toArray());
        $this->assertArrayHasKey('country', $countries->country(7)->toArray());
        $this->assertTrue($countries->country(7)->get('country') === 7);
    }

    public function testGetMessageList()
    {
        $messages = $this->request->free()->getMessageList(
            $this->request->free()->getPhoneList(7)->first()->get('number')
        );
        $this->assertInternalType('array', $messages->toArray());
        $first = $messages->first();
        $this->assertArrayHasKey('text', $first->toArray());
    }

    public function testGetPhoneList()
    {
        $data = $this->request->free()->getPhoneList(7);
        $this->assertInternalType('array', $data->toArray());
        $first = $data->first();
        $this->assertArrayHasKey('number', $first->toArray());
    }
}
