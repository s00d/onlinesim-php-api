<?php

namespace s00d\OnlineSimApi\Apis;

use s00d\OnlineSimApi\OnlineSimApi;
use s00d\OnlineSimApi\RequestException;

class GetRent extends OnlineSimApi
{
    /**
     * https://onlinesim.ru/docs/api/ru#getrentnum
     * @param int $country
     * @param int $days
     * @return mixed|null
     * @throws RequestException
     */
    public function get($country = 7, $days = 1) {
        $data = [
            'country' => $country,
            'days' => $days,
        ];

        return $this->request->send('rent/getRentNum', $data, 'GET');
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getrentstate
     * @param int $tzid
     * @return mixed|null
     * @throws RequestException
     */
    public function state($tzid) {
        $data = [
            'tzid' => $tzid,
        ];

        return $this->request->send('rent/getRentState', $data, 'GET');
    }

    /**
     * https://onlinesim.ru/docs/api/ru#extendrentstate
     * @param int $tzid
     * @param int $days
     * @return mixed|null
     * @throws RequestException
     */
    public function extend($tzid, $days = 1) {
        $data = [
            'tzid' => $tzid,
            'days' => $days,
        ];

        return $this->request->send('rent/extendRentState', $data, 'GET');
    }

    /**
     * https://onlinesim.ru/docs/api/ru#portreload
     * @param int $tzid
     * @return mixed|null
     * @throws RequestException
     */
    public function portReload($tzid) {
        $data = [
            'tzid' => $tzid,
        ];

        return $this->request->send('rent/portReload', $data, 'GET');
    }

    /**
     * https://onlinesim.ru/docs/api/ru#tariffsrent
     * @param null|int $country
     * @return mixed|null
     * @throws RequestException
     */
    public function tariffs($country = null) {
        $data = [];
        if($country) {
            $data['country'] = $country;
        }

        return $this->request->send('rent/tariffsRent', $data, 'GET');
    }

    /**
     * https://onlinesim.ru/docs/api/ru#closerent
     * @param null|int $tzid
     * @return mixed
     * @throws RequestException
     */
    public function close($tzid = null) {
        $data = [
            'tzid' => $tzid
        ];

        return $this->request->send('rent/closeRentNum', $data, 'GET');
    }

}
