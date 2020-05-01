<?php

namespace Tests;

use s00d\OnlineSimApi\Apis\GetForward;
use PHPUnit\Framework\TestCase;
use s00d\OnlineSimApi\OnlineSimApi;
use s00d\OnlineSimApi\Responses\GetForward\Get;

class GetForwardTest extends TestCase
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

    public function testGet()
    {
        $data = $this->request->forward()->get([9111111111]);
        $data = new Get(['tzid' => 152097]);
        $this->assertInternalType('array', $data->toArray());
        $this->assertArrayHasKey('tzid', $data->toArray());
    }

    public function testState()
    {
        $data = $this->request->forward()->state();
        $this->assertInternalType('array', $data->toArray());
        $first = $data->first();
        $this->assertArrayHasKey('tzid', $first->toArray());
    }

    public function testStateOne()
    {
        $data = $this->request->forward()->state();
        $data = $this->request->forward()->stateOne($data->first()->tzid);
        $this->assertInternalType('array', $data->toArray());
        $this->assertArrayHasKey('tzid', $data->toArray());
    }

    public function testSave()
    {
        $data = $this->request->forward()->save(
            $this->request->forward()->state()->first()->tzid,
            false
        );
    }


    public function testRemove()
    {

    }

    public function testForwardingList()
    {
        $data = $this->request->forward()->forwardingList();
        $this->assertInternalType('array', $data->toArray());
        $first = $data->first();
        $this->assertArrayHasKey('id', $first->toArray());

        $data = $this->request->forward()->forwardingList($first->id);
        $this->assertInternalType('array', $data->toArray());
        $this->assertArrayHasKey('id', $first->toArray());
    }

    public function testCallList()
    {
        $data = $this->request->forward()->forwardingList();
        $data = $this->request->forward()->callList($data->first()->number);
        $this->assertInternalType('array', $data->toArray());
        $first = $data->first();
        $this->assertInternalType('array', $first->toArray());
        $this->assertArrayHasKey('id', $first->toArray());
    }

    public function testSetEnable()
    {

    }
}
