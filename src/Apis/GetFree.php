<?php

namespace s00d\OnlineSimApi\Apis;

use s00d\OnlineSimApi\OnlineSimApi;
use s00d\OnlineSimApi\RequestException;

class GetFree extends OnlineSimApi
{
    /**
     * https://onlinesim.ru/docs/api/ru#getfreecountrylist
     * @return mixed|null
     * @throws RequestException
     */
    public function getList() {
        return $this->request->send('getFreeCountryList', [], 'GET');
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

        return $this->request->send('getFreePhoneList', $data, 'GET');
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

        return $this->request->send('getFreeMessageList', $data, 'GET');
    }
}
