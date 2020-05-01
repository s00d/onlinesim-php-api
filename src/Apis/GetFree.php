<?php

namespace s00d\OnlineSimApi\Apis;

use s00d\OnlineSimApi\Exceptions\RequestException;
use s00d\OnlineSimApi\Responses\GetFree\GetList;
use s00d\OnlineSimApi\Responses\GetFree\GetMessageList;
use s00d\OnlineSimApi\Responses\GetFree\GetPhoneList;

class GetFree extends GetUser
{
    /**
     * https://onlinesim.ru/docs/api/ru#getfreecountrylist
     * @return mixed|null
     * @throws RequestException
     */
    public function getList() {
        return new GetList($this->request->send('getFreeCountryList', [], 'GET')['countries']);
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getfreephonelist
     * @param int $country
     * @return mixed|null
     * @throws RequestException
     */
    public function getPhoneList($country = 7) {
        $data = [
            'country' => $country,
        ];

        return new GetPhoneList($this->request->send('getFreePhoneList', $data, 'GET')['numbers']);
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getfreephonelist
     * @param int $number
     * @param int $page
     * @return mixed|null
     * @throws RequestException
     */
    public function getMessageList($number, $page = 1) {
        $data = [
            'phone' => $number,
            'page' => $page,
        ];

        return new GetMessageList($this->request->send('getFreeMessageList', $data, 'GET')['messages']['data']);
    }
}
