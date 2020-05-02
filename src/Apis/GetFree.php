<?php

namespace s00d\OnlineSimApi\Apis;

use s00d\OnlineSimApi\Exceptions\RequestException;
use s00d\OnlineSimApi\Responses\GetFree\GetCountries;
use s00d\OnlineSimApi\Responses\GetFree\GetMessages;
use s00d\OnlineSimApi\Responses\GetFree\GetNumbers;

class GetFree extends GetUser
{
    /**
     * https://onlinesim.ru/docs/api/ru#getfreecountrylist
     * @return GetCountries
     * @throws RequestException
     */
    public function countries() {
        return new GetCountries($this->request->send('getFreeCountryList', [], 'GET')['countries']);
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getfreephonelist
     * @param int $country
     * @return GetNumbers
     * @throws RequestException
     */
    public function numbers($country = 7) {
        $data = [
            'country' => $country,
        ];

        return new GetNumbers($this->request->send('getFreePhoneList', $data, 'GET')['numbers']);
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getfreephonelist
     * @param int $number
     * @param int $page
     * @return GetMessages
     * @throws RequestException
     */
    public function messages($number, $page = 1) {
        $data = [
            'phone' => $number,
            'page' => $page,
        ];

        return new GetMessages($this->request->send('getFreeMessageList', $data, 'GET')['messages']['data']);
    }
}
