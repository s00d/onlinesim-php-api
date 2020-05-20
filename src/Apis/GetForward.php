<?php

namespace s00d\OnlineSimApi\Apis;

use RuntimeException;
use s00d\OnlineSimApi\Exceptions\NoNumberException;
use s00d\OnlineSimApi\Exceptions\RequestException;
use Exception;
use s00d\OnlineSimApi\Responses\GetForward\CallList;
use s00d\OnlineSimApi\Responses\GetForward\ForwardingList;
use s00d\OnlineSimApi\Responses\GetForward\ForwardingListOne;
use s00d\OnlineSimApi\Responses\GetForward\Get;
use s00d\OnlineSimApi\Responses\GetForward\Remove;
use s00d\OnlineSimApi\Responses\GetForward\Save;
use s00d\OnlineSimApi\Responses\GetForward\SetEnable;
use s00d\OnlineSimApi\Responses\GetForward\State;
use s00d\OnlineSimApi\Responses\GetForward\StateOne;
use s00d\OnlineSimApi\Responses\GetNumbers\Close;
use s00d\OnlineSimApi\Responses\GetNumbers\Next;

class GetForward extends GetUser
{

    /**
     * https://onlinesim.ru/docs/api/ru#getforward
     * @param string $service - unlimited_sms|forward_without_sms
     * @param null|int $region - 77|78
     * @param array $forward_numbers
     * @param array $reject
     * @return Get
     * @throws RequestException
     */
    public function get($forward_numbers, $service = 'unlimited_sms', $region = null, $reject = []) {
        $data = [
            'service' => $service,
            'forward_numbers' => $forward_numbers,
            'reject' => $reject,
        ];

        if($region) {
            $data['country'] = $region;
        }
        return new Get($this->request->send('getForward', $data, 'GET'));
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getstate
     * @param int $message_to_code - 0|1
     * @param string $orderby - ASC|DESC
     * @param bool $msg_list
     * @param bool $clean
     * @return State
     * @throws RequestException|Exception
     */
    public function state($message_to_code = 1, $orderby = 'ASC', $msg_list = true, $clean = true) {
        $data = [
            'message_to_code' => $message_to_code,
            'orderby' => $orderby,
            'msg_list' => $msg_list?1:0,
            'clean' => $clean?1:0,
            'type' => 'forward',
        ];
        try {
            return new State($this->request->send('getState', $data, 'GET'));
        } catch (NoNumberException $e) {
            return new State([]);
        } catch (RequestException $e) {
            throw new RequestException($e->getMessage(), $e->getLocale());
        } catch (\Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getstate
     * @param null|int $tzid
     * @param int $message_to_code - 0|1
     * @param bool $msg_list
     * @param bool $clean
     * @return StateOne|null
     * @throws RequestException
     */
    public function stateOne($tzid, $message_to_code = 1, $msg_list = true, $clean = true) {
        $data = [
            'message_to_code' => $message_to_code,
            'msg_list' => $msg_list?1:0,
            'clean' => $clean?1:0,
            'tzid' => $tzid,
            'type' => 'forward',
        ];
        try {
            return new StateOne($this->request->send('getState', $data, 'GET')[0]);
        } catch (NoNumberException $e) {
            return null;
        } catch (RequestException $e) {
            throw new RequestException($e->getMessage(), $e->getLocale());
        } catch (\Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * https://onlinesim.ru/docs/api/ru#setoperationok
     * @param int $tzid
     * @return Close
     * @throws RequestException
     */
    public function close($tzid) {
        $data = [
            'tzid' => $tzid,
        ];

        return new Close($this->request->send('setOperationOk', $data, 'GET'));
    }

    /**
     * https://onlinesim.ru/docs/api/ru#setoperationrevise
     * @param int $tzid
     * @return Next
     * @throws RequestException
     */
    public function next($tzid) {
        $data = [
            'tzid' => $tzid,
        ];

        return new Next($this->request->send('setOperationRevise', $data, 'GET'));
    }

    /**
     * https://onlinesim.ru/docs/api/ru#setforwardstatusenable
     * @param int $tzid
     * @return SetEnable
     * @throws RequestException
     */
    public function setEnable($tzid) {
        $data = [
            'tzid' => $tzid,
        ];

        return new SetEnable($this->request->send('setForwardStatusEnable', $data, 'GET'));
    }

    /**
     * https://onlinesim.ru/docs/api/ru#forwardinglist
     * @param int $tzid
     * @param int $page
     * @param string $orderby
     * @return ForwardingList|ForwardingListOne
     * @throws NoNumberException
     * @throws RequestException
     */
    public function forwardingList($tzid = null, $page = 1, $orderby = 'ASC') {
        $data = [
            'page' => $page,
            'sort' => $orderby,
        ];
        if(!$tzid) {
            return new ForwardingList($this->request->send('forwardingList', $data, 'GET')['forwardingList']['data']);
        }
        $data['id'] = $tzid;
        return new ForwardingListOne($this->request->send('forwardingList', $data, 'GET')['forwarding']);
    }

    /**
     * https://onlinesim.ru/docs/api/ru#forwardingsave
     * @param int $tzid
     * @param bool $auto
     * @param null|array $forward_number
     * @return Save
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

        return new Save($this->request->send('forwardingSave', $data, 'GET'));
    }

    /**
     * https://onlinesim.ru/docs/api/ru#forwardingremove
     * @param int $tzid
     * @return Remove
     * @throws RequestException
     */
    public function remove($tzid) {
        $data = [
            'id' => $tzid,
        ];

        return new Remove($this->request->send('forwardingRemove', $data, 'GET'));
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getcallnumberlist
     * @param int $number
     * @param int $count
     * @param int $page
     * @param string $order
     * @return CallList
     * @throws RequestException
     */
    public function callList($number, $count = 10, $page = 1, $orderby = 'ASC') {
        $data = [
            'number' => $number,
            'count' => $count,
            'page' => $page,
            'order' => $orderby,
        ];

        return new CallList($this->request->send('getCallNumberList', $data, 'GET')['list']['data']);
    }
}
