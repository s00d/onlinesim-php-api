<?php

namespace s00d\OnlineSimApi\Apis;

use s00d\OnlineSimApi\OnlineSimApi;
use s00d\OnlineSimApi\RequestException;

class GetForward extends OnlineSimApi
{

    /**
     * https://onlinesim.ru/docs/api/ru#getforward
     * @param string $service - unlimited_sms|forward_without_sms
     * @param null|int $region - 77|78
     * @param array $forward_numbers
     * @param array $reject
     * @return mixed|null
     * @throws RequestException
     */
    public function get($service = 'unlimited_sms', $region = null, $forward_numbers = [], $reject = []) {
        $data = [
            'service' => $service,
            'forward_numbers' => $forward_numbers,
            'reject' => $reject,
        ];

        if($region) {
            $data['country'] = $region;
        }
        return $this->request->send('getForward', $data, 'GET');
    }

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
            'type' => 'forward',
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
     * https://onlinesim.ru/docs/api/ru#setoperationok
     * @param int $tzid
     * @param bool $ban
     * @return mixed|null
     * @throws RequestException
     */
    public function close($tzid, $ban = false) {
        $data = [
            'tzid' => $tzid,
            'ban' => $ban?1:0,
        ];

        return $this->request->send('setOperationOk', $data, 'GET');
    }

    /**
     * https://onlinesim.ru/docs/api/ru#setforwardstatusenable
     * @param int $tzid
     * @return mixed|null
     * @throws RequestException
     */
    public function setEnable($tzid) {
        $data = [
            'tzid' => $tzid,
        ];

        return $this->request->send('setForwardStatusEnable', $data, 'GET');
    }

    /**
     * https://onlinesim.ru/docs/api/ru#forwardinglist
     * @param int $tzid
     * @param int $page
     * @param string $sort
     * @return mixed|null
     * @throws RequestException
     */
    public function forwardingList($tzid, $page = 1, $sort = 'ASC') {
        $data = [
            'id' => $tzid,
            'page' => $page,
            'sort' => $sort,
        ];
        return $this->request->send('__FUNCTION__', $data, 'GET');
    }

    /**
     * https://onlinesim.ru/docs/api/ru#forwardingsave
     * @param int $tzid
     * @param bool $auto
     * @param null|int $forward_number
     * @return mixed|null
     * @throws RequestException
     */
    public function save($tzid, $auto = true, $forward_number = null) {
        $data = [
            'id' => $tzid,
            'auto' => $auto,
        ];
        if($forward_number) {
            $data['forward_number'] = $forward_number;
        }

        return $this->request->send('forwardingSave', $data, 'GET');
    }

    /**
     * https://onlinesim.ru/docs/api/ru#forwardingremove
     * @param int $tzid
     * @return mixed|null
     * @throws RequestException
     */
    public function remove($tzid) {
        $data = [
            'id' => $tzid,
        ];

        return $this->request->send('forwardingRemove', $data, 'GET');
    }



    /**
     * https://onlinesim.ru/docs/api/ru#getcallnumberlist
     * @param int $number
     * @param int $count
     * @param int $page
     * @param string $order
     * @return mixed|null
     * @throws RequestException
     */
    public function callList($number, $count = 10, $page = 1, $order = 'ASC') {
        $data = [
            'number' => $number,
            'count' => $count,
            'page' => $page,
            'order' => $order,
        ];

        return $this->request->send('getCallNumberList', $data, 'GET');
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getforwardpaymentslist
     * @param int $tzid
     * @return mixed|null
     * @throws RequestException
     */
    public function getForwardPaymentsList($tzid) {
        $data = [
            'id' => $tzid,
        ];

        return $this->request->send(__FUNCTION__, $data, 'GET');
    }

}
