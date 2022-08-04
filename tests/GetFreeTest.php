<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use s00d\OnlineSimApi\OnlineSimApi;

class GetFreeTest extends TestCase
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

    public function testCountries()
    {
        $countries = $this->request->free()->countries();
        $this->assertIsArray($countries->toArray());
        $this->assertArrayHasKey('country', $countries->country(7)->toArray());
        $this->assertSame($countries->country(7)->get('country'), 7);
    }

    public function testMessages()
    {
        $messages = $this->request->free()->messages(
            $this->request->free()->numbers(7)->first()->get('number')
        );
        $this->assertIsArray($messages->toArray());
        $first = $messages->first();
        $this->assertArrayHasKey('text', $first->toArray());
    }

    public function testNumbers()
    {
        $data = $this->request->free()->numbers(7);
        $this->assertIsArray($data->toArray());
        $first = $data->first();
        $this->assertArrayHasKey('number', $first->toArray());
    }
}
