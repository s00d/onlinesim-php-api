<?php

namespace s00d\OnlineSimApi;

class Request
{
    private $url = 'https://onlinesim.ru/api/';
    private $apiKey;
    private $dev_id;
    private $locale;

    public function __construct($apiKey, $locale, $dev_id = null) {
        $this->apiKey = $apiKey;
        $this->dev_id = $dev_id;
        $this->locale = $locale;
    }

    public function send($request, $data, $method = 'GET') {
        $data['apikey'] = $this->apiKey;
        if ($this->dev_id) {
            $data['dev_id'] = $this->dev_id;
        }

        $serializedData = http_build_query($data);

        if ($method === 'GET') {
            $result = file_get_contents("{$this->url}{$request}.php?{$serializedData}");
        } else {
            $context = stream_context_create([
                'http' => [
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => $serializedData
                ]
            ]);
            $result = file_get_contents("{$this->url}{$request}", false, $context);
        }
        $result = json_decode($result, true);
        if(isset($result['response'])) {
            if((int)$result['response'] !== 1) {
                throw new RequestException($result['response'], $this->locale);
            }
            unset($result['response']);
        }

        return $result;
    }
}
