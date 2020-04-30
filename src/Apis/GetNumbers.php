<?php

namespace s00d\OnlineSimApi\Apis;

use s00d\OnlineSimApi\OnlineSimApi;
use s00d\OnlineSimApi\RequestException;

class GetNumbers extends OnlineSimApi
{
    /**
     * https://onlinesim.ru/docs/api/ru#getstate
     * @param null|int $tzid
     * @param int $message_to_code - 0|1
     * @param string $orderby - ASC|DESC
     * @param bool $msg_list
     * @param bool $clean
     * @return mixed|null
     * @throws RequestException
     */
    public function state($tzid = null, $message_to_code = 1, $orderby = 'ASC', $msg_list = true, $clean = false) {
        $data = [
            'message_to_code' => $message_to_code,
            'orderby' => $orderby,
            'msg_list' => $msg_list?1:0,
            'clean' => $clean?1:0,
            'type' => 'index',
        ];
        if($tzid) {
            $data['country'] = $tzid;
        }
        $res = $this->request->send('getState', $data, 'GET');
        if($tzid && count($res) > 0) {
            return $res[0];
        }
        return $res;
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getnum
     * @param string $service - https://onlinesim.ru/docs/api/ru#getnum
     * @param int $country
     * @param array $reject
     * @param bool $extension
     * @return mixed|null
     * @throws RequestException
     */
    public function get($service, $country = 7, $reject = [], $extension = false) {
        $data = [
            'service' => $service,
            'country' => $country,
            'reject' => $reject,
            'extension' => $extension?2:0,
        ];
        return $this->request->send('getNum', $data, 'GET');
    }

    /**
     * https://onlinesim.ru/docs/api/ru#setoperationrevise
     * @param int $tzid
     * @return mixed|null
     * @throws RequestException
     */
    public function close($tzid) {
        $data = [
            'tzid' => $tzid,
        ];

        return $this->request->send('setOperationRevise', $data, 'GET');
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getnumrepeat
     * @param string $service
     * @param int $number
     * @return mixed|null
     * @throws RequestException
     */
    public function repeat($service, $number) {
        $data = [
            'service' => $service,
            'number' => $number,
        ];
        return $this->request->send('getNumRepeat', $data, 'GET');
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getnumbersstats
     * @param null|int $country
     * @return mixed|null
     * @throws RequestException
     */
    public function tariffs($country = null) {
        $data = [];
        if($country) {
            $data['country'] = $country;
        }
        return $this->request->send('getNumbersStats', $data, 'GET');
    }

    /**
     * https://on.test/docs/api/ru#getservice
     * @return mixed|null
     * @throws RequestException
     */
    public function service() {
        return $this->request->send('getService', [], 'GET');
    }

    /**
     * https://on.test/docs/api/ru#getservicenumber
     * @param string $service
     * @return mixed|null
     * @throws RequestException
     */
    public function serviceNumber($service) {
        $data = [
            'service' => $service,
        ];
        return $this->request->send('getServiceNumber', $service, 'GET');
    }
}
