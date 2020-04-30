<?php

namespace s00d\OnlineSimApi\Apis;

use s00d\OnlineSimApi\OnlineSimApi;
use s00d\OnlineSimApi\RequestException;

class GetProxy extends OnlineSimApi
{
    /**
     * https://onlinesim.ru/docs/api/ru#getproxy
     * @param string $class
     * @param string $type
     * @param string $connect
     * @param int $count
     * @param null|string $operator
     * @param int $country
     * @param string $city
     * @param int $port_count
     * @param bool $session
     * @return mixed|null
     * @throws RequestException
     */
    public function get($class = 'days', $type = 'private', $connect = 'https', $count = 1, $operator = null, $country = 7, $city = 'any', $port_count = 1, $session = true) {
        $data = [
            'class' => $class,
            'type' => $type,
            'connect' => $connect,
            'count' => $count,
            'country' => $country,
            'city' => $city,
            'port_count' => $port_count,
            'session' => $session,
        ];
        if($operator) {
            $data['operator'] = $operator;
        }

        return $this->request->send('proxy/getProxy', $data, 'GET');
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getstate34
     * @param null|int $tzid
     * @return mixed|null
     * @throws RequestException
     */
    public function state($tzid = null) {
        $data = [];
        if($tzid) {
            $data['tzid'] = $tzid;
        }

        return $this->request->send('proxy/getProxyState', $data, 'GET');
    }

    /**
     * https://onlinesim.ru/docs/api/ru#changeip
     * @param int $tzid
     * @return mixed|null
     * @throws RequestException
     */
    public function changeIp($tzid) {
        $data = [
            'tzid' => $tzid
        ];
        return $this->request->send('proxy/changeIp', $data, 'GET');
    }

    /**
     * https://onlinesim.ru/docs/api/ru#changetype
     * @param int $tzid
     * @return mixed|null
     * @throws RequestException
     */
    public function changeType($tzid) {
        $data = [
            'tzid' => $tzid
        ];
        return $this->request->send('proxy/changeType', $data, 'GET');
    }

    /**
     * https://onlinesim.ru/docs/api/ru#setcomment
     * @param int $tzid
     * @param string $comment
     * @return mixed|null
     * @throws RequestException
     */
    public function setComment($tzid, $comment = '') {
        $data = [
            'tzid' => $tzid,
            'comment' => $comment
        ];
        return $this->request->send('proxy/setComment', $data, 'GET');
    }

}
